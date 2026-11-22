import { http } from '@/modules/core/api/http.ts'

export function show(id: number | string) {
  return http.get(`/v1/gift-card-transactions/${id}`)
}

export function index(params: Record<string, unknown> = {}) {
  return http.get('/v1/gift-card-transactions', { params })
}

export function financialAction(giftCardId: number | string, payload: Record<string, unknown>) {
  return http.post(`/v1/gift-card-transactions/gift-card/${giftCardId}/financial-action`, payload)
}

export function reverse(id: number | string, payload: Record<string, unknown>) {
  return http.post(`/v1/gift-card-transactions/${id}/reverse`, payload)
}
