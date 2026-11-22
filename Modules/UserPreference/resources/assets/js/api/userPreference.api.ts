import { http } from '@/modules/core/api/http.ts'
import type { UserPreferenceName, UserPreferenceValues } from '../contracts/UserPreference.ts'
import { userPreferenceKeys } from '../contracts/UserPreference.ts'

export function getUserPreferences(token: string) {
  return http.get<{ body: Record<string, unknown> }>('/v1/preferences', {
    headers: { Authorization: `Bearer ${token}` },
    validateStatus: () => true,
  })
}

export function updateUserPreference<K extends UserPreferenceName>(
  name: K,
  value: UserPreferenceValues[K],
  token: string,
) {
  return http.put(
    `/v1/preferences/${userPreferenceKeys[name]}`,
    { value },
    {
      headers: { Authorization: `Bearer ${token}` },
      validateStatus: () => true,
    },
  )
}
