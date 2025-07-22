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

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <div class="bg-white p-6 rounded-lg shadow">
        <h3 class="text-sm font-medium text-gray-500">Total Participants</h3>
        <p class="text-2xl font-bold text-gray-900">{{ number_format($stats['total_assignments']) }}</p>
    </div>
    <div class="bg-white p-6 rounded-lg shadow">
        <h3 class="text-sm font-medium text-gray-500">Total Conversions</h3>
        <p class="text-2xl font-bold text-gray-900">{{ number_format($stats['total_conversions']) }}</p>
    </div>
    <div class="bg-white p-6 rounded-lg shadow">
        <h3 class="text-sm font-medium text-gray-500">Overall Rate</h3>
        <p class="text-2xl font-bold text-gray-900">
            {{ $stats['total_assignments'] > 0 ? number_format(($stats['total_conversions'] / $stats['total_assignments']) * 100, 2) : 0 }}%
        </p>
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

<script>
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
