@extends('ab-testing::dashboard.layout')

@section('title', $experiment->name)

@section('content')
<div class="w-full max-w-none px-4 sm:px-6 lg:px-8">
<!-- Header Section -->
<div class="mb-8 bg-gradient-to-r from-red-600 to-red-800 rounded shadow-xl p-6 text-white">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold mb-2">{{ $experiment->name }}</h1>
            <p class="text-red-100 text-lg">{{ $experiment->description }}</p>
            <div class="flex items-center mt-3 space-x-4">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $experiment->is_active ? 'bg-red-500 text-white' : 'bg-red-500 text-white' }}">
                    <div class="w-2 h-2 rounded-full mr-2 {{ $experiment->is_active ? 'bg-red-300' : 'bg-red-300' }}"></div>
                    {{ $experiment->is_active ? 'Active' : 'Paused' }}
                </span>
                <span class="text-red-100">
                    <i class="fas fa-users mr-1"></i>
                    {{ number_format($stats['total_assignments']) }} participants
                </span>
            </div>
        </div>
        <div class="flex items-center space-x-3">
            <form action="{{ route('ab-testing.dashboard.toggle', $experiment) }}" method="POST" class="inline">
                @csrf
                @method('PATCH')
                <button type="submit" class="px-6 py-3 rounded font-medium transition-all duration-200 transform hover:scale-105 {{ $experiment->is_active ? 'bg-red-500 hover:bg-red-600 text-white' : 'bg-red-500 hover:bg-red-600 text-white' }}">
                    {{ $experiment->is_active ? 'Pause' : 'Activate' }}
                </button>
            </form>
            <a href="{{ route('ab-testing.dashboard.edit', $experiment) }}" 
               class="px-6 py-3 bg-white text-red-600 rounded hover:bg-red-50 font-medium transition-all duration-200 transform hover:scale-105">
                Edit
            </a>
        </div>
    </div>
</div>

<!-- Real-time Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Total Participants -->
    <div class="bg-white rounded shadow-lg p-6 relative overflow-hidden group hover:shadow-xl transition-all duration-300">
        <div class="absolute top-0 right-0 w-20 h-20 bg-red-100 rounded-full -mr-10 -mt-10"></div>
        <div class="relative">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-medium text-gray-600 flex items-center">
                    Total Participants
                    <i class="fas fa-info-circle text-gray-400 ml-1 text-xs cursor-help" 
                       title="Total number of users assigned to this experiment"></i>
                </h3>
                <div class="text-red-500">
                    <i class="fas fa-users text-xl"></i>
                </div>
            </div>
            <div class="text-3xl font-bold text-gray-900 mb-1">{{ number_format($stats['total_assignments']) }}</div>
            <div class="flex items-center">
                <span class="text-xs text-red-500 font-medium" id="participants-trend">
                    +<span id="recent-participants">{{ $stats['today_assignments'] ?? 0 }}</span> today
                </span>
            </div>
        </div>
    </div>

    <!-- Total Conversions -->
    <div class="bg-white rounded shadow-lg p-6 relative overflow-hidden group hover:shadow-xl transition-all duration-300">
        <div class="absolute top-0 right-0 w-20 h-20 bg-red-100 rounded-full -mr-10 -mt-10"></div>
        <div class="relative">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-medium text-gray-600 flex items-center">
                    Total Conversions
                    <i class="fas fa-info-circle text-gray-400 ml-1 text-xs cursor-help"
                       title="Users who completed the desired action (purchase, signup, etc.)"></i>
                </h3>
                <div class="text-red-500">
                    <i class="fas fa-chart-line text-xl"></i>
                </div>
            </div>
            <div class="text-3xl font-bold text-gray-900 mb-1">{{ number_format($stats['total_conversions']) }}</div>
            <div class="flex items-center">
                <span class="text-xs text-red-500 font-medium" id="conversions-trend">
                    +<span id="recent-conversions">{{ $stats['today_conversions'] ?? 0 }}</span> today
                </span>
            </div>
        </div>
    </div>

    <!-- Conversion Rate -->
    <div class="bg-white rounded shadow-lg p-6 relative overflow-hidden group hover:shadow-xl transition-all duration-300">
        <div class="absolute top-0 right-0 w-20 h-20 bg-red-100 rounded-full -mr-10 -mt-10"></div>
        <div class="relative">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-medium text-gray-600 flex items-center">
                    Conversion Rate
                    <i class="fas fa-info-circle text-gray-400 ml-1 text-xs cursor-help"
                       title="Percentage of participants who converted (Conversions ÷ Participants × 100)"></i>
                </h3>
                <div class="text-red-500">
                    <i class="fas fa-percentage text-xl"></i>
                </div>
            </div>
            <div class="text-3xl font-bold text-gray-900 mb-1">
                {{ $stats['total_assignments'] > 0 ? number_format(($stats['total_conversions'] / $stats['total_assignments']) * 100, 2) : 0 }}%
            </div>
            <div class="flex items-center">
                <span class="text-xs font-medium text-red-500" id="rate-trend">
                    <i class="fas fa-arrow-up"></i>
                    <span id="rate-change">2.3</span>% vs yesterday
                </span>
            </div>
        </div>
    </div>

    <!-- Statistical Significance -->
    <div class="bg-white rounded shadow-lg p-6 relative overflow-hidden group hover:shadow-xl transition-all duration-300">
        <div class="absolute top-0 right-0 w-20 h-20 bg-red-100 rounded-full -mr-10 -mt-10"></div>
        <div class="relative">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-medium text-gray-600 flex items-center">
                    Significance
                    <i class="fas fa-info-circle text-gray-400 ml-1 text-xs cursor-help"
                       title="Statistical confidence that the results are real, not due to chance. 95%+ = Significant. Need more participants for reliable results."></i>
                </h3>
                <div class="text-red-500">
                    <i class="fas fa-flask text-xl"></i>
                </div>
            </div>
            <div class="text-2xl font-bold text-red-600 mb-1">85%</div>
            <div class="flex items-center">
                <span class="text-xs font-medium text-red-500" id="significance-status">
                    <span id="significance-message">Needs More Data</span>
                </span>
            </div>
        </div>
    </div>
