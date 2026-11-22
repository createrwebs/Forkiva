import { http } from '@/modules/core/api/http.ts'

export function showReservationTableBlock(id: number | string, returnAllTranslations = false) {
  return http.get(`/v1/reservation-table-blocks/${id}`, {
    params: { return_all_translations: returnAllTranslations },
  })
}

export function storeReservationTableBlock(item: Record<string, unknown>) {
  return http.post('/v1/reservation-table-blocks', item)
}

export function updateReservationTableBlock(id: number | string, item: Record<string, unknown>) {
  return http.put(`/v1/reservation-table-blocks/${id}`, item)
}
