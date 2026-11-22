import { http } from '@/modules/core/api/http.ts'

export function showReservationWaitlist(id: number | string) {
  return http.get(`/v1/reservation-waitlists/${id}`)
}

export function getReservationWaitlistFormMeta(branchId?: number | null) {
  return http.get('/v1/reservation-waitlists/form/meta', {
    params: {
      branch_id: branchId,
    },
  })
}

export function storeReservationWaitlist(item: Record<string, unknown>) {
  return http.post('/v1/reservation-waitlists', item)
}

export function updateReservationWaitlist(id: number | string, item: Record<string, unknown>) {
  return http.put(`/v1/reservation-waitlists/${id}`, item)
}

export function notifyReservationWaitlist(id: number | string) {
  return http.post(`/v1/reservation-waitlists/${id}/notify`)
}

export function convertReservationWaitlist(id: number | string, item: Record<string, unknown>) {
  return http.post(`/v1/reservation-waitlists/${id}/convert`, item)
}

export function cancelReservationWaitlist(id: number | string, reason?: string) {
  return http.post(`/v1/reservation-waitlists/${id}/cancel`, { reason })
}
