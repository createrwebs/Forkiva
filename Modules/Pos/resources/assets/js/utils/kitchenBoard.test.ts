import { describe, expect, it } from 'vitest'
import type { KitchenOrder, KitchenProduct } from '@/modules/pos/contracts/kitchen'
import {
  elapsedLabel,
  isDelayed,
  isKitchenOrderVisible,
  kitchenState,
  kitchenViews,
  nextOrderAction,
  sortKitchenOrders,
} from './kitchenBoard'

const product = (
  id: number,
  status: string,
  extra: Partial<KitchenProduct> = {},
): KitchenProduct => ({
  id,
  quantity: 1,
  product: { id, name: 'Soup' },
  options: [],
  status: { id: status, name: status },
  next_status: { id: 'next', name: 'Next' },
  ...extra,
})
const order = (products: KitchenProduct[], extra: Partial<KitchenOrder> = {}): KitchenOrder => ({
  id: 1,
  order_number: '01',
  reference_no: 'REF1',
  created_at: '2026-09-21T10:00:00Z',
  time: '10:00',
  date: '2026-09-21',
  type: { id: 'takeaway', name: 'Takeaway' },
  status: { id: 'preparing', name: 'Preparing' },
  products,
  ...extra,
})

describe('Kitchen ticket priority and progression', () => {
  it('classifies finished kitchen items instead of showing an old confirmed order as new', () => {
    const extra = { status: { id: 'confirmed', name: 'Confirmed' } }
    expect(kitchenState(order([product(1, 'served'), product(2, 'refunded')], extra))).toBe(
      'completed',
    )
    expect(kitchenState(order([product(1, 'ready'), product(2, 'served')], extra))).toBe('ready')
    expect(kitchenState(order([product(1, 'pending'), product(2, 'ready')], extra))).toBe('new')
    expect(kitchenState(order([product(1, 'preparing'), product(2, 'pending')], extra))).toBe(
      'preparing',
    )
  })
  it('uses the visibility setting for both finished tickets and available filters', () => {
    expect(kitchenViews(false)).not.toContain('ready')
    expect(kitchenViews(false)).not.toContain('completed')
    expect(kitchenViews(true)).toEqual(expect.arrayContaining(['ready', 'completed']))
    for (const status of ['ready', 'served']) {
      const ticket = order([product(1, status)])
      expect(isKitchenOrderVisible(ticket, false)).toBe(false)
      expect(isKitchenOrderVisible(ticket, true)).toBe(true)
    }
    expect(isKitchenOrderVisible(order([product(1, 'pending'), product(2, 'ready')]), false)).toBe(
      true,
    )
  })
  it('removes tickets with only refunded or cancelled items while retaining mixed tickets', () => {
    expect(
      isKitchenOrderVisible(order([product(1, 'refunded'), product(2, 'cancelled')]), true),
    ).toBe(false)
    expect(
      isKitchenOrderVisible(order([product(1, 'refunded'), product(2, 'preparing')]), false),
    ).toBe(true)
  })
  it('advances only new items in a mixed ticket, without serving ready items', () => {
    expect(
      nextOrderAction(order([product(1, 'pending'), product(2, 'preparing'), product(3, 'ready')])),
    ).toEqual({ stage: 'pending', ids: [1] })
  })
  it('advances only preparing items after all new items have started', () => {
    expect(nextOrderAction(order([product(2, 'preparing'), product(3, 'ready')]))).toEqual({
      stage: 'preparing',
      ids: [2],
    })
  })
  it('respects server restrictions and completed orders', () => {
    expect(nextOrderAction(order([product(3, 'ready', { next_status: null })]))).toBeNull()
    expect(
      nextOrderAction(order([product(3, 'ready')], { status: { id: 'served', name: 'Served' } })),
    ).toBeNull()
  })
  it('does not let a ready item keep an unfinished ticket overdue', () => {
    const ticket = order([
      product(1, 'ready', { expected_ready_at: '2026-09-21T10:01:00Z' }),
      product(2, 'preparing', { expected_ready_at: '2026-09-21T10:20:00Z' }),
    ])
    expect(isDelayed(ticket, Date.parse('2026-09-21T10:10:00Z'), 15)).toBe(false)
    expect(isDelayed(ticket, Date.parse('2026-09-21T10:20:00Z'), 15)).toBe(true)
  })
  it('flags waiting items using the displayed waiting threshold', () => {
    const ticket = order([product(1, 'pending')], {
      status: { id: 'confirmed', name: 'Confirmed' },
    })
    expect(isDelayed(ticket, Date.parse('2026-09-21T10:16:00Z'), 15)).toBe(true)
    expect(isDelayed(ticket, Date.parse('2026-09-21T10:16:00Z'), 20)).toBe(false)
  })
  it('never flags ready or served orders as delayed', () => {
    expect(
      isDelayed(
        order([product(1, 'pending')], { status: { id: 'served', name: 'Served' } }),
        Date.parse('2026-09-22T10:00:00Z'),
        15,
      ),
    ).toBe(false)
  })
  it('uses due times for priority and creation times for chronological sorting', () => {
    const older = order([product(1, 'preparing', { expected_ready_at: '2026-09-21T10:30:00Z' })])
    const urgent = order([product(2, 'preparing', { expected_ready_at: '2026-09-21T10:10:00Z' })], {
      id: 2,
      created_at: '2026-09-21T10:05:00Z',
    })
    expect(sortKitchenOrders([older, urgent], 'priority', 15).map((o) => o.id)).toEqual([2, 1])
    expect(sortKitchenOrders([older, urgent], 'oldest', 15).map((o) => o.id)).toEqual([1, 2])
    expect(sortKitchenOrders([older, urgent], 'newest', 15).map((o) => o.id)).toEqual([2, 1])
  })
  it('freezes preparation duration when ready and handles absent timestamps honestly', () => {
    expect(
      elapsedLabel(
        order([], { status: { id: 'ready', name: 'Ready' }, prepared_at: '2026-09-21T10:12:00Z' }),
        Date.parse('2026-09-21T11:00:00Z'),
      ),
    ).toBe('12:00')
    expect(elapsedLabel(order([], { created_at: null }), Date.now())).toBe('—')
  })
})
