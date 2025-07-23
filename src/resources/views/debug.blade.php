@if(config('app.debug') && !empty($experiments))
<div id="ab-test-debug" class="fixed bottom-5 right-5 bg-gradient-to-br from-slate-800 to-slate-700 text-white rounded-xl font-mono text-xs z-[999999] shadow-2xl min-w-80 max-w-96 border border-white/10 cursor-grab select-none">
    <!-- Header -->
    <div class="flex items-center px-4 py-3 border-b border-white/10 bg-white/5 rounded-t-xl">
        <span class="mr-2 text-sm">🧪</span>
        <span class="font-semibold flex-1">A/B Testing Debugger</span>
        <button onclick="toggleDebugPanel()" class="bg-white/10 hover:bg-white/20 border-0 text-white px-2 py-1 rounded cursor-pointer text-xs mr-2 transition-colors">△</button>
        <button onclick="document.getElementById('ab-test-debug').style.display='none'" class="bg-red-500/20 hover:bg-red-500/30 border-0 text-red-300 px-2 py-1 rounded cursor-pointer text-xs transition-colors">×</button>
    </div>
    
    <div id="debug-content" class="p-4">
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
            
            <div class="mb-4 p-3 bg-white/5 rounded-lg border-l-4 border-emerald-500">
                <!-- Experiment Header -->
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <div class="font-semibold text-emerald-400 text-sm">{{ $experimentName }}</div>
                        <div class="text-xs text-white/60">{{ $data['calls'] }} calls this request</div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="bg-emerald-500/20 text-emerald-300 px-2 py-1 rounded text-xs font-semibold">{{ $data['variant'] }}</span>
                    </div>
                </div>
                
                <!-- Variant Switcher -->
                <div class="mb-2">
                    <div class="text-xs text-white/70 mb-1">Switch Variant:</div>
                    <div class="flex gap-1 flex-wrap">
                        @foreach($variants as $variant => $weight)
                            <button 
                                onclick="switchVariant('{{ $experimentName }}', '{{ $variant }}')"
                                class="px-2 py-1 rounded cursor-pointer text-xs font-medium transition-all hover:scale-105 {{ $variant === $data['variant'] 
                                    ? 'bg-emerald-500/30 border border-emerald-500 text-emerald-300' 
                                    : 'bg-white/10 border border-white/20 text-white/80 hover:bg-white/20' }}">
                                {{ $variant }} ({{ $weight }}%)
                            </button>
                        @endforeach
                    </div>
                </div>
                
                <!-- Recent Events -->
                @if($recentEvents->count() > 0)
                    <div>
                        <div class="text-xs text-white/70 mb-1">Recent Events:</div>
                        @foreach($recentEvents as $event)
                            <div class="flex justify-between items-center px-2 py-1 bg-white/5 rounded mb-1">
                                <span class="text-xs text-amber-300">{{ $event->event_name }}</span>
                                <span class="text-xs text-white/50">{{ \Carbon\Carbon::parse($event->created_at)->diffForHumans() }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
        
        <!-- Actions -->
        <div class="mt-4 pt-3 border-t border-white/10">
            <div class="flex gap-2 mb-2">
                <button onclick="clearAllOverrides()" class="bg-red-500/20 border border-red-500/30 text-red-300 px-3 py-1.5 rounded cursor-pointer text-xs flex-1 hover:bg-red-500/30 transition-colors">Clear Overrides</button>
                <button onclick="refreshPage()" class="bg-blue-500/20 border border-blue-500/30 text-blue-300 px-3 py-1.5 rounded cursor-pointer text-xs flex-1 hover:bg-blue-500/30 transition-colors">Refresh</button>
            </div>
            <div class="text-xs text-white/50 text-center">
                Session: {{ substr(session()->getId(), 0, 8) }}... | User: {{ substr(session('ab_user_id', 'guest'), 0, 8) }}...
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
})();
</script>
@endif