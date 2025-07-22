<?php

namespace Homemove\AbTesting\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Homemove\AbTesting\Facades\AbTest;

class SimpleAbTestController extends Controller
{
    /**
     * Get A/B test configuration for a given experiment
     */
    public function getConfig(Request $request): JsonResponse
    {
        $experimentName = $request->get('experiment', 'button_color_test');
        $userId = $request->session()->getId();
        
        try {
            // Get variant assignment
            $variant = AbTest::variant($experimentName, $userId);
            
            // Track page view
            AbTest::track($experimentName, $userId, 'page_view');
            
            // Define configurations for each variant
            $configs = [
                'control' => [
                    'variant' => 'control',
                    'buttonClass' => 'hm-cta-primary', // Default blue
                    'buttonText' => 'Pay Now',
                    'description' => 'Default blue buttons'
                ],
                'variant_b' => [
                    'variant' => 'variant_b', 
                    'buttonClass' => 'bg-red-600 hover:bg-red-700', // Homemove red
                    'buttonText' => 'Pay Now',
                    'description' => 'Red buttons for better visibility'
                ]
            ];
            
            $config = $configs[$variant] ?? $configs['control'];
            $config['experiment'] = $experimentName;
            $config['userId'] = $userId;
            
            return response()->json([
                'success' => true,
                'config' => $config
            ]);
            
        } catch (\Exception $e) {
            // Fallback to control on error
            return response()->json([
                'success' => false,
                'config' => [
                    'variant' => 'control',
                    'buttonClass' => 'hm-cta-primary',
                    'buttonText' => 'Pay Now',
                    'description' => 'Default (fallback)',
                    'experiment' => $experimentName,
                    'userId' => $userId
                ],
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Track A/B test events with simple endpoint
     */
    public function track(Request $request): JsonResponse
    {
        $experimentName = $request->get('experiment', 'button_color_test');
        $userId = $request->session()->getId();
        $eventName = $request->get('event', 'button_click');
        $properties = $request->get('properties', []);
        
        try {
            AbTest::track($experimentName, $userId, $eventName, $properties);
            
            return response()->json([
                'success' => true,
                'message' => 'Event tracked successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to track event',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}