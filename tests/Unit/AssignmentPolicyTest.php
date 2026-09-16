<?php

namespace Homemove\AbTesting\Tests\Unit;

use Homemove\AbTesting\Services\AbTestService;
use Homemove\AbTesting\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The opt-in assignment policy flags (config ab-testing.assignment.*).
 * Every flag defaults to the v1.6 behaviour; each test states which side
 * of the flag it exercises.
 */
class AssignmentPolicyTest extends TestCase
{
    protected AbTestService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AbTestService();
        session()->flush();
        $_COOKIE = [];
    }

    private function experiment(array $overrides = []): int
    {
        return DB::table('ab_experiments')->insertGetId(array_merge([
            'name' => 'policy_test',
            'variants' => json_encode(['control' => 50, 'variant_a' => 50]),
            'is_active' => true,
            'status' => 'running',
            'traffic_allocation' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function hashArm(string $experiment, string $userId, array $variants): string
    {
        $percentage = (hexdec(substr(md5($experiment . $userId), 0, 8)) % 100) + 1;
        $cumulative = 0;
        foreach ($variants as $variant => $weight) {
            $cumulative += $weight;
            if ($percentage <= $cumulative) {
                return $variant;
            }
        }

        return 'control';
    }

    // ── Traffic allocation ────────────────────────────────────────────

    /** @test */
    public function traffic_allocation_is_ignored_by_default()
    {
        $id = $this->experiment(['traffic_allocation' => 0]);

        $this->service->variant('policy_test', 'user_1');

        $this->assertDatabaseHas('ab_user_assignments', ['experiment_id' => $id, 'user_id' => 'user_1']);
    }

    /** @test */
    public function enforced_traffic_allocation_returns_control_without_a_row()
    {
        config()->set('ab-testing.assignment.enforce_traffic_allocation', true);
        $id = $this->experiment(['traffic_allocation' => 0]);

        $this->assertSame('control', $this->service->variant('policy_test', 'user_1'));
        $this->assertDatabaseMissing('ab_user_assignments', ['experiment_id' => $id, 'user_id' => 'user_1']);
    }

    /** @test */
    public function enforced_traffic_allocation_at_100_assigns_everyone()
    {
        config()->set('ab-testing.assignment.enforce_traffic_allocation', true);
        $id = $this->experiment(['traffic_allocation' => 100]);

        foreach (range(1, 20) as $i) {
            $this->service->variant('policy_test', "user_$i");
        }

        $this->assertSame(20, DB::table('ab_user_assignments')->where('experiment_id', $id)->count());
    }

    /** @test */
    public function traffic_allocation_is_deterministic_per_user()
    {
        config()->set('ab-testing.assignment.enforce_traffic_allocation', true);
        $this->experiment(['traffic_allocation' => 50]);

        $first = [];
        foreach (range(1, 40) as $i) {
            $first["u$i"] = $this->service->variant('policy_test', "u$i");
        }
        Cache::flush();
        foreach (range(1, 40) as $i) {
            $this->assertSame($first["u$i"], $this->service->variant('policy_test', "u$i"));
        }
    }

    /** @test */
    public function traffic_allocation_is_independent_of_variant_bucketing()
    {
        config()->set('ab-testing.assignment.enforce_traffic_allocation', true);
        $id = $this->experiment(['traffic_allocation' => 50]);

        foreach (range(1, 300) as $i) {
            $this->service->variant('policy_test', "user_$i");
        }

        $arms = DB::table('ab_user_assignments')->where('experiment_id', $id)
            ->selectRaw('variant, COUNT(*) as n')->groupBy('variant')->pluck('n', 'variant');

        // Roughly half admitted, and the admitted half spans BOTH arms — the
        // salt keeps allocation and arm choice independent.
        $this->assertGreaterThan(100, $arms->sum());
        $this->assertLessThan(200, $arms->sum());
        $this->assertGreaterThan(30, $arms['control'] ?? 0);
        $this->assertGreaterThan(30, $arms['variant_a'] ?? 0);
    }

    /** @test */
    public function raising_traffic_allocation_only_adds_users()
    {
        config()->set('ab-testing.assignment.enforce_traffic_allocation', true);
        $id = $this->experiment(['traffic_allocation' => 30]);

        foreach (range(1, 200) as $i) {
            $this->service->variant('policy_test', "user_$i");
        }
        $at30 = DB::table('ab_user_assignments')->where('experiment_id', $id)->pluck('user_id')->all();

        DB::table('ab_experiments')->where('id', $id)->update(['traffic_allocation' => 60]);
        $this->service->clearCache('policy_test');
        Cache::flush();

        foreach (range(1, 200) as $i) {
            $this->service->variant('policy_test', "user_$i");
        }
        $at60 = DB::table('ab_user_assignments')->where('experiment_id', $id)->pluck('user_id')->all();

        $this->assertEmpty(array_diff($at30, $at60));
        $this->assertGreaterThan(count($at30), count($at60));
    }

    // ── Schedule / status ─────────────────────────────────────────────

    /** @test */
    public function schedule_is_ignored_by_default()
    {
        $id = $this->experiment(['status' => 'draft', 'start_date' => now()->addDay()]);

        $this->service->variant('policy_test', 'user_1');

        $this->assertDatabaseHas('ab_user_assignments', ['experiment_id' => $id, 'user_id' => 'user_1']);
    }

    /** @test */
    public function enforced_schedule_returns_control_for_draft_status()
    {
        config()->set('ab-testing.assignment.enforce_schedule', true);
        $id = $this->experiment(['status' => 'draft']);

        $this->assertSame('control', $this->service->variant('policy_test', 'user_1'));
        $this->assertDatabaseMissing('ab_user_assignments', ['experiment_id' => $id, 'user_id' => 'user_1']);
    }

    /** @test */
    public function enforced_schedule_returns_control_before_start_date()
    {
        config()->set('ab-testing.assignment.enforce_schedule', true);
        $id = $this->experiment(['start_date' => now()->addHour()]);

        $this->assertSame('control', $this->service->variant('policy_test', 'user_1'));
        $this->assertDatabaseMissing('ab_user_assignments', ['experiment_id' => $id, 'user_id' => 'user_1']);
    }

    /** @test */
    public function enforced_schedule_returns_control_after_end_date()
    {
        config()->set('ab-testing.assignment.enforce_schedule', true);
        $id = $this->experiment(['start_date' => now()->subDays(2), 'end_date' => now()->subHour()]);

        $this->assertSame('control', $this->service->variant('policy_test', 'user_1'));
        $this->assertDatabaseMissing('ab_user_assignments', ['experiment_id' => $id, 'user_id' => 'user_1']);
    }

    /** @test */
    public function enforced_schedule_assigns_inside_window()
    {
        config()->set('ab-testing.assignment.enforce_schedule', true);
        $id = $this->experiment(['start_date' => now()->subHour(), 'end_date' => now()->addHour()]);

        $variant = $this->service->variant('policy_test', 'user_1');

        $this->assertContains($variant, ['control', 'variant_a']);
        $this->assertDatabaseHas('ab_user_assignments', ['experiment_id' => $id, 'user_id' => 'user_1', 'variant' => $variant]);
    }

    /** @test */
    public function existing_assignment_wins_when_experiment_is_paused()
    {
        config()->set('ab-testing.assignment.enforce_schedule', true);
        $id = $this->experiment(['status' => 'paused', 'is_active' => false]);
        DB::table('ab_user_assignments')->insert([
            'experiment_id' => $id, 'user_id' => 'veteran', 'variant' => 'variant_a',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame('variant_a', $this->service->variant('policy_test', 'veteran'));
        $this->assertSame('control', $this->service->variant('policy_test', 'newcomer'));
        $this->assertDatabaseMissing('ab_user_assignments', ['experiment_id' => $id, 'user_id' => 'newcomer']);
    }

    /** @test */
    public function schedule_change_takes_effect_after_clear_cache()
    {
        config()->set('ab-testing.assignment.enforce_schedule', true);
        $id = $this->experiment();

        $arm = $this->service->variant('policy_test', 'early');
        $this->assertDatabaseHas('ab_user_assignments', ['experiment_id' => $id, 'user_id' => 'early']);

        DB::table('ab_experiments')->where('id', $id)->update(['status' => 'paused']);
        $this->service->clearCache('policy_test');

        $this->assertSame($arm, $this->service->variant('policy_test', 'early'));
        $this->assertSame('control', $this->service->variant('policy_test', 'late'));
        $this->assertDatabaseMissing('ab_user_assignments', ['experiment_id' => $id, 'user_id' => 'late']);
    }

    // ── Adaptive allocation ───────────────────────────────────────────

    /** @test */
    public function adaptive_allocation_can_be_disabled()
    {
        config()->set('ab-testing.assignment.adaptive_allocation', false);
        $id = $this->experiment();

        // 22/3 skew: with adaptive on, the next user is forced into variant_a.
        foreach (range(1, 22) as $i) {
            DB::table('ab_user_assignments')->insert([
                'experiment_id' => $id, 'user_id' => "user_$i", 'variant' => 'control',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        foreach (range(23, 25) as $i) {
            DB::table('ab_user_assignments')->insert([
                'experiment_id' => $id, 'user_id' => "user_$i", 'variant' => 'variant_a',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // Find a fresh id whose md5 arm is control — adaptive would override it.
        $variants = ['control' => 50, 'variant_a' => 50];
        $userId = null;
        foreach (range(1, 500) as $i) {
            if ($this->hashArm('policy_test', "fresh_$i", $variants) === 'control') {
                $userId = "fresh_$i";
                break;
            }
        }
        $this->assertNotNull($userId);

        $this->assertSame('control', $this->service->variant('policy_test', $userId));
    }
}
