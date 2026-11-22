import type {
  DashboardFilters,
  DashboardFiltersResponse,
  DashboardRequest,
  DashboardResponse,
} from '@/modules/dashboard/contracts/dashboard.ts'
import { http } from '@/modules/core/api/http.ts'

export function dashboardFilters(filters: Partial<DashboardFilters> = {}, signal?: AbortSignal) {
  return http.get<{ body: DashboardFiltersResponse }>('/v1/dashboards/filters', {
    params: { filters },
    signal,
  })
}

export function dashboard(filters: DashboardRequest, signal?: AbortSignal) {
  return http.get<{ body: DashboardResponse }>('/v1/dashboards', {
    params: filters,
    signal,
  })
}
