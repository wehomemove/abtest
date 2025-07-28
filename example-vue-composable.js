// Enhanced Vue Composable for A/B Testing with Debug Panel Support
// Copy this logic into your existing useAbTest composable

import { ref, computed, onMounted } from 'vue'

export function useAbTest(experimentName, defaultVariant = 'control') {
  const variant = ref(defaultVariant)
  const loading = ref(true)

  // Check for debug panel overrides first
  const checkDebugOverrides = () => {
    // Check individual localStorage override (set by debug panel)
    const individualOverride = localStorage.getItem(`ab_test_${experimentName}`)
    if (individualOverride) {
      return individualOverride
    }

    // Check global overrides object (set by debug panel)
    const globalOverrides = localStorage.getItem('ab_test_overrides')
    if (globalOverrides) {
      try {
        const overrides = JSON.parse(globalOverrides)
        if (overrides[experimentName]) {
          return overrides[experimentName]
        }
      } catch (e) {
        console.warn('Failed to parse ab_test_overrides from localStorage')
      }
    }

    return null
  }

  // Listen for debug panel variant changes
  const handleVariantChange = (event) => {
    if (event.detail && event.detail.experiment === experimentName) {
      variant.value = event.detail.variant
      loading.value = false
    }
  }

  const initializeVariant = async () => {
    try {
      // 1. First check for debug overrides
      const debugOverride = checkDebugOverrides()
      if (debugOverride) {
        variant.value = debugOverride
        loading.value = false
        return
      }

      // 2. Check if Laravel has set an override cookie
      const laravelOverride = getCookie(`ab_test_override_${experimentName}`)
      if (laravelOverride) {
        variant.value = laravelOverride
        loading.value = false
        return
      }

      // 3. Fetch from your Laravel backend API
      const response = await fetch(`/api/ab-testing/variant/${experimentName}`)
      if (response.ok) {
        const data = await response.json()
        variant.value = data.variant || defaultVariant
      } else {
        variant.value = defaultVariant
      }
    } catch (error) {
      console.error(`Failed to get variant for ${experimentName}:`, error)
      variant.value = defaultVariant
    } finally {
      loading.value = false
    }
  }

  // Helper function to get cookies
  const getCookie = (name) => {
    const value = `; ${document.cookie}`
    const parts = value.split(`; ${name}=`)
    if (parts.length === 2) return parts.pop().split(';').shift()
    return null
  }

  // Track events
  const track = (event = 'conversion', properties = {}) => {
    if (typeof window.abtrack === 'function') {
      window.abtrack(experimentName, event, {
        ...properties,
        variant: variant.value
      })
    }
  }

  onMounted(() => {
    // Listen for debug panel changes
    window.addEventListener('ab-test-variant-changed', handleVariantChange)
    
    // Initialize the variant
    initializeVariant()
  })

  // Clean up event listener
  const cleanup = () => {
    window.removeEventListener('ab-test-variant-changed', handleVariantChange)
  }

  return {
    variant: computed(() => variant.value),
    loading: computed(() => loading.value),
    track,
    cleanup,
    
    // Helper methods for common use cases
    isVariant: (variantName) => computed(() => variant.value === variantName),
    isControl: computed(() => variant.value === 'control'),
    
    // Force refresh (useful if you need to manually update)
    refresh: initializeVariant
  }
}

// Example usage in your Vue component:
/*
<template>
  <div>
    <!-- Show red buttons if user is in red_buttons variant -->
    <button 
      v-if="surveyTest.isVariant('red_buttons').value"
      class="ab-test-red-button bg-red-500 text-white px-4 py-2 rounded"
      @click="surveyTest.track('button_click')"
    >
      Red Submit Button
    </button>
    
    <!-- Show normal button for control -->
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
import { useAbTest } from './composables/useAbTest'

const surveyTest = useAbTest('survey_red_buttons', 'control')

// Clean up when component unmounts
onUnmounted(() => {
  surveyTest.cleanup()
})
</script>
*/