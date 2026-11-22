import type { PaginationMeta } from '@/modules/core/contracts/Table.ts'

export interface TableExportStatus {
  id: string
  name: string
}

export interface TableExportFormat {
  id: string
  name: string
}

export interface TableExportItem {
  id: number
  resource_key: string
  context_key: string
  status: TableExportStatus
  format: TableExportFormat
  file_name: string | null
  download_url: string | null
  error_message: string | null
  is_downloaded: boolean
  created_at: string | null
  started_at: string | null
  finished_at: string | null
  downloaded_at: string | null
  expires_at: string | null
}

export interface CreateTableExportPayload {
  format: string
  filters: Record<string, unknown>
  sorts: Record<string, unknown>[]
  context: Record<string, unknown>
  context_key: string
}

export interface TableExportListResponseBody {
  data: TableExportItem[]
  latest: TableExportItem | null
  pagination: PaginationMeta
}

export interface TableExportListFilters {
  format: string | null
  is_downloaded: string | null
}
