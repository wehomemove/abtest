@extends('ab-testing::dashboard.layout')

@section('title', 'Create Experiment')

@section('content')
<div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-900">Create New Experiment</h2>
    <p class="text-gray-600">Set up a new A/B test</p>
</div>

<div class="bg-white shadow rounded-lg">
    <form action="{{ route('ab-testing.dashboard.store') }}" method="POST" class="p-6" x-data="experimentForm()">
        @csrf
        
        <div class="space-y-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Experiment Name
                </label>
                <input type="text" name="name" value="{{ old('name') }}" required
                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                       placeholder="e.g., checkout_flow">
                <p class="text-sm text-gray-500 mt-1">Use snake_case format for easy reference in code</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Description
                </label>
                <textarea name="description" rows="3"
                          class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                          placeholder="What are you testing?">{{ old('description') }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Variants & Traffic Split
                </label>
                <div class="space-y-3" x-data="{ variants: [{ name: 'control', weight: 50 }, { name: 'variant_b', weight: 50 }] }">
                    <template x-for="(variant, index) in variants" :key="index">
                        <div class="flex items-center space-x-3">
                            <input type="text" x-model="variant.name" :name="`variant_names[${index}]`"
                                   class="flex-1 border border-gray-300 rounded-md px-3 py-2"
                                   placeholder="Variant name">
                            <input type="number" x-model="variant.weight" :name="`variants[${variant.name}]`"
                                   class="w-24 border border-gray-300 rounded-md px-3 py-2" min="0" max="100">
                            <span class="text-gray-500">%</span>
                            <button type="button" @click="variants.splice(index, 1)" x-show="variants.length > 2"
                                    class="text-red-600 hover:text-red-900">Remove</button>
                        </div>
                    </template>
                    
                    <button type="button" @click="variants.push({ name: '', weight: 0 })"
                            class="text-blue-600 hover:text-blue-900 text-sm">+ Add Variant</button>
                    
                    <p class="text-sm text-gray-500">
                        Total: <span x-text="variants.reduce((sum, v) => sum + parseInt(v.weight || 0), 0)"></span>% (must equal 100%)
                    </p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Funnel steps <span class="text-gray-400 font-normal">(optional — ordered event names for the dashboard funnel)</span>
                </label>
                <div x-data="{ steps: @json(old('funnel_steps', [])) }" class="space-y-2">
                    <template x-for="(step, index) in steps" :key="index">
                        <div class="flex items-center space-x-2">
                            <span class="text-xs text-gray-400 w-5 text-right" x-text="index + 1 + '.'"></span>
                            <input type="text" x-model="steps[index]" name="funnel_steps[]"
                                   placeholder="e.g. flow_viewed"
                                   class="flex-1 border border-gray-300 rounded-md px-3 py-2">
                            <button type="button" @click="index > 0 && steps.splice(index - 1, 0, steps.splice(index, 1)[0])"
                                    class="px-2 py-1 text-gray-400 hover:text-gray-700" title="Move up">↑</button>
                            <button type="button" @click="index < steps.length - 1 && steps.splice(index + 1, 0, steps.splice(index, 1)[0])"
                                    class="px-2 py-1 text-gray-400 hover:text-gray-700" title="Move down">↓</button>
                            <button type="button" @click="steps.splice(index, 1)"
                                    class="px-2 py-1 text-red-400 hover:text-red-600" title="Remove">✕</button>
                        </div>
                    </template>
                    <button type="button" @click="steps.push('')"
                            class="text-sm text-blue-600 hover:underline">+ Add step</button>
                    <p class="text-xs text-gray-500">Order drives the funnel display. 'conversion' is always shown last; leave empty to order steps by first observation.</p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Traffic Allocation
                </label>
                <div class="flex items-center space-x-3">
                    <input type="number" name="traffic_allocation" value="{{ old('traffic_allocation', 100) }}"
                           min="0" max="100" required
                           class="w-24 border border-gray-300 rounded-md px-3 py-2">
                    <span class="text-gray-500">% of users will see this experiment</span>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Device Targeting
                </label>
                @php
                    $oldDevices = old('allowed_device_types', ['mobile', 'tablet', 'desktop']);
                @endphp
                <div class="flex flex-wrap gap-4">
                    @foreach (['mobile' => 'Mobile', 'tablet' => 'Tablet', 'desktop' => 'Desktop'] as $value => $label)
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="allowed_device_types[]" value="{{ $value }}"
                                   {{ in_array($value, $oldDevices) ? 'checked' : '' }}
                                   class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                            <span class="ml-2 text-sm text-gray-700">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                <p class="text-sm text-gray-500 mt-2">
                    Leave all checked to include everyone; uncheck to exclude that device class. Excluded users fall through to the control variant and are not recorded as participants.
                </p>
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
                    <input type="datetime-local" name="start_date" id="start_date" value="{{ old('start_date') }}"
                           class="w-full border border-gray-300 rounded-md px-3 py-2">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        End Date (Optional)
                    </label>
                    <input type="datetime-local" name="end_date" id="end_date" value="{{ old('end_date') }}"
                           class="w-full border border-gray-300 rounded-md px-3 py-2">
                </div>
            </div>
        </div>

        <div class="flex justify-end space-x-3 mt-8 pt-6 border-t">
            <a href="{{ route('ab-testing.dashboard.index') }}" 
               class="px-4 py-2 text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">
                Cancel
            </a>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                Create Experiment
            </button>
        </div>
    </form>
</div>

<script>
function experimentForm() {
    return {
        variants: [
            { name: 'control', weight: 50 },
            { name: 'variant_b', weight: 50 }
        ]
    }
}

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