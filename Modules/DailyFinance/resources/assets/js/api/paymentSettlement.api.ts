import { http } from '@/modules/core/api/http'
import type {
  CancelSettlement,
  CreateSettlement,
  PaymentSettlement,
  ReceiveSettlement,
  SettlementCandidate,
  SettlementCandidateFilters,
  SettlementMeta,
  SettlementPage,
  ElectronicMethod,
  SettlementStatusFilter,
  PaymentSettlementSummary,
} from '../contracts/paymentSettlement'

const path = '/v1/daily-finance/settlements'

export function settlementSummary(branchId: number, signal?: AbortSignal) {
  return http.get<{ body: PaymentSettlementSummary }>(`${path}/summary`, {
    params: { branch_id: branchId },
    signal,
  })
}

export function settlementMeta(branchId: number, signal?: AbortSignal) {
  return http.get<{ body: SettlementMeta }>(`${path}/meta`, {
    params: { branch_id: branchId },
    signal,
  })
}

export function settlementCandidates(filters: SettlementCandidateFilters, signal?: AbortSignal) {
  return http.get<{ body: SettlementPage<SettlementCandidate> }>(`${path}/candidates`, {
    params: filters,
    signal,
  })
}

export function settlementHistory(
  filters: {
    branch_id: number
    method?: ElectronicMethod | null
    status?: SettlementStatusFilter | null
    page?: number
  },
  signal?: AbortSignal,
) {
  return http.get<{ body: SettlementPage<PaymentSettlement> }>(path, { params: filters, signal })
}

export function settlementDetails(branchId: number, id: number, signal?: AbortSignal) {
  return http.get<{ body: PaymentSettlement }>(`${path}/${id}`, {
    params: { branch_id: branchId },
    signal,
  })
}

export function createSettlement(data: CreateSettlement) {
  return http.post<{ body: PaymentSettlement }>(path, data)
}

export function receiveSettlement(id: number, data: ReceiveSettlement) {
  return http.post<{ body: PaymentSettlement }>(`${path}/${id}/receive`, data)
}

export function cancelSettlement(id: number, data: CancelSettlement) {
  return http.post<{ body: PaymentSettlement }>(`${path}/${id}/cancel`, data)
}
