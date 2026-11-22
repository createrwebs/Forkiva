export interface TranslationRecord {
  key: string
  translations: Record<string, string | null>
}

export type TranslationRow = {
  key: string
} & Record<string, string | null>

export interface ImportPreview {
  locale: string | null
  is_zip?: boolean
  locales?: Record<string, ImportPreview>
  total: number
  valid: number
  unknown: number
  unknown_keys: string[]
  empty_skipped: number
  would_fill_missing: number
  would_overwrite: number
  placeholder_warning_count: number
  placeholder_warnings: { key: string; missing_placeholders: string[] }[]
  updated?: number
  mode?: string
}

export interface TranslationLanguage {
  id: string
  name: string
}

export interface TranslationEditingState {
  key: string
  lang: string
  oldValue: string
  value: string
}

export type ExportMode = 'values' | 'empty_all' | 'empty_missing'
export type ImportFileType = 'json' | 'zip'
export type ImportMode = 'fill_missing' | 'overwrite'
export type TranslationStatus = 'all' | 'missing' | 'filled'

export interface PaginationMeta {
  current_page: number
  from: number
  last_page: number
  per_page: number
  to: number
  total: number
}
