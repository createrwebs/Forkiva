import { http } from '@/modules/core/api/http'
export function getTableQrContext(uuid: string, token?: string | null) {
  return http.get(`/v1/table-orders/${uuid}/context`, { params: { token } })
}
export function getTableQrTracking(uuid: string, token?: string | null, signal?: AbortSignal) {
  return http.get(`/v1/table-orders/${uuid}/tracking`, { params: { token }, signal })
}
export function quoteTableQrOrder(
  uuid: string,
  token: string | null,
  data: Record<string, unknown>,
) {
  return http.post(`/v1/table-orders/${uuid}/quote`, data, { params: { token } })
}
export function submitTableQrOrder(
  uuid: string,
  token: string | null,
  data: Record<string, unknown>,
) {
  return http.post(`/v1/table-orders/${uuid}/submit`, data, { params: { token } })
}
