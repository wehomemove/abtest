<?php

namespace Homemove\AbTesting\Tests\Unit\Models;

use Homemove\AbTesting\Models\Experiment;
use Homemove\AbTesting\Models\UserAssignment;
use Homemove\AbTesting\Models\Event;
use Homemove\AbTesting\Tests\TestCase;
use Illuminate\Support\Facades\DB;

class ExperimentTest extends TestCase
{
    /** @test */
    public function it_can_create_experiment()
    {
        $experiment = Experiment::create([
            'name' => 'test_experiment',
            'description' => 'A test experiment',
            'variants' => ['control' => 50, 'variant_a' => 50],
            'is_active' => true,
            'traffic_allocation' => 100,
        ]);

        $this->assertInstanceOf(Experiment::class, $experiment);
        $this->assertEquals('test_experiment', $experiment->name);
        $this->assertEquals(['control' => 50, 'variant_a' => 50], $experiment->variants);
        $this->assertTrue($experiment->is_active);
    }

    /** @test */
    public function it_casts_attributes_correctly()
    {
        $experiment = Experiment::create([
            'name' => 'cast_test',
            'variants' => ['control' => 60, 'variant_a' => 40],
            'target_applications' => ['motus', 'apollo'],
            'success_metrics' => ['conversion_rate', 'revenue'],
            'custom_events' => ['signup', 'purchase'],
            'targeting_rules' => ['country' => 'US'],
            'is_active' => '1', // String should be cast to boolean
            'start_date' => '2024-01-01 00:00:00',
            'end_date' => '2024-12-31 23:59:59',
            'confidence_level' => '95.00',
        ]);

        $this->assertIsArray($experiment->variants);
        $this->assertIsArray($experiment->target_applications);
        $this->assertIsArray($experiment->success_metrics);
        $this->assertIsArray($experiment->custom_events);
        $this->assertIsArray($experiment->targeting_rules);
        $this->assertIsBool($experiment->is_active);
        $this->assertTrue($experiment->is_active);
        $this->assertInstanceOf(\DateTime::class, $experiment->start_date);
        $this->assertInstanceOf(\DateTime::class, $experiment->end_date);
        $this->assertEquals(95.00, $experiment->confidence_level);
    }

