import { ref } from 'vue'
import { describe, expect, it } from 'vitest'
import { analyticsWidgets } from './config.ts'
import { normalizeAnalyticsLayout, packAnalyticsLayout, useAnalyticsLayout } from './layout.ts'

describe('reservation analytics layout', () => {
  it('uses the account layout ahead of browser storage', () => {
    const storageKey = 'forkiva.reservation-analytics.layout.v3.921'
    localStorage.setItem(
      storageKey,
      JSON.stringify([{ key: 'total_reservations', visible: false, minimized: false, order: 0 }]),
    )
    const { layout } = useAnalyticsLayout(
      921,
      analyticsWidgets,
      ref(new Set(analyticsWidgets.map((widget) => widget.key))),
      [{ key: 'total_guests', visible: false, minimized: true, order: 0 }],
    )

    expect(layout.value.find((item) => item.key === 'total_guests')).toMatchObject({
      visible: false,
      minimized: true,
    })
    expect(layout.value.find((item) => item.key === 'total_reservations')?.visible).toBe(true)
    localStorage.removeItem(storageKey)
  })

  it('creates the concept layout with four opening metrics and twelve visible widgets', () => {
    const layout = normalizeAnalyticsLayout(null, analyticsWidgets)
    const visible = layout.filter((item) => item.visible)

    expect(visible).toHaveLength(12)
    expect(visible.slice(0, 4).map((item) => item.key)).toEqual([
      'total_reservations',
      'total_guests',
      'average_party_size',
      'cancellation_rate',
    ])
    expect(visible.slice(0, 4).map(({ x, y, w, h }) => ({ x, y, w, h }))).toEqual([
      { x: 0, y: 0, w: 3, h: 2 },
      { x: 3, y: 0, w: 3, h: 2 },
      { x: 6, y: 0, w: 3, h: 2 },
      { x: 9, y: 0, w: 3, h: 2 },
    ])
  })

  it('keeps saved choices and appends widgets added after the saved layout', () => {
    const layout = normalizeAnalyticsLayout(
      [
        {
          key: 'total_guests',
          visible: false,
          minimized: true,
          order: 0,
          x: 0,
          y: 0,
          w: 3,
          h: 1,
        },
      ],
      analyticsWidgets,
    )

    expect(layout).toHaveLength(analyticsWidgets.length)
    expect(layout[0]).toMatchObject({ key: 'total_guests', visible: false, minimized: true })
    expect(new Set(layout.map((item) => item.key)).size).toBe(analyticsWidgets.length)
  })

  it('repacks colliding saved positions into free cells', () => {
    const definitions = analyticsWidgets.slice(0, 2)
    const layout = packAnalyticsLayout(
      definitions.map((definition, order) => ({
        key: definition.key,
        visible: true,
        minimized: false,
        order,
        x: 0,
        y: 0,
      })),
      definitions,
    )

    expect(layout[0]).toMatchObject({ x: 0, y: 0 })
    expect(layout[1]).toMatchObject({ x: 3, y: 0 })
  })

  it('migrates enabled KPI widgets ahead of analytics panels', () => {
    const userId = 4821
    const legacyKey = `forkiva.reservation-analytics.layout.v2.${userId}`
    const currentKey = `forkiva.reservation-analytics.layout.v3.${userId}`
    const legacyLayout = normalizeAnalyticsLayout(null, analyticsWidgets).map((item) =>
      [
        'today_count',
        'assigned_count',
        'unassigned_count',
        'no_show_rate',
        'completion_rate',
      ].includes(item.key)
        ? { ...item, visible: true }
        : item,
    )
    window.localStorage.setItem(legacyKey, JSON.stringify(legacyLayout))

    const { visibleLayout } = useAnalyticsLayout(
      userId,
      analyticsWidgets,
      ref(new Set(analyticsWidgets.map((definition) => definition.key))),
    )

    const firstPanelIndex = visibleLayout.value.findIndex(
      (item) =>
        analyticsWidgets.find((definition) => definition.key === item.key)?.kind !== 'metric',
    )
    const metricBandHeight = Math.max(
      ...visibleLayout.value.slice(0, firstPanelIndex).map((item) => item.y + item.h),
    )
    expect(firstPanelIndex).toBe(9)
    expect(visibleLayout.value.slice(0, firstPanelIndex).every((item) => item.h === 2)).toBe(true)
    expect(visibleLayout.value[firstPanelIndex]?.y).toBeGreaterThanOrEqual(metricBandHeight)
    expect(window.localStorage.getItem(currentKey)).not.toBeNull()

    window.localStorage.removeItem(legacyKey)
    window.localStorage.removeItem(currentKey)
  })
})
