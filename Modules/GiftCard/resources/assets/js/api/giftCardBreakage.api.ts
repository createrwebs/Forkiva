import { http } from '@/modules/core/api/http.ts'

export const index = (params: Record<string, unknown> = {}) =>
  http.get('/v1/gift-card-breakage-batches', { params })
export const show = (id: number | string) => http.get(`/v1/gift-card-breakage-batches/${id}`)
export const getFormMeta = (branchId?: number | string | null) =>
  http.get('/v1/gift-card-breakage-batches/form/meta', {
    params: { branch_id: branchId || undefined },
  })
export const store = (payload: Record<string, unknown>) =>
  http.post('/v1/gift-card-breakage-batches', payload)
export const review = (id: number | string, payload: Record<string, unknown>) =>
  http.post(`/v1/gift-card-breakage-batches/${id}/review`, payload)
export const post = (id: number | string) => http.post(`/v1/gift-card-breakage-batches/${id}/post`)
export const reverse = (id: number | string, payload: Record<string, unknown>) =>
  http.post(`/v1/gift-card-breakage-batches/${id}/reverse`, payload)
