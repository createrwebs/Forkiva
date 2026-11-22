import { http } from '@/modules/core/api/http.ts'

export function getServices() {
  return http.get('/v1/services')
}

export function getService(category: string, service: string) {
  return http.get(`/v1/services/${category}/${service}`)
}

export function updateServiceSettings(
  category: string,
  service: string,
  data: Record<string, unknown>,
) {
  return http.put(`/v1/services/${category}/${service}/settings`, data)
}
