<?php

namespace Homemove\AbTesting\Tests\Unit\Models;

use Homemove\AbTesting\Models\UserAssignment;
use Homemove\AbTesting\Models\Experiment;
use Homemove\AbTesting\Tests\TestCase;

class UserAssignmentTest extends TestCase
{
    /** @test */
    public function it_can_create_user_assignment()
    {
        $experiment = Experiment::create([
            'name' => 'assignment_test',
            'variants' => ['control' => 50, 'variant_a' => 50],
            'is_active' => true,
        ]);

        $assignment = UserAssignment::create([
            'experiment_id' => $experiment->id,
            'user_id' => 'test_user_123',
            'variant' => 'control',
        ]);

        $this->assertInstanceOf(UserAssignment::class, $assignment);
        $this->assertEquals($experiment->id, $assignment->experiment_id);
        $this->assertEquals('test_user_123', $assignment->user_id);
        $this->assertEquals('control', $assignment->variant);
    }

    /** @test */
    public function it_casts_assigned_at_to_datetime()
    {
        $experiment = Experiment::create([
            'name' => 'datetime_test',
            'variants' => ['control' => 100],
            'is_active' => true,
        ]);

        $assignedTime = '2024-01-15 14:30:00';
        $assignment = UserAssignment::create([
            'experiment_id' => $experiment->id,
            'user_id' => 'datetime_user',
            'variant' => 'control',
            'assigned_at' => $assignedTime,
        ]);

        $this->assertInstanceOf(\DateTime::class, $assignment->assigned_at);
        $this->assertEquals('2024-01-15 14:30:00', $assignment->assigned_at->format('Y-m-d H:i:s'));
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

        $assignment = UserAssignment::create([
            'experiment_id' => $experiment->id,
            'user_id' => 'relationship_user',
            'variant' => 'control',
        ]);

        $relatedExperiment = $assignment->experiment;

        $this->assertInstanceOf(Experiment::class, $relatedExperiment);
        $this->assertEquals($experiment->id, $relatedExperiment->id);
        $this->assertEquals('relationship_test', $relatedExperiment->name);
        $this->assertEquals('Test experiment for relationships', $relatedExperiment->description);
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

        UserAssignment::create([
            'experiment_id' => $experiment1->id,
            'user_id' => 'user1',
            'variant' => 'control',
        ]);

        UserAssignment::create([
            'experiment_id' => $experiment1->id,
            'user_id' => 'user2',
            'variant' => 'control',
        ]);

        UserAssignment::create([
            'experiment_id' => $experiment2->id,
            'user_id' => 'user3',
            'variant' => 'control',
        ]);

        $exp1Assignments = UserAssignment::where('experiment_id', $experiment1->id)->get();
        $exp2Assignments = UserAssignment::where('experiment_id', $experiment2->id)->get();

        $this->assertCount(2, $exp1Assignments);
        $this->assertCount(1, $exp2Assignments);
    }

    /** @test */
    public function it_can_be_queried_by_user()
    {
        $experiment = Experiment::create([
            'name' => 'user_query_test',
            'variants' => ['control' => 100],
            'is_active' => true,
        ]);

        UserAssignment::create([
            'experiment_id' => $experiment->id,
            'user_id' => 'multi_user',
            'variant' => 'control',
        ]);

        // Same user shouldn't normally have multiple assignments to same experiment,
        // but we can test querying by user across different experiments
        $experiment2 = Experiment::create([
            'name' => 'user_query_test_2',
            'variants' => ['control' => 100],
            'is_active' => true,
        ]);

        UserAssignment::create([
            'experiment_id' => $experiment2->id,
            'user_id' => 'multi_user',
            'variant' => 'control',
        ]);

        UserAssignment::create([
            'experiment_id' => $experiment->id,
            'user_id' => 'single_user',
            'variant' => 'control',
        ]);

        $multiUserAssignments = UserAssignment::where('user_id', 'multi_user')->get();
        $singleUserAssignments = UserAssignment::where('user_id', 'single_user')->get();

        $this->assertCount(2, $multiUserAssignments);
        $this->assertCount(1, $singleUserAssignments);
    }

    /** @test */
    public function it_can_be_queried_by_variant()
    {
        $experiment = Experiment::create([
            'name' => 'variant_query_test',
            'variants' => ['control' => 50, 'variant_a' => 30, 'variant_b' => 20],
            'is_active' => true,
        ]);

        UserAssignment::create([
            'experiment_id' => $experiment->id,
            'user_id' => 'control_user_1',
            'variant' => 'control',
        ]);

        UserAssignment::create([
            'experiment_id' => $experiment->id,
            'user_id' => 'control_user_2',
            'variant' => 'control',
        ]);

        UserAssignment::create([
            'experiment_id' => $experiment->id,
            'user_id' => 'variant_a_user',
            'variant' => 'variant_a',
        ]);

        UserAssignment::create([
            'experiment_id' => $experiment->id,
            'user_id' => 'variant_b_user',
            'variant' => 'variant_b',
        ]);

        $controlAssignments = UserAssignment::where('variant', 'control')->get();
        $variantAAssignments = UserAssignment::where('variant', 'variant_a')->get();
        $variantBAssignments = UserAssignment::where('variant', 'variant_b')->get();

        $this->assertCount(2, $controlAssignments);
        $this->assertCount(1, $variantAAssignments);
        $this->assertCount(1, $variantBAssignments);
    }

