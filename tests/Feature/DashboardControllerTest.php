<?php

namespace Homemove\AbTesting\Tests\Feature;

use Homemove\AbTesting\Models\Experiment;
use Homemove\AbTesting\Tests\TestCase;
use Illuminate\Support\Facades\DB;

class DashboardControllerTest extends TestCase
{
    /** @test */
    public function it_displays_experiments_index()
    {
        // Create test experiments
        $exp1Id = DB::table('ab_experiments')->insertGetId([
            'name' => 'index_test_1',
            'description' => 'First test experiment',
            'variants' => json_encode(['control' => 50, 'variant_a' => 50]),
            'is_active' => true,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $exp2Id = DB::table('ab_experiments')->insertGetId([
            'name' => 'index_test_2',
            'description' => 'Second test experiment',
            'variants' => json_encode(['control' => 100]),
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get('/ab-testing/dashboard');

        $response->assertStatus(200)
                ->assertViewIs('ab-testing::dashboard.index')
                ->assertViewHas('experiments');

        $experiments = $response->viewData('experiments');
        $this->assertCount(2, $experiments);
        
        // Should be ordered by created_at desc (newest first)
        $this->assertEquals('index_test_2', $experiments->first()->name);
        $this->assertEquals('index_test_1', $experiments->last()->name);
    }

    /** @test */
    public function it_shows_experiment_details()
    {
        $experimentId = DB::table('ab_experiments')->insertGetId([
            'name' => 'show_test',
            'description' => 'Test experiment for show',
            'variants' => json_encode(['control' => 60, 'variant_a' => 40]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Add some test data
        DB::table('ab_user_assignments')->insert([
            ['experiment_id' => $experimentId, 'user_id' => 'user1', 'variant' => 'control', 'created_at' => now(), 'updated_at' => now()],
            ['experiment_id' => $experimentId, 'user_id' => 'user2', 'variant' => 'variant_a', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('ab_events')->insert([
            ['experiment_id' => $experimentId, 'user_id' => 'user1', 'variant' => 'control', 'event_name' => 'conversion', 'properties' => '{"count": 1}', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->get("/ab-testing/dashboard/{$experimentId}");

        $response->assertStatus(200)
                ->assertViewIs('ab-testing::dashboard.show')
                ->assertViewHas(['experiment', 'stats']);

        $stats = $response->viewData('stats');
        $this->assertEquals(2, $stats['total_assignments']);
        $this->assertEquals(1, $stats['total_conversions']);
        $this->assertEquals(1, $stats['unique_users']);
    }

    /** @test */
    public function it_displays_create_form()
    {
        $response = $this->get('/ab-testing/dashboard/create');

        $response->assertStatus(200)
                ->assertViewIs('ab-testing::dashboard.create');
    }

    /** @test */
    public function it_can_create_new_experiment()
    {
        $data = [
            'name' => 'new_test_experiment',
            'description' => 'A new test experiment',
            'variants' => ['control' => 50, 'variant_a' => 50],
            'traffic_allocation' => 100,
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addWeeks(2)->format('Y-m-d'),
        ];

        $response = $this->post('/ab-testing/dashboard', $data);

        $this->assertDatabaseHas('ab_experiments', [
            'name' => 'new_test_experiment',
            'description' => 'A new test experiment',
            'traffic_allocation' => 100,
        ]);

        $experiment = DB::table('ab_experiments')->where('name', 'new_test_experiment')->first();
        $variants = json_decode($experiment->variants, true);
        $this->assertEquals(['control' => 50, 'variant_a' => 50], $variants);

        $response->assertRedirect("/ab-testing/dashboard/{$experiment->id}")
                ->assertSessionHas('success', 'Experiment created successfully!');
    }

    /** @test */
    public function it_validates_experiment_creation()
    {
        $response = $this->post('/ab-testing/dashboard', [
            'name' => '', // Required field missing
            'variants' => ['control' => 60], // Only one variant (minimum 2 required)
        ]);

        $response->assertSessionHasErrors(['name', 'variants']);
    }

    /** @test */
    public function it_validates_variant_weights_sum_to_100()
    {
        $response = $this->post('/ab-testing/dashboard', [
            'name' => 'invalid_weights_test',
            'variants' => ['control' => 60, 'variant_a' => 50], // Sums to 110
            'traffic_allocation' => 100,
        ]);

        $response->assertSessionHasErrors(['variants']);
    }

    /** @test */
    public function it_validates_unique_experiment_names()
    {
        DB::table('ab_experiments')->insert([
            'name' => 'existing_experiment',
            'variants' => json_encode(['control' => 100]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->post('/ab-testing/dashboard', [
            'name' => 'existing_experiment',
            'variants' => ['control' => 100],
            'traffic_allocation' => 100,
        ]);

        $response->assertSessionHasErrors(['name']);
    }

    /** @test */
    public function it_displays_edit_form()
    {
        $experimentId = DB::table('ab_experiments')->insertGetId([
            'name' => 'edit_test',
            'description' => 'Edit test experiment',
            'variants' => json_encode(['control' => 50, 'variant_a' => 50]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get("/ab-testing/dashboard/{$experimentId}/edit");

        $response->assertStatus(200)
                ->assertViewIs('ab-testing::dashboard.edit')
                ->assertViewHas('experiment');
    }

    /** @test */
    public function it_can_update_experiment()
    {
        $experimentId = DB::table('ab_experiments')->insertGetId([
            'name' => 'update_test',
            'description' => 'Original description',
            'variants' => json_encode(['control' => 100]),
            'traffic_allocation' => 50,
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $data = [
            'name' => 'updated_test',
            'description' => 'Updated description',
            'variants' => ['control' => 40, 'variant_a' => 60],
            'traffic_allocation' => 80,
            'is_active' => true,
        ];

        $response = $this->put("/ab-testing/dashboard/{$experimentId}", $data);

        $this->assertDatabaseHas('ab_experiments', [
            'id' => $experimentId,
            'name' => 'updated_test',
            'description' => 'Updated description',
            'traffic_allocation' => 80,
            'is_active' => true,
        ]);

        $response->assertRedirect("/ab-testing/dashboard/{$experimentId}")
                ->assertSessionHas('success', 'Experiment updated successfully!');
    }

    /** @test */
    public function it_validates_experiment_updates()
    {
        $experimentId = DB::table('ab_experiments')->insertGetId([
            'name' => 'validation_test',
            'variants' => json_encode(['control' => 100]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->put("/ab-testing/dashboard/{$experimentId}", [
            'name' => '',
            'variants' => ['control' => 150], // Invalid weight > 100
        ]);

        $response->assertSessionHasErrors(['name', 'variants']);
    }

    /** @test */
    public function it_allows_same_name_on_update()
    {
        $experimentId = DB::table('ab_experiments')->insertGetId([
            'name' => 'same_name_test',
            'variants' => json_encode(['control' => 50, 'variant_b' => 50]),
            'traffic_allocation' => 100,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->put("/ab-testing/dashboard/{$experimentId}", [
            'name' => 'same_name_test', // Same name should be allowed for updates
            'variants' => ['control' => 50, 'variant_b' => 50],
            'traffic_allocation' => 100,
            'is_active' => true,
        ]);

        $response->assertRedirect("/ab-testing/dashboard/{$experimentId}")
                ->assertSessionHas('success', 'Experiment updated successfully!');
    }

    /** @test */
    public function it_can_delete_experiment()
    {
        $experimentId = DB::table('ab_experiments')->insertGetId([
            'name' => 'delete_test',
            'variants' => json_encode(['control' => 100]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->delete("/ab-testing/dashboard/{$experimentId}");

        $this->assertDatabaseMissing('ab_experiments', [
            'id' => $experimentId,
        ]);

        $response->assertRedirect('/ab-testing/dashboard')
                ->assertSessionHas('success', 'Experiment deleted successfully!');
    }

    /** @test */
    public function it_can_toggle_experiment_status()
    {
        $experimentId = DB::table('ab_experiments')->insertGetId([
            'name' => 'toggle_test',
            'variants' => json_encode(['control' => 100]),
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->patch("/ab-testing/dashboard/{$experimentId}/toggle");

        $this->assertDatabaseHas('ab_experiments', [
            'id' => $experimentId,
            'is_active' => true, // Should be toggled to true
        ]);

        $response->assertRedirect()
                ->assertSessionHas('success', 'Experiment status updated!');

        // Toggle again
        $response = $this->patch("/ab-testing/dashboard/{$experimentId}/toggle");

        $this->assertDatabaseHas('ab_experiments', [
            'id' => $experimentId,
            'is_active' => false, // Should be toggled back to false
        ]);
    }

    /** @test */
    public function it_calculates_experiment_stats_correctly()
    {
        $experimentId = DB::table('ab_experiments')->insertGetId([
            'name' => 'stats_test',
            'variants' => json_encode(['control' => 50, 'variant_a' => 50]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create assignments
        DB::table('ab_user_assignments')->insert([
            ['experiment_id' => $experimentId, 'user_id' => 'user1', 'variant' => 'control', 'created_at' => now(), 'updated_at' => now()],
            ['experiment_id' => $experimentId, 'user_id' => 'user2', 'variant' => 'control', 'created_at' => now(), 'updated_at' => now()],
            ['experiment_id' => $experimentId, 'user_id' => 'user3', 'variant' => 'variant_a', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Create events
        DB::table('ab_events')->insert([
            ['experiment_id' => $experimentId, 'user_id' => 'user1', 'variant' => 'control', 'event_name' => 'conversion', 'properties' => '{"count": 1}', 'created_at' => now(), 'updated_at' => now()],
            ['experiment_id' => $experimentId, 'user_id' => 'user1', 'variant' => 'control', 'event_name' => 'click', 'properties' => '{"count": 3}', 'created_at' => now(), 'updated_at' => now()],
            ['experiment_id' => $experimentId, 'user_id' => 'user3', 'variant' => 'variant_a', 'event_name' => 'conversion', 'properties' => '{"count": 1}', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->get("/ab-testing/dashboard/{$experimentId}");

        $stats = $response->viewData('stats');

        $this->assertEquals(3, $stats['total_assignments']);
        $this->assertEquals(2, $stats['total_conversions']);
        $this->assertEquals(3, $stats['total_events']);
        $this->assertEquals(2, $stats['unique_users']); // user1 and user3

        // Variant stats
        $this->assertEquals(2, $stats['variants']['control']['assigned']);
        $this->assertEquals(1, $stats['variants']['control']['converted']);
        $this->assertEquals(50.0, $stats['variants']['control']['conversion_rate']);

        $this->assertEquals(1, $stats['variants']['variant_a']['assigned']);
        $this->assertEquals(1, $stats['variants']['variant_a']['converted']);
        $this->assertEquals(100.0, $stats['variants']['variant_a']['conversion_rate']);
    }

    /** @test */
    public function it_computes_significance_for_every_variant_against_control()
    {
        $experimentId = DB::table('ab_experiments')->insertGetId([
            'name' => 'four_arm_sig_test',
            'variants' => json_encode(['control' => 25, 'variant_b' => 25, 'variant_c' => 25, 'variant_d' => 25]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 100 participants per arm; conversion counts chosen so variant_c is
        // the clear winner and must be the named headline.
        $assignments = [];
        $events = [];
        $converted = ['control' => 10, 'variant_b' => 12, 'variant_c' => 30, 'variant_d' => 8];
        foreach ($converted as $variant => $conversions) {
            for ($i = 0; $i < 100; $i++) {
                $userId = "{$variant}-user-{$i}";
                $assignments[] = ['experiment_id' => $experimentId, 'user_id' => $userId, 'variant' => $variant, 'created_at' => now(), 'updated_at' => now()];
                if ($i < $conversions) {
                    $events[] = ['experiment_id' => $experimentId, 'user_id' => $userId, 'variant' => $variant, 'event_name' => 'conversion', 'properties' => '{"count": 1}', 'created_at' => now(), 'updated_at' => now()];
                }
            }
        }
        DB::table('ab_user_assignments')->insert($assignments);
        DB::table('ab_events')->insert($events);

        $stats = $this->get("/ab-testing/dashboard/{$experimentId}")->viewData('stats');

        // Every non-control arm gets its own result — not just the first.
        $this->assertSame(['variant_b', 'variant_c', 'variant_d'], array_keys($stats['significance_by_variant']));
        $this->assertSame('significant', $stats['significance_by_variant']['variant_c']['status']);

        // Headline names the best-confidence arm.
        $this->assertSame('variant_c', $stats['statistical_significance']['variant']);
        $this->assertGreaterThanOrEqual(95, $stats['statistical_significance']['percentage']);
    }

    /** @test */
    public function it_reports_real_day_over_day_rate_change()
    {
        $experimentId = DB::table('ab_experiments')->insertGetId([
            'name' => 'rate_change_test',
            'variants' => json_encode(['control' => 50, 'variant_b' => 50]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Yesterday: 4 participants, 1 conversion (25%). Today: +1 participant
        // who converts -> cumulative 2/5 = 40% -> +15pp.
        $yesterday = now()->subDay();
        $rows = [];
        for ($i = 0; $i < 4; $i++) {
            $rows[] = ['experiment_id' => $experimentId, 'user_id' => "y{$i}", 'variant' => $i % 2 ? 'variant_b' : 'control', 'created_at' => $yesterday, 'updated_at' => $yesterday];
        }
        $rows[] = ['experiment_id' => $experimentId, 'user_id' => 'today-user', 'variant' => 'control', 'created_at' => now(), 'updated_at' => now()];
        DB::table('ab_user_assignments')->insert($rows);
        DB::table('ab_events')->insert([
            ['experiment_id' => $experimentId, 'user_id' => 'y0', 'variant' => 'control', 'event_name' => 'conversion', 'properties' => '{"count": 1}', 'created_at' => $yesterday, 'updated_at' => $yesterday],
            ['experiment_id' => $experimentId, 'user_id' => 'today-user', 'variant' => 'control', 'event_name' => 'conversion', 'properties' => '{"count": 1}', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $stats = $this->get("/ab-testing/dashboard/{$experimentId}")->viewData('stats');

        $this->assertEqualsWithDelta(15.0, $stats['rate_change_pp'], 0.01);
    }

    /** @test */
    public function rate_change_is_null_with_no_prior_day_data()
    {
        $experimentId = DB::table('ab_experiments')->insertGetId([
            'name' => 'rate_change_null_test',
            'variants' => json_encode(['control' => 50, 'variant_b' => 50]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ab_user_assignments')->insert([
            ['experiment_id' => $experimentId, 'user_id' => 'u1', 'variant' => 'control', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $stats = $this->get("/ab-testing/dashboard/{$experimentId}")->viewData('stats');

        $this->assertNull($stats['rate_change_pp']);
    }

    /** @test */
    public function dashboard_uses_a_bound_funnel_provider_and_falls_back_on_null()
    {
        $experimentId = DB::table('ab_experiments')->insertGetId([
            'name' => 'provider_test',
            'variants' => json_encode(['control' => 50, 'variant_b' => 50]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ab_user_assignments')->insert([
            ['experiment_id' => $experimentId, 'user_id' => 'u1', 'variant' => 'control', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('ab_events')->insert([
            ['experiment_id' => $experimentId, 'user_id' => 'u1', 'variant' => 'control', 'event_name' => 'conversion', 'properties' => '{"count":1}', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Bound provider with data: its funnel wins.
        $this->app->bind(\Homemove\AbTesting\Contracts\FunnelStepDataProvider::class, function () {
            return new class implements \Homemove\AbTesting\Contracts\FunnelStepDataProvider {
                public function funnelFor(string $experimentName, ?string $deviceType = null): ?array
                {
                    return [
                        'source' => 'fake_step_source',
                        'steps' => [
                            ['key' => 'landing', 'label' => 'Landing', 'index' => 0, 'counts' => ['control' => 10, 'variant_b' => 9]],
                            ['key' => 'question_1', 'label' => 'Question 1', 'index' => 1, 'counts' => ['control' => 4, 'variant_b' => 8]],
                        ],
                    ];
                }
            };
        });

        $stats = $this->get("/ab-testing/dashboard/{$experimentId}")->viewData('stats');
        $this->assertSame('fake_step_source', $stats['funnel']['source']);
        // biggest drop computed for provider data that arrives without one
        $this->assertSame('landing', $stats['funnel']['biggest_drop_after']['control']);

        // Provider returning null: package reach funnel takes over.
        $this->app->bind(\Homemove\AbTesting\Contracts\FunnelStepDataProvider::class, function () {
            return new class implements \Homemove\AbTesting\Contracts\FunnelStepDataProvider {
                public function funnelFor(string $experimentName, ?string $deviceType = null): ?array
                {
                    return null;
                }
            };
        });

        $stats = $this->get("/ab-testing/dashboard/{$experimentId}")->viewData('stats');
        $this->assertSame('ab_events', $stats['funnel']['source']);
    }

    /** @test */
    public function funnel_steps_round_trip_through_custom_events()
    {
        $response = $this->post('/ab-testing/dashboard', [
            'name' => 'funnel_steps_test',
            'variants' => ['control' => 50, 'variant_b' => 50],
            'traffic_allocation' => 100,
            'funnel_steps' => ['flow_viewed', '  ', 'lead_created', ''],
        ]);

        $experiment = Experiment::where('name', 'funnel_steps_test')->firstOrFail();
        // blanks dropped, order kept
        $this->assertSame(['flow_viewed', 'lead_created'], $experiment->custom_events);

        // What a real "remove all steps" form submission sends: only the
        // hidden sentinel (an empty string), never a bare [].
        $this->put("/ab-testing/dashboard/{$experiment->id}", [
            'name' => 'funnel_steps_test',
            'variants' => ['control' => 50, 'variant_b' => 50],
            'traffic_allocation' => 100,
            'is_active' => true,
            'funnel_steps' => [''],
        ]);

        $this->assertNull($experiment->fresh()->custom_events);

        // A caller that omits the key entirely (non-form API usage) leaves
        // the stored list untouched.
        $experiment->update(['custom_events' => ['flow_viewed']]);
        $this->put("/ab-testing/dashboard/{$experiment->id}", [
            'name' => 'funnel_steps_test',
            'variants' => ['control' => 50, 'variant_b' => 50],
            'traffic_allocation' => 100,
            'is_active' => true,
        ]);
        $this->assertSame(['flow_viewed'], $experiment->fresh()->custom_events);
    }

    /** @test */
    public function it_handles_zero_assignments_in_stats()
    {
        $experimentId = DB::table('ab_experiments')->insertGetId([
            'name' => 'zero_stats_test',
            'variants' => json_encode(['control' => 100]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get("/ab-testing/dashboard/{$experimentId}");

        $stats = $response->viewData('stats');

        $this->assertEquals(0, $stats['total_assignments']);
        $this->assertEquals(0, $stats['total_conversions']);
        $this->assertEquals(0, $stats['variants']['control']['conversion_rate']);
    }

}