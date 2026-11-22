import { http } from '@/modules/core/api/http'
import type {
  CashHandover,
  CashHandoverFollowUp,
  CashHandoverFollowUpKind,
  CashHandoverFollowUpSessions,
  CashHandoverSummary,
  CreateCashHandover,
} from '../contracts/cashHandover'

function path(sessionId: number) {
  return `/v1/daily-finance/sessions/${sessionId}/handovers`
}

export function handovers(sessionId: number, page = 1, signal?: AbortSignal) {
  return http.get<{ body: CashHandoverSummary }>(path(sessionId), { params: { page }, signal })
}

export function createHandover(sessionId: number, data: CreateCashHandover) {
  return http.post<{ body: CashHandover }>(path(sessionId), data)
}

export function receiveHandover(
  sessionId: number,
  id: number,
  data: { received_amount: string; variance_reason: string | null },
) {
  return http.post<{ body: CashHandover }>(`${path(sessionId)}/${id}/receive`, data)
}

export function cancelHandover(sessionId: number, id: number, cancellationReason: string) {
  return http.post<{ body: CashHandover }>(`${path(sessionId)}/${id}/cancel`, {
    cancellation_reason: cancellationReason,
  })
}

export function handoverFollowUp(branchId: number, signal?: AbortSignal) {
  return http.get<{ body: CashHandoverFollowUp }>('/v1/daily-finance/handovers/summary', {
    params: { branch_id: branchId },
    signal,
  })
}

export function handoverFollowUpSessions(
  branchId: number,
  kind: CashHandoverFollowUpKind,
  page = 1,
  signal?: AbortSignal,
) {
  return http.get<{ body: CashHandoverFollowUpSessions }>('/v1/daily-finance/handovers/sessions', {
    params: { branch_id: branchId, kind, page },
    signal,
  })
}
