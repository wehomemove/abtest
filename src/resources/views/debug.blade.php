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
<div id="ab-test-debug" style="position: fixed; bottom: 20px; right: 20px; background: #1e293b; color: white; border-radius: 8px; font-family: monospace; font-size: 12px; z-index: 999999; box-shadow: 0 4px 20px rgba(0,0,0,0.3); min-width: 300px; border: 1px solid #374151; padding: 16px;">
    <div style="display: flex; align-items: center; margin-bottom: 12px;">
        <span style="margin-right: 8px;">🧪</span>
        <span style="font-weight: bold;">A/B Testing Debugger</span>
        <button onclick="document.getElementById('ab-test-debug').style.display='none'" style="margin-left: auto; background: #ef4444; border: none; color: white; padding: 4px 8px; border-radius: 4px; cursor: pointer;">×</button>
    </div>
    
    @if(empty($experiments))
        <div style="text-align: center; color: #9ca3af; padding: 20px;">
            <p style="margin: 0;">No A/B tests active on this page</p>
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
            
            <div style="margin-bottom: 12px; padding: 8px; background: rgba(255,255,255,0.1); border-radius: 4px;">
                <div style="font-weight: bold; color: #10b981; margin-bottom: 4px;">{{ $experimentName }}</div>
                <div style="font-size: 10px; color: #d1d5db; margin-bottom: 8px;">
                    {{ $calls }} calls • 
                    Variant: <strong>{{ $variant }}</strong>
                </div>
                
                @if(is_array($variants) && count($variants) > 0)
                    <div style="margin-bottom: 8px;">
                        <div style="font-size: 10px; color: #d1d5db; margin-bottom: 4px;">Switch Variant:</div>
                        @foreach($variants as $variantName => $weight)
                            <button onclick="switchVariant('{{ $experimentName }}', '{{ $variantName }}')" 
                                    style="background: {{ $variantName === $variant ? '#10b981' : '#374151' }}; border: none; color: white; padding: 4px 8px; margin-right: 4px; border-radius: 4px; cursor: pointer; font-size: 10px;">
                                {{ $variantName }} ({{ $weight }}%)
                            </button>
                        @endforeach
                    </div>
                @endif
                
                @if($source)
                    <div style="font-size: 9px; color: #9ca3af;">Source: {{ $source }}</div>
                @endif
            </div>
        @endforeach
    @endif
    
    <div style="border-top: 1px solid #374151; padding-top: 8px; margin-top: 8px;">
        <button onclick="clearAllOverrides()" style="background: #ef4444; border: none; color: white; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 10px; margin-right: 8px;">Clear Overrides</button>
        <button onclick="location.reload()" style="background: #3b82f6; border: none; color: white; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 10px;">Reload</button>
    </div>
    
    @if($userInfo && is_array($userInfo))
        @php
            $userId = $userInfo['user_id'] ?? 'unknown';
            $source = $userInfo['source'] ?? 'unknown';
            $cookieExists = $userInfo['cookie_exists'] ?? false;
            $sessionExists = $userInfo['session_exists'] ?? false;
        @endphp
        
        <div style="font-size: 9px; color: rgba(255,255,255,0.5); text-align: center; margin-top: 8px; padding-top: 8px; border-top: 1px solid #374151;">
            <div style="margin-bottom: 4px;">
                User ID: <span style="font-family: monospace; color: #fbbf24;">{{ substr($userId, 0, 8) }}...</span>
            </div>
            <div style="margin-bottom: 4px;">
                Source: <span style="color: #10b981;">{{ ucfirst($source) }}</span>
            </div>
            <div style="margin-bottom: 4px; display: flex; justify-content: center; gap: 8px;">
                <span style="color: {{ $cookieExists ? '#10b981' : 'rgba(255,255,255,0.4)' }};">
                    🍪 {{ $cookieExists ? 'Cookie' : 'No Cookie' }}
                </span>
                <span style="color: {{ $sessionExists ? '#10b981' : 'rgba(255,255,255,0.4)' }};">
                    📝 {{ $sessionExists ? 'Session' : 'No Session' }}
                </span>
            </div>
        </div>
    @endif
</div>

<script>
window.switchVariant = function(experiment, variant) {
    document.cookie = 'ab_test_override_' + experiment + '=' + variant + '; path=/; max-age=3600';
    document.cookie = 'js_ab_test_override_' + experiment + '=' + variant + '; path=/; max-age=3600';
    
    var jsOverrides = JSON.parse(localStorage.getItem('ab_test_overrides') || '{}');
    jsOverrides[experiment] = variant;
    localStorage.setItem('ab_test_overrides', JSON.stringify(jsOverrides));
    
    setTimeout(function() { 
        location.reload(); 
    }, 300);
};

window.clearAllOverrides = function() {
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
        location.reload(); 
    }, 200);
};
</script>
@endif