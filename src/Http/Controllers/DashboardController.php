<?php

namespace Homemove\AbTesting\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Homemove\AbTesting\Models\Experiment;
use Homemove\AbTesting\Models\UserAssignment;
use Homemove\AbTesting\Models\Event;
use Homemove\AbTesting\Facades\AbTest;

class DashboardController extends Controller
{
    public function index()
    {
        $experiments = Experiment::withCount(['assignments', 'events'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('ab-testing::dashboard.index', compact('experiments'));
    }

    public function show(Experiment $experiment)
    {
        $stats = $this->getExperimentStats($experiment);
        
        return view('ab-testing::dashboard.show', compact('experiment', 'stats'));
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
            'variants' => 'required|array|min:2',
            'variants.*' => 'required|integer|min:0|max:100',
            'traffic_allocation' => 'required|integer|min:0|max:100',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
        ]);

        // Ensure variants sum to 100
        if (array_sum($validated['variants']) !== 100) {
            return back()->withErrors(['variants' => 'Variant weights must sum to 100%']);
        }

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
            'variants' => 'required|array|min:2',
            'variants.*' => 'required|integer|min:0|max:100',
            'traffic_allocation' => 'required|integer|min:0|max:100',
            'is_active' => 'boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
        ]);

        if (array_sum($validated['variants']) !== 100) {
            return back()->withErrors(['variants' => 'Variant weights must sum to 100%']);
        }

        $experiment->update($validated);
        
        // Clear cache
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

    protected function getExperimentStats(Experiment $experiment): array
    {
        // Get assignment counts by variant
        $assignments = UserAssignment::where('experiment_id', $experiment->id)
            ->selectRaw('variant, COUNT(*) as count')
            ->groupBy('variant')
            ->pluck('count', 'variant')
            ->toArray();

        // Get conversion counts by variant
        $conversions = Event::where('experiment_id', $experiment->id)
            ->where('event_name', 'conversion')
            ->selectRaw('variant, COUNT(DISTINCT user_id) as count')
            ->groupBy('variant')
            ->pluck('count', 'variant')
            ->toArray();

        // Get user-organized event data
        $userEvents = Event::where('experiment_id', $experiment->id)
            ->orderBy('updated_at', 'desc')
            ->get()
            ->groupBy('user_id')
            ->map(function ($events, $userId) {
                $userVariant = $events->first()->variant;
                $totalInteractions = 0;
                $eventSummary = [];
                
                foreach ($events as $event) {
                    $properties = is_string($event->properties) 
                        ? json_decode($event->properties, true) ?? [] 
                        : (is_array($event->properties) ? $event->properties : []);
                    $count = $properties['count'] ?? 1;
                    $totalInteractions += $count;
                    
                    if (!isset($eventSummary[$event->event_name])) {
                        $eventSummary[$event->event_name] = [
                            'count' => 0,
                            'last_occurred' => $event->updated_at,
                        ];
                    }
                    
                    $eventSummary[$event->event_name]['count'] += $count;
                    if ($event->updated_at > $eventSummary[$event->event_name]['last_occurred']) {
                        $eventSummary[$event->event_name]['last_occurred'] = $event->updated_at;
                    }
                }
                
                return [
                    'user_id' => $userId,
                    'variant' => $userVariant,
                    'total_interactions' => $totalInteractions,
                    'unique_events' => count($eventSummary),
                    'events' => $eventSummary,
                    'last_activity' => $events->max('updated_at'),
                    'first_activity' => $events->min('created_at'),
                ];
            })
            ->sortByDesc('last_activity')
            ->values();

        // Calculate stats for each variant
        $stats = [];
        foreach ($experiment->variants as $variant => $weight) {
            $assigned = $assignments[$variant] ?? 0;
            $converted = $conversions[$variant] ?? 0;
            $rate = $assigned > 0 ? round(($converted / $assigned) * 100, 2) : 0;

            $stats[$variant] = [
                'weight' => $weight,
                'assigned' => $assigned,
                'converted' => $converted,
                'conversion_rate' => $rate,
            ];
        }

        // Overall summary stats
        $totalEvents = Event::where('experiment_id', $experiment->id)->count();
        $totalInteractions = Event::where('experiment_id', $experiment->id)
            ->get()
            ->sum(function ($event) {
                $properties = is_string($event->properties) 
                    ? json_decode($event->properties, true) ?? [] 
                    : (is_array($event->properties) ? $event->properties : []);
                return $properties['count'] ?? 1;
            });

        return [
            'variants' => $stats,
            'total_assignments' => array_sum($assignments),
            'total_conversions' => array_sum($conversions),
            'total_events' => $totalEvents,
            'total_interactions' => $totalInteractions,
            'unique_users' => $userEvents->count(),
            'user_events' => $userEvents,
        ];
    }
}