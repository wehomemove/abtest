@extends('ab-testing::dashboard.layout')

@section('title', $experiment->name)

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h2 class="text-2xl font-bold text-gray-900">{{ $experiment->name }}</h2>
        <p class="text-gray-600">{{ $experiment->description }}</p>
    </div>
    <div class="flex items-center space-x-3">
        <form action="{{ route('ab-testing.dashboard.toggle', $experiment) }}" method="POST" class="inline">
            @csrf
            @method('PATCH')
            <button type="submit" class="px-4 py-2 text-sm font-medium rounded-md {{ $experiment->is_active ? 'bg-red-100 text-red-800 hover:bg-red-200' : 'bg-green-100 text-green-800 hover:bg-green-200' }}">
                {{ $experiment->is_active ? 'Pause' : 'Activate' }}
            </button>
        </form>
        <a href="{{ route('ab-testing.dashboard.edit', $experiment) }}" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
            Edit
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white p-6 rounded-lg shadow hover:shadow-lg transition-shadow duration-200">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-sm font-medium text-gray-500">Total Participants</h3>
                <p class="text-2xl font-bold text-gray-900">{{ number_format($stats['total_assignments']) }}</p>
                <p class="text-xs text-green-500 mt-1" id="participants-trend">
                    <i class="fas fa-arrow-up"></i> +<span id="recent-participants">{{ $stats['today_assignments'] ?? 0 }}</span> today
                </p>
            </div>
            <div class="text-blue-500">
                <i class="fas fa-users text-2xl"></i>
            </div>
        </div>
    </div>
    <div class="bg-white p-6 rounded-lg shadow hover:shadow-lg transition-shadow duration-200">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-sm font-medium text-gray-500">Total Conversions</h3>
                <p class="text-2xl font-bold text-gray-900">{{ number_format($stats['total_conversions']) }}</p>
                <p class="text-xs text-green-500 mt-1" id="conversions-trend">
                    <i class="fas fa-arrow-up"></i> +<span id="recent-conversions">{{ $stats['today_conversions'] ?? 0 }}</span> today
                </p>
            </div>
            <div class="text-green-500">
                <i class="fas fa-chart-line text-2xl"></i>
            </div>
        </div>
    </div>
    <div class="bg-white p-6 rounded-lg shadow hover:shadow-lg transition-shadow duration-200">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-sm font-medium text-gray-500">Overall Rate</h3>
                <p class="text-2xl font-bold text-gray-900">
                    {{ $stats['total_assignments'] > 0 ? number_format(($stats['total_conversions'] / $stats['total_assignments']) * 100, 2) : 0 }}%
                </p>
                <p class="text-xs text-green-500 mt-1" id="rate-trend">
                    <i class="fas fa-arrow-up"></i> +<span id="rate-change">2.3</span>% vs yesterday
                </p>
            </div>
            <div class="text-purple-500">
                <i class="fas fa-percentage text-2xl"></i>
            </div>
        </div>
    </div>
    <div class="bg-white p-6 rounded-lg shadow hover:shadow-lg transition-shadow duration-200">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-sm font-medium text-gray-500">Statistical Significance</h3>
                <p class="text-2xl font-bold text-yellow-600">85%</p>
                <p class="text-xs text-yellow-500 mt-1" id="significance-status">
                    <i class="fas fa-info-circle"></i> <span id="significance-message">Need more data</span>
                </p>
            </div>
            <div class="text-yellow-500">
                <i class="fas fa-flask text-2xl"></i>
            </div>
        </div>
    </div>
</div>

