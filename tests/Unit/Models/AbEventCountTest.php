<?php

namespace Homemove\AbTesting\Tests\Unit\Models;

use Homemove\AbTesting\Models\AbEventCount;
use Homemove\AbTesting\Models\Experiment;
use Homemove\AbTesting\Tests\TestCase;

class AbEventCountTest extends TestCase
{
    /** @test */
    public function it_can_create_event_count()
    {
        $experiment = Experiment::create([
            'name' => 'count_test',
            'variants' => ['control' => 100],
            'is_active' => true,
        ]);

        $eventCount = AbEventCount::create([
            'experiment_id' => $experiment->id,
            'user_id' => 'test_user',
            'event_name' => 'button_click',
            'variant' => 'control',
            'count' => 1,
            'properties' => ['button_type' => 'cta', 'page' => 'homepage'],
        ]);

        $this->assertInstanceOf(AbEventCount::class, $eventCount);
        $this->assertEquals($experiment->id, $eventCount->experiment_id);
        $this->assertEquals('test_user', $eventCount->user_id);
        $this->assertEquals('button_click', $eventCount->event_name);
        $this->assertEquals('control', $eventCount->variant);
        $this->assertEquals(1, $eventCount->count);
        $this->assertEquals(['button_type' => 'cta', 'page' => 'homepage'], $eventCount->properties);
    }

    /** @test */
    public function it_casts_properties_to_array()
    {
        $experiment = Experiment::create([
            'name' => 'cast_test',
            'variants' => ['control' => 100],
            'is_active' => true,
        ]);

        $eventCount = AbEventCount::create([
            'experiment_id' => $experiment->id,
            'user_id' => 'cast_user',
            'event_name' => 'conversion',
            'variant' => 'control',
            'count' => 1,
            'properties' => ['value' => 100, 'currency' => 'USD'],
        ]);

        $this->assertIsArray($eventCount->properties);
        $this->assertEquals(100, $eventCount->properties['value']);
        $this->assertEquals('USD', $eventCount->properties['currency']);
    }

    /** @test */
    public function it_casts_count_to_integer()
    {
        $experiment = Experiment::create([
            'name' => 'int_cast_test',
            'variants' => ['control' => 100],
            'is_active' => true,
        ]);

        $eventCount = AbEventCount::create([
            'experiment_id' => $experiment->id,
            'user_id' => 'int_user',
            'event_name' => 'click',
            'variant' => 'control',
            'count' => '5', // String should be cast to integer
        ]);

        $this->assertIsInt($eventCount->count);
        $this->assertEquals(5, $eventCount->count);
    }

    /** @test */
    public function it_belongs_to_experiment()
    {
        $experiment = Experiment::create([
            'name' => 'relationship_test',
            'description' => 'Test experiment for relationships',
            'variants' => ['control' => 100],
            'is_active' => true,
        ]);

        $eventCount = AbEventCount::create([
            'experiment_id' => $experiment->id,
            'user_id' => 'relationship_user',
            'event_name' => 'signup',
            'variant' => 'control',
            'count' => 1,
        ]);

        $relatedExperiment = $eventCount->experiment;

        $this->assertInstanceOf(Experiment::class, $relatedExperiment);
        $this->assertEquals($experiment->id, $relatedExperiment->id);
        $this->assertEquals('relationship_test', $relatedExperiment->name);
        $this->assertEquals('Test experiment for relationships', $relatedExperiment->description);
    }

    /** @test */
    public function it_can_increment_count_for_new_event()
    {
        $experiment = Experiment::create([
            'name' => 'increment_new_test',
            'variants' => ['control' => 100],
            'is_active' => true,
        ]);

        $result = AbEventCount::incrementCount(
            $experiment->id,
            'increment_user',
            'click',
            'control',
            ['page' => 'homepage']
        );

        $this->assertTrue($result); // incrementCount returns the result of increment()

        $eventCount = AbEventCount::where([
            'experiment_id' => $experiment->id,
            'user_id' => 'increment_user',
            'event_name' => 'click',
            'variant' => 'control',
        ])->first();

        $this->assertNotNull($eventCount);
        $this->assertEquals(1, $eventCount->count);
        $this->assertEquals(['page' => 'homepage'], $eventCount->properties);
    }

    /** @test */
    public function it_can_increment_count_for_existing_event()
    {
        $experiment = Experiment::create([
            'name' => 'increment_existing_test',
            'variants' => ['control' => 100],
            'is_active' => true,
        ]);

        // Create initial event count
        $eventCount = AbEventCount::create([
            'experiment_id' => $experiment->id,
            'user_id' => 'existing_user',
            'event_name' => 'click',
            'variant' => 'control',
            'count' => 3,
            'properties' => ['initial' => 'data'],
        ]);

        // Increment the count
        AbEventCount::incrementCount(
            $experiment->id,
            'existing_user',
            'click',
            'control',
            ['updated' => 'data']
        );

        $eventCount->refresh();

        $this->assertEquals(4, $eventCount->count); // Should be incremented from 3 to 4
        $this->assertEquals(['updated' => 'data'], $eventCount->properties); // Properties should be updated
    }

