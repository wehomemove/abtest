@extends('ab-testing::dashboard.layout')

@section('title', 'Edit ' . $experiment->name)

@section('content')
<div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-900">Edit Experiment</h2>
    <p class="text-gray-600">Modify {{ $experiment->name }} settings</p>
</div>

<div class="bg-white shadow rounded-lg">
    <form action="{{ route('ab-testing.dashboard.update', $experiment) }}" method="POST" class="p-6">
        @csrf
        @method('PUT')
        
        <div class="space-y-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Experiment Name
                </label>
                <input type="text" name="name" value="{{ old('name', $experiment->name) }}" required
                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Description
                </label>
                <textarea name="description" rows="3"
                          class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">{{ old('description', $experiment->description) }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Variants & Traffic Split
                </label>
                <div class="space-y-3">
                    @foreach($experiment->variants as $variantName => $weight)
                        <div class="flex items-center space-x-3">
                            <input type="text" value="{{ $variantName }}" readonly
                                   class="flex-1 border border-gray-300 rounded-md px-3 py-2 bg-gray-50">
                            <input type="number" name="variants[{{ $variantName }}]" value="{{ old('variants.' . $variantName, $weight) }}"
                                   class="w-24 border border-gray-300 rounded-md px-3 py-2" min="0" max="100">
                            <span class="text-gray-500">%</span>
                        </div>
                    @endforeach
                </div>
                <p class="text-sm text-gray-500 mt-2">Note: Changing variants will affect ongoing tests</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Traffic Allocation
                </label>
                <div class="flex items-center space-x-3">
                    <input type="number" name="traffic_allocation" value="{{ old('traffic_allocation', $experiment->traffic_allocation) }}"
                           min="0" max="100" required
                           class="w-24 border border-gray-300 rounded-md px-3 py-2">
                    <span class="text-gray-500">% of users will see this experiment</span>
                </div>
            </div>

            <div class="flex items-center">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $experiment->is_active) ? 'checked' : '' }}
                       class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                <label class="ml-2 text-sm text-gray-700">
                    Experiment is active
                </label>
            </div>

            <!-- Duration Quick Settings -->
            <div class="bg-blue-50 rounded-lg p-4 mb-4">
                <h4 class="text-sm font-medium text-gray-700 mb-3">Quick Duration Setup:</h4>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-4">
                    <button type="button" onclick="setDuration(7)" 
                            class="px-3 py-2 bg-white border border-blue-200 text-blue-600 rounded-md hover:bg-blue-50 text-sm font-medium">
                        7 Days
                    </button>
                    <button type="button" onclick="setDuration(14)" 
                            class="px-3 py-2 bg-white border border-blue-200 text-blue-600 rounded-md hover:bg-blue-50 text-sm font-medium">
                        2 Weeks
                    </button>
                    <button type="button" onclick="setDuration(30)" 
                            class="px-3 py-2 bg-white border border-blue-200 text-blue-600 rounded-md hover:bg-blue-50 text-sm font-medium">
                        1 Month
                    </button>
                    <button type="button" onclick="setDuration(90)" 
                            class="px-3 py-2 bg-white border border-blue-200 text-blue-600 rounded-md hover:bg-blue-50 text-sm font-medium">
                        3 Months
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Start Date (Optional)
                    </label>
                    <input type="datetime-local" name="start_date" id="start_date"
                           value="{{ old('start_date', $experiment->start_date?->format('Y-m-d\TH:i')) }}"
                           class="w-full border border-gray-300 rounded-md px-3 py-2">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        End Date (Optional)
                    </label>
                    <input type="datetime-local" name="end_date" id="end_date"
                           value="{{ old('end_date', $experiment->end_date?->format('Y-m-d\TH:i')) }}"
                           class="w-full border border-gray-300 rounded-md px-3 py-2">
                </div>
            </div>
        </div>

        <div class="flex justify-end space-x-3 mt-8 pt-6 border-t">
            <a href="{{ route('ab-testing.dashboard.show', $experiment) }}" 
               class="px-4 py-2 text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">
                Cancel
            </a>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                Update Experiment
            </button>
        </div>
    </form>
</div>

<!-- Delete Form (separate from main form) -->
<div class="mt-4">
    <form action="{{ route('ab-testing.dashboard.destroy', $experiment) }}" method="POST" class="inline"
          onsubmit="return confirm('Are you sure you want to delete this experiment? This cannot be undone.')">
        @csrf
        @method('DELETE')
        <button type="submit" class="px-4 py-2 text-red-700 bg-red-100 rounded-md hover:bg-red-200">
            Delete Experiment
        </button>
    </form>
</div>

<script>
function setDuration(days) {
    const now = new Date();  
    const start = new Date(now);
    const end = new Date(now);
    end.setDate(start.getDate() + days);
    
    // Format for datetime-local input
    document.getElementById('start_date').value = formatDateTimeLocal(start);
    document.getElementById('end_date').value = formatDateTimeLocal(end);
}

function formatDateTimeLocal(date) {
    const offset = date.getTimezoneOffset();
    const localDate = new Date(date.getTime() - (offset * 60 * 1000));
    return localDate.toISOString().slice(0, 16);
}
</script>
@endsection