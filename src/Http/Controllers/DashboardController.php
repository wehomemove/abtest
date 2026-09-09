<?php

namespace Homemove\AbTesting\Http\Controllers;

use Homemove\AbTesting\Facades\AbTest;
use Homemove\AbTesting\Models\Event;
use Homemove\AbTesting\Models\Experiment;
use Homemove\AbTesting\Contracts\FunnelStepDataProvider;
use Homemove\AbTesting\Services\ExperimentStatsService;
use Homemove\AbTesting\Support\Statistics;
use Homemove\AbTesting\Models\UserAssignment;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DashboardController extends Controller
{
    public function index()
    {
        $experiments = Experiment::withCount(['assignments', 'events'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('ab-testing::dashboard.index', compact('experiments'));
    }

    public function show(Experiment $experiment, Request $request)
    {
        $deviceType = $this->resolveDeviceTypeFilter($request->query('device_type'));
        $conversionEvent = app(ExperimentStatsService::class)
            ->resolveConversionEvent($experiment, $request->query('event'));
        $eventFilter = $this->resolveEventFilter($request->query('event_filter'), $experiment, $deviceType, $conversionEvent);
        $stats = $this->getExperimentStats($experiment, $deviceType, $eventFilter, $conversionEvent);
        $stats['funnel'] = $this->resolveFunnel($experiment, $deviceType, $conversionEvent);

        return view('ab-testing::dashboard.show', [
            'experiment' => $experiment,
            'stats' => $stats,
            'activeDeviceType' => $deviceType,
            'activeEventFilter' => $eventFilter,
            'activeConversionEvent' => $conversionEvent,
            'latestAcceptanceReport' => $experiment->isAccepted()
                ? \Homemove\AbTesting\Models\AcceptanceReport::where('experiment_id', $experiment->id)->latest('id')->first()
                : null,
        ]);
    }

    protected function resolveFunnel(Experiment $experiment, ?string $deviceType, ?string $conversionEvent = null): ?array
    {
        return app(ExperimentStatsService::class)->funnel($experiment, $deviceType, $conversionEvent);
    }

    /**
     * The funnel step list lives in the (previously unused) custom_events
     * column — ordered event names driving the reach funnel's step order.
     */
    protected function mapFunnelSteps(array $validated): array
    {
        if (array_key_exists('funnel_steps', $validated)) {
            $steps = array_values(array_filter(
                array_map(fn ($step) => trim((string) $step), $validated['funnel_steps'] ?? []),
                fn ($step) => $step !== ''
            ));
            $validated['custom_events'] = $steps === [] ? null : $steps;
            unset($validated['funnel_steps']);
        }

        return $validated;
    }

    protected function resolveEventFilter(?string $eventFilter, Experiment $experiment, ?string $deviceType = null, ?string $conversionEvent = null): ?string
    {
        if (! $eventFilter || $eventFilter === ($conversionEvent ?? $experiment->conversionEvent())) {
            return null;
        }

        $query = Event::where('experiment_id', $experiment->id)
            ->where('event_name', $eventFilter);
        if ($deviceType !== null) {
            $query->where('device_type', $deviceType);
        }

        return $query->exists() ? $eventFilter : null;
    }

    public function create()
    {
        return view('ab-testing::dashboard.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:ab_experiments,name',
            'description' => 'nullable|string',
            'funnel_steps' => 'nullable|array',
            'funnel_steps.*' => 'nullable|string|max:100',
            'variants' => 'required|array|min:2',
            'variants.*' => 'required|integer|min:0|max:100',
            'traffic_allocation' => 'required|integer|min:0|max:100',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'allowed_device_types' => 'nullable|array',
            'allowed_device_types.*' => 'in:mobile,tablet,desktop',
        ]);

        // Ensure variants sum to 100
        if (array_sum($validated['variants']) !== 100) {
            return back()->withErrors(['variants' => 'Variant weights must sum to 100%']);
        }

        $validated['allowed_device_types'] = $this->normaliseAllowedDeviceTypes(
            $validated['allowed_device_types'] ?? null
        );
        $validated = $this->mapFunnelSteps($validated);

        $experiment = Experiment::create($validated);

        // Clear cache
        AbTest::clearCache($experiment->name);

        return redirect()->route('ab-testing.dashboard.show', $experiment)
            ->with('success', 'Experiment created successfully!');
    }

    public function edit(Experiment $experiment)
    {
        return view('ab-testing::dashboard.edit', compact('experiment'));
    }

    public function update(Request $request, Experiment $experiment)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:ab_experiments,name,' . $experiment->id,
            'description' => 'nullable|string',
            'funnel_steps' => 'nullable|array',
            'funnel_steps.*' => 'nullable|string|max:100',
            'variants' => 'required|array|min:2',
            'variants.*' => 'required|integer|min:0|max:100',
            'traffic_allocation' => 'required|integer|min:0|max:100',
            'is_active' => 'boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'allowed_device_types' => 'nullable|array',
            'allowed_device_types.*' => 'in:mobile,tablet,desktop',
        ]);

        if (array_sum($validated['variants']) !== 100) {
            return back()->withErrors(['variants' => 'Variant weights must sum to 100%']);
        }

        $validated['allowed_device_types'] = $this->normaliseAllowedDeviceTypes(
            $validated['allowed_device_types'] ?? null
        );
        $validated = $this->mapFunnelSteps($validated);

        $experiment->update($validated);

        // Clear cache — also invalidates per-device variant cache keys so users previously
        // on excluded devices immediately drop out.
        AbTest::clearCache($experiment->name);

        return redirect()->route('ab-testing.dashboard.show', $experiment)
            ->with('success', 'Experiment updated successfully!');
    }

    public function destroy(Experiment $experiment)
    {
        $experiment->delete();

        // Clear cache
        AbTest::clearCache($experiment->name);

        return redirect()->route('ab-testing.dashboard.index')
            ->with('success', 'Experiment deleted successfully!');
    }

    /**
     * Persist which tracked event the dashboard treats as this experiment's
     * conversion. 'conversion' (the universal default) stores as null.
     */
    public function setPrimaryMetric(Request $request, Experiment $experiment)
    {
        $validated = $request->validate([
            'primary_metric' => 'nullable|string|max:255',
        ]);

        $metric = $validated['primary_metric'] ?? null;
        if ($metric === '' || $metric === 'conversion') {
            $metric = null;
        }

        if ($metric !== null
            && !Event::where('experiment_id', $experiment->id)->where('event_name', $metric)->exists()) {
            return back()->withErrors(['primary_metric' => 'That event has not been tracked for this experiment.']);
        }

        $experiment->update(['primary_metric' => $metric]);

        return redirect()->route('ab-testing.dashboard.show', $experiment)
            ->with('success', $metric === null
                ? 'Primary metric reset to conversion.'
                : "Primary metric saved: {$metric}");
    }

    /**
     * Roll the winning variant out to 100% of traffic — existing assignments
     * included (variant() short-circuits to the accepted arm). Reversible via
     * reopen(); assignment history is never rewritten.
     */
    public function accept(Request $request, Experiment $experiment)
    {
        $validated = $request->validate([
            'variant' => 'required|string',
            'confirm_name' => 'required|string',
        ]);

        if ($experiment->isAccepted()) {
            return back()->withErrors(['variant' => 'A variant has already been accepted for this experiment.']);
        }

        if (!array_key_exists($validated['variant'], $experiment->variants ?? [])) {
            return back()->withErrors(['variant' => 'Unknown variant for this experiment.']);
        }

        if (!hash_equals($validated['variant'], $validated['confirm_name'])) {
            return back()->withErrors(['confirm_name' => 'Type the variant name exactly to confirm.']);
        }

        $report = \Illuminate\Support\Facades\DB::transaction(function () use ($experiment, $validated) {
            $variantNames = array_keys($experiment->variants);
            $rolledOut = array_combine(
                $variantNames,
                array_map(fn ($name) => $name === $validated['variant'] ? 100 : 0, $variantNames)
            );

            $experiment->update([
                'pre_acceptance' => [
                    'variants' => $experiment->variants,
                    'status' => $experiment->status,
                    'is_active' => $experiment->is_active,
                ],
                'variants' => $rolledOut,
                'accepted_variant' => $validated['variant'],
                'accepted_at' => now(),
                'status' => 'completed',
                // Stays active so tracking keeps recording under the winner.
                'is_active' => true,
            ]);

            return \Homemove\AbTesting\Models\AcceptanceReport::create([
                'experiment_id' => $experiment->id,
                'accepted_variant' => $validated['variant'],
                'status' => 'pending',
            ]);
        });

        AbTest::clearCache($experiment->name);

        \Homemove\AbTesting\Jobs\GenerateCleanupReport::dispatch($report->id);

        return redirect()->route('ab-testing.dashboard.show', $experiment)
            ->with('success', "Variant '{$validated['variant']}' accepted — all traffic now receives it. Cleanup report generating.");
    }

    /** Undo accept(): restore the pre-acceptance weights and lifecycle. */
    public function reopen(Request $request, Experiment $experiment)
    {
        $validated = $request->validate([
            'confirm_name' => 'required|string',
        ]);

        if (!$experiment->isAccepted()) {
            return back()->withErrors(['confirm_name' => 'This experiment has no accepted variant.']);
        }

        if (!hash_equals($experiment->name, $validated['confirm_name'])) {
            return back()->withErrors(['confirm_name' => 'Type the experiment name exactly to confirm.']);
        }

        $snapshot = $experiment->pre_acceptance ?? [];

        $experiment->update([
            'variants' => $snapshot['variants'] ?? $experiment->variants,
            'status' => $snapshot['status'] ?? 'running',
            'is_active' => $snapshot['is_active'] ?? true,
            'accepted_variant' => null,
            'accepted_at' => null,
            'pre_acceptance' => null,
        ]);

        AbTest::clearCache($experiment->name);

        return redirect()->route('ab-testing.dashboard.show', $experiment)
            ->with('success', 'Experiment reopened — pre-acceptance weights restored.');
    }

    public function toggleStatus(Experiment $experiment)
    {
        $experiment->update(['is_active' => !$experiment->is_active]);

        // Clear cache
        AbTest::clearCache($experiment->name);

        return back()->with('success', 'Experiment status updated!');
    }

    protected function getExperimentStats(Experiment $experiment, ?string $deviceType = null, ?string $eventFilter = null, ?string $conversionEvent = null): array
    {
        $conversionEvent ??= $experiment->conversionEvent();

        // Shared computations — same numbers the polled API serves.
        $statsService = app(ExperimentStatsService::class);
        $unfilteredVariantStats = $statsService->variantStats($experiment, $deviceType, $conversionEvent);
        $assignments = array_map(fn ($v) => $v['assigned'], $unfilteredVariantStats);
        $conversions = array_map(fn ($v) => $v['converted'], $unfilteredVariantStats);
        $eventCountsByName = $statsService->eventCountsByName($experiment, $deviceType);

        // When an event filter is active, the Variant Performance table switches to funnel
        // mode: participants = users who fired $eventFilter, converted = those who also
        // fired 'conversion'. Top stat cards, chart, and significance keep their unfiltered
        // numbers — significance answers "is the variant winning overall" and shouldn't
        // shift around as the user explores the funnel filter.
        $variantAssigned = $assignments;
        $variantConverted = $conversions;

        if ($eventFilter !== null) {
            $filteredAssignedQuery = Event::where('experiment_id', $experiment->id)
                ->where('event_name', $eventFilter);
            if ($deviceType !== null) {
                $filteredAssignedQuery->where('device_type', $deviceType);
            }
            $variantAssigned = (clone $filteredAssignedQuery)
                ->selectRaw('variant, COUNT(DISTINCT user_id) as count')
                ->groupBy('variant')
                ->pluck('count', 'variant')
                ->toArray();

            $filterUserIdsQuery = Event::where('experiment_id', $experiment->id)
                ->where('event_name', $eventFilter)
                ->select('user_id');
            if ($deviceType !== null) {
                $filterUserIdsQuery->where('device_type', $deviceType);
            }

            $filteredConvertedQuery = Event::where('experiment_id', $experiment->id)
                ->where('event_name', $conversionEvent)
                ->whereIn('user_id', $filterUserIdsQuery);
            if ($deviceType !== null) {
                $filteredConvertedQuery->where('device_type', $deviceType);
            }
            $variantConverted = $filteredConvertedQuery
                ->selectRaw('variant, COUNT(DISTINCT user_id) as count')
                ->groupBy('variant')
                ->pluck('count', 'variant')
                ->toArray();
        }

        $stats = [];
        foreach ($experiment->variants as $variant => $weight) {
            $assigned = $variantAssigned[$variant] ?? 0;
            $converted = $variantConverted[$variant] ?? 0;
            $rate = $assigned > 0 ? round(($converted / $assigned) * 100, 2) : 0;

            $stats[$variant] = [
                'weight' => $weight,
                'assigned' => $assigned,
                'converted' => $converted,
                'conversion_rate' => $rate,
            ];
        }

        // Overall summary stats
        $totalEventsQuery = Event::where('experiment_id', $experiment->id);
        if ($deviceType !== null) {
            $totalEventsQuery->where('device_type', $deviceType);
        }
        $totalEvents = (clone $totalEventsQuery)->count();
        $uniqueUsers = (clone $totalEventsQuery)->distinct('user_id')->count('user_id');

        // Today's stats (from midnight today)
        $todayStart = now()->startOfDay();
        $todayAssignmentsQuery = UserAssignment::where('experiment_id', $experiment->id)
            ->where('created_at', '>=', $todayStart);
        if ($deviceType !== null) {
            $todayAssignmentsQuery->where('device_type', $deviceType);
        }
        $todayAssignments = $todayAssignmentsQuery->count();

        $todayConversionsQuery = Event::where('experiment_id', $experiment->id)
            ->where('event_name', $conversionEvent)
            ->where('created_at', '>=', $todayStart);
        if ($deviceType !== null) {
            $todayConversionsQuery->where('device_type', $deviceType);
        }
        $todayConversions = $todayConversionsQuery
            ->distinct('user_id')
            ->count();

        // Significance is computed against the unfiltered counts so the live-polled
        // value doesn't disagree with the headline question (does the variant win overall).
        $significanceByVariant = $statsService->significanceByVariant($unfilteredVariantStats);
        $significance = $statsService->headlineSignificance($significanceByVariant);
        $rateChangePp = $statsService->rateChangePp($experiment, $deviceType, $conversionEvent);

        return [
            'variants' => $stats,
            'total_assignments' => array_sum($assignments),
            'total_conversions' => array_sum($conversions),
            'total_events' => $totalEvents,
            'unique_users' => $uniqueUsers,
            'today_assignments' => $todayAssignments,
            'today_conversions' => $todayConversions,
            'statistical_significance' => $significance,
            'significance_by_variant' => $significanceByVariant,
            'rate_change_pp' => $rateChangePp,
            'event_counts_by_name' => $eventCountsByName,
        ];
    }



    /**
     * Normalise submitted device-type selection. All three selected (or empty) collapses
     * to null — meaning "no restriction" — so existing behaviour is preserved for any
     * experiment whose author didn't narrow the targeting.
     */
    private function normaliseAllowedDeviceTypes(?array $types): ?array
    {
        if (empty($types)) {
            return null;
        }

        $types = array_values(array_unique($types));
        sort($types);

        if ($types === ['desktop', 'mobile', 'tablet']) {
            return null;
        }

        return $types;
    }

    /**
     * Clamp stats-filter input to one of the three device types (or null for "All").
     */
    private function resolveDeviceTypeFilter($raw): ?string
    {
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        return in_array($raw, ['mobile', 'tablet', 'desktop'], true) ? $raw : null;
    }
}
