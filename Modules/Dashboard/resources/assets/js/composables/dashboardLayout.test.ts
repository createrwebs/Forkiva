import { ref } from 'vue'
import { describe, expect, it } from 'vitest'
import { dashboardWidgets } from '@/modules/dashboard/config/dashboard.widgets.ts'
import {
  getStoredDashboardFilters,
  normalizeDashboardLayout,
  packDashboardLayout,
  persistDashboardFilters,
  useDashboardLayout,
} from '@/modules/dashboard/composables/dashboardLayout.ts'

describe('dashboard widget configuration', () => {
  it('defines 27 unique widgets and shows only the eight priority widgets by default', () => {
    const keys = dashboardWidgets.map((widget) => widget.key)
    const visibleKeys = dashboardWidgets
      .filter((widget) => widget.defaultVisible)
      .map((widget) => widget.key)

    expect(new Set(keys).size).toBe(27)
    expect(visibleKeys).toEqual([
      'total_sales',
      'total_orders',
      'total_active_orders',
      'average_order_value',
      'sales_analytics',
      'order_total_by_status',
      'top_selling_products',
      'low_stock_alerts',
    ])
  })

  it('repairs stale saved layouts and appends newly introduced widgets', () => {
    const value = [
      { key: 'total_orders', visible: true, minimized: true, order: 0 },
      { key: 'removed_widget', visible: true, minimized: false, order: 1 },
      { key: 'total_sales', visible: false, minimized: false, order: 2 },
    ]

    const normalized = normalizeDashboardLayout(value, dashboardWidgets)

    expect(normalized).toHaveLength(27)
    expect(normalized[0]).toMatchObject({
      key: 'total_orders',
      visible: true,
      minimized: true,
      order: 0,
      x: 0,
      y: 0,
      w: 3,
      h: 1,
    })
    expect(normalized[1]).toMatchObject({
      key: 'total_sales',
      visible: false,
      minimized: false,
      order: 1,
    })
    expect(normalized.some((item) => item.key === 'fulfillment_health')).toBe(true)
    expect(normalized.map((item) => String(item.key))).not.toContain('removed_widget')
  })

  it('keeps expensive secondary analytics hidden until the user enables them', () => {
    const secondaryWidgets = [
      'hourly_sales_trend',
      'branch_wise_sales_comparison',
      'payments_overview',
      'cash_movements_overview',
      'gross_profit_margin',
      'customer_mix',
      'order_source_distribution',
      'fulfillment_health',
      'outstanding_balance',
      'total_guests',
      'revenue_per_guest',
      'payment_status_distribution',
      'sales_by_weekday',
    ]

    for (const key of secondaryWidgets) {
      expect(dashboardWidgets.find((widget) => widget.key === key)?.defaultVisible).toBe(false)
    }

    expect(
      dashboardWidgets.find((widget) => widget.key === 'outstanding_balance')?.inverseTrend,
    ).toBe(true)
  })

  it('packs a short metric into the open cell below a tall chart', () => {
    const definitions = dashboardWidgets.filter((widget) =>
      ['total_sales', 'sales_analytics', 'total_orders'].includes(widget.key),
    )
    const packed = packDashboardLayout(
      [
        { key: 'total_sales', visible: true, minimized: false, order: 0 },
        { key: 'sales_analytics', visible: true, minimized: false, order: 1 },
        { key: 'total_orders', visible: true, minimized: false, order: 2 },
      ],
      definitions,
    )

    expect(packed).toMatchObject([
      { key: 'total_sales', x: 0, y: 0, w: 3, h: 2 },
      { key: 'sales_analytics', x: 3, y: 0, w: 8, h: 5 },
      { key: 'total_orders', x: 0, y: 2, w: 3, h: 2 },
    ])
  })

  it('persists a reordered layout synchronously', () => {
    const definitions = dashboardWidgets.slice(0, 3)
    const authorizedKeys = ref(new Set(definitions.map((definition) => definition.key)))
    const { visibleLayout } = useDashboardLayout(42, definitions, authorizedKeys)

    visibleLayout.value = [...visibleLayout.value].reverse()

    const saved = JSON.parse(window.localStorage.getItem('forkiva.dashboard.layout.v5.42') ?? '[]')
    expect(saved.map((item: { key: string }) => item.key)).toEqual([
      'total_active_orders',
      'total_orders',
      'total_sales',
    ])
    expect(saved).toMatchObject([
      { x: 0, y: 0, w: 3, h: 2 },
      { x: 3, y: 0, w: 3, h: 2 },
      { x: 6, y: 0, w: 3, h: 2 },
    ])
  })

  it('persists and restores dashboard filters for the current user only', () => {
    const filters = { branch_id: 3, from: '2026-08-01', to: '2026-08-31' }
    persistDashboardFilters(42, filters)

    expect(getStoredDashboardFilters(42)).toEqual(filters)
    expect(getStoredDashboardFilters(43)).toEqual({})
  })

  it('prefers account filters and falls back to valid local filters if the remote value is invalid', () => {
    const local = { branch_id: 2, from: '2026-08-01', to: '2026-08-31' }
    const remote = { branch_id: 3, from: '2026-09-01', to: '2026-09-27' }
    persistDashboardFilters(44, local)

    expect(getStoredDashboardFilters(44, remote)).toEqual(remote)
    expect(getStoredDashboardFilters(44, { from: 'invalid', to: '2026-09-27' })).toEqual(local)
  })

  it('does not leave gaps for hidden widgets when packing visible cards', () => {
    const definitions = dashboardWidgets.slice(0, 3)
    const packed = packDashboardLayout(
      definitions.map((definition, order) => ({
        key: definition.key,
        visible: order !== 1,
        minimized: false,
        order,
      })),
      definitions,
    )

    expect(packed.filter((item) => item.visible)).toMatchObject([
      { key: 'total_sales', x: 0, y: 0 },
      { key: 'total_active_orders', x: 3, y: 0 },
    ])
    expect(normalizeDashboardLayout(packed, definitions)).toEqual(packed)
  })

  it('closes large gaps in a saved dashboard while preserving widget order', () => {
    const definitions = dashboardWidgets.filter((widget) =>
      [
        'sales_analytics',
        'order_total_by_status',
        'top_selling_products',
        'low_stock_alerts',
      ].includes(widget.key),
    )
    const saved = [
      { key: 'sales_analytics', x: 0, y: 0, visible: true, minimized: false, order: 0 },
      { key: 'order_total_by_status', x: 8, y: 0, visible: true, minimized: false, order: 1 },
      { key: 'top_selling_products', x: 0, y: 10, visible: true, minimized: false, order: 2 },
      { key: 'low_stock_alerts', x: 6, y: 8, visible: true, minimized: false, order: 3 },
    ]

    const compacted = normalizeDashboardLayout(saved, definitions)
    expect(compacted).toMatchObject([
      { key: 'sales_analytics', x: 0, y: 0 },
      { key: 'order_total_by_status', x: 8, y: 0 },
      { key: 'top_selling_products', x: 0, y: 5 },
      { key: 'low_stock_alerts', x: 6, y: 5 },
    ])

    const key = 'forkiva.dashboard.layout.v5.91'
    window.localStorage.setItem(key, JSON.stringify(saved))
    const authorizedKeys = ref(new Set(definitions.map((definition) => definition.key)))
    useDashboardLayout(91, definitions, authorizedKeys)
    expect(JSON.parse(window.localStorage.getItem(key)!)).toEqual(compacted)
    window.localStorage.removeItem(key)
  })

  it('keeps horizontal spacing in saved rows while closing vertical gaps', () => {
    const definitions = dashboardWidgets.filter((widget) =>
      ['total_sales', 'sales_analytics'].includes(widget.key),
    )
    const saved = [
      { key: 'total_sales', x: 0, y: 0, visible: true, minimized: false, order: 0 },
      { key: 'sales_analytics', x: 4, y: 7, visible: true, minimized: false, order: 1 },
    ]

    expect(normalizeDashboardLayout(saved, definitions)).toMatchObject([
      { key: 'total_sales', x: 0, y: 0 },
      { key: 'sales_analytics', x: 4, y: 0 },
    ])
  })

  it('does not move a lower card through another card to fill an upper gap', () => {
    const definitions = dashboardWidgets.filter((widget) =>
      ['sales_analytics', 'low_stock_alerts', 'total_sales'].includes(widget.key),
    )
    const saved = [
      { key: 'sales_analytics', x: 0, y: 0, visible: true, minimized: false, order: 0 },
      { key: 'low_stock_alerts', x: 6, y: 5, visible: true, minimized: false, order: 1 },
      { key: 'total_sales', x: 8, y: 10, visible: true, minimized: false, order: 2 },
    ]

    expect(normalizeDashboardLayout(saved, definitions)).toMatchObject([
      { key: 'sales_analytics', x: 0, y: 0 },
      { key: 'low_stock_alerts', x: 6, y: 5 },
      { key: 'total_sales', x: 8, y: 10 },
    ])
  })

  it('migrates enabled KPI widgets ahead of dashboard panels', () => {
    const userId = 84
    const legacyKey = `forkiva.dashboard.layout.v4.${userId}`
    const currentKey = `forkiva.dashboard.layout.v5.${userId}`
    const legacyLayout = normalizeDashboardLayout(null, dashboardWidgets).map((item) =>
      item.key === 'total_users' ? { ...item, visible: true } : item,
    )
    window.localStorage.setItem(legacyKey, JSON.stringify(legacyLayout))

    const authorizedKeys = ref(new Set(dashboardWidgets.map((definition) => definition.key)))
    const { visibleLayout } = useDashboardLayout(userId, dashboardWidgets, authorizedKeys)
    const visibleKeys = visibleLayout.value.map((item) => item.key)
    const enabledMetric = visibleLayout.value.find((item) => item.key === 'total_users')!
    const firstPanel = visibleLayout.value.find(
      (item) =>
        dashboardWidgets.find((definition) => definition.key === item.key)?.kind !== 'metric',
    )!

    expect(visibleKeys.indexOf('total_users')).toBeLessThan(visibleKeys.indexOf('sales_analytics'))
    expect(firstPanel.y).toBeGreaterThanOrEqual(enabledMetric.y + enabledMetric.h)
    expect(window.localStorage.getItem(currentKey)).not.toBeNull()

    window.localStorage.removeItem(legacyKey)
    window.localStorage.removeItem(currentKey)
  })
})
