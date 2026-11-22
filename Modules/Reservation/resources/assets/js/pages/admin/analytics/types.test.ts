import { describe, expect, it } from 'vitest'
import { emptyAnalyticsPayload, normalizeAnalytics } from './types.ts'

describe('reservation analytics payload normalization', () => {
  it('preserves additive widget data while filling missing arrays', () => {
    const payload = normalizeAnalytics({
      widgets: {
        total_reservations: {
          key: 'total_reservations',
          permission: 'admin.reservations.analytics',
          authorized: true,
          data: { value: 3 },
        },
        average_party_size: {
          key: 'average_party_size',
          permission: 'admin.reservations.analytics',
          authorized: true,
          data: { value: 4.5 },
        },
        reservation_trend: {
          key: 'reservation_trend',
          permission: 'admin.reservations.analytics',
          authorized: true,
          data: [{ date: '2026-09-07', label: 'Sep 7', reservations_count: 3, guests_count: 14 }],
        },
        weekday_demand: {
          key: 'weekday_demand',
          permission: 'admin.reservations.analytics',
          authorized: true,
          data: [{ day: 1, label: 'Monday', reservations_count: 3 }],
        },
        party_size_distribution: {
          key: 'party_size_distribution',
          permission: 'admin.reservations.analytics',
          authorized: true,
          data: [{ id: 'three_four', label: '3–4', reservations_count: 2 }],
        },
      },
    })

    expect(payload.summary.average_party_size).toBe(4.5)
    expect(payload.reservation_trend).toHaveLength(1)
    expect(payload.weekday_demand[0]?.label).toBe('Monday')
    expect(payload.status_breakdown).toEqual([])
    expect(payload.meta).toEqual(emptyAnalyticsPayload().meta)
  })
})
