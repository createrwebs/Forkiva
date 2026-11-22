export interface ImportField {
  key: string
  label: string
  type: 'text' | 'integer' | 'boolean' | 'date'
  required: boolean
  options?: Array<{ value: string; label: string; matches?: Record<string, string> }>
  multiple?: boolean
}

export type ImportResourceKey =
  | 'menus'
  | 'categories'
  | 'products'
  | 'units'
  | 'ingredients'
  | 'suppliers'
  | 'customers'
  | 'users'
  | 'pos_registers'
  | 'tables'
  | 'floors'
  | 'zones'

export interface ImportEnvelope<T> {
  body: T
  message?: string
}

export type ImportMode =
  'create_only' | 'update_only' | 'upsert' | 'skip_duplicates' | 'replace_existing'

export type ImportStatus =
  | 'uploaded'
  | 'staging'
  | 'staged'
  | 'validating'
  | 'validation_failed'
  | 'ready'
  | 'queued'
  | 'processing'
  | 'completed'
  | 'partially_completed'
  | 'failed'
  | 'cancelled'

export interface ImportEnum<T extends string> {
  id: T
  name: string
}

export interface DataImport {
  requires_processing?: boolean
  id: number
  resource: ImportResourceKey
  status: ImportEnum<ImportStatus>
  mode: ImportEnum<ImportMode>
  original_name: string
  headers: string[]
  mapping: Record<string, number>
  context: Record<string, number | null>
  total_rows: number
  processed_rows: number
  valid_rows: number
  invalid_rows: number
  new_rows: number
  update_rows: number
  skipped_rows: number
  imported_rows: number
  failed_rows: number
  failure_reason: string | null
  has_error_file: boolean
  has_original_file: boolean
  created_at: string
  started_at: string | null
  finished_at: string | null
}

export interface ImportRow {
  id: number
  row_number: number
  values: Array<string | null>
  data: Record<string, string | number | null>
  errors: Record<string, string[]> | null
  operation: ImportEnum<'create' | 'update' | 'skip' | 'invalid'> | null
  status: ImportEnum<'pending' | 'valid' | 'invalid' | 'processed' | 'skipped' | 'failed'>
}

export interface ImportPage<T> {
  data: T[]
  pagination: {
    current_page: number
    last_page: number
    total: number
  }
}
