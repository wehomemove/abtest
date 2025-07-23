@if(config('app.debug') && !empty($experiments))
<div id="ab-test-debug" style="position: fixed; bottom: 20px; right: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px; border-radius: 8px; font-family: 'SF Mono', Monaco, monospace; font-size: 12px; z-index: 999999; box-shadow: 0 4px 12px rgba(0,0,0,0.3); min-width: 200px;">
    <div style="display: flex; align-items: center; margin-bottom: 8px; font-weight: bold;">
        <span style="margin-right: 8px;">🧪</span>
        A/B Tests Active
        <button onclick="document.getElementById('ab-test-debug').style.display='none'" style="margin-left: auto; background: rgba(255,255,255,0.2); border: none; color: white; padding: 2px 6px; border-radius: 3px; cursor: pointer; font-size: 10px;">×</button>
    </div>
    
    @foreach($experiments as $experiment => $data)
        <div style="margin-bottom: 6px; padding: 6px; background: rgba(255,255,255,0.1); border-radius: 4px;">
            <div style="font-weight: 600; color: #FFD700;">{{ $experiment }}</div>
            <div style="display: flex; justify-content: space-between; margin-top: 2px;">
                <span style="color: #B8E6B8;">{{ $data['variant'] }}</span>
                <span style="color: #FFB6C1; font-size: 10px;">{{ $data['calls'] }} calls</span>
            </div>
        </div>
    @endforeach
    
    <div style="margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(255,255,255,0.2); font-size: 10px; color: rgba(255,255,255,0.7);">
        Session: {{ substr(session()->getId(), 0, 8) }}...
    </div>
</div>

<script>
// Make debug panel draggable
(function() {
    const panel = document.getElementById('ab-test-debug');
    let isDragging = false;
    let currentX, currentY, initialX, initialY;
    
    panel.addEventListener('mousedown', function(e) {
        if (e.target.tagName !== 'BUTTON') {
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
})();
</script>
@endif