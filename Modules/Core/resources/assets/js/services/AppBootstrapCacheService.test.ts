import { beforeEach, describe, expect, it, vi } from 'vitest'
import { appBootstrapCacheService } from './AppBootstrapCacheService.ts'

const storage = vi.hoisted(() => ({ setItems: vi.fn(), removeItem: vi.fn() }))
vi.mock('@/modules/core/services/IndexedDbEngine.ts', () => ({ indexedDbEngine: storage }))

describe('partial bootstrap cache updates', () => {
  beforeEach(() => vi.resetAllMocks())

  it('writes translations and their version together without overwriting settings', async () => {
    const translations = { en: { welcome: 'Welcome' } }
    const meta = { settingsVersion: 'old-settings', translationsVersion: 'new-translations' }
    await appBootstrapCacheService.store(null, translations, meta)

    expect(storage.setItems).toHaveBeenCalledOnce()
    const entries = storage.setItems.mock.calls[0]![0]
    expect(Object.keys(entries).sort()).toEqual([
      'app_bootstrap_meta_cache',
      'app_bootstrap_translations_cache',
    ])
    expect(JSON.parse(entries.app_bootstrap_translations_cache).value).toEqual(translations)
    expect(JSON.parse(entries.app_bootstrap_meta_cache).value).toEqual(meta)
    expect(storage.removeItem).not.toHaveBeenCalled()
  })

  it('invalidates metadata when new data has no confirmed version', async () => {
    await appBootstrapCacheService.store(null, { en: {} }, null)
    expect(storage.removeItem).toHaveBeenCalledExactlyOnceWith('app_bootstrap_meta_cache')
  })
})
