<?php

namespace Homemove\AbTesting\Middleware;

use Closure;
use Illuminate\Http\Request;
use Homemove\AbTesting\Facades\AbTest;

class AbTestMiddleware
{
    public function handle(Request $request, Closure $next, ...$experiments)
    {
        // Auto-assign variants for specified experiments
        foreach ($experiments as $experiment) {
            $variant = AbTest::variant($experiment);
            $request->attributes->set("ab_{$experiment}", $variant);
        }

        $response = $next($request);

        // Track page views if enabled
        if (config('ab-testing.tracking.enabled')) {
            foreach ($experiments as $experiment) {
                AbTest::track($experiment, null, 'page_view', [
                    'url' => $request->url(),
                    'user_agent' => $request->userAgent(),
                ]);
            }
        }

        return $response;
    }
}