    /** @test */
    public function it_has_assignments_relationship()
    {
        $experiment = Experiment::create([
            'name' => 'assignment_test',
            'variants' => ['control' => 100],
            'is_active' => true,
        ]);

        // Create assignments
        DB::table('ab_user_assignments')->insert([
            ['experiment_id' => $experiment->id, 'user_id' => 'user1', 'variant' => 'control', 'created_at' => now(), 'updated_at' => now()],
            ['experiment_id' => $experiment->id, 'user_id' => 'user2', 'variant' => 'control', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $assignments = $experiment->assignments;

        $this->assertCount(2, $assignments);
        $this->assertEquals('user1', $assignments->first()->user_id);
    }

    /** @test */
    public function it_has_events_relationship()
    {
        $experiment = Experiment::create([
            'name' => 'events_test',
            'variants' => ['control' => 100],
            'is_active' => true,
        ]);

        // Create events
        DB::table('ab_events')->insert([
            ['experiment_id' => $experiment->id, 'user_id' => 'user1', 'variant' => 'control', 'event_name' => 'click', 'properties' => '{}', 'created_at' => now(), 'updated_at' => now()],
            ['experiment_id' => $experiment->id, 'user_id' => 'user2', 'variant' => 'control', 'event_name' => 'conversion', 'properties' => '{}', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $events = $experiment->events;

        $this->assertCount(2, $events);
        $this->assertEquals('click', $events->first()->event_name);
    }

    /** @test */
    public function it_calculates_conversion_rate_attribute()
    {
        $experiment = Experiment::create([
            'name' => 'conversion_test',
            'variants' => ['control' => 50, 'variant_a' => 50],
            'is_active' => true,
        ]);

        // Create assignments
        DB::table('ab_user_assignments')->insert([
            ['experiment_id' => $experiment->id, 'user_id' => 'user1', 'variant' => 'control', 'created_at' => now(), 'updated_at' => now()],
            ['experiment_id' => $experiment->id, 'user_id' => 'user2', 'variant' => 'control', 'created_at' => now(), 'updated_at' => now()],
            ['experiment_id' => $experiment->id, 'user_id' => 'user3', 'variant' => 'variant_a', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Create conversion events
        DB::table('ab_events')->insert([
            ['experiment_id' => $experiment->id, 'user_id' => 'user1', 'variant' => 'control', 'event_name' => 'conversion', 'properties' => '{}', 'created_at' => now(), 'updated_at' => now()],
            ['experiment_id' => $experiment->id, 'user_id' => 'user3', 'variant' => 'variant_a', 'event_name' => 'conversion', 'properties' => '{}', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $conversionRates = $experiment->conversion_rate;

        $this->assertEquals(2, $conversionRates['control']['total']);
        $this->assertEquals(1, $conversionRates['control']['conversions']);
        $this->assertEquals(50.0, $conversionRates['control']['rate']);

        $this->assertEquals(1, $conversionRates['variant_a']['total']);
        $this->assertEquals(1, $conversionRates['variant_a']['conversions']);
        $this->assertEquals(100.0, $conversionRates['variant_a']['rate']);
    }

    /** @test */
    public function it_handles_zero_assignments_in_conversion_rate()
    {
        $experiment = Experiment::create([
            'name' => 'zero_conversion_test',
            'variants' => ['control' => 100],
            'is_active' => true,
        ]);

        $conversionRates = $experiment->conversion_rate;

        $this->assertEmpty($conversionRates);
    }

    /** @test */
    public function it_checks_if_experiment_is_active()
    {
        // Active experiment
        $activeExperiment = Experiment::create([
            'name' => 'active_test',
            'variants' => ['control' => 100],
            'is_active' => true,
            'status' => 'running',
        ]);

        $this->assertTrue($activeExperiment->isActive());

        // Inactive experiment
        $inactiveExperiment = Experiment::create([
            'name' => 'inactive_test',
            'variants' => ['control' => 100],
            'is_active' => false,
            'status' => 'running',
        ]);

        $this->assertFalse($inactiveExperiment->isActive());

        // Wrong status
        $draftExperiment = Experiment::create([
            'name' => 'draft_test',
            'variants' => ['control' => 100],
            'is_active' => true,
            'status' => 'draft',
        ]);

        $this->assertFalse($draftExperiment->isActive());
    }

    /** @test */
    public function it_checks_start_and_end_dates_for_active_status()
    {
        // Not started yet
        $futureExperiment = Experiment::create([
            'name' => 'future_test',
            'variants' => ['control' => 100],
            'is_active' => true,
            'status' => 'running',
            'start_date' => now()->addDay(),
        ]);

        $this->assertFalse($futureExperiment->isActive());

        // Already ended
        $endedExperiment = Experiment::create([
            'name' => 'ended_test',
            'variants' => ['control' => 100],
            'is_active' => true,
            'status' => 'running',
            'start_date' => now()->subWeek(),
            'end_date' => now()->subDay(),
        ]);

        $this->assertFalse($endedExperiment->isActive());

        // Currently running
        $runningExperiment = Experiment::create([
            'name' => 'running_test',
            'variants' => ['control' => 100],
            'is_active' => true,
            'status' => 'running',
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
        ]);

        $this->assertTrue($runningExperiment->isActive());
    }

    /** @test */
    public function it_calculates_statistical_significance()
    {
        $experiment = Experiment::create([
            'name' => 'significance_test',
            'variants' => ['control' => 50, 'variant_a' => 50],
            'is_active' => true,
            'minimum_sample_size' => 100,
            'confidence_level' => 95,
        ]);

        // Create sufficient assignments for statistical significance
        for ($i = 1; $i <= 200; $i++) {
            DB::table('ab_user_assignments')->insert([
                'experiment_id' => $experiment->id,
                'user_id' => "user{$i}",
                'variant' => $i <= 100 ? 'control' : 'variant_a',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create conversions (control: 10%, variant_a: 20%)
        for ($i = 1; $i <= 10; $i++) {
            DB::table('ab_events')->insert([
                'experiment_id' => $experiment->id,
                'user_id' => "user{$i}",
                'variant' => 'control',
                'event_name' => 'conversion',
                'properties' => '{}',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        for ($i = 101; $i <= 120; $i++) {
            DB::table('ab_events')->insert([
                'experiment_id' => $experiment->id,
                'user_id' => "user{$i}",
                'variant' => 'variant_a',
                'event_name' => 'conversion',
                'properties' => '{}',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $significance = $experiment->getStatisticalSignificance('variant_a');

        $this->assertIsArray($significance);
        $this->assertArrayHasKey('significant', $significance);
        $this->assertArrayHasKey('confidence', $significance);
        $this->assertArrayHasKey('p_value', $significance);
        $this->assertArrayHasKey('z_score', $significance);
        $this->assertArrayHasKey('message', $significance);

        // With this difference (10% vs 20%), it should be significant
        $this->assertTrue($significance['significant']);
        $this->assertGreaterThan(95, $significance['confidence']);
    }

    /** @test */
    public function it_returns_insufficient_sample_size_message()
    {
        $experiment = Experiment::create([
            'name' => 'small_sample_test',
            'variants' => ['control' => 50, 'variant_a' => 50],
            'is_active' => true,
            'minimum_sample_size' => 1000, // High requirement
            'confidence_level' => 95,
        ]);

        // Create only a few assignments
        DB::table('ab_user_assignments')->insert([
            ['experiment_id' => $experiment->id, 'user_id' => 'user1', 'variant' => 'control', 'created_at' => now(), 'updated_at' => now()],
            ['experiment_id' => $experiment->id, 'user_id' => 'user2', 'variant' => 'variant_a', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $significance = $experiment->getStatisticalSignificance('variant_a');

        $this->assertFalse($significance['significant']);
        $this->assertEquals(0, $significance['confidence']);
        $this->assertEquals(1.0, $significance['p_value']);
        $this->assertEquals('Insufficient sample size', $significance['message']);
    }

    /** @test */
    public function it_handles_no_variance_in_statistical_significance()
    {
        $experiment = Experiment::create([
            'name' => 'no_variance_test',
            'variants' => ['control' => 50, 'variant_a' => 50],
            'is_active' => true,
            'minimum_sample_size' => 10,
            'confidence_level' => 95,
        ]);

        // Create assignments but no conversions (0% for both variants)
        for ($i = 1; $i <= 20; $i++) {
            DB::table('ab_user_assignments')->insert([
                'experiment_id' => $experiment->id,
                'user_id' => "user{$i}",
                'variant' => $i <= 10 ? 'control' : 'variant_a',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $significance = $experiment->getStatisticalSignificance('variant_a');

        $this->assertFalse($significance['significant']);
        $this->assertEquals(0, $significance['confidence']);
        $this->assertEquals(1.0, $significance['p_value']);
        $this->assertEquals('No variance in data', $significance['message']);
    }

    /** @test */
    public function it_checks_if_experiment_can_run_in_application()
    {
        // Experiment with specific target applications
        $experiment = Experiment::create([
            'name' => 'app_test',
            'variants' => ['control' => 100],
            'target_applications' => ['motus', 'apollo'],
            'is_active' => true,
        ]);

        $this->assertTrue($experiment->canRunInApplication('motus'));
        $this->assertTrue($experiment->canRunInApplication('apollo'));
        $this->assertFalse($experiment->canRunInApplication('olympus'));

        // Experiment with no target applications (should default to all)
        $experimentAll = Experiment::create([
            'name' => 'app_all_test',
            'variants' => ['control' => 100],
            'target_applications' => null,
            'is_active' => true,
        ]);

        $this->assertTrue($experimentAll->canRunInApplication('motus'));
        $this->assertTrue($experimentAll->canRunInApplication('apollo'));
        $this->assertTrue($experimentAll->canRunInApplication('olympus'));
    }

    /** @test */
    public function it_gets_variant_stats_correctly()
    {
        $experiment = Experiment::create([
            'name' => 'variant_stats_test',
            'variants' => ['control' => 100],
            'is_active' => true,
        ]);

        // Create assignments and conversions
        DB::table('ab_user_assignments')->insert([
            ['experiment_id' => $experiment->id, 'user_id' => 'user1', 'variant' => 'control', 'created_at' => now(), 'updated_at' => now()],
            ['experiment_id' => $experiment->id, 'user_id' => 'user2', 'variant' => 'control', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('ab_events')->insert([
            ['experiment_id' => $experiment->id, 'user_id' => 'user1', 'variant' => 'control', 'event_name' => 'conversion', 'properties' => '{}', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Use reflection to test private method
        $reflection = new \ReflectionClass($experiment);
        $method = $reflection->getMethod('getVariantStats');
        $method->setAccessible(true);

        $stats = $method->invoke($experiment, 'control');

        $this->assertEquals(2, $stats['total']);
        $this->assertEquals(1, $stats['conversions']);
        $this->assertEquals(0.5, $stats['rate']);
    }

    /** @test */
    public function it_handles_mathematical_functions_correctly()
    {
        $experiment = new Experiment();
        $reflection = new \ReflectionClass($experiment);

        // Test normalCDF
        $normalCDF = $reflection->getMethod('normalCDF');
        $normalCDF->setAccessible(true);
        
        $result = $normalCDF->invoke($experiment, 0);
        $this->assertEqualsWithDelta(0.5, $result, 0.01);

        // Test erf
        $erf = $reflection->getMethod('erf');
        $erf->setAccessible(true);
        
        $result = $erf->invoke($experiment, 0);
        $this->assertEqualsWithDelta(0, $result, 0.01);

        $result = $erf->invoke($experiment, 1);
        $this->assertGreaterThan(0.8, $result);
    }
}