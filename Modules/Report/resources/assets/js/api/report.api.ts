import { http } from '@/modules/core/api/http.ts'

export interface ReportExportOptions {
  apiUri: string
  sorts?: { key: string; order: 'asc' | 'desc' }[]
}

export function exportReport(
  method: string,
  filters: Record<string, unknown>,
  options: ReportExportOptions,
) {
  return http.get(`${options.apiUri}/${encodeURIComponent(method)}`, {
    params: { filters, ...(options.sorts ? { sorts: options.sorts } : {}) },
    responseType: 'blob',
  })
}
