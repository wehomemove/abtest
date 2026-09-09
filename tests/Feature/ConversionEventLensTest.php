<?php

namespace Homemove\AbTesting\Tests\Feature;

use Homemove\AbTesting\Models\Experiment;
use Homemove\AbTesting\Services\ExperimentStatsService;
use Homemove\AbTesting\Services\ReachFunnelService;
use Homemove\AbTesting\Tests\TestCase;
use Illuminate\Support\Facades\DB;

class ConversionEventLensTest extends TestCase
{
    private function makeExperiment(array $attributes = []): Experiment
    {
        return Experiment::create(array_merge([
            'name' => 'lens_test_' . uniqid(),
            'variants' => ['control' => 50, 'variant_b' => 50],
            'traffic_allocation' => 100,
            'is_active' => true,
        ], $attributes));
    }

    private function seedJourney(Experiment $experiment, string $variant, string $user, array $events): void
    {
        $at = now()->subMinutes(10);
        DB::table('ab_user_assignments')->insertOrIgnore([
            'experiment_id' => $experiment->id, 'user_id' => $user, 'variant' => $variant,
            'created_at' => $at, 'updated_at' => $at,
        ]);
        foreach ($events as $i => $event) {
            DB::table('ab_events')->insert([
                'experiment_id' => $experiment->id, 'user_id' => $user, 'variant' => $variant,
                'event_name' => $event, 'properties' => '{"count": 1}',
                'created_at' => $at->copy()->addSeconds($i), 'updated_at' => $at->copy()->addSeconds($i),
            ]);
        }
    }

    /** @test */
    public function variant_stats_count_only_the_selected_event()
    {
        $experiment = $this->makeExperiment();
        $this->seedJourney($experiment, 'control', 'c1', ['lead_created', 'conversion']);
        $this->seedJourney($experiment, 'control', 'c2', ['lead_created']);
        $this->seedJourney($experiment, 'variant_b', 'b1', ['conversion']);

        $service = new ExperimentStatsService;

        $default = $service->variantStats($experiment);
        $this->assertSame(1, $default['control']['converted']);
        $this->assertSame(1, $default['variant_b']['converted']);

        $lens = $service->variantStats($experiment, null, 'lead_created');
        $this->assertSame(2, $lens['control']['converted']);
        $this->assertSame(0, $lens['variant_b']['converted']);
    }

    /** @test */
    public function persisted_primary_metric_becomes_the_default_conversion()
    {
        $experiment = $this->makeExperiment(['primary_metric' => 'lead_created']);
        $this->seedJourney($experiment, 'control', 'c1', ['lead_created']);
        $this->seedJourney($experiment, 'control', 'c2', ['conversion']);

        $this->assertSame('lead_created', $experiment->conversionEvent());

        $stats = (new ExperimentStatsService)->variantStats($experiment);
        $this->assertSame(1, $stats['control']['converted']);
    }

    /** @test */
    public function resolver_falls_back_on_unknown_events_and_honours_explicit_conversion()
    {
        $experiment = $this->makeExperiment(['primary_metric' => 'lead_created']);
        $this->seedJourney($experiment, 'control', 'c1', ['lead_created', 'conversion']);

        $service = new ExperimentStatsService;

        $this->assertSame('lead_created', $service->resolveConversionEvent($experiment, null));
        $this->assertSame('lead_created', $service->resolveConversionEvent($experiment, 'not_a_real_event'));
        $this->assertSame('lead_created', $service->resolveConversionEvent($experiment, ['array' => 'shape']));
        // Explicit 'conversion' overrides the persisted primary.
        $this->assertSame('conversion', $service->resolveConversionEvent($experiment, 'conversion'));
        $this->assertSame('conversion', $service->resolveConversionEvent($experiment->fresh(), 'conversion'));
    }

