<?php

namespace Homemove\AbTesting\Tests\Unit\Models;

use Homemove\AbTesting\Models\Event;
use Homemove\AbTesting\Models\Experiment;
use Homemove\AbTesting\Models\UserAssignment;
use Homemove\AbTesting\Tests\TestCase;
use Illuminate\Support\Facades\DB;

class EventTest extends TestCase
{
    /** @test */
    public function it_can_create_event()
    {
        $experiment = Experiment::create([
            'name' => 'event_test',
            'variants' => ['control' => 100],
            'is_active' => true,
        ]);

        $event = Event::create([
            'experiment_id' => $experiment->id,
            'user_id' => 'test_user',
            'variant' => 'control',
            'event_name' => 'button_click',
            'properties' => ['button_type' => 'cta', 'page' => 'homepage'],
        ]);

        $this->assertInstanceOf(Event::class, $event);
        $this->assertEquals($experiment->id, $event->experiment_id);
        $this->assertEquals('test_user', $event->user_id);
        $this->assertEquals('control', $event->variant);
        $this->assertEquals('button_click', $event->event_name);
        $this->assertEquals(['button_type' => 'cta', 'page' => 'homepage'], $event->properties);
    }

    /** @test */
    public function it_casts_properties_to_array()
    {
        $experiment = Experiment::create([
            'name' => 'cast_test',
            'variants' => ['control' => 100],
            'is_active' => true,
        ]);

        $event = Event::create([
            'experiment_id' => $experiment->id,
            'user_id' => 'test_user',
            'variant' => 'control',
            'event_name' => 'conversion',
            'properties' => ['value' => 100, 'currency' => 'USD'],
        ]);

        $this->assertIsArray($event->properties);
        $this->assertEquals(100, $event->properties['value']);
        $this->assertEquals('USD', $event->properties['currency']);
    }

    /** @test */
    public function it_casts_event_time_to_datetime()
    {
        $experiment = Experiment::create([
            'name' => 'datetime_test',
            'variants' => ['control' => 100],
            'is_active' => true,
        ]);

        $eventTime = '2024-01-15 10:30:00';
        $event = Event::create([
            'experiment_id' => $experiment->id,
            'user_id' => 'test_user',
            'variant' => 'control',
            'event_name' => 'page_view',
            'event_time' => $eventTime,
        ]);

        $this->assertInstanceOf(\DateTime::class, $event->event_time);
        $this->assertEquals('2024-01-15 10:30:00', $event->event_time->format('Y-m-d H:i:s'));
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

        $event = Event::create([
            'experiment_id' => $experiment->id,
            'user_id' => 'test_user',
            'variant' => 'control',
            'event_name' => 'signup',
        ]);

        $relatedExperiment = $event->experiment;

        $this->assertInstanceOf(Experiment::class, $relatedExperiment);
        $this->assertEquals($experiment->id, $relatedExperiment->id);
        $this->assertEquals('relationship_test', $relatedExperiment->name);
        $this->assertEquals('Test experiment for relationships', $relatedExperiment->description);
    }