<div class="bg-white shadow rounded-lg mb-8">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-medium text-gray-900">Variant Performance</h3>
    </div>
    <div class="p-6">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-left text-sm font-medium text-gray-500">
                        <th class="pb-3">Variant</th>
                        <th class="pb-3">Traffic %</th>
                        <th class="pb-3">Participants</th>
                        <th class="pb-3">Conversions</th>
                        <th class="pb-3">Conv. Rate</th>
                        <th class="pb-3">Lift</th>
                    </tr>
                </thead>
                <tbody class="space-y-2">
                    @php
                        $controlRate = $stats['variants']['control']['conversion_rate'] ?? 0;
                    @endphp
                    @foreach($stats['variants'] as $variant => $data)
                        <tr class="border-t">
                            <td class="py-3">
                                <span class="font-medium">{{ $variant }}</span>
                                @if($variant === 'control')
                                    <span class="ml-2 px-2 py-1 text-xs bg-gray-100 text-gray-600 rounded">Control</span>
                                @endif
                            </td>
                            <td class="py-3">{{ $data['weight'] }}%</td>
                            <td class="py-3">{{ number_format($data['assigned']) }}</td>
                            <td class="py-3">{{ number_format($data['converted']) }}</td>
                            <td class="py-3 font-medium">{{ $data['conversion_rate'] }}%</td>
                            <td class="py-3">
                                @if($variant !== 'control' && $controlRate > 0)
                                    @php
                                        $lift = (($data['conversion_rate'] - $controlRate) / $controlRate) * 100;
                                    @endphp
                                    <span class="font-medium {{ $lift > 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $lift > 0 ? '+' : '' }}{{ number_format($lift, 1) }}%
                                    </span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Charts Section -->
<div class="bg-white shadow rounded-lg mb-8">
    <div class="px-6 py-4 border-b border-gray-200">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-medium text-gray-900">Conversion Rate Trends</h3>
            <div class="flex space-x-2">
                <button onclick="updateChartPeriod('24h')" id="btn-24h"
                        class="px-3 py-1 rounded-md text-sm font-medium bg-blue-500 text-white">24h</button>
                <button onclick="updateChartPeriod('7d')" id="btn-7d"
                        class="px-3 py-1 rounded-md text-sm font-medium bg-gray-200 text-gray-700 hover:bg-gray-300">7d</button>
                <button onclick="updateChartPeriod('30d')" id="btn-30d"
                        class="px-3 py-1 rounded-md text-sm font-medium bg-gray-200 text-gray-700 hover:bg-gray-300">30d</button>
            </div>
        </div>
    </div>
    <div class="p-6">
        <div class="relative h-64">
            <canvas id="conversionChart"></canvas>
        </div>
    </div>
</div>

<!-- Live Activity Feed -->
<div class="bg-white shadow rounded-lg mb-8">
    <div class="px-6 py-4 border-b border-gray-200">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-medium text-gray-900">Live Activity</h3>
            <div class="flex items-center text-green-500">
                <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse mr-2"></div>
                <span class="text-xs font-medium">LIVE</span>
            </div>
        </div>
    </div>
    <div class="p-4">
        <div id="live-activity-feed" class="space-y-3 max-h-32 overflow-y-auto">
            <div class="flex items-start space-x-3 text-sm text-gray-600">
                <div class="w-2 h-2 bg-blue-500 rounded-full mt-2"></div>
                <div>
                    <span class="font-medium">User converted</span> in control group
                    <div class="text-xs text-gray-400">2 minutes ago</div>
                </div>
            </div>
            <div class="flex items-start space-x-3 text-sm text-gray-600">
                <div class="w-2 h-2 bg-green-500 rounded-full mt-2"></div>
                <div>
                    <span class="font-medium">New participant</span> assigned to variant_a
                    <div class="text-xs text-gray-400">5 minutes ago</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Summary Stats -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
    <div class="bg-white shadow rounded-lg p-6">
        <div class="text-2xl font-bold text-blue-600">{{ $stats['unique_users'] }}</div>
        <div class="text-sm text-gray-600">Unique Users</div>
    </div>
    <div class="bg-white shadow rounded-lg p-6">
        <div class="text-2xl font-bold text-green-600">{{ number_format($stats['total_interactions']) }}</div>
        <div class="text-sm text-gray-600">Total Interactions</div>
    </div>
    <div class="bg-white shadow rounded-lg p-6">
        <div class="text-2xl font-bold text-purple-600">{{ $stats['total_events'] }}</div>
        <div class="text-sm text-gray-600">Unique Events</div>
    </div>
    <div class="bg-white shadow rounded-lg p-6">
        <div class="text-2xl font-bold text-orange-600">{{ $stats['total_assignments'] }}</div>
        <div class="text-sm text-gray-600">Total Assignments</div>
    </div>
