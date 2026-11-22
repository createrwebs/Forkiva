import { describe, expect, it } from 'vitest'
import { giftCardAnalyticsWidgets } from '@/modules/giftcard/config/giftCardAnalytics.widgets.ts'
import {
  normalizeGiftCardAnalyticsLayout,
  packGiftCardAnalyticsLayout,
  useGiftCardAnalyticsLayout,
} from '@/modules/giftcard/composables/giftCardAnalyticsLayout.ts'

describe('Gift Card Analytics layout', () => {
  it('defines unique widgets and keeps eight priority widgets visible by default', () => {
    const keys = giftCardAnalyticsWidgets.map((widget) => widget.key)
    const visibleKeys = giftCardAnalyticsWidgets
      .filter((widget) => widget.defaultVisible)
      .map((widget) => widget.key)

    expect(new Set(keys).size).toBe(21)
    expect(visibleKeys).toEqual([
      'total_cards',
      'outstanding_balance',
      'sold_value',
      'redeemed_value',
      'sales_over_time',
      'transaction_mix',
      'cards_health',
      'expiry_watch',
    ])
  })

  it('repairs stale saved layouts and appends newly introduced widgets', () => {
    const normalized = normalizeGiftCardAnalyticsLayout(
      [
        { key: 'sold_value', visible: true, minimized: true, order: 0 },
        { key: 'removed_widget', visible: true, minimized: false, order: 1 },
      ],
      giftCardAnalyticsWidgets,
    )

    expect(normalized).toHaveLength(21)
    expect(normalized[0]).toMatchObject({
      key: 'sold_value',
      visible: true,
      minimized: true,
      x: 0,
      y: 0,
      w: 3,
      h: 1,
    })
    expect(normalized.some((item) => item.key === 'liability_by_currency')).toBe(true)
    expect(normalized.map((item) => String(item.key))).not.toContain('removed_widget')
  })

  it('packs visible widgets without reserving space for hidden widgets', () => {
    const definitions = giftCardAnalyticsWidgets.slice(0, 3)
    const packed = packGiftCardAnalyticsLayout(
      definitions.map((definition, order) => ({
        key: definition.key,
        visible: order !== 1,
        minimized: false,
        order,
      })),
      definitions,
    )

    expect(packed.filter((item) => item.visible)).toMatchObject([
      { key: 'total_cards', x: 0, y: 0 },
      { key: 'sold_value', x: 3, y: 0 },
    ])
  })

  it('persists layout changes synchronously', () => {
    const userId = 908
    const { visibleLayout } = useGiftCardAnalyticsLayout(
      userId,
      giftCardAnalyticsWidgets.slice(0, 3),
    )

    visibleLayout.value = [...visibleLayout.value].reverse()

    const saved = JSON.parse(
      window.localStorage.getItem(`forkiva.gift-card-analytics.layout.v1.${userId}`) ?? '[]',
    )
    expect(saved.map((item: { key: string }) => item.key)).toEqual([
      'sold_value',
      'outstanding_balance',
      'total_cards',
    ])

    window.localStorage.removeItem(`forkiva.gift-card-analytics.layout.v1.${userId}`)
  })

  it('uses the account layout ahead of stale browser storage', () => {
    const userId = 909
    const storageKey = `forkiva.gift-card-analytics.layout.v1.${userId}`
    const definitions = giftCardAnalyticsWidgets.slice(0, 3)
    window.localStorage.setItem(
      storageKey,
      JSON.stringify([{ key: 'total_cards', visible: false, minimized: false, order: 0 }]),
    )

    const { layout } = useGiftCardAnalyticsLayout(userId, definitions, [
      { key: 'sold_value', visible: false, minimized: true, order: 0 },
    ])

    expect(layout.value.find((item) => item.key === 'sold_value')).toMatchObject({
      visible: false,
      minimized: true,
    })
    expect(layout.value.find((item) => item.key === 'total_cards')?.visible).toBe(true)
    window.localStorage.removeItem(storageKey)
  })
})
