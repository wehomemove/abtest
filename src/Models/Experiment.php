<?php

namespace Homemove\AbTesting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Experiment extends Model
{
    protected $table = 'ab_experiments';

    protected $fillable = [
        'name',
        'description',
        'variants',
        'is_active',
        'traffic_allocation',
        'target_applications',
        'success_metrics',
        'custom_events',
        'minimum_sample_size',
        'confidence_level',
        'start_date',
        'end_date',
        'targeting_rules',
        'status',
    ];

    protected $casts = [
        'variants' => 'array',
        'target_applications' => 'array',
        'success_metrics' => 'array',
        'custom_events' => 'array',
        'targeting_rules' => 'array',
        'is_active' => 'boolean',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'confidence_level' => 'decimal:2',
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(UserAssignment::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function getConversionRateAttribute(): array
    {
        $assignments = $this->assignments()
            ->selectRaw('variant, COUNT(*) as total')
            ->groupBy('variant')
            ->pluck('total', 'variant')
            ->toArray();

        $conversions = $this->events()
            ->where('event_name', 'conversion')
            ->selectRaw('variant, COUNT(DISTINCT user_id) as conversions')
            ->groupBy('variant')
            ->pluck('conversions', 'variant')
            ->toArray();

        $rates = [];
        foreach ($assignments as $variant => $total) {
            $converted = $conversions[$variant] ?? 0;
            $rates[$variant] = [
                'total' => $total,
                'conversions' => $converted,
                'rate' => $total > 0 ? round(($converted / $total) * 100, 2) : 0,
            ];
        }

        return $rates;
    }

    public function isActive(): bool
    {
        if (!$this->is_active || $this->status !== 'running') {
            return false;
        }

        $now = now();
        
        if ($this->start_date && $now->lt($this->start_date)) {
            return false;
        }

        if ($this->end_date && $now->gt($this->end_date)) {
            return false;
        }

        return true;
    }

    public function getStatisticalSignificance($variant): array
    {
        $controlStats = $this->getVariantStats('control');
        $variantStats = $this->getVariantStats($variant);
        
        if ($controlStats['total'] < $this->minimum_sample_size || 
            $variantStats['total'] < $this->minimum_sample_size) {
            return [
                'significant' => false,
                'confidence' => 0,
                'p_value' => 1.0,
                'message' => 'Insufficient sample size'
            ];
        }

        // Two-proportion z-test
        $p1 = $controlStats['conversions'] / $controlStats['total'];
        $p2 = $variantStats['conversions'] / $variantStats['total'];
        $pooledP = ($controlStats['conversions'] + $variantStats['conversions']) / 
                   ($controlStats['total'] + $variantStats['total']);
        
        $se = sqrt($pooledP * (1 - $pooledP) * 
                  ((1 / $controlStats['total']) + (1 / $variantStats['total'])));
        
        if ($se == 0) {
            return [
                'significant' => false,
                'confidence' => 0,
                'p_value' => 1.0,
                'message' => 'No variance in data'
            ];
        }

        $z = ($p2 - $p1) / $se;
        $pValue = 2 * (1 - $this->normalCDF(abs($z)));
        
        $isSignificant = $pValue < (1 - ($this->confidence_level / 100));
        $confidence = (1 - $pValue) * 100;

        return [
            'significant' => $isSignificant,
            'confidence' => round($confidence, 2),
            'p_value' => round($pValue, 4),
            'z_score' => round($z, 3),
            'message' => $isSignificant ? 
                "Statistically significant at {$this->confidence_level}% confidence" :
                'Not statistically significant'
        ];
    }

    private function getVariantStats($variant): array
    {
        $assignments = $this->assignments()->where('variant', $variant)->count();
        $conversions = $this->events()
            ->where('variant', $variant)
            ->where('event_name', 'conversion')
            ->distinct('user_id')
            ->count();

        return [
            'total' => $assignments,
            'conversions' => $conversions,
            'rate' => $assignments > 0 ? ($conversions / $assignments) : 0
        ];
    }

    private function normalCDF($x): float
    {
        // Approximation of the cumulative distribution function for standard normal
        return 0.5 * (1 + $this->erf($x / sqrt(2)));
    }

    private function erf($x): float
    {
        // Approximation of the error function
        $a1 =  0.254829592;
        $a2 = -0.284496736;
        $a3 =  1.421413741;
        $a4 = -1.453152027;
        $a5 =  1.061405429;
        $p  =  0.3275911;

        $sign = $x < 0 ? -1 : 1;
        $x = abs($x);

        $t = 1.0 / (1.0 + $p * $x);
        $y = 1.0 - (((($a5 * $t + $a4) * $t + $a3) * $t + $a2) * $t + $a1) * $t * exp(-$x * $x);

        return $sign * $y;
    }

    public function canRunInApplication($app): bool
    {
        return in_array($app, $this->target_applications ?? ['motus', 'apollo', 'olympus']);
    }
}