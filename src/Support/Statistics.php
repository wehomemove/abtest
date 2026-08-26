<?php

namespace Homemove\AbTesting\Support;

/**
 * The one two-proportion z-test. Three hand-rolled copies of this maths used
 * to live in DashboardController, ApiController and the Experiment model,
 * disagreeing in their normal-CDF approximations — every caller now delegates
 * here so significance means the same thing everywhere it is displayed.
 */
class Statistics
{
    /**
     * Two-proportion z-test: (n1, x1) = control participants/conversions,
     * (n2, x2) = variant participants/conversions.
     *
     * @return array{percentage: float|int, status: string, message: string, confidence_level: string, p_value?: float, z_score?: float, sample_sizes?: array{control: int, test: int}}
     */
    public static function twoProportionZTest(int $n1, int $x1, int $n2, int $x2, int $minSample = 30): array
    {
        if ($n1 < $minSample || $n2 < $minSample) {
            return [
                'percentage' => 0,
                'status' => 'insufficient_data',
                'message' => "Need at least {$minSample} participants per variant",
                'confidence_level' => 'low',
            ];
        }

        $p1 = $x1 / $n1;
        $p2 = $x2 / $n2;

        $pPool = ($x1 + $x2) / ($n1 + $n2);
        $se = sqrt($pPool * (1 - $pPool) * (1 / $n1 + 1 / $n2));

        if ($se == 0.0) {
            return [
                'percentage' => 0,
                'status' => 'no_difference',
                'message' => 'No measurable difference',
                'confidence_level' => 'low',
            ];
        }

        $z = abs($p2 - $p1) / $se;
        $pValue = 2 * (1 - self::normalCDF($z));
        $confidence = (1 - $pValue) * 100;

        if ($confidence >= 95) {
            [$status, $message, $level] = ['significant', 'Statistically Significant', 'high'];
        } elseif ($confidence >= 90) {
            [$status, $message, $level] = ['approaching', 'Approaching Significance', 'medium'];
        } elseif ($confidence >= 80) {
            [$status, $message, $level] = ['trending', 'Trending Towards Significance', 'medium'];
        } else {
            [$status, $message, $level] = ['not_significant', 'Not Yet Significant', 'low'];
        }

        return [
            'percentage' => round($confidence, 1),
            'status' => $status,
            'message' => $message,
            'confidence_level' => $level,
            'p_value' => round($pValue, 4),
            'z_score' => round($z, 3),
            'sample_sizes' => ['control' => $n1, 'test' => $n2],
        ];
    }

    /**
     * Standard normal CDF, Abramowitz & Stegun 26.2.17 approximation
     * (|error| < 7.5e-8) — accurate beyond anything a dashboard displays.
     */
    public static function normalCDF(float $x): float
    {
        $t = 1.0 / (1.0 + 0.2316419 * abs($x));
        $y = $t * (0.319381530 + $t * (-0.356563782 + $t * (1.781477937 + $t * (-1.821255978 + $t * 1.330274429))));
        $tail = 0.3989423 * exp(-0.5 * $x * $x) * $y;

        return $x >= 0 ? 1.0 - $tail : $tail;
    }
}
