@extends('ab-testing::dashboard.layout')

@section('title', $experiment->name)

@section('content')
<div x-data="abTestDashboard({{ $experiment->id }})" x-init="init()">
    <!-- Header Section -->
    <div class="mb-8 bg-gradient-to-r from-blue-600 to-purple-600 rounded-xl shadow-xl p-6 text-white">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold mb-2">{{ $experiment->name }}</h1>
                <p class="text-blue-100 text-lg">{{ $experiment->description }}</p>
                <div class="flex items-center mt-3 space-x-4">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium" 
                          :class="isActive ? 'bg-green-500 text-white' : 'bg-red-500 text-white'">
                        <div class="w-2 h-2 rounded-full mr-2" :class="isActive ? 'bg-green-300' : 'bg-red-300'"></div>
                        <span x-text="isActive ? 'Active' : 'Paused'"></span>
                    </span>
                    <span class="text-blue-100">
                        <i class="fas fa-users mr-1"></i>
                        <span x-text="stats.total_assignments"></span> participants
                    </span>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <button @click="toggleExperiment()" 
                        class="px-6 py-3 rounded-lg font-medium transition-all duration-200 transform hover:scale-105"
                        :class="isActive ? 'bg-red-500 hover:bg-red-600 text-white' : 'bg-green-500 hover:bg-green-600 text-white'">
                    <span x-text="isActive ? 'Pause' : 'Activate'"></span>
                </button>
                <a href="{{ route('ab-testing.dashboard.edit', $experiment) }}" 
                   class="px-6 py-3 bg-white text-blue-600 rounded-lg hover:bg-blue-50 font-medium transition-all duration-200 transform hover:scale-105">
                    Edit
                </a>
            </div>
        </div>
    </div>

    <!-- Real-time Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Participants -->
        <div class="bg-white rounded-xl shadow-lg p-6 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-20 h-20 bg-blue-100 rounded-full -mr-10 -mt-10"></div>
            <div class="relative">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-medium text-gray-600">Total Participants</h3>
                    <div class="text-blue-500">
                        <i class="fas fa-users text-xl"></i>
                    </div>
                </div>
                <div class="text-3xl font-bold text-gray-900 mb-1" x-text="animateNumber(stats.total_assignments)"></div>
                <div class="flex items-center">
                    <span class="text-xs text-green-500 font-medium">
                        +<span x-text="recentParticipants"></span> today
                    </span>
                </div>
            </div>
        </div>

        <!-- Total Conversions -->
        <div class="bg-white rounded-xl shadow-lg p-6 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-20 h-20 bg-green-100 rounded-full -mr-10 -mt-10"></div>
            <div class="relative">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-medium text-gray-600">Total Conversions</h3>
                    <div class="text-green-500">
                        <i class="fas fa-chart-line text-xl"></i>
                    </div>
                </div>
                <div class="text-3xl font-bold text-gray-900 mb-1" x-text="animateNumber(stats.total_conversions)"></div>
                <div class="flex items-center">
                    <span class="text-xs text-green-500 font-medium">
                        +<span x-text="recentConversions"></span> today
                    </span>
                </div>
            </div>
        </div>

        <!-- Conversion Rate -->
        <div class="bg-white rounded-xl shadow-lg p-6 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-20 h-20 bg-purple-100 rounded-full -mr-10 -mt-10"></div>
            <div class="relative">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-medium text-gray-600">Conversion Rate</h3>
                    <div class="text-purple-500">
                        <i class="fas fa-percentage text-xl"></i>
                    </div>
                </div>
                <div class="text-3xl font-bold text-gray-900 mb-1">
                    <span x-text="(stats.total_assignments > 0 ? ((stats.total_conversions / stats.total_assignments) * 100).toFixed(2) : 0)"></span>%
                </div>
                <div class="flex items-center">
                    <span class="text-xs font-medium" :class="conversionTrend >= 0 ? 'text-green-500' : 'text-red-500'">
                        <i :class="conversionTrend >= 0 ? 'fas fa-arrow-up' : 'fas fa-arrow-down'"></i>
                        <span x-text="Math.abs(conversionTrend).toFixed(1)"></span>% vs yesterday
                    </span>
                </div>
            </div>
        </div>

        <!-- Statistical Significance -->
        <div class="bg-white rounded-xl shadow-lg p-6 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-20 h-20 bg-yellow-100 rounded-full -mr-10 -mt-10"></div>
            <div class="relative">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-medium text-gray-600">Significance</h3>
                    <div class="text-yellow-500">
                        <i class="fas fa-flask text-xl"></i>
                    </div>
                </div>
                <div class="text-2xl font-bold mb-1" :class="significance.is_significant ? 'text-green-600' : 'text-yellow-600'">
                    <span x-text="significance.confidence"></span>%
                </div>
                <div class="flex items-center">
                    <span class="text-xs font-medium" :class="significance.is_significant ? 'text-green-500' : 'text-yellow-500'">
                        <span x-text="significance.is_significant ? 'Significant' : 'Not Yet'"></span>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <!-- Conversion Rate Over Time -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Conversion Rate Trends</h3>
                <div class="flex space-x-2">
                    <button @click="chartPeriod = '24h'" :class="chartPeriod === '24h' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700'" 
                            class="px-3 py-1 rounded text-sm font-medium">24h</button>
                    <button @click="chartPeriod = '7d'" :class="chartPeriod === '7d' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700'" 
                            class="px-3 py-1 rounded text-sm font-medium">7d</button>
                    <button @click="chartPeriod = '30d'" :class="chartPeriod === '30d' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700'" 
                            class="px-3 py-1 rounded text-sm font-medium">30d</button>
                </div>
            </div>
            <div class="relative h-64">
                <canvas id="conversionChart"></canvas>
            </div>
        </div>

        <!-- Variant Performance -->
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-6">Variant Performance</h3>
            <div class="relative h-64">
                <canvas id="variantChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Real-time Activity Feed -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Variant Details Table -->
        <div class="lg:col-span-2 bg-white rounded-xl shadow-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Variant Performance Details</h3>
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
                        <template x-for="(variant, name) in variants" :key="name">
                            <tr class="hover:bg-gray-50 transition-colors duration-200">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-3 h-3 rounded-full mr-3" :style="`background-color: ${variant.color}`"></div>
                                        <span class="text-sm font-medium text-gray-900 capitalize" x-text="name"></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="variant.participants"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="variant.conversions"></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm font-medium" :class="variant.rate > controlRate ? 'text-green-600' : variant.rate < controlRate ? 'text-red-600' : 'text-gray-900'">
                                        <span x-text="variant.rate"></span>%
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span x-show="name !== 'control'" class="text-sm font-medium" :class="variant.lift > 0 ? 'text-green-600' : variant.lift < 0 ? 'text-red-600' : 'text-gray-900'">
                                        <span x-text="variant.lift > 0 ? '+' : ''"></span><span x-text="variant.lift"></span>%
                                    </span>
                                    <span x-show="name === 'control'" class="text-sm text-gray-500">Baseline</span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Live Activity Feed -->
        <div class="bg-white rounded-xl shadow-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">Live Activity</h3>
                    <div class="flex items-center text-green-500">
                        <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse mr-2"></div>
                        <span class="text-xs font-medium">LIVE</span>
                    </div>
                </div>
            </div>
            <div class="p-6">
                <div class="space-y-4 max-h-96 overflow-y-auto">
                    <template x-for="event in recentEvents" :key="event.id">
                        <div class="flex items-start space-x-3 p-3 bg-gray-50 rounded-lg">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-medium"
                                     :class="event.type === 'conversion' ? 'bg-green-500' : event.type === 'assignment' ? 'bg-blue-500' : 'bg-gray-500'">
                                    <i :class="event.type === 'conversion' ? 'fas fa-check' : event.type === 'assignment' ? 'fas fa-user-plus' : 'fas fa-mouse-pointer'"></i>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900" x-text="event.description"></p>
                                <p class="text-xs text-gray-500" x-text="event.time"></p>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function abTestDashboard(experimentId) {
    return {
        experimentId: experimentId,
        isActive: {{ $experiment->is_active ? 'true' : 'false' }},
        stats: @json($stats),
        variants: @json($stats['variants']),
        significance: {
            is_significant: false,
            confidence: 85,
        },
        recentParticipants: 12,
        recentConversions: 8,
        conversionTrend: 2.3,
        chartPeriod: '24h',
        recentEvents: [],
        conversionChart: null,
        variantChart: null,
        controlRate: 0,
        
        init() {
            this.controlRate = this.variants.control ? this.variants.control.rate : 0;
            this.initCharts();
            this.startRealTimeUpdates();
            this.loadRecentEvents();
        },
        
        animateNumber(target) {
            return target.toLocaleString();
        },
        
        async toggleExperiment() {
            try {
                const response = await fetch(`/ab-testing/dashboard/{{ $experiment->id }}/toggle`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                
                if (response.ok) {
                    this.isActive = !this.isActive;
                }
            } catch (error) {
                console.error('Error toggling experiment:', error);
            }
        },
        
        initCharts() {
            // Conversion Rate Chart
            const conversionCtx = document.getElementById('conversionChart').getContext('2d');
            this.conversionChart = new Chart(conversionCtx, {
                type: 'line',
                data: {
                    labels: this.generateTimeLabels(),
                    datasets: Object.keys(this.variants).map((variant, index) => ({
                        label: variant.charAt(0).toUpperCase() + variant.slice(1),
                        data: this.generateMockData(),
                        borderColor: this.getVariantColor(variant),
                        backgroundColor: this.getVariantColor(variant) + '20',
                        tension: 0.4,
                        fill: false
                    }))
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 25,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        }
                    }
                }
            });
            
            // Variant Performance Chart
            const variantCtx = document.getElementById('variantChart').getContext('2d');
            this.variantChart = new Chart(variantCtx, {
                type: 'doughnut',
                data: {
                    labels: Object.keys(this.variants),
                    datasets: [{
                        data: Object.values(this.variants).map(v => v.conversions),
                        backgroundColor: Object.keys(this.variants).map(variant => this.getVariantColor(variant)),
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        },
        
        getVariantColor(variant) {
            const colors = {
                control: '#3B82F6',
                variant_a: '#10B981',
                variant_b: '#F59E0B',
                new_design: '#8B5CF6',
                default: '#6B7280'
            };
            return colors[variant] || colors.default;
        },
        
        generateTimeLabels() {
            const labels = [];
            const now = new Date();
            for (let i = 23; i >= 0; i--) {
                const time = new Date(now.getTime() - (i * 60 * 60 * 1000));
                labels.push(time.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}));
            }
            return labels;
        },
        
        generateMockData() {
            return Array.from({length: 24}, () => Math.random() * 20 + 5);
        },
        
        startRealTimeUpdates() {
            setInterval(async () => {
                await this.fetchLatestStats();
                this.updateCharts();
            }, 30000); // Update every 30 seconds
        },
        
        async fetchLatestStats() {
            try {
                const response = await fetch(`/api/ab-testing/experiments/${this.experimentId}/stats`);
                if (response.ok) {
                    const newStats = await response.json();
                    this.stats = newStats;
                    this.variants = newStats.variants;
                }
            } catch (error) {
                console.error('Error fetching stats:', error);
            }
        },
        
        updateCharts() {
            if (this.conversionChart) {
                // Add new data point
                this.conversionChart.data.labels.push(new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}));
                this.conversionChart.data.labels.shift();
                
                this.conversionChart.data.datasets.forEach(dataset => {
                    dataset.data.push(Math.random() * 20 + 5);
                    dataset.data.shift();
                });
                
                this.conversionChart.update('none');
            }
        },
        
        loadRecentEvents() {
            // Mock recent events
            this.recentEvents = [
                { id: 1, type: 'conversion', description: 'User converted in variant_a', time: '2 minutes ago' },
                { id: 2, type: 'assignment', description: 'New user assigned to control', time: '5 minutes ago' },
                { id: 3, type: 'event', description: 'Button click tracked', time: '8 minutes ago' },
                { id: 4, type: 'conversion', description: 'User converted in control', time: '12 minutes ago' },
                { id: 5, type: 'assignment', description: 'New user assigned to variant_a', time: '15 minutes ago' }
            ];
        }
    }
}
</script>

<!-- FontAwesome for icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
@endsection