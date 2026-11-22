import type { ThemeMode } from '@/modules/core/contracts/AppState.ts'

export const userPreferenceKeys = {
  language: 'ui.language',
  themeMode: 'ui.theme_mode',
  sidebarOpen: 'ui.sidebar_open',
  dashboardLayout: 'ui.dashboard.layout',
  dashboardFilters: 'ui.dashboard.filters',
  giftCardAnalytics: 'ui.gift_card_analytics',
  inventoryAnalytics: 'ui.inventory_analytics',
  reservationAnalytics: 'ui.reservation_analytics',
  accountingOverview: 'ui.accounting_overview',
  deliveryDashboard: 'ui.delivery_dashboard',
  financialAnalytics: 'ui.financial_analytics',
  payrollAnalytics: 'ui.payroll_analytics',
  hrDashboard: 'ui.hr_dashboard',
} as const

export interface DashboardViewPreference {
  layout: unknown[] | null
  filters: Record<string, unknown> | null
}

export type DashboardViewPreferenceName =
  | 'giftCardAnalytics'
  | 'inventoryAnalytics'
  | 'reservationAnalytics'
  | 'accountingOverview'
  | 'deliveryDashboard'
  | 'financialAnalytics'
  | 'payrollAnalytics'
  | 'hrDashboard'

export interface UserPreferenceValues {
  language: string
  themeMode: ThemeMode
  sidebarOpen: boolean
  dashboardLayout: unknown[] | null
  dashboardFilters: Record<string, unknown> | null
  giftCardAnalytics: DashboardViewPreference | null
  inventoryAnalytics: DashboardViewPreference | null
  reservationAnalytics: DashboardViewPreference | null
  accountingOverview: DashboardViewPreference | null
  deliveryDashboard: DashboardViewPreference | null
  financialAnalytics: DashboardViewPreference | null
  payrollAnalytics: DashboardViewPreference | null
  hrDashboard: DashboardViewPreference | null
}

export type UserPreferenceName = keyof UserPreferenceValues
