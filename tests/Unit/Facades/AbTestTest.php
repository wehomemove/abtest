<?php

namespace Homemove\AbTesting\Tests\Unit\Facades;

use Homemove\AbTesting\Facades\AbTest;
use Homemove\AbTesting\Services\AbTestService;
use Homemove\AbTesting\Tests\TestCase;
use Illuminate\Support\Facades\DB;

class AbTestTest extends TestCase
{
    /** @test */
    public function it_resolves_to_ab_testing_service()
    {
        $this->assertInstanceOf(AbTestService::class, AbTest::getFacadeRoot());
    }

    /** @test */
    public function it_can_get_variant_through_facade()
    {
        DB::table('ab_experiments')->insert([
            'name' => 'facade_test',
            'variants' => json_encode(['control' => 50, 'variant_a' => 50]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $variant = AbTest::variant('facade_test', 'facade_user');

        $this->assertContains($variant, ['control', 'variant_a']);
    }

    /** @test */
    public function it_can_check_variant_through_facade()
    {
        DB::table('ab_experiments')->insert([
            'name' => 'facade_check_test',
            'variants' => json_encode(['control' => 100]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue(AbTest::isVariant('facade_check_test', 'control', 'check_user'));
        $this->assertFalse(AbTest::isVariant('facade_check_test', 'variant_a', 'check_user'));
    }

    /** @test */
    public function it_can_track_events_through_facade()
    {
        $experimentId = DB::table('ab_experiments')->insertGetId([
            'name' => 'facade_track_test',
            'variants' => json_encode(['control' => 100]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        AbTest::track('facade_track_test', 'track_user', 'button_click', ['page' => 'home']);

        $this->assertDatabaseHas('ab_events', [
            'experiment_id' => $experimentId,
            'user_id' => 'track_user',
            'event_name' => 'button_click',
            'variant' => 'control',
        ]);
    }

    /** @test */
    public function it_can_track_events_with_default_parameters()
    {
        $experimentId = DB::table('ab_experiments')->insertGetId([
            'name' => 'facade_default_test',
            'variants' => json_encode(['control' => 100]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Track with default event name and properties
        AbTest::track('facade_default_test', 'default_user');

        $this->assertDatabaseHas('ab_events', [
            'experiment_id' => $experimentId,
            'user_id' => 'default_user',
            'event_name' => 'conversion', // Default event name
            'variant' => 'control',
        ]);
    }

    /** @test */
    public function it_can_track_events_without_user_id()
    {
        $experimentId = DB::table('ab_experiments')->insertGetId([
            'name' => 'facade_no_user_test',
            'variants' => json_encode(['control' => 100]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Track without user ID (should use session)
        AbTest::track('facade_no_user_test', null, 'page_view');

        $this->assertDatabaseHas('ab_events', [
            'experiment_id' => $experimentId,
            'event_name' => 'page_view',
            'variant' => 'control',
        ]);
    }

    /** @test */
    public function it_can_clear_cache_through_facade()
    {
        // This test verifies that the clearCache method can be called
        // The actual cache clearing functionality is tested in the service tests
        
        DB::table('ab_experiments')->insert([
            'name' => 'facade_cache_test',
            'variants' => json_encode(['control' => 100]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Should not throw any exceptions
        AbTest::clearCache('facade_cache_test');
        AbTest::clearCache(); // Clear all cache

        $this->assertTrue(true); // If we get here, the methods executed successfully
    }

    /** @test */
    public function it_maintains_consistent_behavior_with_service()
    {
        $experimentId = DB::table('ab_experiments')->insertGetId([
            'name' => 'consistency_test',
            'variants' => json_encode(['control' => 50, 'variant_a' => 50]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userId = 'consistency_user';

        // Get variant through facade
        $facadeVariant = AbTest::variant('consistency_test', $userId);

        // Get variant through service directly
        $service = app('ab-testing');
        $serviceVariant = $service->variant('consistency_test', $userId);

        // Should be the same
        $this->assertEquals($facadeVariant, $serviceVariant);

        // Check isVariant consistency
        $facadeCheck = AbTest::isVariant('consistency_test', $facadeVariant, $userId);
        $serviceCheck = $service->isVariant('consistency_test', $serviceVariant, $userId);

        $this->assertTrue($facadeCheck);
        $this->assertTrue($serviceCheck);
        $this->assertEquals($facadeCheck, $serviceCheck);
    }

    /** @test */
    public function it_handles_nonexistent_experiments_gracefully()
    {
        $variant = AbTest::variant('nonexistent_experiment', 'test_user');
        $this->assertEquals('control', $variant);

        $isVariant = AbTest::isVariant('nonexistent_experiment', 'control', 'test_user');
        $this->assertTrue($isVariant);

        $isOtherVariant = AbTest::isVariant('nonexistent_experiment', 'variant_a', 'test_user');
        $this->assertFalse($isOtherVariant);

        // Track should not fail for nonexistent experiment
        AbTest::track('nonexistent_experiment', 'test_user', 'click');
        
        // No events should be created for nonexistent experiment
        $this->assertDatabaseMissing('ab_events', [
            'user_id' => 'test_user',
            'event_name' => 'click',
        ]);
    }

    /** @test */
    public function it_can_be_used_in_static_context()
    {
        DB::table('ab_experiments')->insert([
            'name' => 'static_test',
            'variants' => json_encode(['control' => 100]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // These calls should work in static context
        $variant = AbTest::variant('static_test');
        $isControl = AbTest::isVariant('static_test', 'control');
        
        $this->assertEquals('control', $variant);
        $this->assertTrue($isControl);

        // Should be able to track
        AbTest::track('static_test', null, 'static_event');
        
        $this->assertDatabaseHas('ab_events', [
            'event_name' => 'static_event',
            'variant' => 'control',
        ]);
    }

    /** @test */
    public function it_preserves_method_signatures()
    {
        // This test ensures that the facade's PHPDoc annotations match the actual service methods
        
        $reflection = new \ReflectionClass(AbTestService::class);
        
        // Check that all documented methods exist in the service
        $this->assertTrue($reflection->hasMethod('variant'));
        $this->assertTrue($reflection->hasMethod('isVariant'));
        $this->assertTrue($reflection->hasMethod('track'));
        $this->assertTrue($reflection->hasMethod('clearCache'));

        // Check method signatures
        $variantMethod = $reflection->getMethod('variant');
        $this->assertEquals(2, $variantMethod->getNumberOfParameters());

        $isVariantMethod = $reflection->getMethod('isVariant');
        $this->assertEquals(3, $isVariantMethod->getNumberOfParameters());

        $trackMethod = $reflection->getMethod('track');
        $this->assertEquals(4, $trackMethod->getNumberOfParameters());

        $clearCacheMethod = $reflection->getMethod('clearCache');
        $this->assertEquals(1, $clearCacheMethod->getNumberOfParameters());
    }
}