</div>

<!-- User Activity -->
<div class="bg-white shadow rounded-lg">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-medium text-gray-900">User Activity</h3>
        <p class="text-sm text-gray-600">Organized by individual users with event counts</p>
    </div>
    <div class="p-6">
        @if($stats['user_events']->count() > 0)
            <div class="space-y-3">
                @foreach($stats['user_events'] as $index => $userData)
                    <div class="border border-gray-200 rounded-lg">
                        <!-- Accordion Header -->
                        <div class="p-4 cursor-pointer hover:bg-gray-50" onclick="toggleAccordion('user-{{ $index }}')">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-4">
                                    <div class="flex items-center space-x-2">
                                        <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-medium">
                                            {{ $userData['variant'] }}
                                        </span>
                                        <span class="font-medium text-gray-900">
                                            User {{ substr($userData['user_id'], 0, 8) }}...
                                        </span>
                                    </div>
                                    <div class="flex items-center space-x-4 text-sm text-gray-500">
                                        <span>{{ $userData['total_interactions'] }} interactions</span>
                                        <span>{{ $userData['unique_events'] }} unique events</span>
                                        <span>Last: {{ \Carbon\Carbon::parse($userData['last_activity'])->diffForHumans() }}</span>
                                    </div>
                                </div>
                                <svg id="icon-user-{{ $index }}" class="h-5 w-5 text-gray-400 transform transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>

                        <!-- Accordion Content -->
                        <div id="user-{{ $index }}" class="hidden border-t border-gray-200 bg-gray-50 p-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <h5 class="text-sm font-medium text-gray-700 mb-2">Event Breakdown:</h5>
                                    <div class="space-y-2">
                                        @foreach($userData['events'] as $eventName => $eventData)
                                            <div class="flex items-center justify-between text-sm">
                                                <span class="font-medium">{{ $eventName }}</span>
                                                <div class="flex items-center space-x-2">
                                                    <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs">
                                                        {{ $eventData['count'] }} times
                                                    </span>
                                                    <span class="text-gray-500">
                                                        {{ \Carbon\Carbon::parse($eventData['last_occurred'])->format('M j, H:i') }}
                                                    </span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                <div>
                                    <h5 class="text-sm font-medium text-gray-700 mb-2">Timeline:</h5>
                                    <div class="text-sm text-gray-600 space-y-1">
                                        <div>First activity: {{ \Carbon\Carbon::parse($userData['first_activity'])->format('M j, Y H:i') }}</div>
                                        <div>Last activity: {{ \Carbon\Carbon::parse($userData['last_activity'])->format('M j, Y H:i') }}</div>
                                        <div>Duration: {{ \Carbon\Carbon::parse($userData['first_activity'])->diffForHumans(\Carbon\Carbon::parse($userData['last_activity']), true) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-gray-500 text-center py-8">No user activity recorded yet</p>
        @endif
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let conversionChart = null;
let currentPeriod = '24h';
let statsData = @json($stats);
let experimentData = {
    isActive: {{ $experiment->is_active ? 'true' : 'false' }},
    id: {{ $experiment->id }}
};

function initChart() {
    const ctx = document.getElementById('conversionChart').getContext('2d');
    
    // Destroy existing chart if it exists
    if (conversionChart) {
        conversionChart.destroy();
    }
    
    const variants = @json($stats['variants']);
    const variantNames = Object.keys(variants);
    const colors = ['#3B82F6', '#10B981', '#F59E0B', '#8B5CF6'];
    
    conversionChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: generateTimeLabels(currentPeriod),
            datasets: variantNames.map((variant, index) => ({
                label: variant.charAt(0).toUpperCase() + variant.slice(1).replace('_', ' '),
                data: generateMockData(currentPeriod),
                borderColor: colors[index] || '#6B7280',
                backgroundColor: (colors[index] || '#6B7280') + '20',
                tension: 0.4,
                fill: false,
                pointRadius: 4,
                pointHoverRadius: 6
            }))
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        padding: 15
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    }
                },
                y: {
                    beginAtZero: true,
                    max: 25,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    },
                    ticks: {
                        callback: function(value) {
                            return value + '%';
                        }
                    }
                }
            }
        }
    });
}

