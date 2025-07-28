<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>A/B Testing Test Page</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .test-section {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .test-button {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            margin: 5px;
            font-size: 16px;
        }
        .test-button:hover {
            background: #2563eb;
        }
        .red-button {
            background: #ef4444 !important;
        }
        .red-button:hover {
            background: #dc2626 !important;
        }
        .results {
            background: #1f2937;
            color: white;
            padding: 15px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 14px;
            margin: 10px 0;
            white-space: pre-wrap;
        }
        .status {
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .info { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
        h1 { color: #1f2937; }
        h2 { color: #374151; border-bottom: 2px solid #e5e7eb; padding-bottom: 10px; }
    </style>
</head>
<body>
    <h1>🧪 A/B Testing Test Page</h1>
    
    <div class="test-section">
        <h2>API Endpoint Tests</h2>
        
        <button class="test-button" onclick="testGetVariant()">
            Test GET /api/ab-testing/variant/{experiment}
        </button>
        
        <button class="test-button" onclick="testPostVariant()">
            Test POST /api/ab-testing/variant
        </button>
        
        <button class="test-button" onclick="testTrackEvent()">
            Test POST /api/ab-testing/track
        </button>
        
        <button class="test-button" onclick="testRegisterDebug()">
            Test POST /api/ab-testing/register-debug
        </button>
        
        <div id="api-results" class="results"></div>
    </div>

    <div class="test-section">
        <h2>A/B Test Simulation</h2>
        <div id="experiment-status" class="info">
            Experiment: <strong>survey_red_buttons</strong> | 
            Current Variant: <span id="current-variant">Loading...</span>
        </div>
        
        <!-- This will show different buttons based on variant -->
        <div id="button-container">
            <div class="info">Loading experiment variant...</div>
        </div>
        
        <div id="experiment-results" class="results"></div>
    </div>

    <div class="test-section">
        <h2>JavaScript Helper Tests</h2>
        
        <button class="test-button" onclick="testAbTrack()">
            Test window.abtrack()
        </button>
        
        <button class="test-button" onclick="testAbRegisterDebug()">
            Test window.abregisterDebug()
        </button>
        
        <button class="test-button" onclick="clearOverrides()">
            Clear All Overrides
        </button>
        
        <div id="js-results" class="results"></div>
    </div>

    <div class="test-section">
        <h2>Storage Inspection</h2>
        
        <button class="test-button" onclick="inspectStorage()">
            Inspect Cookies & localStorage
        </button>
        
        <div id="storage-results" class="results"></div>
    </div>

    <script>
        let currentVariant = 'control';
        
        // Helper function to get CSRF token
        function getCsrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        }
        
        // Helper function to display results
        function displayResults(elementId, data, isError = false) {
            const element = document.getElementById(elementId);
            const timestamp = new Date().toLocaleTimeString();
            const status = isError ? 'ERROR' : 'SUCCESS';
            const result = `[${timestamp}] ${status}: ${JSON.stringify(data, null, 2)}\n\n${element.textContent}`;
            element.textContent = result;
        }

        // API Tests
        async function testGetVariant() {
            try {
                const response = await fetch('/api/ab-testing/variant/survey_red_buttons');
                const data = await response.json();
                displayResults('api-results', { endpoint: 'GET /variant/{experiment}', response: data });
                
                if (data.success) {
                    currentVariant = data.variant;
                    updateExperimentDisplay();
                }
            } catch (error) {
                displayResults('api-results', { endpoint: 'GET /variant/{experiment}', error: error.message }, true);
            }
        }

        async function testPostVariant() {
            try {
                const response = await fetch('/api/ab-testing/variant', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken()
                    },
                    body: JSON.stringify({
                        experiment: 'survey_red_buttons'
                    })
                });
                const data = await response.json();
                displayResults('api-results', { endpoint: 'POST /variant', response: data });
                
                if (data.success) {
                    currentVariant = data.variant;
                    updateExperimentDisplay();
                }
            } catch (error) {
                displayResults('api-results', { endpoint: 'POST /variant', error: error.message }, true);
            }
        }

        async function testTrackEvent() {
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
                            page: 'test_page',
                            timestamp: Date.now()
                        }
                    })
                });
                const data = await response.json();
                displayResults('api-results', { endpoint: 'POST /track', response: data });
            } catch (error) {
                displayResults('api-results', { endpoint: 'POST /track', error: error.message }, true);
            }
        }

        async function testRegisterDebug() {
            try {
                const response = await fetch('/api/ab-testing/register-debug', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken()
                    },
                    body: JSON.stringify({
                        experiment: 'survey_red_buttons',
                        variant: currentVariant,
                        source: 'test_page'
                    })
                });
                const data = await response.json();
                displayResults('api-results', { endpoint: 'POST /register-debug', response: data });
            } catch (error) {
                displayResults('api-results', { endpoint: 'POST /register-debug', error: error.message }, true);
            }
        }

        // Experiment simulation
        function updateExperimentDisplay() {
            document.getElementById('current-variant').textContent = currentVariant;
            
            const container = document.getElementById('button-container');
            
            if (currentVariant === 'red_buttons') {
                container.innerHTML = `
                    <button class="test-button red-button ab-test-red-button" onclick="simulateButtonClick('red_button')">
                        🔴 Red Submit Button (Variant: red_buttons)
                    </button>
                `;
            } else {
                container.innerHTML = `
                    <button class="test-button" onclick="simulateButtonClick('normal_button')">
                        🔵 Normal Submit Button (Variant: control)
                    </button>
                `;
            }
        }

        function simulateButtonClick(buttonType) {
            const event = {
                type: 'button_click',
                buttonType: buttonType,
                variant: currentVariant,
                timestamp: new Date().toISOString()
            };
            
            displayResults('experiment-results', { action: 'Button clicked', event });
            
            // Track the event
            if (typeof window.abtrack === 'function') {
                window.abtrack('survey_red_buttons', 'button_click', {
                    button_type: buttonType,
                    page: 'test_page'
                });
            }
        }

        // JavaScript helper tests
        async function testAbTrack() {
            if (typeof window.abtrack === 'function') {
                try {
                    await window.abtrack('survey_red_buttons', 'test_event', {
                        source: 'test_page',
                        variant: currentVariant
                    });
                    displayResults('js-results', { function: 'window.abtrack', status: 'Called successfully' });
                } catch (error) {
                    displayResults('js-results', { function: 'window.abtrack', error: error.message }, true);
                }
            } else {
                displayResults('js-results', { function: 'window.abtrack', error: 'Function not available' }, true);
            }
        }

        async function testAbRegisterDebug() {
            if (typeof window.abregisterDebug === 'function') {
                try {
                    await window.abregisterDebug('survey_red_buttons', currentVariant, 'test_page');
                    displayResults('js-results', { function: 'window.abregisterDebug', status: 'Called successfully' });
                } catch (error) {
                    displayResults('js-results', { function: 'window.abregisterDebug', error: error.message }, true);
                }
            } else {
                displayResults('js-results', { function: 'window.abregisterDebug', error: 'Function not available' }, true);
            }
        }

        function clearOverrides() {
            // Clear localStorage
            localStorage.removeItem('ab_test_overrides');
            localStorage.removeItem('ab_test_survey_red_buttons');
            
            // Clear cookies
            document.cookie = 'ab_test_override_survey_red_buttons=; path=/; expires=Thu, 01 Jan 1970 00:00:01 GMT;';
            document.cookie = 'js_ab_test_override_survey_red_buttons=; path=/; expires=Thu, 01 Jan 1970 00:00:01 GMT;';
            
            displayResults('js-results', { action: 'Cleared all overrides', status: 'Success' });
        }

        // Storage inspection
        function inspectStorage() {
            const cookies = document.cookie.split(';').reduce((acc, cookie) => {
                const [key, value] = cookie.trim().split('=');
                if (key && key.includes('ab_')) {
                    acc[key] = value;
                }
                return acc;
            }, {});
            
            const localStorage_items = {};
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (key && key.includes('ab_')) {
                    localStorage_items[key] = localStorage.getItem(key);
                }
            }
            
            const inspection = {
                cookies: cookies,
                localStorage: localStorage_items,
                csrf_token: getCsrfToken(),
                current_variant: currentVariant
            };
            
            displayResults('storage-results', inspection);
        }

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Try to get initial variant
            testGetVariant();
            
            // Listen for debug panel changes
            window.addEventListener('ab-test-variant-changed', function(event) {
                if (event.detail && event.detail.experiment === 'survey_red_buttons') {
                    currentVariant = event.detail.variant;
                    updateExperimentDisplay();
                    displayResults('experiment-results', { 
                        action: 'Variant changed via debug panel', 
                        new_variant: currentVariant 
                    });
                }
            });
        });
    </script>
</body>
</html>