import {
  getGiftCardAnalytics,
  getGiftCardAnalyticsFilters,
  getGiftCardAnalyticsSection,
} from '@/modules/giftcard/api/giftCardAnalytics.api.ts'

export function useGiftCardAnalytics() {
  return {
    getGiftCardAnalytics,
    getGiftCardAnalyticsFilters,
    getGiftCardAnalyticsSection,
  }
}
