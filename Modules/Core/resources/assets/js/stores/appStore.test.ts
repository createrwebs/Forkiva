import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useAppStore } from './appStore'

describe('initial website language', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
    document.documentElement.lang = 'ar'
  })

  it('uses the server-rendered language until settings arrive and applies the configured default', () => {
    const store = useAppStore()
    store.restoreApp()
    expect(store.currentLocale).toBe('ar')
    store.setSettings({ ...store.settings, supported_locales: ['en', 'ar'], locale: 'ar' })
    expect(store.currentLocale).toBe('ar')
    expect(document.documentElement.dir).toBe('rtl')
    expect(localStorage.getItem('current_locale')).toBeNull()
  })

  it('uses Arabic even when the initial shell has an English fallback', () => {
    document.documentElement.lang = 'en'
    const store = useAppStore()
    store.restoreApp()
    store.setSettings({ ...store.settings, supported_locales: ['en', 'ar'], locale: 'ar' })
    expect(store.currentLocale).toBe('ar')
  })

  it('preserves a supported language explicitly selected by the visitor', () => {
    localStorage.setItem('current_locale', 'en')
    const store = useAppStore()
    store.restoreApp()
    store.setSettings({ ...store.settings, supported_locales: ['en', 'ar'], locale: 'ar' })
    expect(store.currentLocale).toBe('en')
    expect(localStorage.getItem('current_locale')).toBe('en')
  })

  it('falls back to the configured language when a saved language is unsupported', () => {
    localStorage.setItem('current_locale', 'fr')
    const store = useAppStore()
    store.restoreApp()
    store.setSettings({ ...store.settings, supported_locales: ['en', 'ar'], locale: 'ar' })
    expect(store.currentLocale).toBe('ar')
  })
})
