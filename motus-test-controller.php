<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Drop this file into your Motus project at:
 * app/Http/Controllers/AbTestController.php
 */
class AbTestController extends Controller
{
    /**
     * Show the A/B testing test page
     */
    public function testPage()
    {
        return view('ab-test-page');
    }
    
    /**
     * API endpoint to test A/B testing functionality
     */
    public function apiTest(Request $request)
    {
        try {
            // Test if the package is loaded
            if (!app()->bound('ab-testing')) {
                return response()->json([
                    'success' => false,
                    'error' => 'A/B Testing package not loaded',
                    'suggestion' => 'Check if AbTestingServiceProvider is registered'
                ]);
            }
            
            $service = app('ab-testing');
            
            // Test variant assignment
            $variant = $service->variant('survey_red_buttons');
            
            // Test tracking
            $service->track('survey_red_buttons', null, 'api_test', [
                'source' => 'motus_test',
                'timestamp' => now()
            ]);
            
            return response()->json([
                'success' => true,
                'package_loaded' => true,
                'variant' => $variant,
                'experiment' => 'survey_red_buttons',
                'message' => 'A/B Testing package is working correctly!',
                'debug_info' => [
                    'service_class' => get_class($service),
                    'debug_enabled' => config('app.debug'),
                    'database_connection' => config('database.default'),
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : 'Enable debug mode for stack trace'
            ], 500);
        }
    }
}