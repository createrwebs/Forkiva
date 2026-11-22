import { http } from '@/modules/core/api/http'
import type {
  CloseDay,
  DayClosing,
  DayClosingHistory,
  DayClosingReview,
} from '../contracts/dayClosing'

const path = '/v1/daily-finance/closings'

export function reviewClosing(branchId: number, date: string, signal?: AbortSignal) {
  return http.get<{ body: DayClosingReview }>(`${path}/review`, {
    params: { branch_id: branchId, date },
    signal,
  })
}

export function closingHistory(branchId: number, page = 1, signal?: AbortSignal) {
  return http.get<{ body: DayClosingHistory }>(path, {
    params: { branch_id: branchId, page },
    signal,
  })
}

export function closeDay(data: CloseDay) {
  return http.post<{ body: DayClosing }>(path, data)
}
