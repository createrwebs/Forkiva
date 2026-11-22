import { beforeEach, describe, expect, it, vi } from 'vitest'
import { featureMiddleware } from './feature.middleware.ts'

const { appStore } = vi.hoisted(() => ({
  appStore: {
    settings: {
      features: {} as Record<string, { enabled: boolean }>,
    },
  },
}))

vi.mock('@/modules/core/stores/appStore.ts', () => ({
  useAppStore: () => appStore,
}))

describe('featureMiddleware', () => {
  beforeEach(() => {
    appStore.settings.features = {}
  })

  it('redirects routes owned by a disabled or missing module', () => {
    const next = vi.fn()

    featureMiddleware('accounting')({} as never, {} as never, next)

    expect(next).toHaveBeenCalledWith({ name: 'admin.dashboard' })
  })

  it('allows routes owned by an enabled module', () => {
    const next = vi.fn()
    appStore.settings.features.accounting = { enabled: true }

    featureMiddleware('accounting')({} as never, {} as never, next)

    expect(next).toHaveBeenCalledWith()
  })
})
