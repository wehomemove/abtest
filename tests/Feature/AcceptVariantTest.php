<?php

namespace Homemove\AbTesting\Tests\Feature;

use Homemove\AbTesting\Events\VariantAccepted;
use Homemove\AbTesting\Facades\AbTest;
use Homemove\AbTesting\Jobs\GenerateCleanupReport;
use Homemove\AbTesting\Models\AcceptanceReport;
use Homemove\AbTesting\Models\Experiment;
use Homemove\AbTesting\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

class AcceptVariantTest extends TestCase
{
    private function makeExperiment(array $attributes = []): Experiment
    {
        return Experiment::create(array_merge([
            'name' => 'accept_test_' . uniqid(),
            'variants' => ['control' => 50, 'variant_b' => 50],
            'traffic_allocation' => 100,
            'is_active' => true,
        ], $attributes));
    }

    private function seedAssignment(Experiment $experiment, string $user, string $variant): void
    {
        DB::table('ab_user_assignments')->insert([
            'experiment_id' => $experiment->id, 'user_id' => $user, 'variant' => $variant,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function accept(Experiment $experiment, string $variant, ?string $confirm = null)
    {
        return $this->post(route('ab-testing.dashboard.accept', $experiment), [
            'variant' => $variant,
            'confirm_name' => $confirm ?? $variant,
        ]);
    }

    /** @test */
    public function accepting_rolls_out_the_winner_and_snapshots_the_prior_state()
    {
        Queue::fake();
        $experiment = $this->makeExperiment(['status' => 'running']);

        $this->accept($experiment, 'variant_b')
            ->assertRedirect(route('ab-testing.dashboard.show', $experiment));

        $experiment->refresh();
        $this->assertSame(['control' => 0, 'variant_b' => 100], $experiment->variants);
        $this->assertSame('variant_b', $experiment->accepted_variant);
        $this->assertSame('completed', $experiment->status);
        $this->assertTrue($experiment->is_active);
        $this->assertNotNull($experiment->accepted_at);
        $this->assertSame(['control' => 50, 'variant_b' => 50], $experiment->pre_acceptance['variants']);
        $this->assertSame('running', $experiment->pre_acceptance['status']);

        $report = AcceptanceReport::where('experiment_id', $experiment->id)->first();
        $this->assertNotNull($report);
        $this->assertSame('pending', $report->status);
        Queue::assertPushed(GenerateCleanupReport::class, fn ($job) => $job->reportId === $report->id);
    }

    /** @test */
    public function accept_requires_the_typed_variant_name_and_a_known_variant()
    {
        Queue::fake();
        $experiment = $this->makeExperiment();

        $this->from(route('ab-testing.dashboard.show', $experiment))
            ->accept($experiment, 'variant_b', 'wrong_name')
            ->assertSessionHasErrors('confirm_name');

        $this->from(route('ab-testing.dashboard.show', $experiment))
            ->accept($experiment, 'variant_zzz')
            ->assertSessionHasErrors('variant');

        $this->assertNull($experiment->fresh()->accepted_variant);
        Queue::assertNothingPushed();
    }

    /** @test */
    public function accept_cannot_run_twice()
    {
        Queue::fake();
        $experiment = $this->makeExperiment();
        $this->accept($experiment, 'variant_b');

        $this->from(route('ab-testing.dashboard.show', $experiment))
            ->accept($experiment->fresh(), 'control')
            ->assertSessionHasErrors('variant');

        $this->assertSame('variant_b', $experiment->fresh()->accepted_variant);
    }

    /** @test */
    public function previously_assigned_users_receive_the_winner_with_no_new_assignment_rows()
    {
        Queue::fake();
        $experiment = $this->makeExperiment();
        $this->seedAssignment($experiment, 'loser-user', 'control');

        $this->assertSame('control', AbTest::variant($experiment->name, 'loser-user'));

        $this->accept($experiment, 'variant_b');

        $this->assertSame('variant_b', AbTest::variant($experiment->name, 'loser-user'));
        $this->assertSame('variant_b', AbTest::variant($experiment->name, 'brand-new-user'));

        // History untouched: still exactly one row, still recording control.
        $rows = DB::table('ab_user_assignments')->where('experiment_id', $experiment->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame('control', $rows->first()->variant);
    }

    /** @test */
    public function acceptance_bypasses_a_stale_per_user_variant_cache()
    {
        Queue::fake();
        $experiment = $this->makeExperiment();

        // Simulate the 1h per-user cache clearCache() admits it cannot purge.
        Cache::put("ab_test:variant:{$experiment->name}:stale-user:unknown", 'control', 3600);

        $this->accept($experiment, 'variant_b');

        $this->assertSame('variant_b', AbTest::variant($experiment->name, 'stale-user'));
    }

    /** @test */
    public function tracking_after_acceptance_records_under_the_winner()
    {
        Queue::fake();
        $experiment = $this->makeExperiment();
        $this->seedAssignment($experiment, 'loser-user', 'control');
        $this->accept($experiment, 'variant_b');

        AbTest::track($experiment->name, 'loser-user');

        $event = DB::table('ab_events')->where('experiment_id', $experiment->id)->first();
        $this->assertSame('variant_b', $event->variant);
    }

    /** @test */
    public function reopen_restores_the_pre_acceptance_state()
    {
        Queue::fake();
        $experiment = $this->makeExperiment(['status' => 'running']);
        $this->seedAssignment($experiment, 'loser-user', 'control');
        $this->accept($experiment, 'variant_b');

        // Wrong confirm text is rejected.
        $this->from(route('ab-testing.dashboard.show', $experiment))
            ->post(route('ab-testing.dashboard.reopen', $experiment), ['confirm_name' => 'nope'])
            ->assertSessionHasErrors('confirm_name');

        $this->post(route('ab-testing.dashboard.reopen', $experiment), ['confirm_name' => $experiment->name])
            ->assertRedirect(route('ab-testing.dashboard.show', $experiment));

        $experiment->refresh();
        $this->assertSame(['control' => 50, 'variant_b' => 50], $experiment->variants);
        $this->assertSame('running', $experiment->status);
        $this->assertNull($experiment->accepted_variant);
        $this->assertNull($experiment->pre_acceptance);

        // Old assignments are honoured again.
        $this->assertSame('control', AbTest::variant($experiment->name, 'loser-user'));
    }

    /** @test */
    public function report_job_completes_the_report_fires_the_event_and_posts_the_webhook()
    {
        Event::fake([VariantAccepted::class]);
        Http::fake(['bots.example.test/*' => Http::response(['ok' => true])]);
        config([
            'ab-testing.accept.webhook_url' => 'https://bots.example.test/abtest-intake',
            'ab-testing.accept.repo' => 'wehomemove/motus',
            'ab-testing.accept.scan.paths' => [__DIR__ . '/../fixtures/fake-app'],
        ]);

        $experiment = $this->makeExperiment([
            'name' => 'demo_landing_v1',
            'variants' => ['control' => 40, 'variant_b' => 30, 'variant_c' => 30],
        ]);
        $experiment->update([
            'variants' => ['control' => 0, 'variant_b' => 100, 'variant_c' => 0],
            'accepted_variant' => 'variant_b',
            'accepted_at' => now(),
            'status' => 'completed',
        ]);
        $report = AcceptanceReport::create([
            'experiment_id' => $experiment->id,
            'accepted_variant' => 'variant_b',
            'status' => 'pending',
        ]);

        (new GenerateCleanupReport($report->id))->handle();

        $report->refresh();
        $this->assertSame('completed', $report->status);
        $payload = $report->payload;
        $this->assertSame('wehomemove/motus', $payload['repo']);
        $this->assertSame('variant_b', $payload['accepted_variant']);
        $this->assertSame(['control', 'variant_c'], $payload['losing_variants']);
        $this->assertNotEmpty($payload['references']);
        $this->assertFalse($payload['scan']['truncated']);

        Event::assertDispatched(VariantAccepted::class, fn ($e) => $e->experiment->id === $experiment->id
            && $e->report['accepted_variant'] === 'variant_b');
        Http::assertSent(fn ($request) => str_contains($request->url(), 'bots.example.test')
            && $request['experiment']['name'] === 'demo_landing_v1');
    }

    /** @test */
    public function report_job_without_webhook_still_completes_and_fires_the_event()
    {
        Event::fake([VariantAccepted::class]);
        Http::fake();
        config(['ab-testing.accept.scan.paths' => [__DIR__ . '/../fixtures/fake-app']]);

        $experiment = $this->makeExperiment();
        $experiment->update(['accepted_variant' => 'variant_b', 'accepted_at' => now()]);
        $report = AcceptanceReport::create([
            'experiment_id' => $experiment->id, 'accepted_variant' => 'variant_b', 'status' => 'pending',
        ]);

        (new GenerateCleanupReport($report->id))->handle();

        $this->assertSame('completed', $report->fresh()->status);
        Event::assertDispatched(VariantAccepted::class);
        Http::assertNothingSent();
    }
}
