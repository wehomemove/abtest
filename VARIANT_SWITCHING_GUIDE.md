# Debug Panel Variant Switching Integration Guide

Your debug panel variant switching is now fully functional! Here's how to integrate it with your Vue component:

## How It Works

1. **Debug Panel Sets Overrides**: When you click a variant button, the debug panel sets:
   - Laravel override cookies: `ab_test_override_{experiment}`
   - localStorage overrides: `ab_test_{experiment}` and `ab_test_overrides`

2. **Vue Composable Checks Overrides**: Your Vue component should check localStorage first, then Laravel cookies

3. **Laravel Respects Overrides**: The AbTestService checks override cookies before normal assignment

## Update Your Vue Composable

Replace your existing `useAbTest` composable with the enhanced version in `example-vue-composable.js`. The key changes:

```javascript
// Check for debug panel overrides first
const checkDebugOverrides = () => {
  // Individual override (set by debug panel)
  const individualOverride = localStorage.getItem(`ab_test_${experimentName}`)
  if (individualOverride) return individualOverride

  // Global overrides object (set by debug panel)  
  const globalOverrides = localStorage.getItem('ab_test_overrides')
  if (globalOverrides) {
    const overrides = JSON.parse(globalOverrides)
    if (overrides[experimentName]) return overrides[experimentName]
  }
  
  return null
}
```

## API Changes

Added a new GET endpoint for Vue components:
```javascript
// New: GET request (easier for Vue)
const response = await fetch(`/api/ab-testing/variant/${experimentName}`)

// Existing: POST request (still works)
const response = await fetch('/api/ab-testing/variant', {
  method: 'POST',
  body: JSON.stringify({ experiment: experimentName })
})
```

## Testing the Integration

1. **Load your page** with a Vue component using A/B tests
2. **Open the debug panel** (bottom right corner)
3. **Click a different variant** in the debug panel
4. **Page reloads** and your Vue component should show the new variant
5. **Verify localStorage** contains the override: `localStorage.getItem('ab_test_survey_red_buttons')`

## Example Vue Template

```vue
<template>
  <div>
    <!-- Red button variant -->
    <button 
      v-if="surveyTest.isVariant('red_buttons').value"
      class="ab-test-red-button bg-red-500 text-white px-4 py-2 rounded"
      @click="surveyTest.track('button_click')"
    >
      Red Submit Button
    </button>
    
    <!-- Control variant -->
    <button 
      v-else
      class="bg-blue-500 text-white px-4 py-2 rounded"
      @click="surveyTest.track('button_click')"
    >
      Normal Submit Button
    </button>
  </div>
</template>

<script setup>
const surveyTest = useAbTest('survey_red_buttons', 'control')
</script>
```

## Troubleshooting

**Debug panel not switching variants?**
- Check browser console for errors
- Verify localStorage contains: `ab_test_{experiment_name}`
- Check cookies contain: `ab_test_override_{experiment_name}`

**Vue component not updating?**
- Ensure your composable checks localStorage overrides first
- Verify the experiment name matches exactly
- Check that `ab-test-variant-changed` event listener is working

**Still seeing old variant after switching?**
- Hard refresh the page (Cmd+Shift+R / Ctrl+Shift+F5)
- Clear browser cache and cookies
- Check if caching is interfering with the API response

The variant switching functionality is now complete and ready to use with your Vue components!