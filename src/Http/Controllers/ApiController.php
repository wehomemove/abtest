<?php

namespace Homemove\AbTesting\Http\Controllers;

use Homemove\AbTesting\Facades\AbTest;
use Homemove\AbTesting\Models\Experiment;
use Homemove\AbTesting\Services\ExperimentStatsService;
use Homemove\AbTesting\Support\Statistics;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

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

    /**
     * The 10s dashboard poll. Numbers come from the same ExperimentStatsService
     * the page render uses — before this the two endpoints drifted (the poll
     * had no event breakdown, so the donut and table could never live-refresh).
     * Funnel data rides only on ?include=funnel (a slower 60s timer) to keep
     * the frequent poll light.
     */
    public function getExperimentStats(Request $request, $experimentId)
    {
        try {
            $experiment = Experiment::findOrFail($experimentId);
            $deviceType = $this->resolveDeviceTypeFilter($request->query('device_type'));
            $service = app(ExperimentStatsService::class);

            $variantStats = $service->variantStats($experiment, $deviceType);
            $controlRate = $variantStats['control']['conversion_rate'] ?? 0;

            $variants = [];
            foreach ($variantStats as $variant => $data) {
                $variants[$variant] = [
                    'participants' => $data['assigned'],
                    'conversions' => $data['converted'],
                    'rate' => $data['conversion_rate'],
                    'lift' => ($variant !== 'control' && $controlRate > 0)
                        ? round((($data['conversion_rate'] - $controlRate) / $controlRate) * 100, 1)
                        : 0,
                    'color' => $this->getVariantColor($variant),
                ];
            }

            $significanceByVariant = $service->significanceByVariant($variantStats);

            $payload = [
                'success' => true,
                'device_type' => $deviceType,
                'total_assignments' => array_sum(array_column($variants, 'participants')),
                'total_conversions' => array_sum(array_column($variants, 'conversions')),
                'variants' => $variants,
                'statistical_significance' => $service->headlineSignificance($significanceByVariant),
                'significance_by_variant' => $significanceByVariant,
                'rate_change_pp' => $service->rateChangePp($experiment, $deviceType),
                'event_counts_by_name' => $service->eventCountsByName($experiment, $deviceType),
                'updated_at' => now()->toISOString(),
            ];

            if ($request->query('include') === 'funnel') {
                $payload['funnel'] = $service->funnel($experiment, $deviceType);
            }

            return response()->json($payload);
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
            $deviceType = $this->resolveDeviceTypeFilter($request->query('device_type'));

            // Get recent events and assignments
            $recentEventsQuery = $experiment->events()
                ->with('experiment')
                ->orderBy('created_at', 'desc')
                ->limit(15);

            $recentAssignmentsQuery = $experiment->assignments()
                ->orderBy('created_at', 'desc')
                ->limit(10);

            if ($deviceType !== null) {
                $recentEventsQuery->where('device_type', $deviceType);
                $recentAssignmentsQuery->where('device_type', $deviceType);
            }

            $recentEvents = $recentEventsQuery->get();
            $recentAssignments = $recentAssignmentsQuery->get();

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
            usort($activities, function ($a, $b) {
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

    /**
     * Per-variant time series for the "Conversion over time" chart: for each
     * bucket, participants assigned and the cumulative-in-window conversion
     * rate per arm. Two grouped queries total, bucketed in PHP — portable
     * across SQLite (tests) and MySQL/Postgres, and O(rows) not O(buckets).
     */
    public function getChartData(Request $request, $experimentId)
    {
        try {
            $experiment = Experiment::findOrFail($experimentId);
            $period = in_array($request->get('period'), ['24h', '7d', '30d'], true) ? $request->get('period') : '24h';
            $deviceType = $this->resolveDeviceTypeFilter($request->query('device_type'));

            $now = now();
            [$startTime, $intervalMinutes, $points, $labelFormat] = match ($period) {
                '7d' => [$now->copy()->subDays(7)->startOfDay(), 1440, 8, 'M j'],
                '30d' => [$now->copy()->subDays(30)->startOfDay(), 1440, 31, 'M j'],
                default => [$now->copy()->subHours(24)->startOfHour(), 60, 25, 'H:00'],
            };

            $variantNames = array_keys($experiment->variants ?? []);
            $bucketFor = function ($timestamp) use ($startTime, $intervalMinutes) {
                $minutes = $startTime->diffInMinutes(\Illuminate\Support\Carbon::parse($timestamp), false);

                return $minutes < 0 ? null : intdiv((int) $minutes, $intervalMinutes);
            };

            $assignmentRows = $experiment->assignments()
                ->where('created_at', '>=', $startTime)
                ->when($deviceType !== null, fn ($q) => $q->where('device_type', $deviceType))
                ->get(['variant', 'created_at']);

            $conversionRows = $experiment->events()
                ->where('event_name', 'conversion')
                ->where('created_at', '>=', $startTime)
                ->when($deviceType !== null, fn ($q) => $q->where('device_type', $deviceType))
                ->get(['variant', 'user_id', 'created_at'])
                ->unique(fn ($row) => $row->variant . '|' . $row->user_id);

            $series = [];
            foreach ($variantNames as $variant) {
                $series[$variant] = [
                    'participants' => array_fill(0, $points, 0),
                    'conversions' => array_fill(0, $points, 0),
                ];
            }
            foreach ($assignmentRows as $row) {
                $bucket = $bucketFor($row->created_at);
                if ($bucket !== null && $bucket < $points && isset($series[$row->variant])) {
                    $series[$row->variant]['participants'][$bucket]++;
                }
            }
            foreach ($conversionRows as $row) {
                $bucket = $bucketFor($row->created_at);
                if ($bucket !== null && $bucket < $points && isset($series[$row->variant])) {
                    $series[$row->variant]['conversions'][$bucket]++;
                }
            }

            $labels = [];
            for ($i = 0; $i < $points; $i++) {
                $labels[] = $startTime->copy()->addMinutes($i * $intervalMinutes)->format($labelFormat);
            }

            $variants = [];
            foreach ($variantNames as $variant) {
                $rates = [];
                foreach ($series[$variant]['participants'] as $i => $assigned) {
                    $rates[] = $assigned > 0
                        ? round(($series[$variant]['conversions'][$i] / $assigned) * 100, 2)
                        : 0;
                }
                $variants[$variant] = [
                    'participants' => $series[$variant]['participants'],
                    'conversion_rate' => $rates,
                    'color' => $this->getVariantColor($variant),
                ];
            }

            return response()->json([
                'success' => true,
                'labels' => $labels,
                'variants' => $variants,
                'period' => $period,
                'start_time' => $startTime->toISOString(),
                'end_time' => $now->toISOString(),
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


    private function resolveDeviceTypeFilter($raw): ?string
    {
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        return in_array($raw, ['mobile', 'tablet', 'desktop'], true) ? $raw : null;
    }
}
