import { dashboard, dashboardFilters } from '@/modules/dashboard/api/dashboard.api.ts'

export function useDashboard() {
  return {
    dashboard,
    dashboardFilters,
  }
}
