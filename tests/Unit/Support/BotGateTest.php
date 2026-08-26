<?php

namespace Homemove\AbTesting\Tests\Unit\Support;

use Homemove\AbTesting\Facades\AbTest;
use Homemove\AbTesting\Support\BotDetector;
use Homemove\AbTesting\Tests\TestCase;
use Illuminate\Support\Facades\DB;

class BotGateTest extends TestCase
{
    private function makeExperiment(): int
    {
        return DB::table('ab_experiments')->insertGetId([
            'name' => 'bot_gate_test',
            'variants' => json_encode(['control' => 50, 'variant_b' => 50]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @test */
    public function bot_detector_classifies_user_agents()
    {
        $this->assertTrue(BotDetector::isBot('Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'));
        $this->assertTrue(BotDetector::isBot('curl/8.4.0'));
        $this->assertTrue(BotDetector::isBot(''));
        $this->assertTrue(BotDetector::isBot(null));
        $this->assertFalse(BotDetector::isBot('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15'));
    }

    /** @test */
    public function a_bot_request_resolves_to_control_and_mints_nothing()
    {
        $this->makeExperiment();
        request()->headers->set('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');

        $variant = AbTest::variant('bot_gate_test');

        $this->assertSame('control', $variant);
        $this->assertSame(0, DB::table('ab_user_assignments')->count());
    }

    /** @test */
    public function a_bot_track_records_nothing()
    {
        $this->makeExperiment();
        request()->headers->set('User-Agent', 'AhrefsBot/7.0');

        AbTest::track('bot_gate_test', null, 'conversion');

        $this->assertSame(0, DB::table('ab_events')->count());
        $this->assertSame(0, DB::table('ab_user_assignments')->count());
    }

    /** @test */
    public function an_explicit_user_id_bypasses_the_gate()
    {
        // Server-side attribution passes verified ids — a webhook worker has
        // no browser user agent and must not be mistaken for a crawler.
        $experimentId = $this->makeExperiment();
        DB::table('ab_user_assignments')->insert([
            'experiment_id' => $experimentId, 'user_id' => 'verified-id', 'variant' => 'variant_b',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        request()->headers->set('User-Agent', 'curl/8.4.0');

        AbTest::track('bot_gate_test', 'verified-id', 'lead_paid');

        $this->assertSame(1, DB::table('ab_events')->where('event_name', 'lead_paid')->count());
    }

    /** @test */
    public function the_gate_can_be_disabled_by_config()
    {
        config(['ab-testing.bot_filtering' => false]);
        $this->makeExperiment();
        request()->headers->set('User-Agent', 'Googlebot/2.1');

        AbTest::variant('bot_gate_test');

        $this->assertSame(1, DB::table('ab_user_assignments')->count());
    }
}
