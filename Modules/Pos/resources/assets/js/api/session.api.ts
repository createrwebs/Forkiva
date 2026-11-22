import { http } from '@/modules/core/api/http.ts'
import type {
  ClosingPreview,
  PosSessionDetail,
} from '@/modules/pos/contracts/sessionReconciliation'

export function show(id: number, returnAllTranslations = false) {
  return http.get<{ body: PosSessionDetail }>(`/v1/pos/sessions/${id}`, {
    params: { return_all_translations: returnAllTranslations },
  })
}

export function open(data: Record<string, unknown>) {
  return http.post(`/v1/pos/sessions/open`, data)
}

export function close(id: number, data: Record<string, unknown>) {
  return http.put(`/v1/pos/sessions/${id}/close`, data)
}

export function closingPreview(id: number, declaredCash?: string) {
  return http.post<{ body: ClosingPreview }>(`/v1/pos/sessions/${id}/closing-preview`, {
    ...(declaredCash === undefined ? {} : { declared_cash: declaredCash }),
  })
}

export function getFormMeta(branchId?: number) {
  return http.get(`/v1/pos/sessions/form/meta`, { params: { branch_id: branchId } })
}
