import { registerSW } from 'virtual:pwa-register'
import { localStorageEngine } from '@/modules/core/services/LocalStorageEngine.ts'
import { useAppStore } from '@/modules/core/stores/appStore.ts'

const PWA_ENABLED_STORAGE_KEY = 'pwa_enabled'
let registrationStarted = false

function buildPwaManifestUrl(appStore: ReturnType<typeof useAppStore>): string {
  const settings = appStore.settings
  const baseUrl = '/'
  const appUrl = new URL(baseUrl, window.location.origin)
  const iconUrl = new URL(
    settings.pwa_icon || settings.logo || settings.favicon || '/logo.png',
    window.location.origin,
  ).href
  const fallbackIconUrl = new URL(
    settings.favicon || settings.logo || iconUrl,
    window.location.origin,
  ).href

  const manifest = {
    name: settings.pwa_name || settings.app_name,
    short_name: settings.pwa_short_name || settings.pwa_name || settings.app_name,
    description: settings.pwa_description,
    start_url: appUrl.href,
    scope: appUrl.href,
    display: 'standalone',
    background_color: settings.pwa_background_color,
    theme_color: settings.pwa_theme_color,
    icons: [
      {
        src: iconUrl,
        sizes: '192x192',
        purpose: 'any',
      },
      {
        src: iconUrl,
        sizes: '512x512',
        purpose: 'any maskable',
      },
      {
        src: fallbackIconUrl,
        sizes: '64x64',
        purpose: 'any',
      },
    ],
  }

  return `data:application/manifest+json;charset=utf-8,${encodeURIComponent(JSON.stringify(manifest))}`
}

export function setupPwa() {
  if (import.meta.env.DEV) {
    return
  }

  const appStore = useAppStore()
  const isOnline = typeof navigator === 'undefined' ? true : navigator.onLine
  const isPwaEnabled = appStore.settings.pwa_enabled
  const wasPwaEnabled = localStorageEngine.getItem(PWA_ENABLED_STORAGE_KEY) === 'true'

  if (isPwaEnabled || (!isOnline && wasPwaEnabled)) {
    if (isPwaEnabled) {
      localStorageEngine.setItem(PWA_ENABLED_STORAGE_KEY, 'true')
    }

    const manifestUrl = buildPwaManifestUrl(appStore)
    const manifestLink =
      document.querySelector<HTMLLinkElement>('link[rel="manifest"]') ||
      document.createElement('link')

    manifestLink.setAttribute('rel', 'manifest')
    manifestLink.setAttribute('href', manifestUrl)

    if (!manifestLink.parentNode) {
      document.head.append(manifestLink)
    }

    if (!registrationStarted && 'serviceWorker' in navigator) {
      registrationStarted = true
      registerSW({
        immediate: true,
        onRegisterError(error) {
          registrationStarted = false
          console.error('Failed to register the PWA service worker.', error)
        },
      })
    }
  } else {
    registrationStarted = false
    localStorageEngine.removeItem(PWA_ENABLED_STORAGE_KEY)
    document.querySelector<HTMLLinkElement>('link[rel="manifest"]')?.remove()

    if (isOnline && 'serviceWorker' in navigator) {
      const appScope = new URL('/', window.location.origin).href
      void navigator.serviceWorker
        .getRegistrations()
        .then(async (registrations) => {
          // A settings save can re-enable the PWA while this lookup is in flight.
          if (useAppStore().settings.pwa_enabled) return
          await Promise.all(
            registrations
              .filter((registration) => registration.scope === appScope)
              .map((registration) => registration.unregister()),
          )
        })
        .catch((error) => console.error('Failed to unregister the PWA service worker.', error))
    }
  }
}
