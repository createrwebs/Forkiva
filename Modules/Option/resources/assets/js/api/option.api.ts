import { http } from '@/modules/core/api/http.ts'

function hasPersistedId(id: unknown): boolean {
  return typeof id === 'number' || (typeof id === 'string' && /^\d+$/.test(id))
}

export function toOptionPayload(item: Record<string, unknown>) {
  if (!Array.isArray(item.values)) {
    return item
  }

  return {
    ...item,
    values: item.values.map((value: Record<string, unknown>) => {
      if (hasPersistedId(value.id)) {
        return value
      }

      const { id: _temporaryId, ...newValue } = value

      return newValue
    }),
  }
}

export function show(id: number, returnAllTranslations = false) {
  return http.get(`/v1/options/${id}`, {
    params: { return_all_translations: returnAllTranslations },
  })
}

export function update(id: number, item: Record<string, unknown>) {
  return http.put(`/v1/options/${id}`, toOptionPayload(item))
}

export function store(item: Record<string, unknown>) {
  return http.post(`/v1/options`, toOptionPayload(item))
}

export function getFormMeta() {
  return http.get(`/v1/options/form/meta`)
}
