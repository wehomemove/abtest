<?php

namespace Homemove\AbTesting\Jobs;

use Homemove\AbTesting\Events\VariantAccepted;
use Homemove\AbTesting\Models\AcceptanceReport;
use Homemove\AbTesting\Services\CleanupScanService;
use Homemove\AbTesting\Services\ExperimentStatsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GenerateCleanupReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $reportId)
    {
        if ($queue = config('ab-testing.accept.queue')) {
            $this->onQueue($queue);
        }
    }

    public function handle(): void
    {
        $report = AcceptanceReport::with('experiment')->find($this->reportId);
        if (!$report || !$report->experiment) {
            return;
        }

        $experiment = $report->experiment;

        try {
            $scan = config('ab-testing.accept.scan.enabled', true)
                ? app(CleanupScanService::class)->scan($experiment)
                : ['references' => [], 'scanned_files' => 0, 'duration_ms' => 0, 'truncated' => false];

            $payload = $this->buildPayload($experiment, $scan);
            $report->update(['status' => 'completed', 'payload' => $payload]);
        } catch (\Throwable $e) {
            Log::error('AB accept: cleanup scan failed', ['experiment' => $experiment->name, 'error' => $e->getMessage()]);
            $payload = $this->buildPayload($experiment, ['references' => [], 'scanned_files' => 0, 'duration_ms' => 0, 'truncated' => false]);
            $payload['scan']['failed'] = true;
            $report->update(['status' => 'failed', 'payload' => $payload]);
        }

        VariantAccepted::dispatch($experiment, $payload);

        if ($webhook = config('ab-testing.accept.webhook_url')) {
            try {
                Http::timeout(10)->post($webhook, $payload);
            } catch (\Throwable $e) {
                Log::warning('AB accept: webhook delivery failed', ['experiment' => $experiment->name, 'error' => $e->getMessage()]);
            }
        }
    }

    protected function buildPayload($experiment, array $scan): array
    {
        $statsService = app(ExperimentStatsService::class);
        $variantStats = $statsService->variantStats($experiment);
        $significance = $statsService->headlineSignificance($statsService->significanceByVariant($variantStats));

        $finalStats = [];
        foreach ($variantStats as $variant => $data) {
            $finalStats[$variant] = [
                'participants' => $data['assigned'],
                'conversions' => $data['converted'],
                'rate' => $data['conversion_rate'],
            ];
        }

        return [
            'version' => 1,
            'app' => config('app.name'),
            'repo' => config('ab-testing.accept.repo'),
            'dashboard_url' => route('ab-testing.dashboard.show', $experiment),
            'experiment' => ['id' => $experiment->id, 'name' => $experiment->name],
            'accepted_variant' => $experiment->accepted_variant,
            'losing_variants' => array_values(array_filter(
                array_keys($experiment->variants ?? []),
                fn ($v) => $v !== $experiment->accepted_variant
            )),
            'accepted_at' => optional($experiment->accepted_at)->toIso8601String(),
            'primary_metric' => $experiment->conversionEvent(),
            'final_stats' => $finalStats,
            'significance' => $significance,
            'references' => $scan['references'],
            'scan' => [
                'scanned_files' => $scan['scanned_files'],
                'duration_ms' => $scan['duration_ms'],
                'truncated' => $scan['truncated'],
            ],
        ];
    }
}
