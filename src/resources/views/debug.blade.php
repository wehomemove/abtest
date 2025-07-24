@if(config('app.debug') && !empty($experiments))
<div id="ab-test-debug" style="position: fixed; bottom: 20px; right: 20px; background: linear-gradient(135deg, #1e293b 0%, #334155 100%); color: white; border-radius: 12px; font-family: ui-monospace, 'SF Mono', Monaco, monospace; font-size: 12px; z-index: 999999; box-shadow: 0 8px 25px rgba(0,0,0,0.4); min-width: 320px; max-width: 400px; border: 1px solid rgba(255,255,255,0.1); cursor: grab; user-select: none;">
    <!-- Header -->
    <div style="display: flex; align-items: center; padding: 12px 16px; border-bottom: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.05); border-radius: 12px 12px 0 0;">
        <span style="margin-right: 8px; font-size: 14px;">🧪</span>
        <span style="font-weight: 600; flex: 1;">A/B Testing Debugger</span>
        <button onclick="toggleDebugPanel()" style="background: rgba(255,255,255,0.1); border: none; color: white; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 10px; margin-right: 8px; transition: background 0.2s;">△</button>
        <button onclick="document.getElementById('ab-test-debug').style.display='none'" style="background: rgba(239,68,68,0.2); border: none; color: #fca5a5; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 10px; transition: background 0.2s;">×</button>
    </div>

    <div id="debug-content" style="padding: 16px;">
        @if(empty($experiments))
            <div style="text-align: center; color: rgba(255,255,255,0.7); padding: 20px;">
                <p style="margin: 0; font-size: 11px;">No A/B tests active on this page</p>
                <p style="margin: 4px 0 0 0; font-size: 9px;">Use @variant() directive to activate experiments</p>
            </div>
        @endif
        
        @foreach($experiments as $experimentName => $data)
            @php
                $experiment = DB::table('ab_experiments')->where('name', $experimentName)->first();
                $variants = $experiment ? json_decode($experiment->variants, true) : [];
                $recentEvents = collect();

                if ($experiment) {
                    $recentEvents = DB::table('ab_events')
                        ->where('experiment_id', $experiment->id)
                        ->where('user_id', session('ab_user_id'))
                        ->orderBy('created_at', 'desc')
                        ->limit(10)
                        ->get();
                }
            @endphp

            <div style="margin-bottom: 16px; padding: 12px; background: rgba(255,255,255,0.05); border-radius: 8px; border-left: 4px solid #10b981;">
                <!-- Experiment Header -->
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                    <div>
                        <div style="font-weight: 600; color: #10b981; font-size: 13px;">{{ $experimentName }}</div>
                        <div style="font-size: 10px; color: rgba(255,255,255,0.6);">{{ $data['calls'] }} calls this request</div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="background: rgba(16,185,129,0.2); color: #6ee7b7; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 600;">{{ $data['variant'] }}</span>
                    </div>
                </div>

                <!-- Variant Switcher -->
                <div style="margin-bottom: 8px;">
                    <div style="font-size: 10px; color: rgba(255,255,255,0.7); margin-bottom: 4px;">Switch Variant:</div>
                    <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                        @foreach($variants as $variant => $weight)
                            <button
                                onclick="switchVariant('{{ $experimentName }}', '{{ $variant }}')"
                                style="background: {{ $variant === $data['variant'] ? 'rgba(16,185,129,0.3)' : 'rgba(255,255,255,0.1)' }};
                                       border: 1px solid {{ $variant === $data['variant'] ? '#10b981' : 'rgba(255,255,255,0.2)' }};
                                       color: {{ $variant === $data['variant'] ? '#6ee7b7' : 'rgba(255,255,255,0.8)' }};
                                       padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 10px; font-weight: 500; transition: all 0.2s;">
                                {{ $variant }} ({{ $weight }}%)
                            </button>
                        @endforeach
                    </div>
                </div>

                <!-- Recent Events -->
                @if($recentEvents->count() > 0)
                    <div>
                        <div style="font-size: 10px; color: rgba(255,255,255,0.7); margin-bottom: 4px;">Recent Events:</div>
                        @foreach($recentEvents as $event)
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 3px 6px; background: rgba(255,255,255,0.05); border-radius: 4px; margin-bottom: 2px;">
                                <span style="font-size: 10px; color: #fbbf24;">{{ $event->event_name }}</span>
                                <span style="font-size: 9px; color: rgba(255,255,255,0.5);">{{ \Carbon\Carbon::parse($event->created_at)->diffForHumans() }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach

        <!-- Actions -->
        <div style="margin-top: 16px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.1);">
            <div style="display: flex; gap: 8px; margin-bottom: 8px;">
                <button onclick="clearAllOverrides()" style="background: rgba(239,68,68,0.2); border: 1px solid rgba(239,68,68,0.3); color: #fca5a5; padding: 6px 10px; border-radius: 4px; cursor: pointer; font-size: 10px; flex: 1; transition: background 0.2s;">Clear Overrides</button>
                <button onclick="refreshDebugPanel()" style="background: rgba(34,197,94,0.2); border: 1px solid rgba(34,197,94,0.3); color: #86efac; padding: 6px 10px; border-radius: 4px; cursor: pointer; font-size: 10px; flex: 1; transition: background 0.2s;">Refresh Data</button>
                <button onclick="refreshPage()" style="background: rgba(59,130,246,0.2); border: 1px solid rgba(59,130,246,0.3); color: #93c5fd; padding: 6px 10px; border-radius: 4px; cursor: pointer; font-size: 10px; flex: 1; transition: background 0.2s;">Reload Page</button>
            </div>
            <div style="font-size: 9px; color: rgba(255,255,255,0.5); text-align: center;">
                <div style="margin-bottom: 4px;">
                    <span style="color: rgba(255,255,255,0.7);">User ID:</span> 
                    <span style="font-family: monospace; color: #fbbf24;">{{ substr($userInfo['user_id'], 0, 8) }}...</span>
                </div>
                <div style="margin-bottom: 4px; display: flex; justify-content: center; gap: 8px;">
                    <span style="color: {{ $userInfo['source'] === 'cookie' ? '#10b981' : 'rgba(255,255,255,0.4)' }};">
                        🍪 {{ $userInfo['cookie_exists'] ? 'Cookie' : 'No Cookie' }}
                    </span>
                    <span style="color: {{ $userInfo['source'] === 'session' ? '#10b981' : 'rgba(255,255,255,0.4)' }};">
                        📝 {{ $userInfo['session_exists'] ? 'Session' : 'No Session' }}
                    </span>
                </div>
                <div style="margin-bottom: 4px;">
                    <span style="color: #10b981; font-weight: 600;">Source: {{ ucfirst($userInfo['source']) }}</span>
                </div>
                <button onclick="clearAbSession()" style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: rgba(255,255,255,0.8); padding: 2px 6px; border-radius: 3px; cursor: pointer; font-size: 8px; margin-top: 4px;">Reset A/B Session</button>
            </div>
        </div>
    </div>
</div>

<script>
// Enhanced A/B Testing Debug Panel
(function() {
    const panel = document.getElementById('ab-test-debug');
    let isDragging = false;
    let currentX, currentY, initialX, initialY;
    let isCollapsed = false;

    // Make draggable
    panel.addEventListener('mousedown', function(e) {
        if (!e.target.closest('button') && !e.target.closest('select')) {
            isDragging = true;
            initialX = e.clientX - panel.offsetLeft;
            initialY = e.clientY - panel.offsetTop;
            panel.style.cursor = 'grabbing';
        }
    });

    document.addEventListener('mousemove', function(e) {
        if (isDragging) {
            currentX = e.clientX - initialX;
            currentY = e.clientY - initialY;
            panel.style.left = currentX + 'px';
            panel.style.top = currentY + 'px';
            panel.style.right = 'auto';
            panel.style.bottom = 'auto';
        }
    });

    document.addEventListener('mouseup', function() {
        isDragging = false;
        panel.style.cursor = 'grab';
    });

    panel.style.cursor = 'grab';

    // Functions for the enhanced debug panel
    window.toggleDebugPanel = function() {
        const content = document.getElementById('debug-content');
        if (isCollapsed) {
            content.style.display = 'block';
            isCollapsed = false;
        } else {
            content.style.display = 'none';
            isCollapsed = true;
        }
    };

    window.switchVariant = function(experiment, variant) {
        // Set override cookie
        document.cookie = `ab_test_override_${experiment}=${variant}; path=/; max-age=3600`;

        // Visual feedback
        const button = event.target;
        button.textContent = '✓ Set!';
        button.className = button.className.replace(/bg-\w+\/\d+/g, 'bg-emerald-500/40');

        setTimeout(() => {
            location.reload();
        }, 500);
    };

    window.clearAllOverrides = function() {
        // Clear all ab_test_override cookies
        document.cookie.split(";").forEach(function(c) {
            const cookie = c.trim();
            if (cookie.indexOf('ab_test_override_') === 0) {
                const cookieName = cookie.split('=')[0];
                document.cookie = cookieName + '=; path=/; expires=Thu, 01 Jan 1970 00:00:01 GMT;';
            }
        });

        setTimeout(() => {
            location.reload();
        }, 200);
    };

    window.refreshPage = function() {
        location.reload();
    };

    window.refreshDebugPanel = function() {
        // Create a route to get debug info if it doesn't exist
        fetch('/debug-info', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
        })
        .then(response => response.json())
        .then(data => {
            console.log('Debug data refreshed:', data);
            if (data.debug_experiments && Object.keys(data.debug_experiments).length > 0) {
                // If we have experiments now, reload the page to show them
                location.reload();
            } else {
                // Update the "no experiments" message
                const content = document.getElementById('debug-content');
                if (content && content.innerHTML.includes('No A/B tests active')) {
                    // Already showing the right message
                    console.log('No experiments found');
                }
            }
        })
        .catch(error => {
            console.error('Failed to refresh debug panel:', error);
            // Fallback to page reload
            location.reload();
        });
    };

    window.clearAbSession = function() {
        // Clear A/B testing session via AJAX
        fetch('/ab-testing/clear-session', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
        }).then(() => {
            location.reload();
        }).catch(() => {
            // Fallback: try to clear via browser
            sessionStorage.clear();
            location.reload();
        });
    };
})();
</script>
@endif