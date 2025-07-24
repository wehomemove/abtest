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
        $userInfo = $service->getDebugUserInfo();

        // Debug logging
        \Log::info('AB Debug Middleware', [
            'experiments' => $experiments,
            'content_type' => $response->headers->get('Content-Type'),
            'has_body_tag' => str_contains($response->getContent(), '</body>'),
            'experiments_count' => count($experiments)
        ]);

        // Always show debug panel when debug is enabled, even if no experiments yet
        // Experiments may be populated after middleware runs

        $content = $response->getContent();

        // Only inject if there's a closing body tag
        if (!str_contains($content, '</body>')) {
            return $response;
        }

        try {
            // Inject A/B testing JavaScript helper
            $jsHelper = $this->getAbTestingJavaScript();
            $debugHtml = view('ab-testing::debug', compact('experiments', 'userInfo'))->render();


            // Inject both JS helper and debug panel
            $injection = $jsHelper . $debugHtml;
            $content = str_replace('</body>', $injection . '</body>', $content);
            $response->setContent($content);
            \Log::info('AB Debug: Successfully injected JS helper and debug HTML');
        } catch (\Exception $e) {
            \Log::error('AB Debug: Failed to render debug view', ['error' => $e->getMessage()]);
        }

        return $response;
    }

    /**
     * Get the A/B testing JavaScript helper code
     */
    protected function getAbTestingJavaScript(): string
    {
        $csrfToken = csrf_token();
        $debugEnabled = config('app.debug') ? 'true' : 'false';

        return <<<HTML
<script>
// Simple A/B Testing JavaScript Helper
if (typeof window.abtrack === 'undefined') {
    window.abtrack = function(experiment, event, properties) {
        properties = properties || {};
        
        // Auto-register with debug system
        if ({$debugEnabled} && typeof window.abregisterDebug === 'function') {
            var variant = properties.variant || 'control';
            if (document.querySelector('.ab-test-red-button') && experiment === 'survey_red_buttons') {
                variant = 'red_buttons';
            }
            window.abregisterDebug(experiment, variant, 'auto-detected');
        }
        
        return fetch('/api/ab-testing/track', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{$csrfToken}'
            },
            body: JSON.stringify({
                experiment: experiment,
                event: event,
                properties: properties
            })
        }).catch(function(error) {
            console.error('A/B test tracking error:', error);
        });
    };
}

if (typeof window.abregisterDebug === 'undefined') {
    window.abregisterDebug = function(experiment, variant, source) {
        if (!{$debugEnabled}) return Promise.resolve();
        
        return fetch('/api/ab-testing/register-debug', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{$csrfToken}'
            },
            body: JSON.stringify({
                experiment: experiment,
                variant: variant,
                source: source || 'javascript'
            })
        }).catch(function(error) {
            console.error('A/B test debug registration error:', error);
        });
    };
}

// Auto-detect survey red buttons experiment
if ({$debugEnabled}) {
    setTimeout(function() {
        if (document.querySelector('.ab-test-red-button')) {
            window.abregisterDebug('survey_red_buttons', 'red_buttons', 'dom-detection');
        }
    }, 100);
}
</script>
HTML;
    }
}