import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { AxiosHeaders, type AxiosResponse } from 'axios'
import { getUserPreferences, updateUserPreference } from '../api/userPreference.api.ts'
import { useAuthStore } from '@/modules/auth/stores/authStore.ts'
import { useAppStore } from '@/modules/core/stores/appStore.ts'
import { useUserPreferenceStore } from './userPreferenceStore.ts'

vi.mock('../api/userPreference.api.ts', () => ({
  getUserPreferences: vi.fn(),
  updateUserPreference: vi.fn(),
}))

function deferred<T>() {
  let resolve!: (value: T) => void
  const promise = new Promise<T>((done) => {
    resolve = done
  })
  return { promise, resolve }
}

function preferenceResponse(
  body: Record<string, unknown>,
): AxiosResponse<{ body: Record<string, unknown> }> {
  return {
    status: 200,
    statusText: 'OK',
    headers: {},
    config: { headers: new AxiosHeaders() },
    data: { body },
  }
}

describe('user preference store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
    vi.resetAllMocks()

    const app = useAppStore()
    app.settings.supported_locales = ['en', 'ar']
    useAuthStore().activeAccountId = 1
    useAuthStore().token = 'first-token'
  })

  it('applies saved preferences for the active account', async () => {
    vi.mocked(getUserPreferences).mockResolvedValue(
      preferenceResponse({
        'ui.language': 'ar',
        'ui.theme_mode': 'dark',
        'ui.sidebar_open': false,
        'ui.dashboard.layout': [{ key: 'total_sales', visible: true }],
        'ui.dashboard.filters': { from: '2026-09-01', to: '2026-09-27', branch_id: null },
      }),
    )

    const preferences = useUserPreferenceStore()
    await preferences.load()

    expect(getUserPreferences).toHaveBeenCalledWith('first-token')
    expect(useAppStore().appCurrentLocale).toBe('ar')
    expect(useAppStore().appThemeMode).toBe('dark')
    expect(preferences.values?.sidebarOpen).toBe(false)
    expect(preferences.values?.dashboardLayout).toEqual([{ key: 'total_sales', visible: true }])
    expect(preferences.values?.dashboardFilters).toMatchObject({ from: '2026-09-01' })
    expect(preferences.serverLoaded).toBe(true)
    expect(localStorage.getItem('forkiva.layout.verticalNavRail')).toBe('true')
  })

  it('ignores an older account response after switching accounts', async () => {
    const first = deferred<Awaited<ReturnType<typeof getUserPreferences>>>()
    vi.mocked(getUserPreferences)
      .mockReturnValueOnce(first.promise)
      .mockResolvedValueOnce(preferenceResponse({ 'ui.theme_mode': 'light' }))

    const preferences = useUserPreferenceStore()
    const firstLoad = preferences.load()
    const auth = useAuthStore()
    auth.activeAccountId = 2
    auth.token = 'second-token'
    await preferences.load()

    first.resolve(preferenceResponse({ 'ui.theme_mode': 'dark' }))
    await firstLoad

    expect(preferences.accountId).toBe(2)
    expect(preferences.values?.themeMode).toBe('light')
  })

  it('sends a write with the account token captured at the user action', async () => {
    vi.mocked(updateUserPreference).mockResolvedValue(preferenceResponse({}))

    const saved = await useUserPreferenceStore().save('sidebarOpen', false)

    expect(saved).toBe(true)
    expect(updateUserPreference).toHaveBeenCalledWith('sidebarOpen', false, 'first-token')
  })

  it('saves dashboard settings through the user preference API', async () => {
    vi.mocked(updateUserPreference).mockResolvedValue(preferenceResponse({}))
    const preferences = useUserPreferenceStore()
    const layout = [{ key: 'total_sales', visible: true }]

    expect(await preferences.save('dashboardLayout', layout)).toBe(true)
    expect(updateUserPreference).toHaveBeenCalledWith('dashboardLayout', layout, 'first-token')
  })
})