</div>

<div class="bg-white shadow rounded mb-8">
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
                                    <span class="font-medium {{ $lift > 0 ? 'text-red-600' : 'text-red-600' }}">
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
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
    <!-- Conversion Rate Over Time -->
    <div class="bg-white rounded shadow-lg p-6 hover:shadow-xl transition-all duration-300">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                Conversion Rate Trends
                <i class="fas fa-info-circle text-gray-400 ml-2 text-sm cursor-help" 
                   title="Shows how conversion rates change over time for each variant"></i>
            </h3>
            <div class="flex space-x-2">
                <button onclick="updateChartPeriod('24h')" id="btn-24h"
                        class="px-3 py-1 rounded text-sm font-medium transition-all duration-200 hover:scale-105 bg-red-500 text-white">24h</button>
                <button onclick="updateChartPeriod('7d')" id="btn-7d"
                        class="px-3 py-1 rounded text-sm font-medium transition-all duration-200 hover:scale-105 bg-gray-200 text-gray-700 hover:bg-gray-300">7d</button>
                <button onclick="updateChartPeriod('30d')" id="btn-30d"
                        class="px-3 py-1 rounded text-sm font-medium transition-all duration-200 hover:scale-105 bg-gray-200 text-gray-700 hover:bg-gray-300">30d</button>
            </div>
        </div>
        <div class="relative h-64">
            <canvas id="conversionChart"></canvas>
        </div>
    </div>

    <!-- Variant Performance -->
    <div class="bg-white rounded shadow-lg p-6 hover:shadow-xl transition-all duration-300">
        <h3 class="text-lg font-semibold text-gray-900 mb-6 flex items-center">
            Variant Performance
            <i class="fas fa-info-circle text-gray-400 ml-2 text-sm cursor-help" 
               title="Distribution of conversions across all variants"></i>
        </h3>
        <div class="relative h-64">
            <canvas id="variantChart"></canvas>
        </div>
    </div>
</div>

