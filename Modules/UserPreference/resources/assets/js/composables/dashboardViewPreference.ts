import { onBeforeUnmount, watch, type Ref, type WatchStopHandle } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/modules/auth/stores/authStore.ts'
import type { DashboardViewPreferenceName } from '../contracts/UserPreference.ts'
import { useUserPreferenceStore } from '../stores/userPreferenceStore.ts'

function compatibleFilterValue(value: unknown, defaultValue: unknown): boolean {
  if (value === null) return defaultValue === null
  if (defaultValue === null) {
    return (typeof value === 'number' && Number.isFinite(value)) || typeof value === 'string'
  }
  if (Array.isArray(defaultValue)) return Array.isArray(value)
  if (typeof value !== typeof defaultValue) return false
  if (typeof value === 'string' && (defaultValue === '' || typeof defaultValue === 'string')) {
    return value.length <= 128
  }
  return typeof value !== 'number' || Number.isFinite(value)
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}

export function useDashboardViewPreference(name: DashboardViewPreferenceName) {
  const preferences = useUserPreferenceStore()
  const auth = useAuthStore()
  const { t } = useI18n()
  const toast = useToast()
  const userId = auth.getUser?.id
  const remote = preferences.serverLoaded ? preferences.values?.[name] : null
  let filtersRestored = false
  let saveFailed = false
  let lastSaved = JSON.stringify(remote)
  let stopTracking: WatchStopHandle | null = null
  onBeforeUnmount(() => stopTracking?.())

  function restoreFilters<T extends object>(defaults: T): T {
    if (filtersRestored) return defaults
    filtersRestored = true
    const saved = remote?.filters
    if (!saved) return defaults

    const result = { ...defaults } as Record<string, unknown>
    for (const [key, defaultValue] of Object.entries(defaults)) {
      if (key in saved && compatibleFilterValue(saved[key], defaultValue)) {
        result[key] = saved[key]
      }
    }
    return result as T
  }

  function restoreWidgetOptions<T extends Record<string, unknown>>(defaults: T): T {
    const saved = remote?.filters?.widget_options
    if (!isRecord(saved)) return defaults
    const result = { ...defaults }
    for (const [key, defaultValue] of Object.entries(defaults)) {
      const value = saved[key]
      if (typeof defaultValue === 'number' && typeof value === 'number' && Number.isFinite(value)) {
        result[key as keyof T] = value as T[keyof T]
      } else if (isRecord(defaultValue) && isRecord(value)) {
        const options = { ...defaultValue }
        for (const [option, fallback] of Object.entries(defaultValue)) {
          if (compatibleFilterValue(value[option], fallback)) options[option] = value[option]
        }
        result[key as keyof T] = options as T[keyof T]
      }
    }
    return result
  }

  function track<Layout, Filters extends object>(
    layout: Ref<Layout[]>,
    readFilters: () => Filters,
    readWidgetOptions?: () => Record<string, unknown>,
  ) {
    if (!preferences.serverLoaded || userId === undefined) return

    async function save() {
      if (auth.getUser?.id !== userId) return
      const filters = { ...readFilters() } as Record<string, unknown>
      if (readWidgetOptions) filters.widget_options = readWidgetOptions()
      const value = { layout: layout.value, filters }
      const serialized = JSON.stringify(value)
      if (serialized === lastSaved) return
      lastSaved = serialized
      const saved = await preferences.save(name, value)
      if (saved) {
        saveFailed = false
      } else {
        lastSaved = ''
        if (!saveFailed) toast.error(t('dashboard::dashboards.errors.save_preferences'))
        saveFailed = true
      }
    }

    stopTracking?.()
    stopTracking = watch(
      [layout, readFilters, readWidgetOptions ?? (() => null)],
      () => void save(),
      {
        deep: true,
        flush: 'post',
      },
    )
    void save()
  }

  return { remoteLayout: remote?.layout, restoreFilters, restoreWidgetOptions, track }
}
