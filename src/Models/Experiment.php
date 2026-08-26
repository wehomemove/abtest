<?php

namespace Homemove\AbTesting\Models;

use Illuminate\Database\Eloquent\Model;
use Homemove\AbTesting\Support\Statistics;
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
        'allowed_device_types',
        'status',
    ];

    protected $casts = [
        'variants' => 'array',
        'target_applications' => 'array',
        'success_metrics' => 'array',
        'custom_events' => 'array',
        'targeting_rules' => 'array',
        'allowed_device_types' => 'array',
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

        // Shared maths (Statistics); this method keeps its historical return
        // shape, its configurable confidence_level threshold, and a SIGNED z.
        $result = Statistics::twoProportionZTest(
            (int) $controlStats['total'],
            (int) $controlStats['conversions'],
            (int) $variantStats['total'],
            (int) $variantStats['conversions'],
            1
        );

        if (($result['status'] ?? '') === 'no_difference') {
            return [
                'significant' => false,
                'confidence' => 0,
                'p_value' => 1.0,
                'message' => 'No variance in data'
            ];
        }

        $p1 = $controlStats['conversions'] / $controlStats['total'];
        $p2 = $variantStats['conversions'] / $variantStats['total'];
        $signedZ = ($p2 >= $p1 ? 1 : -1) * $result['z_score'];

        $isSignificant = $result['p_value'] < (1 - ($this->confidence_level / 100));

        return [
            'significant' => $isSignificant,
            'confidence' => round((1 - $result['p_value']) * 100, 2),
            'p_value' => $result['p_value'],
            'z_score' => $signedZ,
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


    public function canRunInApplication($app): bool
    {
        return in_array($app, $this->target_applications ?? ['motus', 'apollo', 'olympus']);
    }

    public function allowsDevice(?string $device): bool
    {
        $allowed = $this->allowed_device_types;

        if (empty($allowed)) {
            return true;
        }

        if ($device === null) {
            return false;
        }

        return in_array($device, $allowed, true);
    }
}
