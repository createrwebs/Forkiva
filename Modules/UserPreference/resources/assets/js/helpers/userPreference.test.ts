import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useAppStore } from '@/modules/core/stores/appStore.ts'
import { normalizeUserPreferences, readSidebarOpen, rememberSidebarOpen } from './userPreference.ts'

describe('user preference helpers', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
  })

  it('accepts only supported locale, theme, and sidebar values', () => {
    const settings = useAppStore().settings
    settings.supported_locales = ['en', 'ar']
    const defaults = {
      language: 'en',
      themeMode: 'light' as const,
      sidebarOpen: true,
      dashboardLayout: null,
      dashboardFilters: null,
      giftCardAnalytics: null,
      inventoryAnalytics: null,
      reservationAnalytics: null,
      accountingOverview: null,
      deliveryDashboard: null,
      financialAnalytics: null,
      payrollAnalytics: null,
      hrDashboard: null,
    }

    expect(
      normalizeUserPreferences(
        {
          'ui.language': 'ar',
          'ui.theme_mode': 'invalid',
          'ui.sidebar_open': false,
        },
        settings,
        defaults,
      ),
    ).toEqual({
      language: 'ar',
      themeMode: 'light',
      sidebarOpen: false,
      dashboardLayout: null,
      dashboardFilters: null,
      giftCardAnalytics: null,
      inventoryAnalytics: null,
      reservationAnalytics: null,
      accountingOverview: null,
      deliveryDashboard: null,
      financialAnalytics: null,
      payrollAnalytics: null,
      hrDashboard: null,
    })
  })

  it('preserves the existing rail storage convention', () => {
    expect(readSidebarOpen()).toBe(true)
    rememberSidebarOpen(false)
    expect(localStorage.getItem('forkiva.layout.verticalNavRail')).toBe('true')
    expect(readSidebarOpen()).toBe(false)
  })

  it('accepts independent dashboard view preferences and rejects malformed shapes', () => {
    const settings = useAppStore().settings
    const defaults = {
      language: 'en',
      themeMode: 'light' as const,
      sidebarOpen: true,
      dashboardLayout: null,
      dashboardFilters: null,
      giftCardAnalytics: null,
      inventoryAnalytics: null,
      reservationAnalytics: null,
      accountingOverview: null,
      deliveryDashboard: null,
      financialAnalytics: null,
      payrollAnalytics: null,
      hrDashboard: null,
    }

    const result = normalizeUserPreferences(
      {
        'ui.gift_card_analytics': {
          layout: [{ key: 'sold_value', visible: true }],
          filters: { from: '2026-09-01' },
        },
        'ui.inventory_analytics': { layout: 'invalid', filters: [] },
      },
      settings,
      defaults,
    )

    expect(result.giftCardAnalytics).toEqual({
      layout: [{ key: 'sold_value', visible: true }],
      filters: { from: '2026-09-01' },
    })
    expect(result.inventoryAnalytics).toEqual({ layout: null, filters: null })
    expect(result.reservationAnalytics).toBeNull()
  })
})
