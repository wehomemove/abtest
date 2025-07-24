<?php

namespace Homemove\AbTesting\Tests\Feature;

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
            'variants' => json_encode(['control' => 100]),
            'traffic_allocation' => 100,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->put("/ab-testing/dashboard/{$experimentId}", [
            'name' => 'same_name_test', // Same name should be allowed for updates
            'variants' => ['control' => 100],
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
        $this->assertEquals(5, $stats['total_interactions']); // 1 + 3 + 1
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

    /** @test */
    public function it_groups_user_events_correctly()
    {
        $experimentId = DB::table('ab_experiments')->insertGetId([
            'name' => 'user_events_test',
            'variants' => json_encode(['control' => 100]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('ab_user_assignments')->insert([
            ['experiment_id' => $experimentId, 'user_id' => 'user1', 'variant' => 'control', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $baseTime = now();
        DB::table('ab_events')->insert([
            ['experiment_id' => $experimentId, 'user_id' => 'user1', 'variant' => 'control', 'event_name' => 'click', 'properties' => '{"count": 2}', 'created_at' => $baseTime, 'updated_at' => $baseTime],
            ['experiment_id' => $experimentId, 'user_id' => 'user1', 'variant' => 'control', 'event_name' => 'conversion', 'properties' => '{"count": 1}', 'created_at' => $baseTime->addMinute(), 'updated_at' => $baseTime->addMinute()],
        ]);

        $response = $this->get("/ab-testing/dashboard/{$experimentId}");

        $stats = $response->viewData('stats');
        $userEvents = $stats['user_events'];

        $this->assertCount(1, $userEvents);
        
        $user = $userEvents->first();
        $this->assertEquals('user1', $user['user_id']);
        $this->assertEquals('control', $user['variant']);
        $this->assertEquals(3, $user['total_interactions']); // 2 + 1
        $this->assertEquals(2, $user['unique_events']); // click, conversion
        
        $this->assertArrayHasKey('click', $user['events']);
        $this->assertArrayHasKey('conversion', $user['events']);
        $this->assertEquals(2, $user['events']['click']['count']);
        $this->assertEquals(1, $user['events']['conversion']['count']);
    }
}