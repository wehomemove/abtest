<?php

namespace Homemove\AbTesting\Services;

use Homemove\AbTesting\Contracts\FunnelStepDataProvider;
use Homemove\AbTesting\Models\Event;
use Homemove\AbTesting\Models\Experiment;
use Homemove\AbTesting\Models\UserAssignment;
use Homemove\AbTesting\Support\Statistics;

/**
 * The shared stats computations behind both the dashboard page and the polled
 * API. Before this class the two endpoints each ran their own queries and
 * their own significance copy, and the polled payload silently lacked whole
 * sections (event counts) — payload drift the live refresh could never fix.
 */
class ExperimentStatsService
{
    /**
     * A valid conversion-event selection for this experiment: the universal
     * default, the persisted primary metric, or any event actually tracked.
     * Anything else falls back to the experiment's own conversion event.
     */
    public function resolveConversionEvent(Experiment $experiment, $raw): string
    {
        $default = $experiment->conversionEvent();

        if (!is_string($raw) || $raw === '' || $raw === $default) {
            return $default;
        }

        if ($raw === 'conversion') {
            return 'conversion';
        }

        return Event::where('experiment_id', $experiment->id)->where('event_name', $raw)->exists()
            ? $raw
            : $default;
    }

    /**
     * @return array<string, array{weight: mixed, assigned: int, converted: int, conversion_rate: float|int}>
     */
    public function variantStats(Experiment $experiment, ?string $deviceType = null, ?string $conversionEvent = null): array
    {
        $assignments = UserAssignment::where('experiment_id', $experiment->id)
            ->when($deviceType !== null, fn ($q) => $q->where('device_type', $deviceType))
            ->selectRaw('variant, COUNT(*) as count')
            ->groupBy('variant')
            ->pluck('count', 'variant')
            ->toArray();

        $conversions = Event::where('experiment_id', $experiment->id)
            ->where('event_name', $conversionEvent ?? $experiment->conversionEvent())
            ->when($deviceType !== null, fn ($q) => $q->where('device_type', $deviceType))
            ->selectRaw('variant, COUNT(DISTINCT user_id) as count')
            ->groupBy('variant')
            ->pluck('count', 'variant')
            ->toArray();

        $stats = [];
        foreach ($experiment->variants as $variant => $weight) {
            $assigned = (int) ($assignments[$variant] ?? 0);
            $converted = (int) ($conversions[$variant] ?? 0);
            $stats[$variant] = [
                'weight' => $weight,
                'assigned' => $assigned,
                'converted' => $converted,
                'conversion_rate' => $assigned > 0 ? round(($converted / $assigned) * 100, 2) : 0,
            ];
        }

        return $stats;
    }

    /** @return array<string, array<string, int>> event_name => variant => distinct users */
    public function eventCountsByName(Experiment $experiment, ?string $deviceType = null): array
    {
        return Event::where('experiment_id', $experiment->id)
            ->when($deviceType !== null, fn ($q) => $q->where('device_type', $deviceType))
            ->selectRaw('event_name, variant, COUNT(DISTINCT user_id) as count')
            ->groupBy('event_name', 'variant')
            ->get()
            ->groupBy('event_name')
            ->map(fn ($rows) => $rows->pluck('count', 'variant')->toArray())
            ->toArray();
    }

    /**
     * Every non-control arm tested against control.
     *
     * @param  array<string, array{assigned: int, converted: int}>  $variantStats
     * @return array<string, array>
     */
    public function significanceByVariant(array $variantStats): array
    {
        $control = $variantStats['control'] ?? null;
        if (!$control) {
            return [];
        }

        $results = [];
        foreach ($variantStats as $variant => $data) {
            if ($variant === 'control') {
                continue;
            }
            $results[$variant] = Statistics::twoProportionZTest(
                (int) $control['assigned'],
                (int) $control['converted'],
                (int) $data['assigned'],
                (int) $data['converted'],
            );
        }

        return $results;
    }

    /**
     * The best-confidence arm's result, named — what the headline card shows.
     *
     * @param  array<string, array>  $significanceByVariant
     */
    public function headlineSignificance(array $significanceByVariant): array
    {
        $headlineVariant = null;
        foreach ($significanceByVariant as $variant => $result) {
            if ($headlineVariant === null
                || ($result['percentage'] ?? 0) > ($significanceByVariant[$headlineVariant]['percentage'] ?? 0)) {
                $headlineVariant = $variant;
            }
        }

        return $headlineVariant !== null
            ? array_merge($significanceByVariant[$headlineVariant], ['variant' => $headlineVariant])
            : [
                'percentage' => 0,
                'status' => 'insufficient_data',
                'message' => 'Need a control and at least one variant',
                'confidence_level' => 'low',
            ];
    }

    /**
     * Percentage-point change of the cumulative conversion rate vs where it
     * stood at the start of today. Null when yesterday had no participants.
     */
    public function rateChangePp(Experiment $experiment, ?string $deviceType = null, ?string $conversionEvent = null): ?float
    {
        $conversionEvent ??= $experiment->conversionEvent();
        $todayStart = now()->startOfDay();

        $assignedBefore = UserAssignment::where('experiment_id', $experiment->id)
            ->where('created_at', '<', $todayStart)
            ->when($deviceType !== null, fn ($q) => $q->where('device_type', $deviceType))
            ->count();

        if ($assignedBefore === 0) {
            return null;
        }

        $convertedBefore = Event::where('experiment_id', $experiment->id)
            ->where('event_name', $conversionEvent)
            ->where('created_at', '<', $todayStart)
            ->when($deviceType !== null, fn ($q) => $q->where('device_type', $deviceType))
            ->distinct('user_id')
            ->count();

        $assignedNow = UserAssignment::where('experiment_id', $experiment->id)
            ->when($deviceType !== null, fn ($q) => $q->where('device_type', $deviceType))
            ->count();
        $convertedNow = Event::where('experiment_id', $experiment->id)
            ->where('event_name', $conversionEvent)
            ->when($deviceType !== null, fn ($q) => $q->where('device_type', $deviceType))
            ->distinct('user_id')
            ->count();

        $before = ($convertedBefore / $assignedBefore) * 100;
        $now = $assignedNow > 0 ? ($convertedNow / $assignedNow) * 100 : 0.0;

        return round($now - $before, 2);
    }

    /**
     * Step-level funnel from a host-bound provider when one exists and has
     * data; otherwise the package-native reach funnel from ab_events.
     */
    public function funnel(Experiment $experiment, ?string $deviceType = null, ?string $conversionEvent = null): ?array
    {
        // Host-provided step funnels are not re-pinned by the conversion lens;
        // only the package-native reach funnel reorders around it.
        if (app()->bound(FunnelStepDataProvider::class)) {
            $provided = app(FunnelStepDataProvider::class)->funnelFor($experiment->name, $deviceType);
            if ($provided !== null && ($provided['steps'] ?? []) !== []) {
                $variants = array_keys($experiment->variants ?? []);
                $provided['biggest_drop_after'] ??= (new ReachFunnelService)->funnelBiggestDrop($provided['steps'], $variants);

                return $provided;
            }
        }

        return (new ReachFunnelService)->funnelFor($experiment, $deviceType, $conversionEvent);
    }
}
