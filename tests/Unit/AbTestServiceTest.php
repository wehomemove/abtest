<?php

namespace Homemove\AbTesting\Tests\Unit;

use Homemove\AbTesting\Services\AbTestService;
use Homemove\AbTesting\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AbTestServiceTest extends TestCase
{
    protected AbTestService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AbTestService();
        
        // Clear any existing sessions/cookies
        session()->flush();
        $_COOKIE = [];
    }

    /** @test */
    public function it_can_get_variant_for_active_experiment()
    {
        // Create test experiment
        $experimentId = DB::table('ab_experiments')->insertGetId([
            'name' => 'test_experiment',
            'variants' => json_encode(['control' => 50, 'variant_a' => 50]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $variant = $this->service->variant('test_experiment', 'test_user_123');

        $this->assertContains($variant, ['control', 'variant_a']);
        
        // Should create user assignment
        $this->assertDatabaseHas('ab_user_assignments', [
            'experiment_id' => $experimentId,
            'user_id' => 'test_user_123',
            'variant' => $variant,
        ]);
    }

    /** @test */
    public function it_returns_control_for_inactive_experiment()
    {
        DB::table('ab_experiments')->insert([
            'name' => 'inactive_experiment',
            'variants' => json_encode(['control' => 50, 'variant_a' => 50]),
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $variant = $this->service->variant('inactive_experiment', 'test_user');

        $this->assertEquals('control', $variant);
    }

    /** @test */
    public function it_returns_control_for_nonexistent_experiment()
    {
        $variant = $this->service->variant('nonexistent', 'test_user');

        $this->assertEquals('control', $variant);
    }

    /** @test */
    public function it_returns_consistent_variant_for_same_user()
    {
        DB::table('ab_experiments')->insert([
            'name' => 'consistency_test',
            'variants' => json_encode(['control' => 50, 'variant_a' => 50]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $variant1 = $this->service->variant('consistency_test', 'consistent_user');
        $variant2 = $this->service->variant('consistency_test', 'consistent_user');

        $this->assertEquals($variant1, $variant2);
    }

    /** @test */
    public function it_respects_debug_override_cookie()
    {
        $experimentId = DB::table('ab_experiments')->insertGetId([
            'name' => 'override_test',
            'variants' => json_encode(['control' => 50, 'variant_a' => 50]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $_COOKIE['ab_test_override_override_test'] = 'variant_a';

        $variant = $this->service->variant('override_test', 'test_user');

        $this->assertEquals('variant_a', $variant);
    }

    /** @test */
    public function it_ignores_invalid_debug_override()
    {
        DB::table('ab_experiments')->insertGetId([
            'name' => 'invalid_override_test',
            'variants' => json_encode(['control' => 50, 'variant_a' => 50]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $_COOKIE['ab_test_override_invalid_override_test'] = 'invalid_variant';

        $variant = $this->service->variant('invalid_override_test', 'test_user');

        $this->assertContains($variant, ['control', 'variant_a']);
    }

    /** @test */
    public function it_can_check_if_user_is_in_variant()
    {
        DB::table('ab_experiments')->insert([
            'name' => 'variant_check_test',
            'variants' => json_encode(['control' => 100]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue($this->service->isVariant('variant_check_test', 'control', 'test_user'));
        $this->assertFalse($this->service->isVariant('variant_check_test', 'variant_a', 'test_user'));
    }

    /** @test */
    public function it_can_track_events()
    {
        $experimentId = DB::table('ab_experiments')->insertGetId([
            'name' => 'tracking_test',
            'variants' => json_encode(['control' => 100]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->service->track('tracking_test', 'track_user', 'conversion', ['value' => 100]);

        $this->assertDatabaseHas('ab_events', [
            'experiment_id' => $experimentId,
            'user_id' => 'track_user',
            'event_name' => 'conversion',
            'variant' => 'control',
        ]);

        $event = DB::table('ab_events')->where('experiment_id', $experimentId)->first();
        $properties = json_decode($event->properties, true);
        $this->assertEquals(100, $properties['value']);
        $this->assertEquals(1, $properties['count']);
    }

    /** @test */
    public function it_increments_count_for_duplicate_events()
    {
        $experimentId = DB::table('ab_experiments')->insertGetId([
            'name' => 'duplicate_test',
            'variants' => json_encode(['control' => 100]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Track same event twice
        $this->service->track('duplicate_test', 'dup_user', 'click');
        $this->service->track('duplicate_test', 'dup_user', 'click');

        $this->assertDatabaseCount('ab_events', 1);
        
        $event = DB::table('ab_events')->where('experiment_id', $experimentId)->first();
        $properties = json_decode($event->properties, true);
        $this->assertEquals(2, $properties['count']);
    }

    /** @test */
    public function it_skips_tracking_for_nonexistent_experiment()
    {
        $this->service->track('nonexistent_exp', 'user', 'event');

        $this->assertDatabaseCount('ab_events', 0);
    }

    /** @test */
    public function it_uses_adaptive_allocation_when_enough_data()
    {
        $experimentId = DB::table('ab_experiments')->insertGetId([
            'name' => 'adaptive_test',
            'variants' => json_encode(['control' => 50, 'variant_a' => 50]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create 25 assignments heavily skewed to control (to trigger adaptive allocation)
        for ($i = 1; $i <= 22; $i++) {
            DB::table('ab_user_assignments')->insert([
                'experiment_id' => $experimentId,
                'user_id' => "user_$i",
                'variant' => 'control',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        for ($i = 23; $i <= 25; $i++) {
            DB::table('ab_user_assignments')->insert([
                'experiment_id' => $experimentId,
                'user_id' => "user_$i",
                'variant' => 'variant_a',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // New user should get variant_a to balance distribution
        $variant = $this->service->variant('adaptive_test', 'new_user');
        
        $this->assertEquals('variant_a', $variant);
    }

    /** @test */
    public function it_uses_hash_based_assignment_with_small_deviation()
    {
        $experimentId = DB::table('ab_experiments')->insertGetId([
            'name' => 'balanced_test',
            'variants' => json_encode(['control' => 50, 'variant_a' => 50]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create balanced assignments (no need for adaptive allocation)
        for ($i = 1; $i <= 12; $i++) {
            DB::table('ab_user_assignments')->insert([
                'experiment_id' => $experimentId,
                'user_id' => "user_$i",
                'variant' => 'control',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        for ($i = 13; $i <= 25; $i++) {
            DB::table('ab_user_assignments')->insert([
                'experiment_id' => $experimentId,
                'user_id' => "user_$i",
                'variant' => 'variant_a',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Should use hash-based assignment since distribution is balanced
        $variant = $this->service->variant('balanced_test', 'hash_user');
        
        $this->assertContains($variant, ['control', 'variant_a']);
    }

    /** @test */
    public function it_generates_session_user_id_when_none_exists()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getSessionUserId');
        $method->setAccessible(true);

        $userId = $method->invoke($this->service);

        $this->assertIsString($userId);
        $this->assertTrue(Str::isUuid($userId));
    }

    /** @test */
    public function it_returns_cookie_user_id_when_available()
    {
        $_COOKIE['ab_user_id'] = 'cookie_user_123';

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getSessionUserId');
        $method->setAccessible(true);

        $userId = $method->invoke($this->service);

        $this->assertEquals('cookie_user_123', $userId);
    }

    /** @test */
    public function it_returns_session_user_id_when_cookie_unavailable()
    {
        session(['ab_user_id' => 'session_user_456']);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getSessionUserId');
        $method->setAccessible(true);

        $userId = $method->invoke($this->service);

        $this->assertEquals('session_user_456', $userId);
    }

    /** @test */
    public function it_can_clear_experiment_cache()
    {
        // Set up cache
        Cache::put('ab_test:experiment:test_exp', 'cached_data', 3600);
        Cache::put('ab_test:variant:test_exp:user123', 'control', 3600);

        $this->assertTrue(Cache::has('ab_test:experiment:test_exp'));

        $this->service->clearCache('test_exp');

        $this->assertFalse(Cache::has('ab_test:experiment:test_exp'));
    }

    /** @test */
    public function it_can_clear_all_cache()
    {
        Cache::put('ab_test:experiment:test1', 'data1', 3600);
        Cache::put('ab_test:experiment:test2', 'data2', 3600);

        $this->service->clearCache();

        $this->assertFalse(Cache::has('ab_test:experiment:test1'));
        $this->assertFalse(Cache::has('ab_test:experiment:test2'));
    }

    /** @test */
    public function it_tracks_debug_experiments_when_debug_enabled()
    {
        config(['app.debug' => true]);

        DB::table('ab_experiments')->insert([
            'name' => 'debug_test',
            'variants' => json_encode(['control' => 100]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->service->variant('debug_test', 'debug_user');
        $this->service->variant('debug_test', 'debug_user'); // Call twice

        $debugExperiments = $this->service->getDebugExperiments();

        $this->assertArrayHasKey('debug_test', $debugExperiments);
        $this->assertEquals('control', $debugExperiments['debug_test']['variant']);
        $this->assertEquals(2, $debugExperiments['debug_test']['calls']);
    }

    /** @test */
    public function it_does_not_track_debug_experiments_when_debug_disabled()
    {
        config(['app.debug' => false]);

        DB::table('ab_experiments')->insert([
            'name' => 'no_debug_test',
            'variants' => json_encode(['control' => 100]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->service->variant('no_debug_test', 'user');

        $debugExperiments = $this->service->getDebugExperiments();

        $this->assertEmpty($debugExperiments);
    }

    /** @test */
    public function it_returns_debug_user_info()
    {
        $_COOKIE['ab_user_id'] = 'debug_cookie_user';
        session(['ab_user_id' => 'debug_session_user']);

        $userInfo = $this->service->getDebugUserInfo();

        $this->assertEquals('debug_cookie_user', $userInfo['user_id']);
        $this->assertEquals('cookie', $userInfo['source']);
        $this->assertTrue($userInfo['cookie_exists']);
        $this->assertTrue($userInfo['session_exists']);
        $this->assertTrue($userInfo['session_started']);
    }

    /** @test */
    public function it_returns_session_source_when_no_cookie()
    {
        session(['ab_user_id' => 'session_only_user']);

        $userInfo = $this->service->getDebugUserInfo();

        $this->assertEquals('session_only_user', $userInfo['user_id']);
        $this->assertEquals('session', $userInfo['source']);
        $this->assertFalse($userInfo['cookie_exists']);
        $this->assertTrue($userInfo['session_exists']);
    }

    /** @test */
    public function it_returns_none_source_when_no_user_id()
    {
        $userInfo = $this->service->getDebugUserInfo();

        $this->assertEquals('not_set', $userInfo['user_id']);
        $this->assertEquals('none', $userInfo['source']);
        $this->assertFalse($userInfo['cookie_exists']);
        $this->assertFalse($userInfo['session_exists']);
    }

    /** @test */
    public function it_handles_session_errors_gracefully()
    {
        Log::shouldReceive('debug')->once();

        // Mock session that throws exception
        $this->app->instance('session.store', new class {
            public function isStarted() { return false; }
            public function start() { throw new \Exception('Session error'); }
            public function has($key) { return false; }
        });

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getSessionUserId');
        $method->setAccessible(true);

        $userId = $method->invoke($this->service);

        $this->assertIsString($userId);
        $this->assertTrue(Str::isUuid($userId));
    }
}