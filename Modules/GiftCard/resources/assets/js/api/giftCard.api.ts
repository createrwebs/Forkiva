import { http } from '@/modules/core/api/http.ts'

export function show(id: number | string, returnAllTranslations = false) {
  return http.get(`/v1/gift-cards/${id}`, {
    params: { return_all_translations: returnAllTranslations },
  })
}

export function update(id: number | string, item: Record<string, unknown>) {
  return http.put(`/v1/gift-cards/${id}`, item)
}

export function store(item: Record<string, unknown>) {
  return http.post('/v1/gift-cards', item)
}

export function getFormMeta(branchId?: number | string | null) {
  return http.get('/v1/gift-cards/form/meta', { params: { branch_id: branchId || undefined } })
}
