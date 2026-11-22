import { http } from '@/modules/core/api/http.ts'

export function show(id: number) {
  return http.get(`/v1/stock-transfers/${id}`)
}

export function store(item: Record<string, unknown>) {
  return http.post('/v1/stock-transfers', item)
}

export function update(id: number, item: Record<string, unknown>) {
  return http.put(`/v1/stock-transfers/${id}`, item)
}

export function getFormMeta(
  sourceBranchId?: number | null,
  destinationBranchId?: number | null,
  sourceIngredientIds: number[] = [],
  destinationIngredientIds: number[] = [],
) {
  return http.get('/v1/stock-transfers/form/meta', {
    params: {
      source_branch_id: sourceBranchId || undefined,
      destination_branch_id: destinationBranchId || undefined,
      source_ingredient_ids: sourceIngredientIds.length > 0 ? sourceIngredientIds : undefined,
      destination_ingredient_ids:
        destinationIngredientIds.length > 0 ? destinationIngredientIds : undefined,
    },
  })
}

export function approve(id: number) {
  return http.post(`/v1/stock-transfers/${id}/approve`)
}

export function dispatch(id: number) {
  return http.post(`/v1/stock-transfers/${id}/dispatch`)
}

export function receive(id: number, item: Record<string, unknown>) {
  return http.post(`/v1/stock-transfers/${id}/receive`, item)
}

export function close(id: number, reason: string) {
  return http.post(`/v1/stock-transfers/${id}/close`, { reason })
}

export function cancel(id: number, reason: string) {
  return http.post(`/v1/stock-transfers/${id}/cancel`, { reason })
}

export function voidDispatch(id: number, reason: string) {
  return http.post(`/v1/stock-transfers/${id}/void`, { reason })
}
