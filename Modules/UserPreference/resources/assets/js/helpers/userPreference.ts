import type { AppSettings, ThemeMode } from '@/modules/core/contracts/AppState.ts'
import {
  userPreferenceKeys,
  type DashboardViewPreference,
  type DashboardViewPreferenceName,
  type UserPreferenceValues,
} from '../contracts/UserPreference.ts'

export const dashboardViewPreferenceNames: DashboardViewPreferenceName[] = [
  'giftCardAnalytics',
  'inventoryAnalytics',
  'reservationAnalytics',
  'accountingOverview',
  'deliveryDashboard',
  'financialAnalytics',
  'payrollAnalytics',
  'hrDashboard',
]

const verticalNavRailStorageKey = 'forkiva.layout.verticalNavRail'

export function readSidebarOpen(): boolean {
  if (typeof window === 'undefined') return true

  try {
    return window.localStorage.getItem(verticalNavRailStorageKey) !== 'true'
  } catch {
    return true
  }
}

export function rememberSidebarOpen(open: boolean): void {
  if (typeof window === 'undefined') return

  try {
    window.localStorage.setItem(verticalNavRailStorageKey, String(!open))
  } catch {
    // Storage may be unavailable; the current layout state still works.
  }
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}

function isThemeMode(value: unknown): value is ThemeMode {
  return value === 'light' || value === 'dark' || value === 'system'
}

export function normalizeUserPreferences(
  payload: unknown,
  settings: AppSettings,
  defaults: UserPreferenceValues,
): UserPreferenceValues {
  const values = isRecord(payload) ? payload : {}
  const language = values[userPreferenceKeys.language]
  const themeMode = values[userPreferenceKeys.themeMode]
  const sidebarOpen = values[userPreferenceKeys.sidebarOpen]
  const dashboardLayout = values[userPreferenceKeys.dashboardLayout]
  const dashboardFilters = values[userPreferenceKeys.dashboardFilters]
  const views = Object.fromEntries(
    dashboardViewPreferenceNames.map((name) => {
      const raw = values[userPreferenceKeys[name]]
      const view: DashboardViewPreference | null = isRecord(raw)
        ? {
            layout: Array.isArray(raw.layout) ? raw.layout : null,
            filters: isRecord(raw.filters) ? raw.filters : null,
          }
        : null
      return [name, view]
    }),
  ) as Pick<UserPreferenceValues, DashboardViewPreferenceName>

  return {
    language:
      typeof language === 'string' && settings.supported_locales.includes(language)
        ? language
        : defaults.language,
    themeMode: isThemeMode(themeMode) ? themeMode : defaults.themeMode,
    sidebarOpen: typeof sidebarOpen === 'boolean' ? sidebarOpen : defaults.sidebarOpen,
    dashboardLayout: Array.isArray(dashboardLayout) ? dashboardLayout : defaults.dashboardLayout,
    dashboardFilters: isRecord(dashboardFilters) ? dashboardFilters : defaults.dashboardFilters,
    ...views,
  }
}
