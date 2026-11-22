import { afterEach, describe, expect, it, vi } from 'vitest'

describe('same-origin API configuration', () => {
  afterEach(() => vi.unstubAllEnvs())

  it('defaults to the Laravel API root independently of the asset base', async () => {
    vi.resetModules()
    vi.stubEnv('BASE_URL', '/build/')
    vi.stubEnv('VITE_API_URL', '')
    const { http, API_BASE_URL } = await import('./http')
    expect(API_BASE_URL).toBe('/api')
    expect(http.defaults.baseURL).toBe('/api')
  })

  it('preserves explicitly configured API deployments', async () => {
    vi.resetModules()
    vi.stubEnv('VITE_API_URL', 'https://api.example.test/api')
    const { http } = await import('./http')
    expect(http.defaults.baseURL).toBe('https://api.example.test/api')
  })
})
