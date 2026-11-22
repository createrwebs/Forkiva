// @vitest-environment node
import { afterEach, describe, expect, it, vi } from 'vitest'

// Reload the singleton so each case reads its own public build configuration.
afterEach(() => {
  vi.unstubAllGlobals()
  vi.unstubAllEnvs()
  vi.resetModules()
})

describe('browser storage configuration', () => {
  it('decrypts with the same build key and rejects a different key', async () => {
    vi.stubEnv('VITE_STORAGE_ENCRYPTION_KEY', 'build-value')
    const first = (await import('./StorageEncryption')).storageEncryption
    const encrypted = await first.encrypt('token', 'test-value')
    expect(encrypted).not.toBe('test-value')

    vi.resetModules()
    const sameBuild = (await import('./StorageEncryption')).storageEncryption
    expect(await sameBuild.decrypt(encrypted)).toBe('test-value')

    vi.resetModules()
    vi.stubEnv('VITE_STORAGE_ENCRYPTION_KEY', 'different-build-value')
    const differentBuild = (await import('./StorageEncryption')).storageEncryption
    expect(await differentBuild.decrypt(encrypted)).toBeNull()
  })

  it('keeps storage readable when encryption is not configured', async () => {
    vi.stubEnv('VITE_STORAGE_ENCRYPTION_KEY', '')
    const encryption = (await import('./StorageEncryption')).storageEncryption
    expect(encryption.isEnabled()).toBe(false)
    expect(await encryption.encrypt('token', 'test-value')).toBe('test-value')
  })

  it('encrypts sensitive keys while leaving preferences readable by default', async () => {
    vi.stubEnv('VITE_STORAGE_ENCRYPTION_KEY', 'build-value')
    vi.stubEnv('VITE_STORAGE_ENCRYPT_ALL', 'false')
    const encryption = (await import('./StorageEncryption')).storageEncryption
    const encrypted = await encryption.encrypt('token', 'test-value')
    expect(encrypted).not.toBe('test-value')
    expect(await encryption.decrypt(encrypted)).toBe('test-value')
    expect(await encryption.encrypt('theme_mode', 'dark')).toBe('dark')
  })
})
