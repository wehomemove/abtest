<?php

namespace Homemove\AbTesting\Tests\Feature;

use Homemove\AbTesting\Tests\TestCase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ApiControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Disable actual logging during tests
        Log::shouldReceive('error')->andReturn(null);
    }

    /** @test */
    public function it_can_track_events_successfully()
    {
        $experimentId = DB::table('ab_experiments')->insertGetId([
            'name' => 'api_track_test',
            'variants' => json_encode(['control' => 100]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/ab-testing/track', [
            'experiment' => 'api_track_test',
            'event' => 'button_click',
            'user_id' => 'api_user_123',
            'properties' => ['page' => 'homepage', 'button_type' => 'cta']
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Event tracked successfully'
                ]);

        $this->assertDatabaseHas('ab_events', [
            'experiment_id' => $experimentId,
            'user_id' => 'api_user_123',
            'event_name' => 'button_click',
            'variant' => 'control',
        ]);
    }

    /** @test */
    public function it_validates_required_fields_for_tracking()
    {
        $response = $this->postJson('/api/ab-testing/track', [
            'experiment' => 'test_exp',
            // Missing 'event' field
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['event']);
    }

    /** @test */
    public function it_can_track_without_user_id()
    {
        DB::table('ab_experiments')->insertGetId([
            'name' => 'no_user_test',
            'variants' => json_encode(['control' => 100]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/ab-testing/track', [
            'experiment' => 'no_user_test',
            'event' => 'page_view',
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Event tracked successfully'
                ]);
    }

    /** @test */
    public function it_can_track_without_properties()
    {
        DB::table('ab_experiments')->insertGetId([
            'name' => 'no_props_test',
            'variants' => json_encode(['control' => 100]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/ab-testing/track', [
            'experiment' => 'no_props_test',
            'event' => 'conversion',
            'user_id' => 'simple_user'
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Event tracked successfully'
                ]);
    }

    /** @test */
    public function it_handles_tracking_errors_gracefully()
    {
        // Force an error by using invalid data that will cause an exception
        $response = $this->postJson('/api/ab-testing/track', [
            'experiment' => ['invalid' => 'array'], // Should be string, not array
            'event' => 'test_event',
        ]);

        $response->assertStatus(422); // Validation error
    }

    /** @test */
    public function it_can_get_variant_successfully()
    {
        DB::table('ab_experiments')->insert([
            'name' => 'variant_test',
            'variants' => json_encode(['control' => 50, 'variant_a' => 50]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/ab-testing/variant', [
            'experiment' => 'variant_test',
            'user_id' => 'variant_user_123'
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'variant',
                    'experiment'
                ])
                ->assertJson([
                    'success' => true,
                    'experiment' => 'variant_test'
                ]);

        $variant = $response->json('variant');
        $this->assertContains($variant, ['control', 'variant_a']);
    }

    /** @test */
    public function it_validates_required_fields_for_variant()
    {
        $response = $this->postJson('/api/ab-testing/variant', [
            // Missing 'experiment' field
            'user_id' => 'test_user'
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['experiment']);
    }

    /** @test */
    public function it_can_get_variant_without_user_id()
    {
        DB::table('ab_experiments')->insert([
            'name' => 'no_user_variant_test',
            'variants' => json_encode(['control' => 100]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/ab-testing/variant', [
            'experiment' => 'no_user_variant_test'
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'variant' => 'control',
                    'experiment' => 'no_user_variant_test'
                ]);
    }

    /** @test */
    public function it_handles_variant_errors_gracefully()
    {
        $response = $this->postJson('/api/ab-testing/variant', [
            'experiment' => ['invalid' => 'array'], // Should be string
        ]);

        $response->assertStatus(422); // Validation error
    }

    /** @test */
    public function it_can_get_results_for_existing_experiment()
    {
        $experimentId = DB::table('ab_experiments')->insertGetId([
            'name' => 'results_test',
            'description' => 'Test experiment for results',
            'variants' => json_encode(['control' => 50, 'variant_a' => 50]),
            'is_active' => true,
            'status' => 'running',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create some test data
        DB::table('ab_user_assignments')->insert([
            ['experiment_id' => $experimentId, 'user_id' => 'user1', 'variant' => 'control', 'created_at' => now(), 'updated_at' => now()],
            ['experiment_id' => $experimentId, 'user_id' => 'user2', 'variant' => 'variant_a', 'created_at' => now(), 'updated_at' => now()],
            ['experiment_id' => $experimentId, 'user_id' => 'user3', 'variant' => 'control', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('ab_events')->insert([
            ['experiment_id' => $experimentId, 'user_id' => 'user1', 'variant' => 'control', 'event_name' => 'conversion', 'properties' => '{}', 'created_at' => now(), 'updated_at' => now()],
            ['experiment_id' => $experimentId, 'user_id' => 'user2', 'variant' => 'variant_a', 'event_name' => 'conversion', 'properties' => '{}', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->getJson('/api/ab-testing/results/results_test');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'experiment' => ['name', 'description', 'is_active', 'status'],
                    'variants' => [
                        'control' => ['weight', 'assignments', 'conversions', 'conversion_rate'],
                        'variant_a' => ['weight', 'assignments', 'conversions', 'conversion_rate']
                    ],
                    'total_assignments',
                    'total_conversions'
                ])
                ->assertJson([
                    'success' => true,
                    'experiment' => [
                        'name' => 'results_test',
                        'description' => 'Test experiment for results',
                        'is_active' => true,
                        'status' => 'running'
                    ],
                    'variants' => [
                        'control' => [
                            'weight' => 50,
                            'assignments' => 2,
                            'conversions' => 1,
                            'conversion_rate' => 50.0
                        ],
                        'variant_a' => [
                            'weight' => 50,
                            'assignments' => 1,
                            'conversions' => 1,
                            'conversion_rate' => 100.0
                        ]
                    ],
                    'total_assignments' => 3,
                    'total_conversions' => 2
                ]);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_experiment_results()
    {
        $response = $this->getJson('/api/ab-testing/results/nonexistent_experiment');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => 'Experiment not found'
                ]);
    }

    /** @test */
    public function it_calculates_zero_conversion_rate_when_no_assignments()
    {
        $experimentId = DB::table('ab_experiments')->insertGetId([
            'name' => 'zero_rate_test',
            'variants' => json_encode(['control' => 100]),
            'is_active' => true,
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/ab-testing/results/zero_rate_test');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'variants' => [
                        'control' => [
                            'assignments' => 0,
                            'conversions' => 0,
                            'conversion_rate' => 0
                        ]
                    ],
                    'total_assignments' => 0,
                    'total_conversions' => 0
                ]);
    }

    /** @test */
    public function it_handles_results_errors_gracefully()
    {
        // This test is harder to trigger since we're handling most error cases
        // but we can test the general error handling structure
        
        $response = $this->getJson('/api/ab-testing/results/test_exp');

        // Should return 404 for non-existent experiment, not 500
        $response->assertStatus(404);
    }

    /** @test */
    public function it_counts_distinct_conversions_per_user()
    {
        $experimentId = DB::table('ab_experiments')->insertGetId([
            'name' => 'distinct_test',
            'variants' => json_encode(['control' => 100]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('ab_user_assignments')->insert([
            ['experiment_id' => $experimentId, 'user_id' => 'user1', 'variant' => 'control', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Same user has multiple conversion events (should count as 1 conversion)
        DB::table('ab_events')->insert([
            ['experiment_id' => $experimentId, 'user_id' => 'user1', 'variant' => 'control', 'event_name' => 'conversion', 'properties' => '{}', 'created_at' => now(), 'updated_at' => now()],
            ['experiment_id' => $experimentId, 'user_id' => 'user1', 'variant' => 'control', 'event_name' => 'conversion', 'properties' => '{}', 'created_at' => now()->addMinute(), 'updated_at' => now()->addMinute()],
        ]);

        $response = $this->getJson('/api/ab-testing/results/distinct_test');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'variants' => [
                        'control' => [
                            'assignments' => 1,
                            'conversions' => 1, // Should be 1, not 2
                            'conversion_rate' => 100.0
                        ]
                    ]
                ]);
    }
}