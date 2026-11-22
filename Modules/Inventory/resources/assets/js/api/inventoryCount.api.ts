import { http } from '@/modules/core/api/http.ts'

export function show(id: number) {
  return http.get(`/v1/inventory-counts/${id}`)
}

export function store(item: Record<string, unknown>) {
  return http.post('/v1/inventory-counts', item)
}

export function update(id: number, item: Record<string, unknown>) {
  return http.put(`/v1/inventory-counts/${id}`, item)
}

export function getFormMeta(branchId?: number | null) {
  return http.get('/v1/inventory-counts/form/meta', {
    params: { branch_id: branchId || undefined },
  })
}

export function approve(id: number) {
  return http.post(`/v1/inventory-counts/${id}/approve`)
}

export function cancel(id: number, reason?: string | null) {
  return http.post(`/v1/inventory-counts/${id}/cancel`, { reason })
}
