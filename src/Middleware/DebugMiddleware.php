<?php

namespace Homemove\AbTesting\Middleware;

use Closure;
use Illuminate\Http\Request;

class DebugMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Only inject debug UI if it's a web request with HTML content and debug is enabled
        if (!config('app.debug') || 
            !method_exists($response, 'getContent') ||
            !str_contains($response->headers->get('Content-Type', ''), 'text/html')) {
            return $response;
        }

        $service = app('ab-testing');
        $experiments = $service->getDebugExperiments();
        
        // Debug logging
        \Log::info('AB Debug Middleware', [
            'experiments' => $experiments,
            'content_type' => $response->headers->get('Content-Type'),
            'has_body_tag' => str_contains($response->getContent(), '</body>')
        ]);
        
        if (empty($experiments)) {
            return $response;
        }

        $content = $response->getContent();
        
        // Only inject if there's a closing body tag
        if (!str_contains($content, '</body>')) {
            return $response;
        }

        $debugHtml = view('ab-testing::debug', compact('experiments'))->render();
        $content = str_replace('</body>', $debugHtml . '</body>', $content);
        $response->setContent($content);

        return $response;
    }
}