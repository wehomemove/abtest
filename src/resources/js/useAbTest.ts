/**
 * Vue 3 Composable for A/B Testing (TypeScript)
 * 
 * Usage:
 * import { useAbTest } from '@/composables/useAbTest'
 * 
 * const { variant, loading, track, isVariant } = useAbTest('survey_red_buttons')
 */

import { ref, computed, onMounted, onUnmounted, type Ref, type ComputedRef } from 'vue'

interface TrackingProperties {
  [key: string]: any
}

interface ApiResponse {
  success: boolean
  variant?: string
  message?: string
}

interface UseAbTestReturn {
  variant: ComputedRef<string>
  loading: ComputedRef<boolean>
  error: ComputedRef<string | null>
  track: (event?: string, properties?: TrackingProperties) => Promise<any>
  refresh: () => Promise<void>
  cleanup: () => void
  isVariant: (variantName: string) => ComputedRef<boolean>
  isControl: ComputedRef<boolean>
  isLoading: ComputedRef<boolean>
  hasError: ComputedRef<boolean>
}

export function useAbTest(experimentName: string, defaultVariant: string = 'control'): UseAbTestReturn {
  const variant: Ref<string> = ref(defaultVariant)
  const loading: Ref<boolean> = ref(true)
  const error: Ref<string | null> = ref(null)

  // Check for debug panel overrides first
  const checkDebugOverrides = (): string | null => {
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
  const handleVariantChange = (event: CustomEvent) => {
    if (event.detail && event.detail.experiment === experimentName) {
      variant.value = event.detail.variant
      loading.value = false
    }
  }

  // Helper function to get cookies
  const getCookie = (name: string): string | null => {
    const value = `; ${document.cookie}`
    const parts = value.split(`; ${name}=`)
    if (parts.length === 2) return parts.pop()?.split(';').shift() || null
    return null
  }

  // Initialize variant assignment
  const initializeVariant = async (): Promise<void> => {
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

      const data: ApiResponse = await response.json()
      
      if (data.success) {
        variant.value = data.variant || defaultVariant
      } else {
        throw new Error(data.message || 'Failed to get variant')
      }

    } catch (err) {
      console.error(`Failed to get variant for ${experimentName}:`, err)
      error.value = err instanceof Error ? err.message : 'Unknown error'
      variant.value = defaultVariant
    } finally {
      loading.value = false
    }
  }

  // Track events
  const track = async (event: string = 'conversion', properties: TrackingProperties = {}): Promise<any> => {
    try {
      if (typeof (window as any).abtrack === 'function') {
        // Use the auto-injected helper if available
        return await (window as any).abtrack(experimentName, event, {
          ...properties,
          variant: variant.value
        })
      } else {
        // Fallback to direct API call
        const response = await fetch('/api/ab-testing/track', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
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
  const refresh = (): Promise<void> => {
    return initializeVariant()
  }

  // Cleanup function
  const cleanup = (): void => {
    window.removeEventListener('ab-test-variant-changed', handleVariantChange as EventListener)
  }

  // Setup
  onMounted(() => {
    // Listen for debug panel changes
    window.addEventListener('ab-test-variant-changed', handleVariantChange as EventListener)
    
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
    isVariant: (variantName: string) => computed(() => variant.value === variantName),
    isControl: computed(() => variant.value === 'control'),
    
    // Quick variant checks
    isLoading: computed(() => loading.value),
    hasError: computed(() => !!error.value)
  }
}