<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>🧪 Motus A/B Testing Test</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: #f8fafc;
        }
        .container {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .test-section {
            background: #f8fafc;
            padding: 20px;
            margin: 20px 0;
            border-radius: 6px;
            border-left: 4px solid #3b82f6;
        }
        .btn {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            margin: 5px;
            font-size: 14px;
        }
        .btn:hover { background: #2563eb; }
        .btn-red { background: #ef4444; }
        .btn-red:hover { background: #dc2626; }
        .btn-success { background: #10b981; }
        .btn-success:hover { background: #059669; }
        .results {
            background: #1f2937;
            color: white;
            padding: 15px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 12px;
            margin: 10px 0;
            white-space: pre-wrap;
            max-height: 400px;
            overflow-y: auto;
        }
        .alert {
            padding: 12px;
            border-radius: 6px;
            margin: 10px 0;
        }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-info { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
        h1 { color: #1f2937; text-align: center; }
        h2 { color: #374151; border-bottom: 2px solid #e5e7eb; padding-bottom: 8px; }
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .status-success { background: #10b981; }
        .status-error { background: #ef4444; }
        .status-loading { background: #f59e0b; animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Motus A/B Testing Integration Test</h1>
        
        <div class="alert alert-info">
            <strong>📍 Current URL:</strong> {{ url()->current() }}<br>
            <strong>🏠 Environment:</strong> {{ app()->environment() }}<br>
            <strong>🐛 Debug Mode:</strong> {{ config('app.debug') ? 'Enabled' : 'Disabled' }}
        </div>

        <div class="test-section">
            <h2>🔍 Package Status Check</h2>
            <p>First, let's check if the A/B testing package is properly loaded:</p>
            
            <button class="btn btn-success" onclick="checkPackageStatus()">
                <span class="status-indicator status-loading" id="status-indicator"></span>
                Check Package Status
            </button>
            
            <div id="status-results" class="results">Click the button above to test package integration...</div>
        </div>

        <div class="test-section">
            <h2>🎯 A/B Test Simulation</h2>
            <div id="experiment-display" class="alert alert-info">
                <strong>Experiment:</strong> survey_red_buttons<br>
                <strong>Current Variant:</strong> <span id="current-variant">Not loaded</span>
            </div>
            
            <div id="button-container" style="text-align: center; padding: 20px; border: 2px dashed #d1d5db; border-radius: 6px;">
                <p style="color: #6b7280;">Load experiment to see variant-specific content</p>
            </div>
            
            <button class="btn" onclick="loadExperiment()">🔄 Load Experiment</button>
            <button class="btn" onclick="trackEvent()">📊 Track Event</button>
            
            <div id="experiment-results" class="results"></div>
        </div>

        <div class="test-section">
            <h2>🛠️ Debug Tools</h2>
            
            <button class="btn" onclick="testRoutes()">🛣️ Test Routes</button>
            <button class="btn" onclick="checkDatabase()">🗄️ Check Database</button>
            <button class="btn" onclick="inspectEnvironment()">🔧 Inspect Environment</button>
            
            <div id="debug-results" class="results"></div>
        </div>
    </div>

    <script>
        let currentVariant = 'unknown';
        
        function getCsrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        }
        
        function displayResults(elementId, data, isError = false) {
            const element = document.getElementById(elementId);
            const timestamp = new Date().toLocaleTimeString();
            const status = isError ? '❌ ERROR' : '✅ SUCCESS';
            const result = `[${timestamp}] ${status}:\n${JSON.stringify(data, null, 2)}\n\n${element.textContent}`;
            element.textContent = result;
        }
        
        function updateStatusIndicator(success) {
            const indicator = document.getElementById('status-indicator');
            indicator.className = success ? 'status-indicator status-success' : 'status-indicator status-error';
        }

        async function checkPackageStatus() {
            try {
                updateStatusIndicator(false); // Loading state
                
                const response = await fetch('/ab-test/api-test', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken()
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    updateStatusIndicator(true);
                    currentVariant = data.variant;
                    document.getElementById('current-variant').textContent = data.variant;
                    updateExperimentDisplay();
                } else {
                    updateStatusIndicator(false);
                }
                
                displayResults('status-results', data, !data.success);
                
            } catch (error) {
                updateStatusIndicator(false);
                displayResults('status-results', { 
                    error: error.message,
                    note: 'This usually means routes are not set up correctly'
                }, true);
            }
        }

        async function loadExperiment() {
            try {
                // First try the package's API endpoint
                const response = await fetch('/api/ab-testing/variant/survey_red_buttons');
                const data = await response.json();
                
                if (data.success) {
                    currentVariant = data.variant;
                    document.getElementById('current-variant').textContent = data.variant;
                    updateExperimentDisplay();
                }
                
                displayResults('experiment-results', { 
                    endpoint: '/api/ab-testing/variant/{experiment}',
                    response: data 
                });
                
            } catch (error) {
                displayResults('experiment-results', { 
                    endpoint: '/api/ab-testing/variant/{experiment}',
                    error: error.message,
                    note: 'Package API routes may not be loaded'
                }, true);
            }
        }

        async function trackEvent() {
            try {
                const response = await fetch('/api/ab-testing/track', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken()
                    },
                    body: JSON.stringify({
                        experiment: 'survey_red_buttons',
                        event: 'button_click',
                        properties: {
                            variant: currentVariant,
                            page: 'motus_test',
                            timestamp: Date.now()
                        }
                    })
                });
                
                const data = await response.json();
                displayResults('experiment-results', { 
                    action: 'Track Event',
                    response: data 
                });
                
            } catch (error) {
                displayResults('experiment-results', { 
                    action: 'Track Event',
                    error: error.message 
                }, true);
            }
        }

        function updateExperimentDisplay() {
            const container = document.getElementById('button-container');
            
            if (currentVariant === 'red_buttons') {
                container.innerHTML = `
                    <button class="btn btn-red ab-test-red-button" onclick="simulateClick('red_button')">
                        🔴 Red Submit Button
                    </button>
                    <p style="color: #991b1b; margin: 10px 0;">✨ You're in the RED BUTTONS variant!</p>
                `;
            } else if (currentVariant === 'control') {
                container.innerHTML = `
                    <button class="btn" onclick="simulateClick('normal_button')">
                        🔵 Normal Submit Button
                    </button>
                    <p style="color: #1e40af; margin: 10px 0;">📋 You're in the CONTROL variant</p>
                `;
            } else {
                container.innerHTML = `<p style="color: #6b7280;">Unknown variant: ${currentVariant}</p>`;
            }
        }

        function simulateClick(buttonType) {
            displayResults('experiment-results', { 
                action: `Button clicked: ${buttonType}`,
                variant: currentVariant,
                note: 'This would trigger conversion tracking'
            });
            
            // Auto-track the click
            trackEvent();
        }

        async function testRoutes() {
            const routes = [
                '/api/ab-testing/variant/survey_red_buttons',
                '/api/ab-testing/track',
                '/api/ab-testing/register-debug',
                '/ab-testing/dashboard'
            ];
            
            const results = {};
            
            for (const route of routes) {
                try {
                    const response = await fetch(route);
                    results[route] = {
                        status: response.status,
                        ok: response.ok,
                        statusText: response.statusText
                    };
                } catch (error) {
                    results[route] = {
                        error: error.message
                    };
                }
            }
            
            displayResults('debug-results', { 
                action: 'Route Test',
                routes: results 
            });
        }

        async function checkDatabase() {
            // This would need a custom endpoint to check database tables
            displayResults('debug-results', { 
                action: 'Database Check',
                note: 'Run these commands in your terminal:',
                commands: [
                    'php artisan migrate --path=vendor/homemove/ab-testing/src/database/migrations',
                    'php artisan db:show --table=ab_experiments',
                    'php artisan db:show --table=ab_user_assignments',
                    'php artisan db:show --table=ab_events'
                ]
            });
        }

        function inspectEnvironment() {
            const env = {
                userAgent: navigator.userAgent,
                url: window.location.href,
                csrf: getCsrfToken(),
                cookies: document.cookie.split(';').map(c => c.trim()).filter(c => c.includes('ab_')),
                localStorage: Object.keys(localStorage).filter(k => k.includes('ab_')).map(k => ({ [k]: localStorage.getItem(k) }))
            };
            
            displayResults('debug-results', { 
                action: 'Environment Inspection',
                environment: env 
            });
        }

        // Auto-check package status on load
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(checkPackageStatus, 1000);
        });
    </script>
</body>
</html>