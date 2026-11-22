import { type ComputedRef, onMounted, onUnmounted, ref, watch } from 'vue'

export interface KitchenAutoReloadSettings {
  enabled: boolean
  mode: 'smart_polling'
  interval: number
  pause_on_idle: boolean
  idle_timeout: number
}

export interface KitchenFetchResponse {
  last_updated_at: string | null
}

export interface KitchenFetchContext {
  requestId: number
  isCurrent: () => boolean
}

interface UseKitchenAutoReloadOptions {
  settings: ComputedRef<KitchenAutoReloadSettings | null>
  fetchData: (context: KitchenFetchContext) => Promise<KitchenFetchResponse>
}

export function useKitchenAutoReload({ settings, fetchData }: UseKitchenAutoReloadOptions) {
  const timer = ref<number | null>(null)
  const idleTimer = ref<number | null>(null)
  const inFlightRequestId = ref<number | null>(null)
  const lastHash = ref<string | null>(null)
  const isPaused = ref<boolean>(false)
  let requestSequence = 0

  const invalidate = (): void => {
    requestSequence += 1
    inFlightRequestId.value = null
  }

  const stop = (): void => {
    if (timer.value !== null) {
      window.clearInterval(timer.value)
      timer.value = null
    }

    invalidate()
  }

  const start = (): void => {
    const config = settings.value

    if (!config || !config.enabled || config.mode !== 'smart_polling') {
      return
    }

    stop()
    timer.value = window.setInterval(run, Math.max(1000, Number(config.interval) || 5000))
  }

  const run = async (): Promise<void> => {
    const config = settings.value

    if (
      !config?.enabled ||
      config.mode !== 'smart_polling' ||
      isPaused.value ||
      inFlightRequestId.value !== null
    ) {
      return
    }

    const requestId = requestSequence + 1

    requestSequence = requestId
    inFlightRequestId.value = requestId

    const isCurrent = () => inFlightRequestId.value === requestId

    try {
      const response = await fetchData({ requestId, isCurrent })

      if (!isCurrent()) {
        return
      }

      if (lastHash.value !== response.last_updated_at) {
        lastHash.value = response.last_updated_at
      }
    } catch {
      if (isCurrent()) {
        lastHash.value = null
      }
    } finally {
      if (isCurrent()) {
        inFlightRequestId.value = null
      }
    }
  }

  const resetIdle = (): void => {
    const config = settings.value
    isPaused.value = false
    if (idleTimer.value !== null) window.clearTimeout(idleTimer.value)
    if (!config || !config.pause_on_idle) return

    idleTimer.value = window.setTimeout(() => {
      isPaused.value = true
    }, config.idle_timeout * 1000)
  }

  watch(
    settings,
    () => {
      stop()
      start()
      resetIdle()
    },
    { deep: true },
  )

  onMounted(() => {
    start()

    window.addEventListener('mousemove', resetIdle)
    window.addEventListener('pointerdown', resetIdle)
    window.addEventListener('keydown', resetIdle)
    resetIdle()
  })

  onUnmounted(() => {
    stop()

    if (idleTimer.value !== null) {
      window.clearTimeout(idleTimer.value)
    }

    window.removeEventListener('mousemove', resetIdle)
    window.removeEventListener('pointerdown', resetIdle)
    window.removeEventListener('keydown', resetIdle)
  })

  return {
    isPaused,
    invalidate,
    start,
    stop,
  }
}
