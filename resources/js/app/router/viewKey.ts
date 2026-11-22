import type { RouteLocationNormalizedLoaded } from 'vue-router'

export function routeViewKey(
  route: RouteLocationNormalizedLoaded,
  accountId?: number | null,
): string {
  const params = Object.keys(route.params)
    .sort()
    .map((key) => `${key}:${route.params[key]}`)
    .join('|')
  const handledQueryKeys = Array.isArray(route.meta.reuseOnQueryChange)
    ? route.meta.reuseOnQueryChange
    : []
  const query = new URLSearchParams(
    Object.entries(route.query).reduce<Record<string, string>>((result, [key, value]) => {
      if (handledQueryKeys.includes(key)) return result
      if (Array.isArray(value)) result[key] = value.join(',')
      else if (value !== null && value !== undefined) result[key] = String(value)
      return result
    }, {}),
  ).toString()

  return `${String(route.name ?? '')}-${params}-${query}-account:${accountId ?? 'guest'}`
}
