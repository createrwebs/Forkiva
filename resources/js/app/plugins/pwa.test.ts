import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const { registerSW, appStore } = vi.hoisted(() => ({
  registerSW: vi.fn(),
  appStore: {
    settings: {
      pwa_enabled: true,
      pwa_name: 'Forkiva',
      pwa_short_name: 'Forkiva',
      pwa_icon: '/icon.png',
    },
  },
}))

vi.mock('virtual:pwa-register', () => ({ registerSW }))
vi.mock('@/modules/core/stores/appStore.ts', () => ({ useAppStore: () => appStore }))

describe('PWA registration and settings', () => {
  const unregister = vi.fn().mockResolvedValue(true)
  const unregisterOther = vi.fn().mockResolvedValue(true)
  const getRegistrations = vi.fn()

  beforeEach(() => {
    vi.resetModules()
    vi.clearAllMocks()
    vi.stubEnv('DEV', false)
    // Laravel's asset base must never become the application's navigation scope.
    vi.stubEnv('BASE_URL', '/build/')
    vi.stubGlobal('navigator', {
      onLine: true,
      serviceWorker: { getRegistrations },
    })
    getRegistrations.mockResolvedValue([
      { scope: `${window.location.origin}/`, unregister },
      { scope: `${window.location.origin}/other/`, unregister: unregisterOther },
    ])
    appStore.settings.pwa_enabled = true
    appStore.settings.pwa_name = 'Forkiva'
    document.head.innerHTML = ''
    localStorage.clear()
  })

  afterEach(() => {
    vi.unstubAllGlobals()
    vi.unstubAllEnvs()
  })

  it('updates the manifest after settings change without duplicate registration', async () => {
    const { setupPwa } = await import('./pwa')
    setupPwa()
    appStore.settings.pwa_name = 'Restaurant'
    setupPwa()

    const link = document.querySelector<HTMLLinkElement>('link[rel="manifest"]')!
    const manifest = JSON.parse(decodeURIComponent(link.href.split(',')[1]!))
    expect(manifest.name).toBe('Restaurant')
    expect(manifest.start_url).toBe(`${window.location.origin}/`)
    expect(document.querySelectorAll('link[rel="manifest"]')).toHaveLength(1)
    expect(registerSW).toHaveBeenCalledOnce()
    expect(registerSW).toHaveBeenCalledWith({
      immediate: true,
      onRegisterError: expect.any(Function),
    })
  })

  it('disables only the service worker for this application scope', async () => {
    const { setupPwa } = await import('./pwa')
    setupPwa()
    appStore.settings.pwa_enabled = false
    setupPwa()
    await vi.waitFor(() => expect(unregister).toHaveBeenCalledOnce())
    expect(unregisterOther).not.toHaveBeenCalled()
    expect(document.querySelector('link[rel="manifest"]')).toBeNull()
    expect(localStorage.getItem('pwa_enabled')).toBeNull()
  })

  it('preserves the registration when settings re-enable PWA during cleanup', async () => {
    const { setupPwa } = await import('./pwa')
    appStore.settings.pwa_enabled = false
    setupPwa()
    appStore.settings.pwa_enabled = true
    setupPwa()
    await Promise.resolve()
    expect(unregister).not.toHaveBeenCalled()
  })

  it('reports registration failure and allows a later attempt', async () => {
    const error = new Error('Service worker unavailable')
    const log = vi.spyOn(console, 'error').mockImplementation(() => {})
    const { setupPwa } = await import('./pwa')
    setupPwa()
    registerSW.mock.calls[0]![0].onRegisterError(error)
    setupPwa()
    expect(registerSW).toHaveBeenCalledTimes(2)
    expect(log).toHaveBeenCalledWith('Failed to register the PWA service worker.', error)
    log.mockRestore()
  })

  it('does not register or remove production workers in development', async () => {
    vi.stubEnv('DEV', true)
    const { setupPwa } = await import('./pwa')
    setupPwa()
    expect(registerSW).not.toHaveBeenCalled()
    expect(getRegistrations).not.toHaveBeenCalled()
    expect(document.querySelector('link[rel="manifest"]')).toBeNull()
  })
})
