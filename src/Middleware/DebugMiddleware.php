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
// A/B Testing JavaScript Helper (auto-injected)
// Auto-tracking system for JavaScript A/B tests
(function() {
    // Track all registered experiments for automatic debug registration
    window.ABTestingExperiments = new Map();
    
    // Enhanced track function with auto-registration
    if (typeof window.abtrack === 'undefined') {
        window.abtrack = function(experiment, event, properties = {}) {
            // Auto-register experiment with debug system if first time seeing it
            if (window.ABTestingConfig?.debug && !window.ABTestingExperiments.has(experiment)) {
                // Get current variant from properties or detect it
                const variant = properties.variant || detectCurrentVariant(experiment);
                if (variant) {
                    window.ABTestingExperiments.set(experiment, {
                        variant: variant,
                        source: properties.source || 'auto-detected'
                    });
                    
                    // Auto-register with debug system
                    fetch('/api/ab-testing/register-debug', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{$csrfToken}'
                        },
                        body: JSON.stringify({
                            experiment: experiment,
                            variant: variant,
                            source: properties.source || 'auto-detected'
                        })
                    }).catch(error => {
                        console.error('A/B test auto-registration error:', error);
                    });
                }
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
            }).catch(error => {
                console.error('A/B test tracking error:', error);
            });
        };
    }

    if (typeof window.abvariant === 'undefined') {
        window.abvariant = function(experiment, userId = null) {
            return fetch('/api/ab-testing/variant', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{$csrfToken}'
                },
                body: JSON.stringify({
                    experiment: experiment,
                    user_id: userId
                })
            })
            .then(response => response.json())
            .then(data => data.variant)
            .catch(error => {
                console.error('A/B test variant error:', error);
                return 'control';
            });
        };
    }

    // Manual registration function (still available if needed)
    if (typeof window.abregisterDebug === 'undefined') {
        window.abregisterDebug = function(experiment, variant, source = 'javascript') {
            if (!window.ABTestingConfig?.debug) return Promise.resolve();
            
            // Store in local tracking
            window.ABTestingExperiments.set(experiment, { variant, source });
            
            return fetch('/api/ab-testing/register-debug', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{$csrfToken}'
                },
                body: JSON.stringify({
                    experiment: experiment,
                    variant: variant,
                    source: source
                })
            }).catch(error => {
                console.error('A/B test debug registration error:', error);
            });
        };
    }

    // Auto-detect current variant from various sources
    function detectCurrentVariant(experiment) {
        // Check debug override cookies first
        const jsOverride = getCookie(`js_ab_test_override_${"${experiment}"}`);
        if (jsOverride) return jsOverride;
        
        const override = getCookie(`ab_test_override_${"${experiment}"}`);
        if (override) return override;
        
        // Check localStorage overrides
        try {
            const overrides = JSON.parse(localStorage.getItem('ab_test_overrides') || '{}');
            if (overrides[experiment]) return overrides[experiment];
        } catch (e) {}
        
        // Check for common Vue/React patterns
        const element = document.querySelector(`[data-ab-test="${experiment}"]`);
        if (element) {
            const variant = element.getAttribute('data-ab-variant');
            if (variant) return variant;
        }
        
        // Check for CSS classes that might indicate variant
        if (document.querySelector('.ab-test-red-button')) {
            return 'red_buttons';
        }
        
        // Check localStorage for stored assignments
        const stored = localStorage.getItem(`ab_test_${"${experiment}"}`);
        if (stored) return stored;
        
        return null;
    }
    
    // Helper function to get cookie value
    function getCookie(name) {
        const value = `; ${"${document.cookie}"}`;
        const parts = value.split(`; ${"${name}"}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return null;
    }
    
    // Auto-scan for Vue composables using A/B tests
    function autoScanForAbTests() {
        // Look for elements with AB test indicators
        const abElements = document.querySelectorAll('[class*="ab-test"], [data-ab-test], [data-variant]');
        
        abElements.forEach(element => {
            const experiment = element.getAttribute('data-ab-test') || 
                             element.className.match(/ab-test-([^\\s]+)/)?.[1];
            const variant = element.getAttribute('data-ab-variant') || 
                           element.getAttribute('data-variant') ||
                           detectCurrentVariant(experiment);
                           
            if (experiment && variant && !window.ABTestingExperiments.has(experiment)) {
                window.abregisterDebug(experiment, variant, 'auto-scan');
            }
        });
        
        // Look for common A/B test patterns in the DOM
        if (document.querySelector('.ab-test-red-button') && !window.ABTestingExperiments.has('survey_red_buttons')) {
            window.abregisterDebug('survey_red_buttons', 'red_buttons', 'dom-detection');
        }
    }
    
    // Auto-scan on DOM ready and periodically
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', autoScanForAbTests);
    } else {
        autoScanForAbTests();
    }
    
    // Re-scan when dynamic content is added
    const observer = new MutationObserver(function(mutations) {
        let shouldScan = false;
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) { // Element node
                        if (node.classList.contains('ab-test-red-button') || 
                            node.querySelector && node.querySelector('[class*="ab-test"], [data-ab-test]')) {
                            shouldScan = true;
                        }
                    }
                });
            }
        });
        
        if (shouldScan) {
            setTimeout(autoScanForAbTests, 100); // Debounce
        }
    });
    
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
    
})();

// Set debug config
window.ABTestingConfig = {
    debug: {$debugEnabled},
    csrfToken: '{$csrfToken}'
};
</script>
HTML;
    }
}