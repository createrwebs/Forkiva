import { http } from '@/modules/core/api/http.ts'

export interface KitchenOrderFilters {
  branchId?: number | null
  search?: string | null
  type?: string | null
  status?: string | null
  limit?: number | null
}

export function getOrders(filters: KitchenOrderFilters = {}, lastUpdatedAt?: string | null) {
  return http.get('/v1/pos/kitchen-viewer/orders', {
    params: {
      branch_id: filters.branchId,
      search: filters.search,
      type: filters.type,
      status: filters.status,
      limit: filters.limit,
      last_updated_at: lastUpdatedAt,
    },
  })
}

export function getConfiguration(branchId?: number | null) {
  return http.get('/v1/pos/kitchen-viewer/configuration', { params: { branch_id: branchId } })
}

export function moveToNextStatus(orderId: number | string, ids?: number[]) {
  return http.patch(`/v1/pos/kitchen-viewer/${orderId}/move-to-next-status`, { ids })
}
