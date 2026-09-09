<?php

namespace Homemove\AbTesting\Tests\Unit\Services;

use Homemove\AbTesting\Models\Experiment;
use Homemove\AbTesting\Services\CleanupScanService;
use Homemove\AbTesting\Tests\TestCase;

class CleanupScanServiceTest extends TestCase
{
    private function makeAcceptedExperiment(): Experiment
    {
        return Experiment::create([
            'name' => 'demo_landing_v1',
            'variants' => ['control' => 0, 'variant_b' => 100, 'variant_c' => 0],
            'traffic_allocation' => 100,
            'is_active' => true,
            'accepted_variant' => 'variant_b',
            'accepted_at' => now(),
        ]);
    }

    private function scanFixtures(array $overrides = []): array
    {
        config(['ab-testing.accept.scan' => array_merge([
            'enabled' => true,
            'paths' => [__DIR__ . '/../../fixtures/fake-app'],
            'extensions' => ['php', 'js', 'ts', 'vue', 'json'],
            'timeout_seconds' => 30,
            'max_references' => 500,
            'max_file_size' => 1048576,
        ], $overrides)]);

        return (new CleanupScanService)->scan($this->makeAcceptedExperiment());
    }

    /** @test */
    public function it_finds_experiment_and_variant_references_with_suggested_actions()
    {
        $result = $this->scanFixtures();

        $this->assertFalse($result['truncated']);
        $this->assertNotEmpty($result['references']);

        $byFile = [];
        foreach ($result['references'] as $ref) {
            $byFile[basename($ref['file'])][] = $ref;
        }

        // Enum file: convention location, flagged for review.
        $this->assertArrayHasKey('DemoLandingExperiment.php', $byFile);
        $this->assertContains('review_enum', array_column($byFile['DemoLandingExperiment.php'], 'suggested_action'));

        // Config funnels entry.
        $this->assertArrayHasKey('ab-testing.php', $byFile);
        $this->assertContains('remove_config_entry', array_column($byFile['ab-testing.php'], 'suggested_action'));

        // Blade conditional on a losing variant.
        $this->assertArrayHasKey('demo-landing.blade.php', $byFile);
        $this->assertContains('delete_losing_branch', array_column($byFile['demo-landing.blade.php'], 'suggested_action'));

        // vendor/ is never scanned.
        foreach ($result['references'] as $ref) {
            $this->assertStringNotContainsString('vendor/', $ref['file']);
        }
    }

    /** @test */
    public function it_truncates_at_the_reference_cap()
    {
        $result = $this->scanFixtures(['max_references' => 1]);

        $this->assertTrue($result['truncated']);
        $this->assertCount(1, $result['references']);
    }

    /** @test */
    public function it_returns_empty_for_missing_paths()
    {
        $result = $this->scanFixtures(['paths' => [__DIR__ . '/does-not-exist']]);

        $this->assertSame([], $result['references']);
        $this->assertSame(0, $result['scanned_files']);
    }
}
