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
        $eventFilter = $this->resolveEventFilter($request->query('event_filter'), $experiment, $deviceType);
        $stats = $this->getExperimentStats($experiment, $deviceType, $eventFilter);
        $stats['funnel'] = $this->resolveFunnel($experiment, $deviceType);

        return view('ab-testing::dashboard.show', [
            'experiment' => $experiment,
            'stats' => $stats,
            'activeDeviceType' => $deviceType,
            'activeEventFilter' => $eventFilter,
        ]);
    }

    protected function resolveFunnel(Experiment $experiment, ?string $deviceType): ?array
    {
        return app(ExperimentStatsService::class)->funnel($experiment, $deviceType);
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

    protected function resolveEventFilter(?string $eventFilter, Experiment $experiment, ?string $deviceType = null): ?string
    {
        if (! $eventFilter || $eventFilter === 'conversion') {
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

    public function toggleStatus(Experiment $experiment)
    {
        $experiment->update(['is_active' => !$experiment->is_active]);

        // Clear cache
        AbTest::clearCache($experiment->name);

        return back()->with('success', 'Experiment status updated!');
    }

    protected function getExperimentStats(Experiment $experiment, ?string $deviceType = null, ?string $eventFilter = null): array
    {
        // Shared computations — same numbers the polled API serves.
        $statsService = app(ExperimentStatsService::class);
        $unfilteredVariantStats = $statsService->variantStats($experiment, $deviceType);
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
                ->where('event_name', 'conversion')
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
            ->where('event_name', 'conversion')
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
        $rateChangePp = $statsService->rateChangePp($experiment, $deviceType);

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
