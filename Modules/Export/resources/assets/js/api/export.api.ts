import type {
  CreateTableExportPayload,
  TableExportListFilters,
} from '@/modules/export/contracts/export.ts'
import { http } from '@/modules/core/api/http.ts'

export function listExports(
  resource: string,
  contextKey: string,
  page = 1,
  perPage = 10,
  filters: TableExportListFilters = {
    format: null,
    is_downloaded: null,
  },
) {
  return http.get('/v1/exports', {
    params: {
      resource,
      context_key: contextKey,
      page,
      per_page: perPage,
      filters,
    },
  })
}

export function createExport(apiUri: string, payload: CreateTableExportPayload) {
  return http.post(apiUri, payload)
}

export function showExport(id: number) {
  return http.get(`/v1/exports/${id}`)
}

export function downloadExport(id: number) {
  return http.get(`/v1/exports/${id}/download`, {
    responseType: 'blob',
  })
}
