import type {
  DataImport,
  ImportEnvelope,
  ImportField,
  ImportMode,
  ImportPage,
  ImportResourceKey,
  ImportRow,
} from '@/modules/import/contracts/import.ts'
import { http } from '@/modules/core/api/http.ts'

type ImportContext = Record<string, number>
type ImportRowData = Record<string, string | null>
type ImportMapping = Record<string, number>

const importsPath = '/v1/imports'
const batchRequest = { headers: { 'X-Import-Batches': '1' } }

function importPath(id: number): string {
  return `${importsPath}/${id}`
}

function resourcePath(resource: ImportResourceKey): string {
  return `${importsPath}/${resource}`
}

function uploadData(file: File, context: ImportContext): FormData {
  const body = new FormData()
  body.append('file', file)

  Object.entries(context).forEach(([key, value]) => body.append(`context[${key}]`, String(value)))

  return body
}

export const importApi = {
  fields: (resource: ImportResourceKey, context: ImportContext = {}) =>
    http.get<ImportEnvelope<ImportField[]>>(`${resourcePath(resource)}/fields`, {
      params: context,
    }),
  template: (resource: ImportResourceKey) =>
    http.get<Blob>(`${resourcePath(resource)}/template`, { responseType: 'blob' }),
  history: (resource: ImportResourceKey, context: ImportContext = {}) =>
    http.get<ImportEnvelope<ImportPage<DataImport>>>(importsPath, {
      params: { resource, ...context },
    }),
  upload: (resource: ImportResourceKey, file: File, context: ImportContext) =>
    http.post<ImportEnvelope<DataImport>>(resourcePath(resource), uploadData(file, context), {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),
  show: (id: number) => http.get<ImportEnvelope<DataImport>>(importPath(id)),
  rows: (id: number, page: number) =>
    http.get<ImportEnvelope<ImportPage<ImportRow>>>(`${importPath(id)}/rows`, {
      params: { page },
    }),
  map: (id: number, mapping: ImportMapping) =>
    http.post<ImportEnvelope<DataImport>>(`${importPath(id)}/mapping`, { mapping }),
  editRow: (id: number, rowId: number, data: ImportRowData) =>
    http.patch<ImportEnvelope<ImportRow>>(`${importPath(id)}/rows/${rowId}`, { data }),
  validate: (id: number, mode: ImportMode) =>
    http.post<ImportEnvelope<DataImport>>(`${importPath(id)}/validate`, { mode }, batchRequest),
  queue: (id: number) =>
    http.post<ImportEnvelope<DataImport>>(`${importPath(id)}/queue`, {}, batchRequest),
  retry: (id: number) =>
    http.post<ImportEnvelope<DataImport>>(`${importPath(id)}/retry`, {}, batchRequest),
  advance: (id: number) => http.post<ImportEnvelope<DataImport>>(`${importPath(id)}/advance`),
  cancel: (id: number) => http.post<ImportEnvelope<DataImport>>(`${importPath(id)}/cancel`),
  download: (id: number) => http.get<Blob>(`${importPath(id)}/download`, { responseType: 'blob' }),
  errors: (id: number) => http.get<Blob>(`${importPath(id)}/errors`, { responseType: 'blob' }),
}
