/**
 * Vue 3 Composable for A/B Testing
 * 
 * Usage:
 * import { useAbTest } from '@/composables/useAbTest'
 * 
 * const { variant, loading, track, isVariant } = useAbTest('survey_red_buttons')
 */

import { ref, computed, onMounted, onUnmounted } from 'vue'

export function useAbTest(experimentName, defaultVariant = 'control') {
  const variant = ref(defaultVariant)
  const loading = ref(true)
  const error = ref(null)

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

  // Helper function to get cookies
  const getCookie = (name) => {
    const value = `; ${document.cookie}`
    const parts = value.split(`; ${name}=`)
    if (parts.length === 2) return parts.pop().split(';').shift()
    return null
  }

  // Initialize variant assignment
  const initializeVariant = async () => {
    try {
      loading.value = true
      error.value = null

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

      // 3. Fetch from Laravel backend API
      const response = await fetch(`/api/ab-testing/variant/${experimentName}`)
      
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`)
      }

      const data = await response.json()
      
      if (data.success) {
        variant.value = data.variant || defaultVariant
      } else {
        throw new Error(data.message || 'Failed to get variant')
      }

    } catch (err) {
      console.error(`Failed to get variant for ${experimentName}:`, err)
      error.value = err.message
      variant.value = defaultVariant
    } finally {
      loading.value = false
    }
  }

  // Track events
  const track = async (event = 'conversion', properties = {}) => {
    try {
      if (typeof window.abtrack === 'function') {
        // Use the auto-injected helper if available
        return await window.abtrack(experimentName, event, {
          ...properties,
          variant: variant.value
        })
      } else {
        // Fallback to direct API call
        const response = await fetch('/api/ab-testing/track', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
          },
          body: JSON.stringify({
            experiment: experimentName,
            event: event,
            properties: {
              ...properties,
              variant: variant.value
            }
          })
        })

        if (!response.ok) {
          throw new Error(`Tracking failed: ${response.status}`)
        }

        return await response.json()
      }
    } catch (err) {
      console.error(`Failed to track event for ${experimentName}:`, err)
      throw err
    }
  }

  // Refresh variant (useful for manual updates)
  const refresh = () => {
    return initializeVariant()
  }

  // Cleanup function
  const cleanup = () => {
    window.removeEventListener('ab-test-variant-changed', handleVariantChange)
  }

  // Setup
  onMounted(() => {
    // Listen for debug panel changes
    window.addEventListener('ab-test-variant-changed', handleVariantChange)
    
    // Initialize the variant
    initializeVariant()
  })

  // Cleanup on unmount
  onUnmounted(() => {
    cleanup()
  })

  return {
    // Reactive state
    variant: computed(() => variant.value),
    loading: computed(() => loading.value),
    error: computed(() => error.value),
    
    // Helper methods
    track,
    refresh,
    cleanup,
    
    // Computed helpers for common use cases
    isVariant: (variantName) => computed(() => variant.value === variantName),
    isControl: computed(() => variant.value === 'control'),
    
    // Quick variant checks
    isLoading: computed(() => loading.value),
    hasError: computed(() => !!error.value)
  }
}