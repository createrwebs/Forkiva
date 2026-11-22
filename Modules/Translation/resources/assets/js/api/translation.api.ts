import { http } from '@/modules/core/api/http.ts'

export interface TranslationListParams {
  page?: number
  per_page?: number
  search?: string
  locale?: string | null
  status?: 'all' | 'missing' | 'filled'
  prefix?: string
}

export function getAppTranslations() {
  return http.get('/v1/app/translations')
}

export function getTranslations(params: TranslationListParams = {}) {
  return http.get('/v1/translations', { params })
}

export function updateTranslation(key: string, locale: string, value: string | null) {
  return http.put(`/v1/translations/${encodeURIComponent(key)}`, { locale, value })
}

export function exportTranslations(params: {
  _locale: string
  mode: 'values' | 'empty_all' | 'empty_missing'
}) {
  return http.get('/v1/translations/export', { params, responseType: 'blob' })
}

export function exportAllTranslations(params: { mode: 'values' | 'empty_all' | 'empty_missing' }) {
  return http.get('/v1/translations/export/all', { params, responseType: 'blob' })
}

export function previewImportTranslation(data: FormData) {
  return http.post('/v1/translations/import/preview', data, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
}

export function importTranslation(data: FormData) {
  return http.post('/v1/translations/import', data, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
}
