import type { AppSettings } from '@/modules/core/contracts/AppState.ts'
import {
  appBootstrapCacheService,
  type AppBootstrapCacheSnapshot,
  type AppBootstrapMeta,
} from '@/modules/core/services/AppBootstrapCacheService.ts'
import { useAppStore } from '@/modules/core/stores/appStore.ts'
import { getAppBootMeta, getAppSettings } from '@/modules/setting/api/setting.api.ts'
import { getAppTranslations } from '@/modules/translation/api/translation.api.ts'

export async function setupBootData(): Promise<void> {
  const appStore = useAppStore()
  appStore.restoreApp()

  const cachedBootstrap = await appBootstrapCacheService.getSnapshot()
  const cachedMeta = cachedBootstrap.meta?.value || null

  applyCachedBootData(cachedBootstrap)

  // Missing resources do not need to wait for the version check on a first visit.
  const missingSettings = cachedBootstrap.settings ? null : fetchSettings()
  const missingTranslations = cachedBootstrap.translations ? null : fetchTranslations()
  const latestMeta = await fetchBootMeta()
  const [settings, translations] = await Promise.all([
    missingSettings ??
      (shouldRefreshSettings(cachedBootstrap, cachedMeta, latestMeta) ? fetchSettings() : null),
    missingTranslations ??
      (shouldRefreshTranslations(cachedBootstrap, cachedMeta, latestMeta)
        ? fetchTranslations()
        : null),
  ])

  if (!settings && !translations) {
    return
  }

  // A failed refresh retains its old version so the next boot retries it.
  const nextMeta: AppBootstrapMeta | null = latestMeta
    ? {
        settingsVersion: settings
          ? latestMeta.settingsVersion
          : (cachedMeta?.settingsVersion ?? ''),
        translationsVersion: translations
          ? latestMeta.translationsVersion
          : (cachedMeta?.translationsVersion ?? ''),
      }
    : null

  try {
    await appBootstrapCacheService.store(settings, translations, nextMeta)
  } catch {
    // Fresh data can still initialize the UI when persistent storage is unavailable.
  }
}

async function fetchBootMeta(): Promise<AppBootstrapMeta | null> {
  try {
    const response = await getAppBootMeta()

    return {
      settingsVersion: response.data.body.settings_version,
      translationsVersion: response.data.body.translations_version,
    }
  } catch {
    return null
  }
}

function applyCachedBootData(cachedBootstrap: AppBootstrapCacheSnapshot): void {
  const appStore = useAppStore()

  if (cachedBootstrap.settings) {
    appStore.setSettings(cachedBootstrap.settings.value)
  }

  if (cachedBootstrap.translations) {
    appStore.setTranslations(cachedBootstrap.translations.value)
  }
}

async function fetchSettings(): Promise<AppSettings | null> {
  try {
    const settings = (await getAppSettings()).data.body
    useAppStore().setSettings(settings)
    return settings
  } catch {
    console.error('Failed to retrieve app settings.')
    return null
  }
}

async function fetchTranslations(): Promise<object | null> {
  try {
    const translations = (await getAppTranslations()).data.body
    useAppStore().setTranslations(translations)
    return translations
  } catch {
    console.error('Failed to retrieve app translations.')
    return null
  }
}

function shouldRefreshSettings(
  cachedBootstrap: AppBootstrapCacheSnapshot,
  cachedMeta: AppBootstrapMeta | null,
  latestMeta: AppBootstrapMeta | null,
): boolean {
  if (!cachedBootstrap.settings) {
    return true
  }

  if (!latestMeta) {
    return false
  }

  if (!cachedMeta) {
    return true
  }

  return cachedMeta.settingsVersion !== latestMeta.settingsVersion
}

function shouldRefreshTranslations(
  cachedBootstrap: AppBootstrapCacheSnapshot,
  cachedMeta: AppBootstrapMeta | null,
  latestMeta: AppBootstrapMeta | null,
): boolean {
  if (!cachedBootstrap.translations) {
    return true
  }

  if (!latestMeta) {
    return false
  }

  if (!cachedMeta) {
    return true
  }

  return cachedMeta.translationsVersion !== latestMeta.translationsVersion
}