<!-- Real-time Activity Feed -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
    <!-- Variant Details Table -->
    <div class="lg:col-span-2 bg-white rounded shadow-lg hover:shadow-xl transition-all duration-300">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                Variant Performance Details
                <i class="fas fa-info-circle text-gray-400 ml-2 text-sm cursor-help" 
                   title="Detailed breakdown of each variant's performance metrics"></i>
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Variant</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Participants</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Conversions</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rate</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lift</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @php
                        $controlRate = $stats['variants']['control']['conversion_rate'] ?? 0;
                        $colors = ['control' => '#DC2626', 'variant_a' => '#B91C1C', 'variant_b' => '#991B1B', 'new_design' => '#7F1D1D'];
                    @endphp
                    @foreach($stats['variants'] as $variant => $data)
                        <tr class="hover:bg-gray-50 transition-colors duration-200">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-3 h-3 rounded-full mr-3" style="background-color: {{ $colors[$variant] ?? '#6B7280' }}"></div>
                                    <span class="text-sm font-medium text-gray-900 capitalize">{{ str_replace('_', ' ', $variant) }}</span>
                                    @if($variant === 'control')
                                        <span class="ml-2 px-2 py-1 text-xs bg-gray-100 text-gray-600 rounded">Control</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ number_format($data['assigned']) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ number_format($data['converted']) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm font-medium {{ $data['conversion_rate'] > $controlRate ? 'text-red-600' : ($data['conversion_rate'] < $controlRate ? 'text-red-600' : 'text-gray-900') }}">
                                    {{ $data['conversion_rate'] }}%
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($variant !== 'control' && $controlRate > 0)
                                    @php
                                        $lift = (($data['conversion_rate'] - $controlRate) / $controlRate) * 100;
                                    @endphp
                                    <span class="text-sm font-medium {{ $lift > 0 ? 'text-red-600' : 'text-red-600' }}">
                                        {{ $lift > 0 ? '+' : '' }}{{ number_format($lift, 1) }}%
                                    </span>
                                @else
                                    <span class="text-sm text-gray-500">Baseline</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Live Activity Feed -->
    <div class="bg-white rounded shadow-lg hover:shadow-xl transition-all duration-300">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    Live Activity
                    <i class="fas fa-info-circle text-gray-400 ml-2 text-sm cursor-help" 
                       title="Real-time feed of user actions and conversions"></i>
                </h3>
                <div class="flex items-center text-red-500">
                    <div class="w-2 h-2 bg-red-500 rounded-full animate-pulse mr-2"></div>
                    <span class="text-xs font-medium">LIVE</span>
                </div>
            </div>
        </div>
        <div class="p-6">
            <div class="space-y-4 max-h-96 overflow-y-auto">
                <div id="live-activity-feed" class="space-y-3">
                    <div class="flex items-start space-x-3 p-3 bg-gray-50 rounded hover:bg-gray-100 transition-colors duration-200">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-red-500 rounded-full flex items-center justify-center text-white text-xs font-medium">
                                <i class="fas fa-check"></i>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <!-- Real activity will load here via JavaScript -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Summary Stats -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
    <div class="bg-white shadow rounded p-6">
        <div class="text-2xl font-bold text-red-600">{{ $stats['unique_users'] }}</div>
        <div class="text-sm text-gray-600">Unique Users</div>
    </div>
    <div class="bg-white shadow rounded p-6">
        <div class="text-2xl font-bold text-red-600">{{ number_format($stats['total_interactions']) }}</div>
        <div class="text-sm text-gray-600">Total Interactions</div>
    </div>
    <div class="bg-white shadow rounded p-6">
        <div class="text-2xl font-bold text-red-600">{{ $stats['total_events'] }}</div>
        <div class="text-sm text-gray-600">Unique Events</div>
    </div>
    <div class="bg-white shadow rounded p-6">
        <div class="text-2xl font-bold text-red-600">{{ $stats['total_assignments'] }}</div>
        <div class="text-sm text-gray-600">Total Assignments</div>
    </div>
</div>