function generateTimeLabels(period) {
    const labels = [];
    const now = new Date();
    
    if (period === '24h') {
        for (let i = 23; i >= 0; i--) {
            const time = new Date(now.getTime() - (i * 60 * 60 * 1000));
            labels.push(time.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}));
        }
    } else if (period === '7d') {
        for (let i = 6; i >= 0; i--) {
            const date = new Date(now.getTime() - (i * 24 * 60 * 60 * 1000));
            labels.push(date.toLocaleDateString([], {month: 'short', day: 'numeric'}));
        }
    } else if (period === '30d') {
        for (let i = 29; i >= 0; i -= 3) {
            const date = new Date(now.getTime() - (i * 24 * 60 * 60 * 1000));
            labels.push(date.toLocaleDateString([], {month: 'short', day: 'numeric'}));
        }
    }
    return labels;
}

async function generateRealData(period) {
    try {
        const response = await fetch(`/api/ab-testing/experiments/${experimentData.id}/chart-data?period=${period}`);
        if (response.ok) {
            const data = await response.json();
            return data.values || [];
        }
    } catch (error) {
        console.error('Error fetching chart data:', error);
    }
    
    // Fallback to empty data if API fails
    let length = period === '24h' ? 24 : period === '7d' ? 7 : 10;
    return Array(length).fill(0);
}

async function updateChartPeriod(period) {
    currentPeriod = period;
    
    // Update button styles
    const buttons = ['24h', '7d', '30d'];
    buttons.forEach(p => {
        const btn = document.getElementById('btn-' + p);
        if (p === period) {
            btn.className = 'px-3 py-1 rounded-md text-sm font-medium bg-blue-500 text-white';
        } else {
            btn.className = 'px-3 py-1 rounded-md text-sm font-medium bg-gray-200 text-gray-700 hover:bg-gray-300';
        }
    });
    
    // Update chart data with real data
    if (conversionChart) {
        conversionChart.data.labels = generateTimeLabels(period);
        
        // Update each dataset with real data
        for (let i = 0; i < conversionChart.data.datasets.length; i++) {
            const dataset = conversionChart.data.datasets[i];
            dataset.data = await generateRealData(period);
        }
        
        conversionChart.update();
    }
}

// Real-time updates
function startRealTimeUpdates() {
    setInterval(async () => {
        await fetchLatestStats();
        await fetchRecentActivity();
        updateCharts();
        updateLiveIndicators();
    }, 10000); // Update every 10 seconds for real data
}

async function fetchLatestStats() {
    try {
        const response = await fetch(`/api/ab-testing/experiments/${experimentData.id}/stats`);
        if (response.ok) {
            const newStats = await response.json();
            statsData = newStats;
        }
    } catch (error) {
        console.error('Error fetching live stats:', error);
    }
}

function updateLiveIndicators() {
    if (!statsData || !statsData.variants) return;
    
    // Calculate real trends from actual data
    const now = new Date();
    const todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    
    // These would come from real API data with time filtering
    // For now, we'll update the main stats display with real data
    const totalAssignments = statsData.total_assignments || 0;
    const totalConversions = statsData.total_conversions || 0;
    const currentRate = totalAssignments > 0 ? ((totalConversions / totalAssignments) * 100).toFixed(2) : 0;
    
    // Update main numbers with real data
    document.querySelector('.text-2xl.font-bold.text-gray-900').textContent = totalAssignments.toLocaleString();
    document.querySelectorAll('.text-2xl.font-bold.text-gray-900')[1].textContent = totalConversions.toLocaleString();
    document.querySelectorAll('.text-2xl.font-bold.text-gray-900')[2].textContent = currentRate + '%';
}