    /** @test */
    public function reach_funnel_pins_the_selected_event_last()
    {
        $experiment = $this->makeExperiment();
        $this->seedJourney($experiment, 'control', 'c1', ['lead_created', 'phone_added', 'conversion']);

        $funnel = (new ReachFunnelService)->funnelFor($experiment, null, 'phone_added');

        $keys = array_column($funnel['steps'], 'key');
        $this->assertSame('phone_added', end($keys));
    }

    /** @test */
    public function show_page_recomputes_against_the_event_param()
    {
        $experiment = $this->makeExperiment();
        $this->seedJourney($experiment, 'control', 'c1', ['lead_created', 'conversion']);
        $this->seedJourney($experiment, 'control', 'c2', ['lead_created']);

        $response = $this->get(route('ab-testing.dashboard.show', $experiment) . '?event=lead_created');

        $response->assertOk();
        $response->assertViewHas('activeConversionEvent', 'lead_created');
        $stats = $response->viewData('stats');
        $this->assertSame(2, $stats['variants']['control']['converted']);
    }

    /** @test */
    public function show_page_without_param_and_without_primary_matches_legacy_behaviour()
    {
        $experiment = $this->makeExperiment();
        $this->seedJourney($experiment, 'control', 'c1', ['lead_created', 'conversion']);
        $this->seedJourney($experiment, 'control', 'c2', ['lead_created']);

        $response = $this->get(route('ab-testing.dashboard.show', $experiment));

        $response->assertOk();
        $response->assertViewHas('activeConversionEvent', 'conversion');
        $stats = $response->viewData('stats');
        $this->assertSame(1, $stats['variants']['control']['converted']);
    }

    /** @test */
    public function stats_api_honours_the_event_param()
    {
        $experiment = $this->makeExperiment();
        $this->seedJourney($experiment, 'control', 'c1', ['lead_created', 'conversion']);
        $this->seedJourney($experiment, 'control', 'c2', ['lead_created']);

        $default = $this->getJson("/api/ab-testing/experiments/{$experiment->id}/stats")->assertOk()->json();
        $this->assertSame(1, $default['variants']['control']['conversions']);
        $this->assertSame('conversion', $default['conversion_event']);

        $lens = $this->getJson("/api/ab-testing/experiments/{$experiment->id}/stats?event=lead_created")->assertOk()->json();
        $this->assertSame(2, $lens['variants']['control']['conversions']);
        $this->assertSame('lead_created', $lens['conversion_event']);
    }

    /** @test */
    public function results_api_honours_the_event_param()
    {
        $experiment = $this->makeExperiment();
        $this->seedJourney($experiment, 'control', 'c1', ['lead_created', 'conversion']);
        $this->seedJourney($experiment, 'control', 'c2', ['lead_created']);

        $lens = $this->getJson("/api/ab-testing/results/{$experiment->name}?event=lead_created")->assertOk()->json();
        $this->assertSame(2, $lens['variants']['control']['conversions']);
    }

    /** @test */
    public function primary_metric_can_be_saved_and_reset()
    {
        $experiment = $this->makeExperiment();
        $this->seedJourney($experiment, 'control', 'c1', ['lead_created', 'conversion']);

        $this->post(route('ab-testing.dashboard.primary-metric', $experiment), ['primary_metric' => 'lead_created'])
            ->assertRedirect(route('ab-testing.dashboard.show', $experiment));
        $this->assertSame('lead_created', $experiment->fresh()->primary_metric);

        // Untracked events are rejected.
        $this->from(route('ab-testing.dashboard.show', $experiment))
            ->post(route('ab-testing.dashboard.primary-metric', $experiment), ['primary_metric' => 'nope'])
            ->assertSessionHasErrors('primary_metric');
        $this->assertSame('lead_created', $experiment->fresh()->primary_metric);

        // 'conversion' stores as null (the universal default).
        $this->post(route('ab-testing.dashboard.primary-metric', $experiment), ['primary_metric' => 'conversion']);
        $this->assertNull($experiment->fresh()->primary_metric);
    }
}