    /** @test */
    public function it_handles_multiple_increments()
    {
        $experiment = Experiment::create([
            'name' => 'multiple_increment_test',
            'variants' => ['control' => 100],
            'is_active' => true,
        ]);

        // Increment multiple times
        AbEventCount::incrementCount($experiment->id, 'multi_user', 'click', 'control');
        AbEventCount::incrementCount($experiment->id, 'multi_user', 'click', 'control');
        AbEventCount::incrementCount($experiment->id, 'multi_user', 'click', 'control');

        $eventCount = AbEventCount::where([
            'experiment_id' => $experiment->id,
            'user_id' => 'multi_user',
            'event_name' => 'click',
            'variant' => 'control',
        ])->first();

        $this->assertEquals(3, $eventCount->count);
    }

    /** @test */
    public function it_handles_different_events_for_same_user()
    {
        $experiment = Experiment::create([
            'name' => 'different_events_test',
            'variants' => ['control' => 100],
            'is_active' => true,
        ]);

        // Create counts for different events for the same user
        AbEventCount::incrementCount($experiment->id, 'same_user', 'click', 'control');
        AbEventCount::incrementCount($experiment->id, 'same_user', 'conversion', 'control');
        AbEventCount::incrementCount($experiment->id, 'same_user', 'click', 'control'); // Increment click again

        $clickCount = AbEventCount::where([
            'experiment_id' => $experiment->id,
            'user_id' => 'same_user',
            'event_name' => 'click',
            'variant' => 'control',
        ])->first();

        $conversionCount = AbEventCount::where([
            'experiment_id' => $experiment->id,
            'user_id' => 'same_user',
            'event_name' => 'conversion',
            'variant' => 'control',
        ])->first();

        $this->assertEquals(2, $clickCount->count);
        $this->assertEquals(1, $conversionCount->count);
    }

    /** @test */
    public function it_handles_different_variants_for_same_user()
    {
        $experiment = Experiment::create([
            'name' => 'different_variants_test',
            'variants' => ['control' => 50, 'variant_a' => 50],
            'is_active' => true,
        ]);

        // This scenario shouldn't normally happen (same user in different variants)
        // but we test that the model can handle it
        AbEventCount::incrementCount($experiment->id, 'variant_user', 'click', 'control');
        AbEventCount::incrementCount($experiment->id, 'variant_user', 'click', 'variant_a');

        $controlCount = AbEventCount::where([
            'experiment_id' => $experiment->id,
            'user_id' => 'variant_user',
            'event_name' => 'click',
            'variant' => 'control',
        ])->first();

        $variantACount = AbEventCount::where([
            'experiment_id' => $experiment->id,
            'user_id' => 'variant_user',
            'event_name' => 'click',
            'variant' => 'variant_a',
        ])->first();

        $this->assertEquals(1, $controlCount->count);
        $this->assertEquals(1, $variantACount->count);
    }

    /** @test */
    public function it_can_be_queried_for_aggregated_counts()
    {
        $experiment = Experiment::create([
            'name' => 'aggregate_test',
            'variants' => ['control' => 50, 'variant_a' => 50],
            'is_active' => true,
        ]);

        // Create event counts for multiple users
        AbEventCount::incrementCount($experiment->id, 'user1', 'click', 'control');
        AbEventCount::incrementCount($experiment->id, 'user1', 'click', 'control'); // 2 clicks for user1
        AbEventCount::incrementCount($experiment->id, 'user2', 'click', 'control'); // 1 click for user2
        AbEventCount::incrementCount($experiment->id, 'user3', 'click', 'variant_a'); // 1 click for user3

        // Get total clicks per variant
        $variantCounts = AbEventCount::where('experiment_id', $experiment->id)
            ->where('event_name', 'click')
            ->selectRaw('variant, SUM(count) as total_clicks')
            ->groupBy('variant')
            ->pluck('total_clicks', 'variant')
            ->toArray();

        $this->assertEquals(3, $variantCounts['control']); // 2 + 1
        $this->assertEquals(1, $variantCounts['variant_a']);
    }

    /** @test */
    public function it_handles_empty_properties()
    {
        $experiment = Experiment::create([
            'name' => 'empty_props_test',
            'variants' => ['control' => 100],
            'is_active' => true,
        ]);

        AbEventCount::incrementCount($experiment->id, 'empty_user', 'click', 'control', []);

        $eventCount = AbEventCount::where([
            'experiment_id' => $experiment->id,
            'user_id' => 'empty_user',
            'event_name' => 'click',
            'variant' => 'control',
        ])->first();

        $this->assertIsArray($eventCount->properties);
        $this->assertEmpty($eventCount->properties);
        $this->assertEquals(1, $eventCount->count);
    }

    /** @test */
    public function it_handles_null_properties()
    {
        $experiment = Experiment::create([
            'name' => 'null_props_test',
            'variants' => ['control' => 100],
            'is_active' => true,
        ]);

        $eventCount = AbEventCount::create([
            'experiment_id' => $experiment->id,
            'user_id' => 'null_user',
            'event_name' => 'click',
            'variant' => 'control',
            'count' => 1,
            'properties' => null,
        ]);

        $this->assertNull($eventCount->properties);
        $this->assertEquals(1, $eventCount->count);
    }
}