async function fetchRecentActivity() {
    try {
        const response = await fetch(`/api/ab-testing/experiments/${experimentData.id}/recent-activity`);
        if (response.ok) {
            const activities = await response.json();
            updateActivityFeed(activities);
        }
    } catch (error) {
        console.error('Error fetching recent activity:', error);
    }
}

function updateActivityFeed(activities) {
    const feed = document.getElementById('live-activity-feed');
    feed.innerHTML = ''; // Clear existing
    
    activities.forEach(activity => {
        addToActivityFeed(activity.message, activity.color, activity.time, false);
    });
}

function addToActivityFeed(message, colorClass, timeAgo = 'Just now', animate = true) {
    const feed = document.getElementById('live-activity-feed');
    
    const activityItem = document.createElement('div');
    activityItem.className = `flex items-start space-x-3 text-sm text-gray-600 ${animate ? 'animate-pulse' : ''}`;
    activityItem.innerHTML = `
        <div class="w-2 h-2 ${colorClass} rounded-full mt-2"></div>
        <div>
            <span class="font-medium">${message}</span>
            <div class="text-xs text-gray-400">${timeAgo}</div>
        </div>
    `;
    
    // Add to top of feed
    feed.insertBefore(activityItem, feed.firstChild);
    
    // Remove animation after 2 seconds
    if (animate) {
        setTimeout(() => {
            activityItem.classList.remove('animate-pulse');
        }, 2000);
    }
    
    // Keep only last 15 items
    while (feed.children.length > 15) {
        feed.removeChild(feed.lastChild);
    }
}

function showLiveNotification(message) {
    const notification = document.createElement('div');
    notification.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg transform translate-x-full transition-transform duration-300 z-50';
    notification.innerHTML = `
        <div class="flex items-center space-x-2">
            <div class="w-2 h-2 bg-green-300 rounded-full animate-pulse"></div>
            <span class="text-sm font-medium">${message}</span>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Slide in
    setTimeout(() => {
        notification.classList.remove('translate-x-full');
    }, 100);
    
    // Slide out and remove
    setTimeout(() => {
        notification.classList.add('translate-x-full');
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 3000);
}

// Start real-time updates when page loads
document.addEventListener('DOMContentLoaded', function() {
    initChart();
    startRealTimeUpdates();
    fetchRecentActivity(); // Load initial activity
});

function toggleAccordion(id) {
    const content = document.getElementById(id);
    const icon = document.getElementById('icon-' + id);
    
    if (content.classList.contains('hidden')) {
        content.classList.remove('hidden');
        icon.classList.add('rotate-180');
    } else {
        content.classList.add('hidden');
        icon.classList.remove('rotate-180');
    }
}
</script>

<!-- FontAwesome for icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<div class="mt-8 bg-gray-50 rounded-lg p-6">
    <h3 class="text-lg font-medium text-gray-900 mb-4">🛠️ Implementation Guide</h3>
    <div class="space-y-4">
        <div>
            <h4 class="text-sm font-medium text-gray-700 mb-2">🖥️ Server-Side (PHP/Laravel):</h4>
            <pre class="bg-gray-800 text-green-400 p-4 rounded text-sm overflow-x-auto"><code>{{-- Check variant --}}
@variant("{{ $experiment->name }}", 'variant_b')
    &lt;div&gt;New design&lt;/div&gt;
@else
    &lt;div&gt;Original design&lt;/div&gt;
@endvariant

{{-- Track conversion --}}
@abtrack('{{ $experiment->name }}', null, 'conversion')</code></pre>
        </div>

        <div>
            <h4 class="text-sm font-medium text-gray-700 mb-2">🌐 JavaScript/Vue Usage:</h4>
            <pre class="bg-gray-800 text-green-400 p-4 rounded text-sm overflow-x-auto"><code>use Homemove\AbTesting\Facades\AbTest;

$variant = AbTest::variant('{{ $experiment->name }}');
AbTest::track('{{ $experiment->name }}', null, 'conversion');</code></pre>
        </div>
    </div>
</div>
@endsection
