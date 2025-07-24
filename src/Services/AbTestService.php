<?php

namespace Homemove\AbTesting\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AbTestService
{
    protected $cachePrefix = 'ab_test:';
    protected $cacheTtl = 3600; // 1 hour
    protected $debugExperiments = [];

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
                    $this->trackDebugExperiment($experimentName, $overrideVariant);
                    return $overrideVariant;
                }
            }
        }
        
        $cacheKey = $this->cachePrefix . "variant:{$experimentName}:{$userId}";
        
        $variant = Cache::remember($cacheKey, $this->cacheTtl, function () use ($experimentName, $userId) {
            return $this->assignVariant($experimentName, $userId);
        });
        
        $this->trackDebugExperiment($experimentName, $variant);
        return $variant;
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
        
        // Check if we should use adaptive allocation to balance distribution
        if ($this->shouldUseAdaptiveAllocation($experiment)) {
            return $this->calculateAdaptiveVariant($userId, $experiment, $variants);
        }
        
        // Original hash-based assignment
        $hash = hexdec(substr(md5($experiment->name . $userId), 0, 8));
        $percentage = ($hash % 100) + 1;
        
        $cumulative = 0;
        foreach ($variants as $variant => $weight) {
            $cumulative += (int) $weight; // Cast to integer to handle string values
            if ($percentage <= $cumulative) {
                return $variant;
            }
        }

        return 'control';
    }

    /**
     * Check if we should use adaptive allocation (only after we have some data)
     */
    protected function shouldUseAdaptiveAllocation($experiment): bool
    {
        // Only use adaptive allocation after we have at least 20 assignments
        // This prevents early skewing from affecting the algorithm
        $totalAssignments = DB::table('ab_user_assignments')
            ->where('experiment_id', $experiment->id)
            ->count();
            
        return $totalAssignments >= 20;
    }

    /**
     * Calculate variant using adaptive allocation to balance distribution
     */
    protected function calculateAdaptiveVariant($userId, $experiment, $variants): string
    {
        // Get current distribution
        $currentCounts = [];
        $totalAssignments = 0;
        
        foreach (array_keys($variants) as $variant) {
            $count = DB::table('ab_user_assignments')
                ->where('experiment_id', $experiment->id)
                ->where('variant', $variant)
                ->count();
            $currentCounts[$variant] = $count;
            $totalAssignments += $count;
        }
        
        if ($totalAssignments == 0) {
            // Fallback to hash-based if no assignments yet
            return $this->calculateHashBasedVariant($userId, $experiment->name, $variants);
        }
        
        // Calculate how far each variant is from its target percentage
        $targetPercentages = [];
        $deviations = [];
        
        foreach ($variants as $variant => $targetWeight) {
            $targetPercentages[$variant] = (int) $targetWeight;
            $currentPercentage = ($currentCounts[$variant] / $totalAssignments) * 100;
            $deviations[$variant] = $targetPercentages[$variant] - $currentPercentage;
        }
        
        // Find the variant that's most under-represented
        $maxDeviation = max($deviations);
        
        // If the maximum deviation is small (< 5%), use normal hash-based assignment
        if ($maxDeviation < 5) {
            return $this->calculateHashBasedVariant($userId, $experiment->name, $variants);
        }
        
        // Otherwise, assign to the most under-represented variant
        $underRepresentedVariants = array_keys($deviations, $maxDeviation);
        
        // If multiple variants are equally under-represented, use hash to pick one
        if (count($underRepresentedVariants) > 1) {
            $hash = hexdec(substr(md5($experiment->name . $userId), 0, 8));
            $index = $hash % count($underRepresentedVariants);
            return $underRepresentedVariants[$index];
        }
        
        return $underRepresentedVariants[0];
    }

    /**
     * Original hash-based variant calculation
     */
    protected function calculateHashBasedVariant($userId, $experimentName, $variants): string
    {
        $hash = hexdec(substr(md5($experimentName . $userId), 0, 8));
        $percentage = ($hash % 100) + 1;
        
        $cumulative = 0;
        foreach ($variants as $variant => $weight) {
            $cumulative += (int) $weight;
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
            return DB::table('ab_experiments')
                ->where('name', $name)
                ->first();
        });
    }


    /**
     * Get or generate session-based user ID
     */
    protected function getSessionUserId(): string
    {
        // First try to get from cookie (most reliable)
        if (isset($_COOKIE['ab_user_id'])) {
            return $_COOKIE['ab_user_id'];
        }

        // Try session as fallback
        if (session()->isStarted() && session()->has('ab_user_id')) {
            $userId = session('ab_user_id');
            // Also store in cookie for reliability
            $this->setUserIdCookie($userId);
            return $userId;
        }

        // Generate new user ID
        $userId = Str::uuid()->toString();
        
        // Store in both cookie and session
        $this->setUserIdCookie($userId);
        
        try {
            if (!session()->isStarted()) {
                session()->start();
            }
            session(['ab_user_id' => $userId]);
            session()->save();
        } catch (\Exception $e) {
            // Session might not be available, cookie will handle it
            \Log::debug('AB Testing: Session not available, using cookie only', ['error' => $e->getMessage()]);
        }
        
        return $userId;
    }

    /**
     * Set the user ID cookie
     */
    protected function setUserIdCookie(string $userId): void
    {
        // Set cookie for 30 days
        $expire = time() + (30 * 24 * 60 * 60);
        setcookie('ab_user_id', $userId, $expire, '/', '', false, true);
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

    /**
     * Track experiment usage for debug panel
     */
    protected function trackDebugExperiment(string $experimentName, string $variant): void
    {
        if (!config('app.debug')) {
            return;
        }

        if (!isset($this->debugExperiments[$experimentName])) {
            $this->debugExperiments[$experimentName] = [
                'variant' => $variant,
                'calls' => 0
            ];
        }
        
        $this->debugExperiments[$experimentName]['calls']++;
    }

    /**
     * Register JavaScript-based experiment for debug panel
     */
    public function registerJsDebugExperiment(string $experimentName, string $variant, string $source = 'javascript'): void
    {
        if (!config('app.debug')) {
            return;
        }

        if (!isset($this->debugExperiments[$experimentName])) {
            $this->debugExperiments[$experimentName] = [
                'variant' => $variant,
                'calls' => 0,
                'source' => $source,
                'variants' => $this->getExperimentVariants($experimentName)
            ];
        }
        
        $this->debugExperiments[$experimentName]['calls']++;
        $this->debugExperiments[$experimentName]['variant'] = $variant; // Update current variant
    }

    /**
     * Get experiment variants from database
     */
    protected function getExperimentVariants(string $experimentName): array
    {
        $experiment = $this->getExperiment($experimentName);
        if (!$experiment) {
            // Default variants if experiment not found in database
            return ['control' => 50, 'red_buttons' => 50];
        }

        return json_decode($experiment->variants, true) ?? [];
    }

    /**
     * Get debug experiments for current request
     */
    public function getDebugExperiments(): array
    {
        return $this->debugExperiments;
    }

    /**
     * Get debug information about user ID source
     */
    public function getDebugUserInfo(): array
    {
        $userId = null;
        $source = 'none';
        
        // Check cookie first
        if (isset($_COOKIE['ab_user_id'])) {
            $userId = $_COOKIE['ab_user_id'];
            $source = 'cookie';
        }
        // Check session as fallback
        elseif (session()->isStarted() && session()->has('ab_user_id')) {
            $userId = session('ab_user_id');
            $source = 'session';
        }
        
        return [
            'user_id' => $userId ?? 'not_set',
            'source' => $source,
            'cookie_exists' => isset($_COOKIE['ab_user_id']),
            'session_exists' => session()->isStarted() && session()->has('ab_user_id'),
            'session_started' => session()->isStarted()
        ];
    }
}