import { http } from '@/modules/core/api/http.ts'
import type { AnalyticsRequest, AnalyticsResponse } from '../pages/admin/analytics/types.ts'

export function showReservation(id: number | string) {
  return http.get(`/v1/reservations/${id}`)
}

export function storeReservation(item: Record<string, unknown>) {
  return http.post('/v1/reservations', item)
}

export function updateReservation(id: number | string, item: Record<string, unknown>) {
  return http.put(`/v1/reservations/${id}`, item)
}

export function getReservationFormMeta(
  branchId?: number | null,
  floorId?: number | null,
  zoneId?: number | null,
) {
  return http.get('/v1/reservations/form/meta', {
    params: {
      branch_id: branchId,
      floor_id: floorId,
      zone_id: zoneId,
    },
  })
}

export function checkAvailability(item: Record<string, unknown>) {
  return http.post('/v1/reservations/availability', item)
}

export function suggestedTables(item: Record<string, unknown>) {
  return http.post('/v1/reservations/suggested-tables', item)
}

export function suggestedSlots(item: Record<string, unknown>) {
  return http.post('/v1/reservations/suggested-slots', item)
}

export function changeReservationStatus(id: number | string, item: Record<string, unknown>) {
  return http.patch(`/v1/reservations/${id}/status`, item)
}

export function assignReservationTables(id: number | string, tableIds: number[]) {
  return http.put(`/v1/reservations/${id}/tables`, { table_ids: tableIds })
}

export function autoAssignReservationTables(id: number | string) {
  return http.post(`/v1/reservations/${id}/tables/auto-assign`)
}

export function getReservationDepositFormMeta(reservationId: number | string) {
  return http.get(`/v1/reservation-deposits/reservation/${reservationId}/form/meta`)
}

export function showReservationDepositTransaction(transactionId: number | string) {
  return http.get(`/v1/reservation-deposits/${transactionId}`)
}

export function recordReservationDeposit(
  reservationId: number | string,
  item: Record<string, unknown>,
) {
  return http.post(`/v1/reservation-deposits/reservation/${reservationId}`, item)
}

export function reverseReservationDeposit(
  transactionId: number | string,
  item: Record<string, unknown>,
) {
  return http.post(`/v1/reservation-deposits/${transactionId}/reverse`, item)
}

export function getReservationCalendar(filters: Record<string, unknown> = {}) {
  return http.get('/v1/reservations/calendar', { params: { filters } })
}

export function getReservationAnalyticsFilters(
  filters: Record<string, unknown> = {},
  signal?: AbortSignal,
) {
  return http.get('/v1/reservation-analytics/filters', { params: { filters }, signal })
}

export function getReservationAnalytics(request: AnalyticsRequest, signal?: AbortSignal) {
  return http.get<{ body: AnalyticsResponse }>('/v1/reservation-analytics', {
    params: request,
    signal,
  })
}
