import type {
  InventoryAnalyticsFiltersResponse,
  InventoryAnalyticsRequest,
  InventoryAnalyticsResponse,
} from '@/modules/inventory/contracts/inventoryAnalytics.ts'
import { http } from '@/modules/core/api/http.ts'

export function getInventoryAnalyticsMeta(signal?: AbortSignal) {
  return http.get<{ body: InventoryAnalyticsFiltersResponse }>(
    '/v1/inventories/analytics/filters',
    { signal },
  )
}

export function getInventoryAnalytics(request: InventoryAnalyticsRequest, signal?: AbortSignal) {
  return http.get<{ body: InventoryAnalyticsResponse }>('/v1/inventories/analytics', {
    params: request,
    signal,
  })
}
