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
        try {
            $validated = $request->validate([
                'experiment' => 'required|string',
                'event' => 'required|string',
                'user_id' => 'nullable|string',
                'properties' => 'array|nullable',
            ]);

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
        try {
            $validated = $request->validate([
                'experiment' => 'required|string',
                'user_id' => 'nullable|string',
            ]);

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
}