import { flushPromises } from '@vue/test-utils'
import type { App } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { initApp } from './initApp.ts'

const mocks = vi.hoisted(() => ({
  setupBootData: vi.fn(),
  setupStore: vi.fn(),
  setupI18n: vi.fn(),
  setupVuetify: vi.fn(),
  setupToast: vi.fn(),
  loadUserPreferences: vi.fn(),
  authStore: {
    isAuthenticated: false,
    getUser: null,
    restoreAuth: vi.fn(),
    setUser: vi.fn(),
    logout: vi.fn(),
  },
}))

vi.mock('@/app/bootstrap/setupBootData', () => ({ setupBootData: mocks.setupBootData }))
vi.mock('@/app/plugins', () => ({
  setupI18n: mocks.setupI18n,
  setupVuetify: mocks.setupVuetify,
  setupToast: mocks.setupToast,
}))
vi.mock('@/modules/auth/api/auth.api.ts', () => ({ check: vi.fn() }))
vi.mock('@/modules/auth/stores/authStore.ts', () => ({ useAuthStore: () => mocks.authStore }))
vi.mock('@/modules/core/stores', () => ({ setupStore: mocks.setupStore }))
vi.mock('@/modules/userPreference/stores/userPreferenceStore.ts', () => ({
  useUserPreferenceStore: () => ({ load: mocks.loadUserPreferences }),
}))
vi.mock('@/modules/core/directives/english-numbers.ts', () => ({ default: {} }))
vi.mock('@/modules/core/directives/english-numbers-with-decimal.ts', () => ({ default: {} }))

function deferred() {
  let resolve!: () => void
  const promise = new Promise<void>((done) => {
    resolve = done
  })
  return { promise, resolve }
}

describe('application initialization', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    mocks.authStore.isAuthenticated = false
    mocks.authStore.getUser = null
  })

  it('loads boot data and restores auth concurrently before installing UI plugins', async () => {
    const bootData = deferred()
    const auth = deferred()
    mocks.setupBootData.mockReturnValue(bootData.promise)
    mocks.authStore.restoreAuth.mockReturnValue(auth.promise)
    const app = { directive: vi.fn() } as unknown as App

    const initialization = initApp({ app })
    await flushPromises()

    expect(mocks.setupBootData).toHaveBeenCalledOnce()
    expect(mocks.authStore.restoreAuth).toHaveBeenCalledOnce()
    expect(mocks.setupI18n).not.toHaveBeenCalled()

    bootData.resolve()
    await flushPromises()
    expect(mocks.setupI18n).not.toHaveBeenCalled()

    auth.resolve()
    await initialization

    expect(mocks.setupI18n).toHaveBeenCalledExactlyOnceWith(app)
    expect(mocks.loadUserPreferences).toHaveBeenCalledOnce()
    expect(mocks.setupVuetify).toHaveBeenCalledExactlyOnceWith(app)
    expect(mocks.setupToast).toHaveBeenCalledExactlyOnceWith(app)
  })
})
