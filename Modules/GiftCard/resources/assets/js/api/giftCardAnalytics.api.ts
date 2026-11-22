import { http } from '@/modules/core/api/http.ts'
import type {
  GiftCardAnalyticsFilters,
  GiftCardAnalyticsFiltersResponse,
  GiftCardAnalyticsRequest,
  GiftCardAnalyticsResponse,
} from '@/modules/giftcard/contracts/giftCardAnalytics.ts'

export function getGiftCardAnalyticsFilters(
  filters: Partial<GiftCardAnalyticsFilters> = {},
  signal?: AbortSignal,
) {
  return http.get<{ body: GiftCardAnalyticsFiltersResponse }>('/v1/gift-cards-analytics/filters', {
    params: filters,
    signal,
  })
}

export function getGiftCardAnalytics(filters: GiftCardAnalyticsRequest, signal?: AbortSignal) {
  return http.get<{ body: GiftCardAnalyticsResponse }>('/v1/gift-cards-analytics', {
    params: filters,
    signal,
  })
}

export function getGiftCardAnalyticsSection(
  section: string,
  filters: Partial<GiftCardAnalyticsFilters> = {},
) {
  return http.get('/v1/gift-cards-analytics', { params: { section, filters } })
}