<!-- User Activity - Optimized for Large Datasets -->
<div class="bg-white shadow-xl rounded border border-gray-100">
    <div class="px-6 py-5 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">User Activity Analytics</h3>
                <p class="text-sm text-gray-600 mt-1">{{ $stats['user_events']->count() }} users tracked</p>
            </div>
            <div class="flex items-center space-x-3">
                <select id="activity-filter" class="px-3 py-2 border border-gray-300 rounded text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="all">All Users</option>
                    <option value="converted">Converted Only</option>
                    <option value="high-activity">High Activity</option>
                    <option value="recent">Recent Activity</option>
                </select>
                <button onclick="toggleUserActivityView()" class="px-4 py-2 bg-red-500 text-white rounded text-sm hover:bg-red-600 transition-colors">
                    <span id="view-toggle-text">Detailed View</span>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Summary Cards -->
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-red-50 rounded p-4 border border-red-100">
                <div class="text-2xl font-bold text-red-600">{{ $stats['unique_users'] }}</div>
                <div class="text-sm text-red-700 font-medium">Total Users</div>
            </div>
            <div class="bg-red-50 rounded p-4 border border-red-100">
                <div class="text-2xl font-bold text-red-600">{{ $stats['user_events']->where(function($user) { return collect($user['events'])->has('conversion'); })->count() }}</div>
                <div class="text-sm text-red-700 font-medium">Converted Users</div>
            </div>
            <div class="bg-red-50 rounded p-4 border border-red-100">
                <div class="text-2xl font-bold text-red-600">{{ number_format($stats['total_interactions']) }}</div>
                <div class="text-sm text-red-700 font-medium">Total Interactions</div>
            </div>
            <div class="bg-red-50 rounded p-4 border border-red-100">
                <div class="text-2xl font-bold text-red-600">{{ number_format($stats['user_events']->avg('total_interactions'), 1) }}</div>
                <div class="text-sm text-red-700 font-medium">Avg per User</div>
            </div>
        </div>
        
        <!-- Compact User List -->
        <div id="user-activity-list" class="space-y-2 max-h-96 overflow-y-auto">
            @if($stats['user_events']->count() > 0)
                @foreach($stats['user_events']->take(20) as $index => $userData)
                    <div class="user-activity-item border border-gray-200 rounded hover:shadow-md transition-all duration-200 cursor-pointer" 
                         data-variant="{{ $userData['variant'] }}" 
                         data-converted="{{ collect($userData['events'])->has('conversion') ? 'true' : 'false' }}" 
                         data-activity="{{ $userData['total_interactions'] }}" 
                         data-recent="{{ \Carbon\Carbon::parse($userData['last_activity'])->diffInHours() < 24 ? 'true' : 'false' }}" 
                         onclick="showUserDetails({{ $index }}, @js($userData))">
                        <div class="p-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-red-400 to-red-600 flex items-center justify-center text-white font-bold text-sm">
                                        {{ strtoupper(substr($userData['user_id'], 0, 2)) }}
                                    </div>
                                    <div>
                                        <div class="flex items-center space-x-2">
                                            <span class="font-semibold text-gray-900">User {{ substr($userData['user_id'], 0, 8) }}...</span>
                                            <span class="px-2 py-1 bg-{{ $userData['variant'] === 'control' ? 'blue' : 'green' }}-100 text-{{ $userData['variant'] === 'control' ? 'blue' : 'green' }}-800 rounded-full text-xs font-medium">
                                                {{ $userData['variant'] }}
                                            </span>
                                            @if(collect($userData['events'])->has('conversion'))
                                                <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs font-medium">
                                                    ✓ Converted
                                                </span>
                                            @endif
                                        </div>
                                        <div class="text-sm text-gray-500 mt-1">
                                            {{ $userData['total_interactions'] }} interactions • {{ $userData['unique_events'] }} event types • Last: {{ \Carbon\Carbon::parse($userData['last_activity'])->diffForHumans() }}
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <div class="text-right">
                                        <div class="text-sm font-medium text-gray-900">{{ \Carbon\Carbon::parse($userData['first_activity'])->format('M j') }}</div>
                                        <div class="text-xs text-gray-500">First seen</div>
                                    </div>
                                    <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
                
                @if($stats['user_events']->count() > 20)
                    <div class="text-center py-4">
                        <button onclick="loadMoreUsers()" class="px-6 py-3 bg-gray-100 text-gray-700 rounded hover:bg-gray-200 transition-colors font-medium">
                            Load More Users ({{ $stats['user_events']->count() - 20 }} remaining)
                        </button>
                    </div>
                @endif
            @else
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No user activity yet</h3>
                    <p class="mt-1 text-sm text-gray-500">Activity will appear here as users interact with your experiment</p>
                </div>
            @endif
        </div>
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

