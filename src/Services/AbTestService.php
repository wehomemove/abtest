<?php

namespace Homemove\AbTesting\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AbTestService
{
    protected $cachePrefix = 'ab_test:';
    protected $cacheTtl = 3600; // 1 hour

    /**
     * Get variant for a user in an experiment
     */
    public function variant(string $experimentName, $userId = null): string
    {
        $userId = $userId ?? $this->getSessionUserId();
        
        // Check for debug override cookie first
        $overrideCookieName = "ab_test_override_{$experimentName}";
        if (isset($_COOKIE[$overrideCookieName])) {
            $overrideVariant = $_COOKIE[$overrideCookieName];
            
            // Validate that the override variant is valid for this experiment
            $experiment = DB::table('ab_experiments')
                ->where('name', $experimentName)
                ->where('is_active', true)
                ->first();
                
            if ($experiment) {
                $variants = json_decode($experiment->variants, true);
                if (isset($variants[$overrideVariant])) {
                    return $overrideVariant;
                }
            }
        }
        
        $cacheKey = $this->cachePrefix . "variant:{$experimentName}:{$userId}";
        
        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($experimentName, $userId) {
            return $this->assignVariant($experimentName, $userId);
        });
    }

    /**
     * Check if user is in a specific variant
     */
    public function isVariant(string $experimentName, string $variantName, $userId = null): bool
    {
        return $this->variant($experimentName, $userId) === $variantName;
    }

    /**
     * Track an event for an experiment
     */
    public function track(string $experimentName, $userId = null, string $eventName = 'conversion', array $properties = []): void
    {
        $userId = $userId ?? $this->getSessionUserId();
        $variant = $this->variant($experimentName, $userId);

        // Get experiment ID from name
        $experiment = DB::table('ab_experiments')->where('name', $experimentName)->first();
        if (!$experiment) {
            return; // Skip tracking if experiment doesn't exist
        }

        // Check if this exact event already exists for this user
        $existingEvent = DB::table('ab_events')
            ->where('experiment_id', $experiment->id)
            ->where('user_id', $userId)
            ->where('event_name', $eventName)
            ->where('variant', $variant)
            ->first();

        if ($existingEvent) {
            // Update existing event - increment a counter in properties
            $existingProperties = json_decode($existingEvent->properties, true) ?? [];
            $count = ($existingProperties['count'] ?? 1) + 1;
            
            // Merge new properties with existing ones, updating the count
            $updatedProperties = array_merge($existingProperties, $properties, ['count' => $count]);
            
            DB::table('ab_events')
                ->where('id', $existingEvent->id)
                ->update([
                    'properties' => json_encode($updatedProperties),
                    'updated_at' => now(),
                ]);
        } else {
            // Create new event with count = 1
            $properties['count'] = 1;
            
            DB::table('ab_events')->insert([
                'experiment_id' => $experiment->id,
                'user_id' => $userId,
                'variant' => $variant,
                'event_name' => $eventName,
                'properties' => json_encode($properties),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Assign a variant to a user
     */
    protected function assignVariant(string $experimentName, $userId): string
    {
        // Get experiment configuration first
        $experiment = $this->getExperiment($experimentName);
        
        if (!$experiment || !$experiment->is_active) {
            return 'control';
        }

        // Check if user already has an assignment
        $existing = DB::table('ab_user_assignments')
            ->where('experiment_id', $experiment->id)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            return $existing->variant;
        }

        // Assign variant based on traffic allocation
        $variant = $this->calculateVariant($userId, $experiment);

        // Store assignment
        DB::table('ab_user_assignments')->insert([
            'experiment_id' => $experiment->id,
            'user_id' => $userId,
            'variant' => $variant,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $variant;
    }

    /**
     * Calculate which variant to assign based on user ID and experiment config
     */
    protected function calculateVariant($userId, $experiment): string
    {
        $variants = json_decode($experiment->variants, true);
        $hash = hexdec(substr(md5($experiment->name . $userId), 0, 8));
        $percentage = ($hash % 100) + 1;
        
        $cumulative = 0;
        foreach ($variants as $variant => $weight) {
            $cumulative += $weight;
            if ($percentage <= $cumulative) {
                return $variant;
            }
        }

        return 'control';
    }

    /**
     * Get experiment configuration
     */
    protected function getExperiment(string $name)
    {
        $cacheKey = $this->cachePrefix . "experiment:{$name}";
        
        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($name) {
            $experiment = DB::table('ab_experiments')
                ->where('name', $name)
                ->first();
                
            if (!$experiment) {
                return null;
            }
            
            // Check if experiment can run in current application
            $currentApp = $this->getCurrentApplication();
            $targetApps = json_decode($experiment->target_applications, true);
            
            if (!in_array($currentApp, $targetApps)) {
                return null;
            }
            
            return $experiment;
        });
    }

    /**
     * Detect current application context
     */
    protected function getCurrentApplication(): string
    {
        $url = request()->getHost();
        
        if (str_contains($url, 'motus') || str_contains($url, 'app.homemove')) {
            return 'motus';
        }
        
        if (str_contains($url, 'apollo') || str_contains($url, 'homemove.test')) {
            return 'apollo';
        }
        
        if (str_contains($url, 'olympus')) {
            return 'olympus';
        }
        
        // Default fallback based on config or ENV
        return config('app.name', 'motus');
    }

    /**
     * Get or generate session-based user ID
     */
    protected function getSessionUserId(): string
    {
        if (session()->has('ab_user_id')) {
            return session('ab_user_id');
        }

        $userId = Str::uuid()->toString();
        session(['ab_user_id' => $userId]);
        
        return $userId;
    }

    /**
     * Clear cache for an experiment
     */
    public function clearCache(string $experimentName = null): void
    {
        if ($experimentName) {
            Cache::forget($this->cachePrefix . "experiment:{$experimentName}");
            // Clear all user variant cache for this experiment
            $pattern = $this->cachePrefix . "variant:{$experimentName}:*";
            // Note: This would need Redis SCAN in production
        } else {
            Cache::flush();
        }
    }
}