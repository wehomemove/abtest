<?php

namespace Homemove\AbTesting\Tests\Unit\Support;

use Homemove\AbTesting\Support\Statistics;
use Homemove\AbTesting\Tests\TestCase;

class StatisticsTest extends TestCase
{
    /** @test */
    public function it_gates_on_minimum_sample_size()
    {
        $result = Statistics::twoProportionZTest(29, 10, 100, 30);

        $this->assertSame('insufficient_data', $result['status']);
        $this->assertSame(0, $result['percentage']);
    }

    /** @test */
    public function it_reports_no_difference_when_standard_error_is_zero()
    {
        // 0 conversions on both sides -> pooled p = 0 -> se = 0
        $result = Statistics::twoProportionZTest(100, 0, 100, 0);

        $this->assertSame('no_difference', $result['status']);
    }

    /** @test */
    public function it_detects_a_clearly_significant_difference()
    {
        // 10% vs 25% on 400 each — decisively significant
        $result = Statistics::twoProportionZTest(400, 40, 400, 100);

        $this->assertSame('significant', $result['status']);
        $this->assertGreaterThanOrEqual(95, $result['percentage']);
        $this->assertLessThan(0.05, $result['p_value']);
        $this->assertSame(['control' => 400, 'test' => 400], $result['sample_sizes']);
    }

    /** @test */
    public function it_reports_identical_rates_as_not_significant()
    {
        $result = Statistics::twoProportionZTest(500, 50, 500, 50);

        $this->assertSame('not_significant', $result['status']);
        $this->assertSame(0.0, (float) $result['z_score']);
    }

    /** @test */
    public function normal_cdf_matches_known_values()
    {
        $this->assertEqualsWithDelta(0.5, Statistics::normalCDF(0.0), 1e-6);
        $this->assertEqualsWithDelta(0.8413, Statistics::normalCDF(1.0), 1e-3);
        $this->assertEqualsWithDelta(0.9772, Statistics::normalCDF(2.0), 1e-3);
        $this->assertEqualsWithDelta(0.0228, Statistics::normalCDF(-2.0), 1e-3);
    }
}