async function initChart() {
    const ctx = document.getElementById('conversionChart').getContext('2d');
    
    // Destroy existing chart if it exists
    if (conversionChart) {
        conversionChart.destroy();
    }
    
    const variants = @json($stats['variants']);
    const variantNames = Object.keys(variants);
    const colors = ['#DC2626', '#B91C1C', '#991B1B', '#7F1D1D'];
    
    conversionChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: generateTimeLabels(currentPeriod),
            datasets: variantNames.map((variant, index) => ({
                label: variant.charAt(0).toUpperCase() + variant.slice(1).replace('_', ' '),
                data: Array(generateTimeLabels(currentPeriod).length).fill(0),
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
            btn.className = 'px-3 py-1 rounded-md text-sm font-medium bg-red-500 text-white';
        } else {
            btn.className = 'px-3 py-1 rounded-md text-sm font-medium bg-gray-200 text-gray-700 hover:bg-gray-300';
        }
    });
    
    // Update chart data with real data
    if (conversionChart) {
        conversionChart.data.labels = generateTimeLabels(period);
        
        // Update each dataset with real data
        const realData = await generateRealData(period);
        for (let i = 0; i < conversionChart.data.datasets.length; i++) {
            const dataset = conversionChart.data.datasets[i];
            dataset.data = realData;
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
    notification.className = 'fixed top-4 right-4 bg-red-500 text-white px-4 py-2 rounded shadow-lg transform translate-x-full transition-transform duration-300 z-50';
    notification.innerHTML = `
        <div class="flex items-center space-x-2">
            <div class="w-2 h-2 bg-red-300 rounded-full animate-pulse"></div>
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
document.addEventListener('DOMContentLoaded', async function() {
    await initChart();
    startRealTimeUpdates();
    fetchRecentActivity(); // Load initial activity
    
    // Load real chart data after initialization
    setTimeout(async () => {
        await updateChartPeriod('24h');
    }, 1000);
});

// User Activity Functions
function filterUserActivity() {
    const filter = document.getElementById('activity-filter').value;
    const items = document.querySelectorAll('.user-activity-item');
    
    items.forEach(item => {
        let show = false;
        
        switch(filter) {
            case 'all':
                show = true;
                break;
            case 'converted':
                show = item.dataset.converted === 'true';
                break;
            case 'high-activity':
                show = parseInt(item.dataset.activity) > 10;
                break;
            case 'recent':
                show = item.dataset.recent === 'true';
                break;
        }
        
        item.style.display = show ? 'block' : 'none';
    });
}

function showUserDetails(index, userData) {
    // Create modal or detailed view for user
    console.log('User details:', userData);
    alert(`User Details:\nID: ${userData.user_id}\nVariant: ${userData.variant}\nTotal Interactions: ${userData.total_interactions}\nUnique Events: ${userData.unique_events}`);
}

function loadMoreUsers() {
    // Ajax call to load more users
    console.log('Loading more users...');
}

function toggleUserActivityView() {
    const toggleText = document.getElementById('view-toggle-text');
    if (toggleText.textContent === 'Detailed View') {
        toggleText.textContent = 'Compact View';
        // Switch to detailed view
    } else {
        toggleText.textContent = 'Detailed View';
        // Switch to compact view
    }
}

// Add event listener for filter
document.addEventListener('DOMContentLoaded', function() {
    const filterSelect = document.getElementById('activity-filter');
    if (filterSelect) {
        filterSelect.addEventListener('change', filterUserActivity);
    }
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

<div class="mt-8 bg-gray-50 rounded p-6">
    <h3 class="text-lg font-medium text-gray-900 mb-4">🛠️ Implementation Guide</h3>
    <div class="space-y-4">
        <div>
            <h4 class="text-sm font-medium text-gray-700 mb-2">🖥️ Server-Side (PHP/Laravel):</h4>
            <pre class="bg-gray-800 text-red-400 p-4 rounded text-sm overflow-x-auto"><code>{{-- Check variant --}}
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
            <pre class="bg-gray-800 text-red-400 p-4 rounded text-sm overflow-x-auto"><code>use Homemove\AbTesting\Facades\AbTest;

$variant = AbTest::variant('{{ $experiment->name }}');
AbTest::track('{{ $experiment->name }}', null, 'conversion');</code></pre>
        </div>
    </div>
</div>
</div>
@endsection
