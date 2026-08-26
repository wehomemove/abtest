<?php

namespace Homemove\AbTesting\Support;

/**
 * Bot classification for experiment gating. Assignment is cookie-based and
 * crawlers carry no cookies, so every bot hit would otherwise mint a fresh
 * phantom participant — inflating every arm of every experiment with users
 * that analytics tools never see. Gated centrally in AbTestService so no
 * individual test or route can forget it.
 */
class BotDetector
{
    /**
     * Crawler-specific by intent: generic vendor names would misclassify the
     * embedded in-app browsers real humans arrive in ("Twitter for iPhone",
     * Facebook's FBAN/FB_IAB webviews) — and a human misread as a bot here is
     * silently excluded from every experiment. Hence twitterbot /
     * facebookexternalhit rather than the bare vendor substrings some legacy
     * analytics lists use.
     *
     * @var array<int, string>
     */
    public const SIGNATURES = [
        'adsbot', 'lighthouse', 'google.com', 'preview', 'facebookexternalhit', 'facebot', 'bingbot',
        'bing.com', 'yahoo', 'baidu', 'duckduckgo', 'yandex', 'exabot', 'sogou',
        'applebot', 'twitterbot', 'headlesschrome', 'robot', 'semrush', 'ahrefs',
        'bot/', 'crawler', 'spider', 'python-requests', 'curl/', 'wget/',
    ];

    /** An empty user agent is treated as automated — real browsers always send one. */
    public static function isBot(?string $userAgent): bool
    {
        if ($userAgent === null || $userAgent === '') {
            return true;
        }

        $ua = strtolower($userAgent);

        foreach (self::SIGNATURES as $signature) {
            if (str_contains($ua, $signature)) {
                return true;
            }
        }

        return false;
    }

    /** The current request's bot status (false when no request, e.g. CLI). */
    public static function currentRequestIsBot(): bool
    {
        try {
            // Console (artisan, queue workers) carries a synthetic request
            // with no user agent — a worker is not a crawler.
            if (app()->runningInConsole() && !app()->runningUnitTests()) {
                return false;
            }

            $request = request();

            return $request ? self::isBot($request->userAgent()) : false;
        } catch (\Throwable) {
            return false;
        }
    }
}
