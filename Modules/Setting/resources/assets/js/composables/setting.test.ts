import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useSetting } from './setting'

const { update, setSettings, setupPwa, storeSettings } = vi.hoisted(() => ({
  update: vi.fn(),
  setSettings: vi.fn(),
  setupPwa: vi.fn(),
  storeSettings: vi.fn(),
}))

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))
vi.mock('vue-toastification', () => ({ useToast: () => ({ error: vi.fn() }) }))
vi.mock('@/app/plugins/pwa.ts', () => ({ setupPwa }))
vi.mock('@/app/plugins/vuetify/index.ts', () => ({ syncVuetifyThemeSettings: vi.fn() }))
vi.mock('@/modules/core/services/AppBootstrapCacheService.ts', () => ({
  appBootstrapCacheService: { storeSettings },
}))
vi.mock('@/modules/core/stores/appStore.ts', () => ({
  useAppStore: () => ({ setSettings }),
}))
vi.mock('@/modules/setting/stores/settingsStore.ts', () => ({ useSettingsStore: () => ({}) }))
vi.mock('@/modules/setting/api/setting.api.ts', () => ({ update }))

describe('applying saved PWA settings', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    storeSettings.mockResolvedValue(undefined)
  })

  it.each([true, false])('applies and caches PWA enabled=%s without a reload', async (enabled) => {
    const settings = { pwa_enabled: enabled, pwa_name: 'Restaurant' }
    const response = { data: { body: { app_settings: settings } } }
    update.mockResolvedValue(response)
    setupPwa.mockImplementation(() => expect(setSettings).toHaveBeenCalledWith(settings))

    expect(await useSetting().update('pwa', settings)).toBe(response)
    expect(setupPwa).toHaveBeenCalledOnce()
    expect(storeSettings).toHaveBeenCalledWith(settings, null)
  })

  it('keeps a successful settings save successful when caching fails', async () => {
    const response = { data: { body: { app_settings: {} } } }
    update.mockResolvedValue(response)
    setupPwa.mockReset()
    storeSettings.mockRejectedValue(new Error('Storage unavailable'))
    await expect(useSetting().update('pwa', {})).resolves.toBe(response)
    expect(setupPwa).toHaveBeenCalledOnce()
  })

  it('does not change PWA registration when saving unrelated settings', async () => {
    update.mockResolvedValue({ data: { body: { app_settings: {} } } })
    await useSetting().update('general', {})
    expect(setupPwa).not.toHaveBeenCalled()
  })
})