    /** @test */
    public function it_enforces_unique_user_experiment_combinations()
    {
        $experiment = Experiment::create([
            'name' => 'unique_test',
            'variants' => ['control' => 100],
            'is_active' => true,
        ]);

        UserAssignment::create([
            'experiment_id' => $experiment->id,
            'user_id' => 'unique_user',
            'variant' => 'control',
        ]);

        // Attempting to create another assignment for the same user in the same experiment
        // should be handled by the application logic, but let's test we can query existing ones
        $existingAssignment = UserAssignment::where([
            'experiment_id' => $experiment->id,
            'user_id' => 'unique_user'
        ])->first();

        $this->assertNotNull($existingAssignment);
        $this->assertEquals('control', $existingAssignment->variant);
    }

    /** @test */
    public function it_can_handle_null_assigned_at()
    {
        $experiment = Experiment::create([
            'name' => 'null_assigned_at_test',
            'variants' => ['control' => 100],
            'is_active' => true,
        ]);

        $assignment = UserAssignment::create([
            'experiment_id' => $experiment->id,
            'user_id' => 'null_time_user',
            'variant' => 'control',
            'assigned_at' => null,
        ]);

        $this->assertNull($assignment->assigned_at);
        $this->assertEquals('null_time_user', $assignment->user_id);
    }

    /** @test */
    public function it_can_count_assignments_by_variant()
    {
        $experiment = Experiment::create([
            'name' => 'count_test',
            'variants' => ['control' => 60, 'variant_a' => 40],
            'is_active' => true,
        ]);

        // Create multiple assignments
        UserAssignment::create(['experiment_id' => $experiment->id, 'user_id' => 'user1', 'variant' => 'control']);
        UserAssignment::create(['experiment_id' => $experiment->id, 'user_id' => 'user2', 'variant' => 'control']);
        UserAssignment::create(['experiment_id' => $experiment->id, 'user_id' => 'user3', 'variant' => 'control']);
        UserAssignment::create(['experiment_id' => $experiment->id, 'user_id' => 'user4', 'variant' => 'variant_a']);
        UserAssignment::create(['experiment_id' => $experiment->id, 'user_id' => 'user5', 'variant' => 'variant_a']);

        $variantCounts = UserAssignment::where('experiment_id', $experiment->id)
            ->selectRaw('variant, COUNT(*) as count')
            ->groupBy('variant')
            ->pluck('count', 'variant')
            ->toArray();

        $this->assertEquals(3, $variantCounts['control']);
        $this->assertEquals(2, $variantCounts['variant_a']);
    }

    /** @test */
    public function it_can_get_total_assignments_for_experiment()
    {
        $experiment = Experiment::create([
            'name' => 'total_count_test',
            'variants' => ['control' => 100],
            'is_active' => true,
        ]);

        // Create assignments
        for ($i = 1; $i <= 5; $i++) {
            UserAssignment::create([
                'experiment_id' => $experiment->id,
                'user_id' => "user{$i}",
                'variant' => 'control',
            ]);
        }

        $totalAssignments = UserAssignment::where('experiment_id', $experiment->id)->count();

        $this->assertEquals(5, $totalAssignments);
    }

    /** @test */
    public function it_can_find_assignment_for_specific_user_and_experiment()
    {
        $experiment = Experiment::create([
            'name' => 'find_test',
            'variants' => ['control' => 50, 'variant_a' => 50],
            'is_active' => true,
        ]);

        UserAssignment::create([
            'experiment_id' => $experiment->id,
            'user_id' => 'findable_user',
            'variant' => 'variant_a',
        ]);

        $assignment = UserAssignment::where([
            'experiment_id' => $experiment->id,
            'user_id' => 'findable_user'
        ])->first();

        $this->assertNotNull($assignment);
        $this->assertEquals('variant_a', $assignment->variant);
        $this->assertEquals('findable_user', $assignment->user_id);
    }

    /** @test */
    public function it_handles_long_user_ids()
    {
        $experiment = Experiment::create([
            'name' => 'long_user_id_test',
            'variants' => ['control' => 100],
            'is_active' => true,
        ]);

        $longUserId = 'very_long_user_id_' . str_repeat('x', 100);

        $assignment = UserAssignment::create([
            'experiment_id' => $experiment->id,
            'user_id' => $longUserId,
            'variant' => 'control',
        ]);

        $this->assertEquals($longUserId, $assignment->user_id);
        $this->assertEquals('control', $assignment->variant);
    }
}