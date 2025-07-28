<?php
/**
 * Standalone A/B Testing Package Test Script
 * 
 * Run this with: php -S localhost:8080 test-standalone.php
 * Then visit: http://localhost:8080
 */

// Simulate Laravel environment for testing
define('LARAVEL_START', microtime(true));

// Basic autoloader for our package
spl_autoload_register(function ($class) {
    $prefix = 'Homemove\\AbTesting\\';
    $base_dir = __DIR__ . '/src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Mock Laravel functions and classes
if (!function_exists('config')) {
    function config($key, $default = null) {
        $configs = [
            'app.debug' => true,
            'database.default' => 'sqlite'
        ];
        return $configs[$key] ?? $default;
    }
}

if (!function_exists('session')) {
    function session($key = null, $value = null) {
        static $session = [];
        
        if ($key === null) {
            return new class($session) {
                private $data;
                
                public function __construct(&$data) {
                    $this->data = &$data;
                }
                
                public function isStarted() { return true; }
                public function has($key) { return isset($this->data[$key]); }
                public function start() { return true; }
                public function save() { return true; }
            };
        }
        
        if ($value !== null) {
            $session[$key] = $value;
        }
        
        return $session[$key] ?? null;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token() {
        return 'test-csrf-token-' . uniqid();
    }
}

if (!function_exists('now')) {
    function now() {
        return date('Y-m-d H:i:s');
    }
}

if (!class_exists('Log')) {
    class Log {
        public static function info($message, $context = []) {
            error_log("INFO: $message " . json_encode($context));
        }
        
        public static function error($message, $context = []) {
            error_log("ERROR: $message " . json_encode($context));
        }
        
        public static function debug($message, $context = []) {
            error_log("DEBUG: $message " . json_encode($context));
        }
    }
}

// Mock database with in-memory storage
class MockDB {
    private static $data = [
        'ab_experiments' => [
            [
                'id' => 1,
                'name' => 'survey_red_buttons',
                'description' => 'Test red buttons on survey pages',
                'variants' => '{"control": 50, "red_buttons": 50}',
                'is_active' => true,
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => '2024-01-01 00:00:00'
            ]
        ],
        'ab_user_assignments' => [],
        'ab_events' => []
    ];
    
    public static function table($table) {
        return new MockQueryBuilder($table);
    }
}

class MockQueryBuilder {
    private $table;
    private $wheres = [];
    private $selects = ['*'];
    
    public function __construct($table) {
        $this->table = $table;
    }
    
    public function where($column, $operator = null, $value = null) {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        $this->wheres[] = [$column, $operator, $value];
        return $this;
    }
    
    public function first() {
        $data = MockDB::$data[$this->table] ?? [];
        
        foreach ($data as $row) {
            $matches = true;
            foreach ($this->wheres as [$column, $operator, $value]) {
                if ($operator === '=' && $row[$column] != $value) {
                    $matches = false;
                    break;
                }
            }
            if ($matches) {
                return (object) $row;
            }
        }
        
        return null;
    }
    
    public function count() {
        $data = MockDB::$data[$this->table] ?? [];
        $count = 0;
        
        foreach ($data as $row) {
            $matches = true;
            foreach ($this->wheres as [$column, $operator, $value]) {
                if ($operator === '=' && $row[$column] != $value) {
                    $matches = false;
                    break;
                }
            }
            if ($matches) {
                $count++;
            }
        }
        
        return $count;
    }
    
    public function insert($data) {
        $data['id'] = count(MockDB::$data[$this->table]) + 1;
        MockDB::$data[$this->table][] = $data;
        return true;
    }
}

// Handle routing
$request_uri = $_SERVER['REQUEST_URI'];
$request_method = $_SERVER['REQUEST_METHOD'];

// Parse the request
$path = parse_url($request_uri, PHP_URL_PATH);
$query = parse_url($request_uri, PHP_URL_QUERY);

// API Routes
if (strpos($path, '/api/ab-testing/') === 0) {
    header('Content-Type: application/json');
    
    // Mock AbTestService
    class MockAbTestService {
        public function variant($experiment, $userId = null) {
            // Check for override cookie first
            $overrideCookieName = "ab_test_override_{$experiment}";
            if (isset($_COOKIE[$overrideCookieName])) {
                return $_COOKIE[$overrideCookieName];
            }
            
            // Simple hash-based assignment for demo
            $userId = $userId ?: ($_COOKIE['ab_user_id'] ?? uniqid());
            $hash = crc32($experiment . $userId);
            return ($hash % 2 === 0) ? 'control' : 'red_buttons';
        }
        
        public function track($experiment, $userId = null, $event = 'conversion', $properties = []) {
            // Mock tracking
            return true;
        }
        
        public function registerJsDebugExperiment($experiment, $variant, $source = 'javascript') {
            // Mock debug registration
            return true;
        }
    }
    
    $service = new MockAbTestService();
    
    if ($path === '/api/ab-testing/variant/survey_red_buttons' && $request_method === 'GET') {
        $variant = $service->variant('survey_red_buttons');
        echo json_encode([
            'success' => true,
            'variant' => $variant,
            'experiment' => 'survey_red_buttons'
        ]);
        exit;
    }
    
    if ($path === '/api/ab-testing/variant' && $request_method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $variant = $service->variant($input['experiment']);
        echo json_encode([
            'success' => true,
            'variant' => $variant,
            'experiment' => $input['experiment']
        ]);
        exit;
    }
    
    if ($path === '/api/ab-testing/track' && $request_method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $service->track($input['experiment'], null, $input['event'], $input['properties']);
        echo json_encode([
            'success' => true,
            'message' => 'Event tracked successfully'
        ]);
        exit;
    }
    
    if ($path === '/api/ab-testing/register-debug' && $request_method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $service->registerJsDebugExperiment($input['experiment'], $input['variant'], $input['source']);
        echo json_encode([
            'success' => true,
            'message' => 'Debug experiment registered successfully'
        ]);
        exit;
    }
}

// Serve the test page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo csrf_token(); ?>">
    <title>🧪 Standalone A/B Testing Package Test</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        .test-section {
            background: #f8fafc;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            border-left: 4px solid #3b82f6;
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
            transition: all 0.2s;
        }
        .test-button:hover {
            background: #2563eb;
            transform: translateY(-1px);
        }
        .red-button {
            background: #ef4444 !important;
        }
        .red-button:hover {
            background: #dc2626 !important;
        }
        .results {
            background: #1f2937;
            color: #e5e7eb;
            padding: 15px;
            border-radius: 6px;
            font-family: 'SF Mono', Monaco, 'Cascadia Code', monospace;
            font-size: 13px;
            margin: 10px 0;
            white-space: pre-wrap;
            max-height: 300px;
            overflow-y: auto;
        }
        .status {
            padding: 12px;
            border-radius: 6px;
            margin: 10px 0;
            font-weight: 500;
        }
        .success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .info { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
        .warning { background: #fefce8; color: #a16207; border: 1px solid #fde68a; }
        h1 { 
            color: #1f2937; 
            text-align: center;
            margin-bottom: 10px;
        }
        h2 { 
            color: #374151; 
            border-bottom: 2px solid #e5e7eb; 
            padding-bottom: 10px;
            margin-top: 30px;
        }
        .subtitle {
            text-align: center;
            color: #6b7280;
            margin-bottom: 30px;
            font-size: 18px;
        }
        .experiment-display {
            text-align: center;
            margin: 20px 0;
            padding: 20px;
            background: white;
            border-radius: 8px;
            border: 2px dashed #d1d5db;
        }
        .variant-indicator {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
            margin-left: 10px;
        }
        .variant-control {
            background: #dbeafe;
            color: #1e40af;
        }
        .variant-red {
            background: #fee2e2;
            color: #991b1b;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 A/B Testing Package</h1>
        <div class="subtitle">Standalone Test Environment</div>
        
        <div class="info">
            <strong>🚀 Quick Start:</strong> This is a standalone test environment for the A/B testing package. 
            No Laravel installation required! Run with <code>php -S localhost:8080 test-standalone.php</code>
        </div>
        
        <div class="test-section">
            <h2>📊 Live A/B Test Experiment</h2>
            <div id="experiment-status" class="status info">
                Experiment: <strong>survey_red_buttons</strong>
                <span id="variant-indicator" class="variant-indicator">Loading...</span>
            </div>
            
            <div class="experiment-display">
                <div id="button-container">
                    <div class="info">🔄 Loading experiment variant...</div>
                </div>
            </div>
            
            <div id="experiment-results" class="results"></div>
        </div>

        <div class="test-section">
            <h2>🔌 API Endpoint Tests</h2>
            <p>Test all the package's API endpoints to ensure they're working correctly:</p>
            
            <button class="test-button" onclick="testGetVariant()">
                📥 GET /api/ab-testing/variant/{experiment}
            </button>
            
            <button class="test-button" onclick="testPostVariant()">
                📤 POST /api/ab-testing/variant
            </button>
            
            <button class="test-button" onclick="testTrackEvent()">
                📈 POST /api/ab-testing/track
            </button>
            
            <button class="test-button" onclick="testRegisterDebug()">
                🐛 POST /api/ab-testing/register-debug
            </button>
            
            <div id="api-results" class="results"></div>
        </div>

        <div class="test-section">
            <h2>⚡ JavaScript Helper Tests</h2>
            <p>Test the auto-injected JavaScript helpers:</p>
            
            <button class="test-button" onclick="testAbTrack()">
                🎯 Test window.abtrack()
            </button>
            
            <button class="test-button" onclick="testAbRegisterDebug()">
                🔧 Test window.abregisterDebug()
            </button>
            
            <button class="test-button" onclick="simulateAutoDetection()">
                🤖 Simulate Auto-Detection
            </button>
            
            <div id="js-results" class="results"></div>
        </div>

        <div class="test-section">
            <h2>🗄️ Storage & Debug Tools</h2>
            
            <button class="test-button" onclick="inspectStorage()">
                🔍 Inspect Storage
            </button>
            
            <button class="test-button" onclick="clearOverrides()">
                🗑️ Clear Overrides
            </button>
            
            <button class="test-button" onclick="switchToVariant('control')">
                🔵 Switch to Control
            </button>
            
            <button class="test-button" onclick="switchToVariant('red_buttons')">
                🔴 Switch to Red Buttons
            </button>
            
            <div id="storage-results" class="results"></div>
        </div>
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
            const status = isError ? '❌ ERROR' : '✅ SUCCESS';
            const result = `[${timestamp}] ${status}:\n${JSON.stringify(data, null, 2)}\n\n${element.textContent}`;
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
                            page: 'standalone_test',
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
                        source: 'standalone_test'
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
            const indicator = document.getElementById('variant-indicator');
            const container = document.getElementById('button-container');
            
            if (currentVariant === 'red_buttons') {
                indicator.textContent = 'red_buttons';
                indicator.className = 'variant-indicator variant-red';
                
                container.innerHTML = `
                    <button class="test-button red-button ab-test-red-button" onclick="simulateButtonClick('red_button')">
                        🔴 Red Submit Button
                    </button>
                    <div style="margin-top: 10px; color: #991b1b; font-weight: 500;">
                        ✨ You're seeing the RED BUTTONS variant!
                    </div>
                `;
            } else {
                indicator.textContent = 'control';
                indicator.className = 'variant-indicator variant-control';
                
                container.innerHTML = `
                    <button class="test-button" onclick="simulateButtonClick('normal_button')">
                        🔵 Normal Submit Button
                    </button>
                    <div style="margin-top: 10px; color: #1e40af; font-weight: 500;">
                        📋 You're seeing the CONTROL variant
                    </div>
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
            
            displayResults('experiment-results', { 
                action: '🎯 Button clicked!', 
                event,
                note: 'This would trigger conversion tracking in a real implementation'
            });
            
            // Auto-track the event
            testTrackEvent();
        }

        // JavaScript helper tests
        async function testAbTrack() {
            const mockData = {
                experiment: 'survey_red_buttons',
                event: 'test_event',
                properties: { source: 'standalone_test', variant: currentVariant }
            };
            
            displayResults('js-results', { 
                function: 'window.abtrack', 
                note: 'Simulated call (would call API in real implementation)',
                data: mockData 
            });
        }

        async function testAbRegisterDebug() {
            const mockData = {
                experiment: 'survey_red_buttons',
                variant: currentVariant,
                source: 'standalone_test'
            };
            
            displayResults('js-results', { 
                function: 'window.abregisterDebug', 
                note: 'Simulated call (would register with debug panel)',
                data: mockData 
            });
        }

        function simulateAutoDetection() {
            displayResults('js-results', { 
                action: 'Auto-detection simulation',
                detected: 'ab-test-red-button elements found: ' + document.querySelectorAll('.ab-test-red-button').length,
                note: 'This is how the package automatically detects A/B tests in the DOM'
            });
        }

        // Storage and debug tools
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
                current_variant: currentVariant,
                user_agent: navigator.userAgent.substring(0, 50) + '...'
            };
            
            displayResults('storage-results', inspection);
        }

        function clearOverrides() {
            // Clear localStorage
            localStorage.removeItem('ab_test_overrides');
            localStorage.removeItem('ab_test_survey_red_buttons');
            
            // Clear cookies
            document.cookie = 'ab_test_override_survey_red_buttons=; path=/; expires=Thu, 01 Jan 1970 00:00:01 GMT;';
            document.cookie = 'js_ab_test_override_survey_red_buttons=; path=/; expires=Thu, 01 Jan 1970 00:00:01 GMT;';
            
            displayResults('storage-results', { action: 'Cleared all overrides', status: 'Success' });
            
            // Refresh variant
            setTimeout(testGetVariant, 500);
        }

        function switchToVariant(variant) {
            // Set override cookie (simulates debug panel functionality)
            document.cookie = `ab_test_override_survey_red_buttons=${variant}; path=/; max-age=3600`;
            
            displayResults('storage-results', { 
                action: `🔄 Switching to variant: ${variant}`,
                note: 'This simulates clicking a variant button in the debug panel'
            });
            
            // Refresh to get new variant (simulates page reload after debug panel click)
            setTimeout(() => {
                testGetVariant();
            }, 500);
        }

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            displayResults('experiment-results', { 
                message: '🎉 Standalone A/B Testing Package initialized!',
                note: 'Click the API test buttons to see the package in action'
            });
            
            // Get initial variant
            testGetVariant();
        });
    </script>
</body>
</html>