@extends('ab-testing::dashboard.layout')

@section('title', 'Experiments')

@section('content')
<div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-900">Experiments</h2>
    <p class="text-gray-600">Manage and monitor your A/B tests</p>
</div>

@if($experiments->count() > 0)
    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <div class="grid gap-6">
                @foreach($experiments as $experiment)
                    <div class="border border-gray-200 rounded-lg p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">
                                    <a href="{{ route('ab-testing.dashboard.show', $experiment) }}" class="hover:text-blue-600">
                                        {{ $experiment->name }}
                                    </a>
                                </h3>
                                <p class="text-gray-600">{{ $experiment->description }}</p>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span class="px-2 py-1 text-xs font-medium rounded-full {{ $experiment->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                    {{ $experiment->is_active ? 'Active' : 'Inactive' }}
                                </span>
                                <a href="{{ route('ab-testing.dashboard.edit', $experiment) }}" class="text-blue-600 hover:text-blue-900">
                                    Edit
                                </a>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
                            <div>
                                <span class="text-gray-500">Variants:</span>
                                <div class="font-medium">{{ count($experiment->variants) }}</div>
                            </div>
                            <div>
                                <span class="text-gray-500">Users:</span>
                                <div class="font-medium">{{ $experiment->assignments_count }}</div>
                            </div>
                            <div>
                                <span class="text-gray-500">Events:</span>
                                <div class="font-medium">{{ $experiment->events_count }}</div>
                            </div>
                            <div>
                                <span class="text-gray-500">Traffic:</span>
                                <div class="font-medium">{{ $experiment->traffic_allocation }}%</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@else
    <div class="text-center py-12">
        <div class="text-gray-400 mb-4">
            <svg class="mx-auto h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
        </div>
        <h3 class="text-lg font-medium text-gray-900">No experiments yet</h3>
        <p class="text-gray-500 mb-6">Get started by creating your first A/B test</p>
        <a href="{{ route('ab-testing.dashboard.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
            Create Experiment
        </a>
    </div>
@endif
@endsection