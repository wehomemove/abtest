@php
    // Ensure all required variables exist with defaults
    $experiments = $experiments ?? [];
    $userInfo = $userInfo ?? [
        'user_id' => 'unknown',
        'source' => 'unknown',
        'cookie_exists' => false,
        'session_exists' => false
    ];
@endphp

@if(config('app.debug'))
<script>
// Immediately define global functions to ensure they're available for onclick handlers
(function() {
    window.toggleDebugger = function() {
        const fullDebugger = document.getElementById('ab-test-debug');
        const collapsedDebugger = document.getElementById('ab-test-debug-collapsed');
        
        if (!fullDebugger || !collapsedDebugger) return;
        
        if (fullDebugger.style.display === 'none') {
            // Show full debugger, hide collapsed icon
            fullDebugger.style.display = 'block';
            collapsedDebugger.style.display = 'none';
        } else {
            // Hide full debugger, show collapsed icon
            fullDebugger.style.display = 'none';
            collapsedDebugger.style.display = 'flex';
        }
    };
})();
</script>
<style>
@keyframes blink {
    0%, 50% { opacity: 1; }
    51%, 100% { opacity: 0.3; }
}
</style>
<div id="ab-test-debug-wrapper" style="position: fixed; bottom: 20px; right: 20px; z-index: 999999;">
    <div id="ab-test-debug-collapsed" style="display: none; width: 40px; height: 40px; background: linear-gradient(135deg, #1e293b 0%, #374151 100%); border-radius: 50%; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); position: relative;" onclick="toggleDebugger()">
        <span style="font-size: 20px;">🐛</span>
        <div style="position: absolute; top: 2px; right: 2px; width: 8px; height: 8px; border-radius: 50%; background: {{ empty($experiments) ? '#ef4444' : '#10b981' }}; animation: blink 1s infinite; box-shadow: 0 0 4px {{ empty($experiments) ? '#ef4444' : '#10b981' }};"></div>
    </div>
    
    <div id="ab-test-debug" style="background: linear-gradient(135deg, #1e293b 0%, #374151 100%); color: white; border-radius: 12px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 13px; box-shadow: 0 10px 40px rgba(0,0,0,0.4), 0 0 0 1px rgba(255,255,255,0.1); min-width: 320px; max-width: 400px; backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1);">
        <div style="display: flex; align-items: center; padding: 16px 20px 12px 20px; border-bottom: 1px solid rgba(255,255,255,0.1);">
            <div style="width: 24px; height: 24px; background: linear-gradient(45deg, #10b981, #059669); border-radius: 6px; display: flex; align-items: center; justify-content: center; margin-right: 12px; font-size: 14px;">🧪</div>
            <span style="font-weight: 600; font-size: 14px; color: #f8fafc;">A/B Testing Debugger</span>
            <button onclick="toggleDebugger()" style="margin-left: auto; background: rgba(239, 68, 68, 0.1); border: none; color: #f87171; padding: 6px 8px; border-radius: 6px; cursor: pointer; font-size: 16px; line-height: 1; transition: all 0.2s; border: 1px solid rgba(239, 68, 68, 0.2);" onmouseover="this.style.background='rgba(239, 68, 68, 0.2)'" onmouseout="this.style.background='rgba(239, 68, 68, 0.1)'">×</button>
        </div>
    
    <div style="padding: 16px 20px; max-height: 400px; overflow-y: auto;">
    
        @if(empty($experiments))
            <div style="text-align: center; color: #94a3b8; padding: 32px 20px;">
                <div style="width: 48px; height: 48px; background: rgba(148, 163, 184, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; font-size: 20px;">🔬</div>
                <p style="margin: 0; font-weight: 500; margin-bottom: 4px;">No A/B tests active</p>
                <p style="margin: 0; font-size: 12px; opacity: 0.7;">Tests will appear here when running</p>
            </div>
        @else
            @foreach($experiments as $experimentName => $experimentData)
                @php
                    // Ensure experiment data has all required fields
                    $experimentData = is_array($experimentData) ? $experimentData : [];
                    $calls = $experimentData['calls'] ?? 0;
                    $variant = $experimentData['variant'] ?? 'unknown';
                    $variants = $experimentData['variants'] ?? [];
                    $source = $experimentData['source'] ?? null;
                @endphp
                
                <div style="margin-bottom: 16px; padding: 16px; background: rgba(255,255,255,0.05); border-radius: 8px; border: 1px solid rgba(255,255,255,0.1);">
                    <div style="display: flex; align-items: center; justify-content: between; margin-bottom: 12px;">
                        <div style="flex: 1;">
                            <div style="font-weight: 600; color: #10b981; margin-bottom: 4px; font-size: 14px;">{{ $experimentName }}</div>
                            <div style="font-size: 12px; color: #cbd5e1; display: flex; align-items: center; gap: 12px;">
                                <span style="display: flex; align-items: center; gap: 4px;">
                                    <div style="width: 6px; height: 6px; background: #3b82f6; border-radius: 50%;"></div>
                                    {{ $calls }} calls
                                </span>
                                <span style="display: flex; align-items: center; gap: 4px;">
                                    <div style="width: 6px; height: 6px; background: #10b981; border-radius: 50%;"></div>
                                    <strong>{{ $variant }}</strong>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    @if(is_array($variants) && count($variants) > 0)
                        <div style="margin-bottom: 12px;">
                            <div style="font-size: 11px; color: #94a3b8; margin-bottom: 8px; font-weight: 500;">Switch Variant:</div>
                            <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                                @foreach($variants as $variantName => $weight)
                                    <button onclick="switchVariant('{{ $experimentName }}', '{{ $variantName }}')" 
                                            style="background: {{ $variantName === $variant ? 'linear-gradient(45deg, #10b981, #059669)' : 'rgba(255,255,255,0.1)' }}; border: {{ $variantName === $variant ? 'none' : '1px solid rgba(255,255,255,0.2)' }}; color: white; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 11px; font-weight: 500; transition: all 0.2s; box-shadow: {{ $variantName === $variant ? '0 2px 8px rgba(16, 185, 129, 0.3)' : 'none' }};"
                                            onmouseover="if ('{{ $variantName }}' !== '{{ $variant }}') { this.style.background='rgba(255,255,255,0.15)'; this.style.transform='translateY(-1px)'; }"
                                            onmouseout="if ('{{ $variantName }}' !== '{{ $variant }}') { this.style.background='rgba(255,255,255,0.1)'; this.style.transform='translateY(0)'; }">
                                        {{ $variantName }} <span style="opacity: 0.7;">({{ $weight }}%)</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    
                    @if($source)
                        <div style="font-size: 11px; color: #64748b; display: flex; align-items: center; gap: 6px; padding-top: 8px; border-top: 1px solid rgba(255,255,255,0.05);">
                            <span style="opacity: 0.7;">Source:</span>
                            <span style="color: #f1f5f9; font-weight: 500;">{{ ucfirst($source) }}</span>
                        </div>
                    @endif
                </div>
            @endforeach
        @endif
    </div>
    
    <div style="padding: 16px 20px; border-top: 1px solid rgba(255,255,255,0.1); display: flex; gap: 8px;">
        <button onclick="clearAllOverrides()" 
                style="flex: 1; background: linear-gradient(45deg, #ef4444, #dc2626); border: none; color: white; padding: 10px 16px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 500; transition: all 0.2s; box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);"
                onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 12px rgba(239, 68, 68, 0.4)';"
                onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(239, 68, 68, 0.3)';">
            Clear Overrides
        </button>
        <button onclick="location.reload()" 
                style="flex: 1; background: linear-gradient(45deg, #3b82f6, #2563eb); border: none; color: white; padding: 10px 16px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 500; transition: all 0.2s; box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);"
                onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 12px rgba(59, 130, 246, 0.4)';"
                onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(59, 130, 246, 0.3)';">
            Reload Page
        </button>
    </div>
    
    @if($userInfo && is_array($userInfo))
        @php
            $userId = $userInfo['user_id'] ?? 'unknown';
            $source = $userInfo['source'] ?? 'unknown';
            $cookieExists = $userInfo['cookie_exists'] ?? false;
            $sessionExists = $userInfo['session_exists'] ?? false;
        @endphp
        
        <div style="padding: 16px 20px; border-top: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.2);">
            <div style="font-size: 11px; color: #94a3b8; margin-bottom: 12px; font-weight: 500;">Session Info</div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div style="background: rgba(255,255,255,0.05); padding: 8px 12px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.1);">
                    <div style="font-size: 10px; color: #64748b; margin-bottom: 2px;">User ID</div>
                    <div style="font-family: monospace; color: #fbbf24; font-size: 11px; font-weight: 600;">{{ substr($userId, 0, 8) }}...</div>
                </div>
                <div style="background: rgba(255,255,255,0.05); padding: 8px 12px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.1);">
                    <div style="font-size: 10px; color: #64748b; margin-bottom: 2px;">Source</div>
                    <div style="color: #10b981; font-size: 11px; font-weight: 600;">{{ ucfirst($source) }}</div>
                </div>
            </div>
            <div style="display: flex; justify-content: center; gap: 16px;">
                <div style="display: flex; align-items: center; gap: 6px; padding: 6px 10px; background: {{ $cookieExists ? 'rgba(16, 185, 129, 0.1)' : 'rgba(148, 163, 184, 0.1)' }}; border-radius: 6px; border: 1px solid {{ $cookieExists ? 'rgba(16, 185, 129, 0.2)' : 'rgba(148, 163, 184, 0.2)' }};">
                    <span style="font-size: 12px;">🍪</span>
                    <span style="font-size: 10px; color: {{ $cookieExists ? '#10b981' : '#94a3b8' }}; font-weight: 500;">{{ $cookieExists ? 'Cookie' : 'No Cookie' }}</span>
                </div>
                <div style="display: flex; align-items: center; gap: 6px; padding: 6px 10px; background: {{ $sessionExists ? 'rgba(16, 185, 129, 0.1)' : 'rgba(148, 163, 184, 0.1)' }}; border-radius: 6px; border: 1px solid {{ $sessionExists ? 'rgba(16, 185, 129, 0.2)' : 'rgba(148, 163, 184, 0.2)' }};">
                    <span style="font-size: 12px;">📝</span>
                    <span style="font-size: 10px; color: {{ $sessionExists ? '#10b981' : '#94a3b8' }}; font-weight: 500;">{{ $sessionExists ? 'Session' : 'No Session' }}</span>
                </div>
            </div>
        </div>
    @endif
    </div>
