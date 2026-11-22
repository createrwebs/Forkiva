import { http } from '@/modules/core/api/http.ts'

export function show(id: number, returnAllTranslations = false) {
  return http.get(`/v1/stock-movements/${id}`, {
    params: { return_all_translations: returnAllTranslations },
  })
}

export function correct(id: number, item: { quantity: number; reason: string }) {
  return http.post(`/v1/stock-movements/${id}/correct`, item)
}

export function store(item: Record<string, unknown>) {
  return http.post(`/v1/stock-movements`, item)
}

export function getFormMeta(branchId?: number) {
  return http.get(`/v1/stock-movements/form/meta`, { params: { branch_id: branchId } })
}
