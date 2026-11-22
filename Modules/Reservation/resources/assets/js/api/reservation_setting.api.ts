import { http } from '@/modules/core/api/http.ts'

export function showReservationSetting(id: number | string) {
  return http.get(`/v1/reservation-settings/${id}`)
}

export function storeReservationSetting(item: Record<string, unknown>) {
  return http.post('/v1/reservation-settings', item)
}

export function updateReservationSetting(id: number | string, item: Record<string, unknown>) {
  return http.put(`/v1/reservation-settings/${id}`, item)
}

export function getReservationSettingFormMeta() {
  return http.get('/v1/reservation-settings/form/meta')
}