</div>

<script>
// Additional A/B test functions
window.switchVariant = function(experiment, variant) {
    // Set override cookies for Laravel A/B tests
    document.cookie = 'ab_test_override_' + experiment + '=' + variant + '; path=/; max-age=3600';
    document.cookie = 'js_ab_test_override_' + experiment + '=' + variant + '; path=/; max-age=3600';
    
    // Set localStorage overrides for JavaScript A/B tests
    var jsOverrides = JSON.parse(localStorage.getItem('ab_test_overrides') || '{}');
    jsOverrides[experiment] = variant;
    localStorage.setItem('ab_test_overrides', JSON.stringify(jsOverrides));
    
    // For Vue components - set individual localStorage for the experiment
    localStorage.setItem('ab_test_' + experiment, variant);
    
    // Enhanced visual feedback
    var button = event.target;
    var originalText = button.textContent;
    var originalStyle = button.style.cssText;
    
    button.textContent = '✓ Switching...';
    button.style.background = 'linear-gradient(45deg, #10b981, #059669)';
    button.style.transform = 'scale(0.95)';
    button.style.boxShadow = '0 2px 8px rgba(16, 185, 129, 0.4)';
    button.style.pointerEvents = 'none';
    
    // Notify any JavaScript A/B test listeners
    if (window.dispatchEvent) {
        window.dispatchEvent(new CustomEvent('ab-test-variant-changed', {
            detail: { experiment: experiment, variant: variant }
        }));
    }
    
    // Force reload to apply changes with improved UX
    setTimeout(function() { 
        button.textContent = '✓ Reloading...';
        setTimeout(function() {
            location.reload(); 
        }, 200);
    }, 800);
};

window.clearAllOverrides = function() {
    // Enhanced visual feedback
    var button = event.target;
    var originalText = button.textContent;
    button.textContent = '✓ Clearing...';
    button.style.transform = 'scale(0.95)';
    button.style.pointerEvents = 'none';
    
    var cookies = document.cookie.split(";");
    for (var i = 0; i < cookies.length; i++) {
        var cookie = cookies[i].trim();
        if (cookie.indexOf('ab_test_override_') === 0 || cookie.indexOf('js_ab_test_override_') === 0) {
            var cookieName = cookie.split('=')[0];
            document.cookie = cookieName + '=; path=/; expires=Thu, 01 Jan 1970 00:00:01 GMT;';
        }
    }
    localStorage.removeItem('ab_test_overrides');
    
    setTimeout(function() { 
        button.textContent = '✓ Reloading...';
        setTimeout(function() {
            location.reload(); 
        }, 200);
    }, 600);
};
</script>
@endif