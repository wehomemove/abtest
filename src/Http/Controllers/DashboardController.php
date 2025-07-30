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

        // Today's stats (from midnight today)
        $todayStart = now()->startOfDay();
        $todayAssignments = UserAssignment::where('experiment_id', $experiment->id)
            ->where('created_at', '>=', $todayStart)
            ->count();
            
        $todayConversions = Event::where('experiment_id', $experiment->id)
            ->where('event_name', 'conversion')
            ->where('created_at', '>=', $todayStart)
            ->distinct('user_id')
            ->count();

        // Calculate statistical significance
        $significance = $this->calculateStatisticalSignificance($stats);

        return [
            'variants' => $stats,
            'total_assignments' => array_sum($assignments),
            'total_conversions' => array_sum($conversions),
            'total_events' => $totalEvents,
            'total_interactions' => $totalInteractions,
            'unique_users' => $userEvents->count(),
            'user_events' => $userEvents,
            'today_assignments' => $todayAssignments,
            'today_conversions' => $todayConversions,
            'statistical_significance' => $significance,
        ];
    }

    protected function calculateStatisticalSignificance(array $stats): array
    {
        // Find control and test variants
        $control = null;
        $test = null;
        
        foreach ($stats as $variant => $data) {
            if ($variant === 'control') {
                $control = $data;
            } else {
                $test = $data; // Use first non-control variant
                break;
            }
        }
        
        if (!$control || !$test || $control['assigned'] < 30 || $test['assigned'] < 30) {
            return [
                'percentage' => 0,
                'status' => 'insufficient_data',
                'message' => 'Need at least 30 participants per variant',
                'confidence_level' => 'low'
            ];
        }

        // Two-proportion z-test
        $n1 = $control['assigned'];
        $x1 = $control['converted'];
        $p1 = $x1 / $n1;
        
        $n2 = $test['assigned'];
        $x2 = $test['converted'];
        $p2 = $x2 / $n2;
        
        // Pooled proportion
        $p_pool = ($x1 + $x2) / ($n1 + $n2);
        
        // Standard error
        $se = sqrt($p_pool * (1 - $p_pool) * (1/$n1 + 1/$n2));
        
        if ($se == 0) {
            return [
                'percentage' => 0,
                'status' => 'no_difference',
                'message' => 'No measurable difference',
                'confidence_level' => 'low'
            ];
        }
        
        // Z-score
        $z = abs($p2 - $p1) / $se;
        
        // Convert to p-value (two-tailed test)
        $p_value = 2 * (1 - $this->normalCDF($z));
        
        // Convert to confidence percentage
        $confidence = (1 - $p_value) * 100;
        
        // Determine status and message
        if ($confidence >= 95) {
            $status = 'significant';
            $message = 'Statistically Significant';
            $level = 'high';
        } elseif ($confidence >= 90) {
            $status = 'approaching';
            $message = 'Approaching Significance';
            $level = 'medium';
        } elseif ($confidence >= 80) {
            $status = 'trending';  
            $message = 'Trending Towards Significance';
            $level = 'medium';
        } else {
            $status = 'not_significant';
            $message = 'Not Yet Significant';
            $level = 'low';
        }
        
        return [
            'percentage' => round($confidence, 1),
            'status' => $status,
            'message' => $message,
            'confidence_level' => $level,
            'p_value' => round($p_value, 4),
            'z_score' => round($z, 3),
            'sample_sizes' => ['control' => $n1, 'test' => $n2]
        ];
    }
    
    private function normalCDF($x)
    {
        // Approximation of the cumulative distribution function for standard normal distribution
        // Using Abramowitz and Stegun approximation
        $t = 1.0 / (1.0 + 0.2316419 * abs($x));
        $y = $t * (0.319381530 + $t * (-0.356563782 + $t * (1.781477937 + $t * (-1.821255978 + $t * 1.330274429))));
        
        if ($x >= 0) {
            return 1.0 - 0.3989423 * exp(-0.5 * $x * $x) * $y;
        } else {
            return 0.3989423 * exp(-0.5 * $x * $x) * $y;
        }
    }
}