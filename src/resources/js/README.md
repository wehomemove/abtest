# Vue 3 A/B Testing Composable

This package includes a ready-to-use Vue 3 composable for easy frontend A/B testing integration.

## Installation

Copy the composable file to your Vue project:

```bash
# JavaScript version
cp vendor/homemove/ab-testing/src/resources/js/useAbTest.js resources/js/composables/

# TypeScript version  
cp vendor/homemove/ab-testing/src/resources/js/useAbTest.ts resources/js/composables/
```

## Basic Usage

```vue
<template>
  <div>
    <!-- Show different content based on variant -->
    <button 
      v-if="surveyTest.isVariant('red_buttons').value"
      class="ab-test-red-button bg-red-500 text-white px-4 py-2 rounded"
      @click="surveyTest.track('button_click')"
    >
      Red Submit Button
    </button>
    
    <button 
      v-else
      class="bg-blue-500 text-white px-4 py-2 rounded"
      @click="surveyTest.track('button_click')"
    >
      Normal Submit Button
    </button>
    
    <!-- Loading state -->
    <div v-if="surveyTest.loading.value" class="text-gray-500">
      Loading experiment...
    </div>
    
    <!-- Error state -->
    <div v-if="surveyTest.hasError.value" class="text-red-500">
      A/B test error: {{ surveyTest.error.value }}
    </div>
  </div>
</template>

<script setup>
import { useAbTest } from '@/composables/useAbTest'

// Initialize the A/B test
const surveyTest = useAbTest('survey_red_buttons', 'control')

// Clean up when component unmounts
onUnmounted(() => {
  surveyTest.cleanup()
})
</script>
```

## Advanced Usage

```vue
<script setup>
import { useAbTest } from '@/composables/useAbTest'
import { watch } from 'vue'

const surveyTest = useAbTest('survey_red_buttons')

// React to variant changes
watch(surveyTest.variant, (newVariant) => {
  console.log('Variant changed to:', newVariant)
})

// Track custom events
const handleFormSubmit = async () => {
  try {
    await surveyTest.track('form_submit', {
      page: 'contact',
      source: 'header_cta'
    })
  } catch (error) {
    console.error('Tracking failed:', error)
  }
}

// Manually refresh variant
const refreshExperiment = () => {
  surveyTest.refresh()
}
</script>
```

## API Reference

### `useAbTest(experimentName, defaultVariant?)`

**Parameters:**
- `experimentName`: string - Name of the experiment
- `defaultVariant`: string - Fallback variant (default: 'control')

**Returns:**
- `variant`: ComputedRef<string> - Current variant
- `loading`: ComputedRef<boolean> - Loading state
- `error`: ComputedRef<string | null> - Error message if any
- `track(event?, properties?)`: Function - Track events
- `refresh()`: Function - Refresh variant assignment
- `cleanup()`: Function - Clean up event listeners
- `isVariant(name)`: Function - Check if current variant matches
- `isControl`: ComputedRef<boolean> - True if variant is 'control'
- `isLoading`: ComputedRef<boolean> - Loading state alias
- `hasError`: ComputedRef<boolean> - True if there's an error

## Features

✅ **Debug Panel Integration** - Automatically responds to variant switching  
✅ **Override Support** - Respects localStorage and cookie overrides  
✅ **Loading States** - Proper loading and error handling  
✅ **Event Tracking** - Easy conversion and custom event tracking  
✅ **TypeScript Support** - Full type definitions included  
✅ **Auto-cleanup** - Automatic event listener cleanup  
✅ **Fallback Support** - Graceful degradation if API fails

## Multiple Experiments

```vue
<script setup>
// Run multiple experiments simultaneously
const headerTest = useAbTest('header_color', 'blue')
const buttonTest = useAbTest('button_size', 'medium')
const layoutTest = useAbTest('page_layout', 'standard')

// Each experiment tracks independently
const handleAction = () => {
  headerTest.track('header_click')
  buttonTest.track('button_click')
  layoutTest.track('page_interaction')
}
</script>
```

## Laravel Integration

The composable automatically:
- Fetches variants from `/api/ab-testing/variant/{experiment}`
- Tracks events via `/api/ab-testing/track`
- Respects debug panel overrides
- Handles CSRF tokens for Laravel

No additional configuration needed - just ensure your Laravel A/B testing package is properly installed and configured!