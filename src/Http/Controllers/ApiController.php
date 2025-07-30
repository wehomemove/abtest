<?php

namespace Homemove\AbTesting\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Homemove\AbTesting\Facades\AbTest;
use Homemove\AbTesting\Models\Experiment;

class ApiController extends Controller
{
    public function track(Request $request)
    {
        $validated = $request->validate([
            'experiment' => 'required|string',
            'event' => 'required|string',
            'user_id' => 'nullable|string',
            'properties' => 'array|nullable',
        ]);

        try {
            AbTest::track(
                $validated['experiment'],
                $validated['user_id'] ?? null, // Use provided user_id or fall back to session
                $validated['event'],
                $validated['properties'] ?? []
            );

            return response()->json([
                'success' => true,
                'message' => 'Event tracked successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('A/B Test tracking error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to track event'
            ], 500);
        }
    }

    public function getVariant(Request $request)
    {
        $validated = $request->validate([
            'experiment' => 'required|string',
            'user_id' => 'nullable|string',
        ]);

        try {
            $variant = AbTest::variant(
                $validated['experiment'],
                $validated['user_id'] ?? null
            );

            return response()->json([
                'success' => true,
                'variant' => $variant,
                'experiment' => $validated['experiment']
            ]);

        } catch (\Exception $e) {
            \Log::error('A/B Test variant error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'variant' => 'control',
                'message' => 'Failed to get variant, defaulting to control'
            ], 500);
        }
    }

    public function getVariantByExperiment(Request $request, $experiment)
    {
        try {
            $variant = AbTest::variant($experiment);

            return response()->json([
                'success' => true,
                'variant' => $variant,
                'experiment' => $experiment
            ]);

        } catch (\Exception $e) {
            \Log::error('A/B Test variant error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'variant' => 'control',
                'message' => 'Failed to get variant, defaulting to control'
            ], 500);
        }
    }

    public function registerDebugExperiment(Request $request)
    {
        $validated = $request->validate([
            'experiment' => 'required|string',
            'variant' => 'required|string',
            'source' => 'string|nullable',
        ]);

        try {
            $service = app('ab-testing');
            $service->registerJsDebugExperiment(
                $validated['experiment'],
                $validated['variant'],
                $validated['source'] ?? 'javascript'
            );

            return response()->json([
                'success' => true,
                'message' => 'Debug experiment registered successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('A/B Test debug registration error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to register debug experiment'
            ], 500);
        }
    }

    public function getResults(Request $request, $experiment)
    {
        try {
            $exp = Experiment::where('name', $experiment)->first();
            
            if (!$exp) {
                return response()->json([
                    'success' => false,
                    'message' => 'Experiment not found'
                ], 404);
            }

            $stats = [];
            foreach ($exp->variants as $variant => $weight) {
                $assignments = $exp->assignments()->where('variant', $variant)->count();
                $conversions = $exp->events()
                    ->where('variant', $variant)
                    ->where('event_name', 'conversion')
                    ->distinct('user_id')
                    ->count();

                $stats[$variant] = [
                    'weight' => $weight,
                    'assignments' => $assignments,
                    'conversions' => $conversions,
                    'conversion_rate' => $assignments > 0 ? round(($conversions / $assignments) * 100, 2) : 0,
                ];
            }

            return response()->json([
                'success' => true,
                'experiment' => $exp->only(['name', 'description', 'is_active', 'status']),
                'variants' => $stats,
                'total_assignments' => array_sum(array_column($stats, 'assignments')),
                'total_conversions' => array_sum(array_column($stats, 'conversions')),
            ]);

        } catch (\Exception $e) {
            \Log::error('A/B Test results error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get experiment results'
            ], 500);
        }
    }

    public function getExperimentStats(Request $request, $experimentId)
    {
        try {
            $experiment = Experiment::findOrFail($experimentId);
            
            // Get variant statistics
            $variants = [];
            $controlRate = 0;
            
            foreach ($experiment->variants as $variant => $weight) {
                $assignments = $experiment->assignments()->where('variant', $variant)->count();
                $conversions = $experiment->events()
                    ->where('variant', $variant)
                    ->where('event_name', 'conversion')
                    ->distinct('user_id')
                    ->count();
                
                $rate = $assignments > 0 ? round(($conversions / $assignments) * 100, 2) : 0;
                
                if ($variant === 'control') {
                    $controlRate = $rate;
                }
                
                $variants[$variant] = [
                    'participants' => $assignments,
                    'conversions' => $conversions,
                    'rate' => $rate,
                    'lift' => 0, // Will calculate after getting control rate
                    'color' => $this->getVariantColor($variant)
                ];
            }
            
            // Calculate lift for non-control variants
            foreach ($variants as $variant => &$data) {
                if ($variant !== 'control' && $controlRate > 0) {
                    $data['lift'] = round((($data['rate'] - $controlRate) / $controlRate) * 100, 1);
                }
            }
            
            $totalAssignments = array_sum(array_column($variants, 'participants'));
            $totalConversions = array_sum(array_column($variants, 'conversions'));
            
            return response()->json([
                'success' => true,
                'total_assignments' => $totalAssignments,
                'total_conversions' => $totalConversions,
                'variants' => $variants,
                'updated_at' => now()->toISOString()
            ]);
            
        } catch (\Exception $e) {
            \Log::error('A/B Test stats error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get experiment stats'
            ], 500);
        }
    }
    
    public function getRecentActivity(Request $request, $experimentId)
    {
        try {
            $experiment = Experiment::findOrFail($experimentId);
            
            // Get recent events and assignments
            $recentEvents = $experiment->events()
                ->with('experiment')
                ->orderBy('created_at', 'desc')
                ->limit(15)
                ->get();
                
            $recentAssignments = $experiment->assignments()
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
            
            $activities = [];
            
            // Add recent events
            foreach ($recentEvents as $event) {
                $color = $this->getEventColor($event->event_name);
                $message = $this->formatEventMessage($event);
                $timeAgo = $event->created_at->diffForHumans();
                
                $activities[] = [
                    'message' => $message,
                    'color' => $color,
                    'time' => $timeAgo,
                    'timestamp' => $event->created_at->timestamp
                ];
            }
            
            // Add recent assignments
            foreach ($recentAssignments as $assignment) {
                $activities[] = [
                    'message' => "New user assigned to {$assignment->variant}",
                    'color' => 'bg-red-500',
                    'time' => $assignment->created_at->diffForHumans(),
                    'timestamp' => $assignment->created_at->timestamp
                ];
            }
            
            // Sort by timestamp (most recent first - descending)
            usort($activities, function($a, $b) {
                return $b['timestamp'] - $a['timestamp'];
            });
            
            // Return only the most recent 15, newest first
            return response()->json(array_slice($activities, 0, 15));
            
        } catch (\Exception $e) {
            \Log::error('A/B Test recent activity error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get recent activity'
            ], 500);
        }
    }
    
    public function getChartData(Request $request, $experimentId)
    {
        try {
            $experiment = Experiment::findOrFail($experimentId);
            $period = $request->get('period', '24h');
            
            // Calculate time range
            $now = now();
            switch ($period) {
                case '24h':
                    $startTime = $now->copy()->subHours(24);
                    $interval = 'hour';
                    $points = 24;
                    break;
                case '7d':
                    $startTime = $now->copy()->subDays(7);
                    $interval = 'day';
                    $points = 7;
                    break;
                case '30d':
                    $startTime = $now->copy()->subDays(30);
                    $interval = 'day';
                    $points = 10; // Show every 3 days
                    break;
                default:
                    $startTime = $now->copy()->subHours(24);
                    $interval = 'hour';
                    $points = 24;
            }
            
            // Get conversion rates over time
            $values = [];
            for ($i = 0; $i < $points; $i++) {
                $timePoint = $interval === 'hour' 
                    ? $startTime->copy()->addHours($i)
                    : $startTime->copy()->addDays($i * ($period === '30d' ? 3 : 1));
                    
                $nextTimePoint = $interval === 'hour'
                    ? $timePoint->copy()->addHour()
                    : $timePoint->copy()->addDay();
                
                // Get assignments and conversions in this time period
                $assignments = $experiment->assignments()
                    ->whereBetween('created_at', [$timePoint, $nextTimePoint])
                    ->count();
                    
                $conversions = $experiment->events()
                    ->where('event_name', 'conversion')
                    ->whereBetween('created_at', [$timePoint, $nextTimePoint])
                    ->distinct('user_id')
                    ->count();
                
                $rate = $assignments > 0 ? round(($conversions / $assignments) * 100, 2) : 0;
                $values[] = $rate;
            }
            
            return response()->json([
                'success' => true,
                'values' => $values,
                'period' => $period,
                'start_time' => $startTime->toISOString(),
                'end_time' => $now->toISOString()
            ]);
            
        } catch (\Exception $e) {
            \Log::error('A/B Test chart data error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get chart data'
            ], 500);
        }
    }
    
    private function getEventColor($eventName)
    {
        $colors = [
            'conversion' => 'bg-red-500',
            'click' => 'bg-red-600',
            'view' => 'bg-red-400',
            'submit' => 'bg-red-700',
            'signup' => 'bg-red-500',
            'default' => 'bg-red-400'
        ];
        
        return $colors[$eventName] ?? $colors['default'];
    }
    
    private function formatEventMessage($event)
    {
        $eventMessages = [
            'conversion' => "User converted in {$event->variant}",
            'click' => "Button clicked in {$event->variant}",
            'view' => "Page viewed in {$event->variant}",
            'submit' => "Form submitted in {$event->variant}",
            'signup' => "User signed up in {$event->variant}",
        ];
        
        return $eventMessages[$event->event_name] ?? "Event '{$event->event_name}' in {$event->variant}";
    }
    
    private function getVariantColor($variant)
    {
        $colors = [
            'control' => '#DC2626',
            'variant_a' => '#B91C1C',
            'variant_b' => '#991B1B',
            'new_design' => '#7F1D1D',
            'default' => '#6B7280'
        ];
        
        return $colors[$variant] ?? $colors['default'];
    }
}