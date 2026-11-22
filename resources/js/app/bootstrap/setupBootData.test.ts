import { flushPromises } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { AppBootstrapCacheSnapshot } from '@/modules/core/services/AppBootstrapCacheService.ts'
import { setupBootData } from './setupBootData.ts'

const mocks = vi.hoisted(() => ({
  snapshot: vi.fn(),
  store: vi.fn(),
  meta: vi.fn(),
  settings: vi.fn(),
  translations: vi.fn(),
  app: { restoreApp: vi.fn(), setSettings: vi.fn(), setTranslations: vi.fn() },
}))

vi.mock('@/modules/core/services/AppBootstrapCacheService.ts', () => ({
  appBootstrapCacheService: { getSnapshot: mocks.snapshot, store: mocks.store },
}))
vi.mock('@/modules/core/stores/appStore.ts', () => ({ useAppStore: () => mocks.app }))
vi.mock('@/modules/setting/api/setting.api.ts', () => ({
  getAppBootMeta: mocks.meta,
  getAppSettings: mocks.settings,
}))
vi.mock('@/modules/translation/api/translation.api.ts', () => ({
  getAppTranslations: mocks.translations,
}))

const response = <T>(body: T) => ({ data: { body } })
const newMeta = { settingsVersion: 'settings-2', translationsVersion: 'translations-2' }
const oldMeta = { settingsVersion: 'settings-1', translationsVersion: 'translations-1' }
const settings = { app_name: 'Test restaurant' }
const translations = { en: { welcome: 'Welcome' } }
const cached = (meta = oldMeta) => ({
  settings: { value: settings, cachedAt: 1 },
  translations: { value: translations, cachedAt: 1 },
  meta: { value: meta, cachedAt: 1 },
})

function deferred<T>() {
  let resolve!: (value: T) => void
  const promise = new Promise<T>((done) => {
    resolve = done
  })
  return { promise, resolve }
}

describe('bootstrap loading', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    mocks.snapshot.mockResolvedValue({ settings: null, translations: null, meta: null })
    mocks.meta.mockResolvedValue(
      response({
        settings_version: newMeta.settingsVersion,
        translations_version: newMeta.translationsVersion,
      }),
    )
    mocks.settings.mockResolvedValue(response(settings))
    mocks.translations.mockResolvedValue(response(translations))
  })

  afterEach(() => vi.restoreAllMocks())

  it('starts all three cold-cache requests before any of them completes', async () => {
    const metaRequest = deferred<ReturnType<typeof response>>()
    const settingsRequest = deferred<ReturnType<typeof response>>()
    const translationsRequest = deferred<ReturnType<typeof response>>()
    mocks.meta.mockReturnValue(metaRequest.promise)
    mocks.settings.mockReturnValue(settingsRequest.promise)
    mocks.translations.mockReturnValue(translationsRequest.promise)

    const boot = setupBootData()
    await flushPromises()
    expect(mocks.meta).toHaveBeenCalledOnce()
    expect(mocks.settings).toHaveBeenCalledOnce()
    expect(mocks.translations).toHaveBeenCalledOnce()
    expect(mocks.store).not.toHaveBeenCalled()

    metaRequest.resolve(
      response({ settings_version: 'settings-2', translations_version: 'translations-2' }),
    )
    settingsRequest.resolve(response(settings))
    translationsRequest.resolve(response(translations))
    await boot

    expect(mocks.store).toHaveBeenCalledExactlyOnceWith(settings, translations, newMeta)
    expect(mocks.app.setSettings).toHaveBeenCalledWith(settings)
    expect(mocks.app.setTranslations).toHaveBeenCalledWith(translations)
  })

  it('reuses current cached data without fetching or rewriting it', async () => {
    mocks.snapshot.mockResolvedValue(cached(newMeta))
    await setupBootData()

    expect(mocks.meta).toHaveBeenCalledOnce()
    expect(mocks.settings).not.toHaveBeenCalled()
    expect(mocks.translations).not.toHaveBeenCalled()
    expect(mocks.store).not.toHaveBeenCalled()
    expect(mocks.app.setSettings).toHaveBeenCalledWith(settings)
    expect(mocks.app.setTranslations).toHaveBeenCalledWith(translations)
  })

  it('refreshes stale settings and translations together after checking versions', async () => {
    mocks.snapshot.mockResolvedValue(cached())
    const settingsRequest = deferred<ReturnType<typeof response>>()
    const translationsRequest = deferred<ReturnType<typeof response>>()
    mocks.settings.mockReturnValue(settingsRequest.promise)
    mocks.translations.mockReturnValue(translationsRequest.promise)

    const boot = setupBootData()
    await flushPromises()
    expect(mocks.settings).toHaveBeenCalledOnce()
    expect(mocks.translations).toHaveBeenCalledOnce()

    settingsRequest.resolve(response(settings))
    translationsRequest.resolve(response(translations))
    await boot
    expect(mocks.store).toHaveBeenCalledExactlyOnceWith(settings, translations, newMeta)
  })

  it('retains the failed resource version so the next boot retries only that resource', async () => {
    mocks.snapshot.mockResolvedValue(cached())
    mocks.translations.mockRejectedValueOnce(new Error('Unavailable'))
    vi.spyOn(console, 'error').mockImplementation(() => {})

    await setupBootData()
    const partialMeta = { ...newMeta, translationsVersion: oldMeta.translationsVersion }
    expect(mocks.store).toHaveBeenCalledWith(settings, null, partialMeta)
    expect(mocks.app.setTranslations).toHaveBeenCalledExactlyOnceWith(translations)

    mocks.snapshot.mockResolvedValue(cached(partialMeta))
    await setupBootData()
    expect(mocks.settings).toHaveBeenCalledOnce()
    expect(mocks.translations).toHaveBeenCalledTimes(2)
    expect(mocks.store).toHaveBeenLastCalledWith(null, translations, newMeta)
  })

  it('keeps a complete cache usable when offline', async () => {
    mocks.snapshot.mockResolvedValue(cached())
    mocks.meta.mockRejectedValue(new Error('Offline'))
    await setupBootData()

    expect(mocks.settings).not.toHaveBeenCalled()
    expect(mocks.translations).not.toHaveBeenCalled()
    expect(mocks.store).not.toHaveBeenCalled()
    expect(mocks.app.setTranslations).toHaveBeenCalledWith(translations)
  })

  it('fetches only missing data even when version metadata is unavailable', async () => {
    const snapshot = { ...cached(), translations: null } as unknown as AppBootstrapCacheSnapshot
    mocks.snapshot.mockResolvedValue(snapshot)
    mocks.meta.mockRejectedValue(new Error('Unavailable'))
    await setupBootData()

    expect(mocks.settings).not.toHaveBeenCalled()
    expect(mocks.translations).toHaveBeenCalledOnce()
    expect(mocks.store).toHaveBeenCalledWith(null, translations, null)
  })

  it('still initializes fresh data when writing the cache fails', async () => {
    mocks.store.mockRejectedValue(new Error('Storage unavailable'))
    await expect(setupBootData()).resolves.toBeUndefined()
    expect(mocks.app.setSettings).toHaveBeenCalledWith(settings)
    expect(mocks.app.setTranslations).toHaveBeenCalledWith(translations)
  })
})
