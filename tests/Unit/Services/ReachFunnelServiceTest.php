<?php

namespace Homemove\AbTesting\Tests\Unit\Services;

use Homemove\AbTesting\Models\Experiment;
use Homemove\AbTesting\Services\ReachFunnelService;
use Homemove\AbTesting\Tests\TestCase;
use Illuminate\Support\Facades\DB;

class ReachFunnelServiceTest extends TestCase
{
    private function makeExperiment(?array $customEvents = null): Experiment
    {
        return Experiment::create([
            'name' => 'funnel_test_' . uniqid(),
            'variants' => ['control' => 50, 'variant_b' => 50],
            'traffic_allocation' => 100,
            'is_active' => true,
            'custom_events' => $customEvents,
        ]);
    }

    private function seedJourney(Experiment $experiment, string $variant, string $user, array $events, $at = null): void
    {
        $at = $at ?? now();
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
    public function it_builds_a_reach_funnel_with_assigned_first_and_conversion_last()
    {
        $experiment = $this->makeExperiment();
        $this->seedJourney($experiment, 'control', 'c1', ['flow_viewed', 'lead_created', 'conversion'], now()->subMinutes(10));
        $this->seedJourney($experiment, 'control', 'c2', ['flow_viewed'], now()->subMinutes(9));
        $this->seedJourney($experiment, 'variant_b', 'b1', ['flow_viewed', 'lead_created'], now()->subMinutes(8));

        $funnel = (new ReachFunnelService)->funnelFor($experiment);

        $this->assertSame('ab_events', $funnel['source']);
        $keys = array_column($funnel['steps'], 'key');
        $this->assertSame('_assigned', $keys[0]);
        $this->assertSame('conversion', end($keys));
        // first-touch ordering: flow_viewed before lead_created
        $this->assertLessThan(array_search('lead_created', $keys), array_search('flow_viewed', $keys));

        $byKey = array_column($funnel['steps'], 'counts', 'key');
        $this->assertSame(2, $byKey['_assigned']['control']);
        $this->assertSame(1, $byKey['_assigned']['variant_b']);
        $this->assertSame(2, $byKey['flow_viewed']['control']);
        $this->assertSame(1, $byKey['lead_created']['control']);
        $this->assertSame(1, $byKey['conversion']['control']);
        $this->assertSame(0, $byKey['conversion']['variant_b']);
    }

    /** @test */
    public function configured_custom_events_control_the_step_order()
    {
        $experiment = $this->makeExperiment(['lead_created', 'flow_viewed']);
        $this->seedJourney($experiment, 'control', 'c1', ['flow_viewed', 'lead_created'], now()->subMinutes(5));

        $funnel = (new ReachFunnelService)->funnelFor($experiment);

        $keys = array_column($funnel['steps'], 'key');
        // configured order wins over first-touch order
        $this->assertLessThan(array_search('flow_viewed', $keys), array_search('lead_created', $keys));
    }

    /** @test */
    public function it_identifies_the_biggest_drop_per_variant()
    {
        $experiment = $this->makeExperiment();
        // control: 4 assigned -> 4 flow_viewed -> 1 lead_created (biggest drop after flow_viewed)
        foreach (range(1, 4) as $i) {
            $this->seedJourney($experiment, 'control', "c{$i}", $i === 1 ? ['flow_viewed', 'lead_created'] : ['flow_viewed'], now()->subMinutes(10));
        }

        $funnel = (new ReachFunnelService)->funnelFor($experiment);

        $this->assertSame('flow_viewed', $funnel['biggest_drop_after']['control']);
    }

    /** @test */
    public function structural_zeros_are_not_counted_as_drops()
    {
        $experiment = $this->makeExperiment(['postcode_submitted', 'flow_viewed']);
        // This arm never fires postcode_submitted (another arm's event) but
        // flows on: assigned 4 -> [postcode 0, skipped] -> flow_viewed 2 ->
        // conversion 2. Biggest real drop = after _assigned (50%), never the
        // structural zero.
        foreach (range(1, 4) as $i) {
            $this->seedJourney($experiment, 'control', "c{$i}", $i <= 2 ? ['flow_viewed', 'conversion'] : [], now()->subMinutes(10));
        }

        $funnel = (new ReachFunnelService)->funnelFor($experiment);

        $this->assertSame('_assigned', $funnel['biggest_drop_after']['control']);
    }

    /** @test */
    public function total_loss_flags_the_last_reached_step()
    {
        $experiment = $this->makeExperiment(['flow_viewed']);
        // Both users reach flow_viewed, nobody converts: 100% loss after
        // flow_viewed must be flagged even though later steps are all zero.
        $this->seedJourney($experiment, 'control', 'c1', ['flow_viewed'], now()->subMinutes(10));
        $this->seedJourney($experiment, 'control', 'c2', ['flow_viewed'], now()->subMinutes(9));
        $this->seedJourney($experiment, 'variant_b', 'b1', ['flow_viewed', 'conversion'], now()->subMinutes(8));

        $funnel = (new ReachFunnelService)->funnelFor($experiment);

        $this->assertSame('flow_viewed', $funnel['biggest_drop_after']['control']);
    }

    /** @test */
    public function it_returns_null_with_no_events()
    {
        $experiment = $this->makeExperiment();

        $this->assertNull((new ReachFunnelService)->funnelFor($experiment));
    }

    /** @test */
    public function device_filter_scopes_the_counts()
    {
        $experiment = $this->makeExperiment();
        DB::table('ab_user_assignments')->insert([
            ['experiment_id' => $experiment->id, 'user_id' => 'm1', 'variant' => 'control', 'device_type' => 'mobile', 'created_at' => now(), 'updated_at' => now()],
            ['experiment_id' => $experiment->id, 'user_id' => 'd1', 'variant' => 'control', 'device_type' => 'desktop', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('ab_events')->insert([
            ['experiment_id' => $experiment->id, 'user_id' => 'm1', 'variant' => 'control', 'device_type' => 'mobile', 'event_name' => 'conversion', 'properties' => '{"count":1}', 'created_at' => now(), 'updated_at' => now()],
            ['experiment_id' => $experiment->id, 'user_id' => 'd1', 'variant' => 'control', 'device_type' => 'desktop', 'event_name' => 'conversion', 'properties' => '{"count":1}', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $funnel = (new ReachFunnelService)->funnelFor($experiment, 'mobile');

        $byKey = array_column($funnel['steps'], 'counts', 'key');
        $this->assertSame(1, $byKey['_assigned']['control']);
        $this->assertSame(1, $byKey['conversion']['control']);
    }
}