    /** @test */
    public function it_belongs_to_user_assignment()
    {
        $experiment = Experiment::create([
            'name' => 'assignment_test',
            'variants' => ['control' => 100],
            'is_active' => true,
        ]);

        // Create user assignment
        DB::table('ab_user_assignments')->insert([
            'experiment_id' => $experiment->id,
            'user_id' => 'assigned_user',
            'variant' => 'control',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $event = Event::create([
            'experiment_id' => $experiment->id,
            'user_id' => 'assigned_user',
            'variant' => 'control',
            'event_name' => 'interaction',
        ]);

        $assignment = $event->assignment;

        $this->assertNotNull($assignment);
        $this->assertEquals($experiment->id, $assignment->experiment_id);
        $this->assertEquals('assigned_user', $assignment->user_id);
        $this->assertEquals('control', $assignment->variant);
    }

    /** @test */
    public function it_handles_null_properties()
    {
        $experiment = Experiment::create([
            'name' => 'null_props_test',
            'variants' => ['control' => 100],
            'is_active' => true,
        ]);

        $event = Event::create([
            'experiment_id' => $experiment->id,
            'user_id' => 'test_user',
            'variant' => 'control',
            'event_name' => 'simple_event',
            'properties' => null,
        ]);

        $this->assertNull($event->properties);
        $this->assertEquals('simple_event', $event->event_name);
    }

    /** @test */
    public function it_handles_empty_properties_array()
    {
        $experiment = Experiment::create([
            'name' => 'empty_props_test',
            'variants' => ['control' => 100],
            'is_active' => true,
        ]);

        $event = Event::create([
            'experiment_id' => $experiment->id,
            'user_id' => 'test_user',
            'variant' => 'control',
            'event_name' => 'empty_event',
            'properties' => [],
        ]);

        $this->assertIsArray($event->properties);
        $this->assertEmpty($event->properties);
    }

    /** @test */
    public function it_can_be_queried_by_experiment()
    {
        $experiment1 = Experiment::create([
            'name' => 'query_test_1',
            'variants' => ['control' => 100],
            'is_active' => true,
        ]);

        $experiment2 = Experiment::create([
            'name' => 'query_test_2',
            'variants' => ['control' => 100],
            'is_active' => true,
        ]);

        Event::create([
            'experiment_id' => $experiment1->id,
            'user_id' => 'user1',
            'variant' => 'control',
            'event_name' => 'click',
        ]);

        Event::create([
            'experiment_id' => $experiment2->id,
            'user_id' => 'user2',
            'variant' => 'control',
            'event_name' => 'conversion',
        ]);

        $exp1Events = Event::where('experiment_id', $experiment1->id)->get();
        $exp2Events = Event::where('experiment_id', $experiment2->id)->get();

        $this->assertCount(1, $exp1Events);
        $this->assertCount(1, $exp2Events);
        $this->assertEquals('click', $exp1Events->first()->event_name);
        $this->assertEquals('conversion', $exp2Events->first()->event_name);
    }

    /** @test */
    public function it_can_be_queried_by_user()
    {
        $experiment = Experiment::create([
            'name' => 'user_query_test',
            'variants' => ['control' => 100],
            'is_active' => true,
        ]);

        Event::create([
            'experiment_id' => $experiment->id,
            'user_id' => 'user1',
            'variant' => 'control',
            'event_name' => 'click',
        ]);

        Event::create([
            'experiment_id' => $experiment->id,
            'user_id' => 'user1',
            'variant' => 'control',
            'event_name' => 'conversion',
        ]);

        Event::create([
            'experiment_id' => $experiment->id,
            'user_id' => 'user2',
            'variant' => 'control',
            'event_name' => 'click',
        ]);

        $user1Events = Event::where('user_id', 'user1')->get();
        $user2Events = Event::where('user_id', 'user2')->get();

        $this->assertCount(2, $user1Events);
        $this->assertCount(1, $user2Events);
    }

    /** @test */
    public function it_can_be_queried_by_variant()
    {
        $experiment = Experiment::create([
            'name' => 'variant_query_test',
            'variants' => ['control' => 50, 'variant_a' => 50],
            'is_active' => true,
        ]);

        Event::create([
            'experiment_id' => $experiment->id,
            'user_id' => 'user1',
            'variant' => 'control',
            'event_name' => 'click',
        ]);

        Event::create([
            'experiment_id' => $experiment->id,
            'user_id' => 'user2',
            'variant' => 'variant_a',
            'event_name' => 'click',
        ]);

        $controlEvents = Event::where('variant', 'control')->get();
        $variantAEvents = Event::where('variant', 'variant_a')->get();

        $this->assertCount(1, $controlEvents);
        $this->assertCount(1, $variantAEvents);
        $this->assertEquals('user1', $controlEvents->first()->user_id);
        $this->assertEquals('user2', $variantAEvents->first()->user_id);
    }

    /** @test */
    public function it_can_be_queried_by_event_name()
    {
        $experiment = Experiment::create([
            'name' => 'event_name_query_test',
            'variants' => ['control' => 100],
            'is_active' => true,
        ]);

        Event::create([
            'experiment_id' => $experiment->id,
            'user_id' => 'user1',
            'variant' => 'control',
            'event_name' => 'click',
        ]);

        Event::create([
            'experiment_id' => $experiment->id,
            'user_id' => 'user1',
            'variant' => 'control',
            'event_name' => 'conversion',
        ]);

        $clickEvents = Event::where('event_name', 'click')->get();
        $conversionEvents = Event::where('event_name', 'conversion')->get();

        $this->assertCount(1, $clickEvents);
        $this->assertCount(1, $conversionEvents);
    }

    /** @test */
    public function it_stores_complex_properties()
    {
        $experiment = Experiment::create([
            'name' => 'complex_props_test',
            'variants' => ['control' => 100],
            'is_active' => true,
        ]);

        $complexProperties = [
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'referrer' => 'https://google.com',
            'viewport' => ['width' => 1920, 'height' => 1080],
            'custom_data' => [
                'experiment_group' => 'new_users',
                'cohort' => 'january_2024',
                'metadata' => ['source' => 'organic', 'campaign' => null]
            ]
        ];

        $event = Event::create([
            'experiment_id' => $experiment->id,
            'user_id' => 'complex_user',
            'variant' => 'control',
            'event_name' => 'page_view',
            'properties' => $complexProperties,
        ]);

        $this->assertEquals($complexProperties, $event->properties);
        $this->assertEquals(1920, $event->properties['viewport']['width']);
        $this->assertEquals('january_2024', $event->properties['custom_data']['cohort']);
        $this->assertNull($event->properties['custom_data']['metadata']['campaign']);
    }
}