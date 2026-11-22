import type {
  KitchenOrder,
  KitchenProduct,
  KitchenSort,
  KitchenView,
} from '@/modules/pos/contracts/kitchen'

export function timestamp(value?: string | null): number | null {
  if (!value) return null
  const parsed = Date.parse(value)
  return Number.isFinite(parsed) ? parsed : null
}

export function kitchenState(order: KitchenOrder): Exclude<KitchenView, 'active' | 'delayed'> {
  if (['served', 'completed'].includes(order.status.id)) return 'completed'
  if (order.status.id === 'ready') return 'ready'
  // The kitchen may receive only its assigned categories. Classify the work
  // on this ticket without rewriting the server's overall order status.
  const items = order.products.filter((item) => !['cancelled', 'refunded'].includes(item.status.id))
  if (items.length) {
    if (items.some((item) => item.status.id === 'preparing')) return 'preparing'
    if (items.some((item) => item.status.id === 'pending')) return 'new'
    if (items.some((item) => item.status.id === 'ready')) return 'ready'
    if (items.every((item) => item.status.id === 'served')) return 'completed'
  }
  return order.status.id === 'preparing' ? 'preparing' : 'new'
}

export function isKitchenOrderVisible(order: KitchenOrder, keepFinished: boolean): boolean {
  if (['cancelled', 'refunded'].includes(order.status.id)) return false
  if (
    order.products.length &&
    order.products.every((item) => ['cancelled', 'refunded'].includes(item.status.id))
  )
    return false
  return keepFinished || !['ready', 'completed'].includes(kitchenState(order))
}

export function kitchenViews(keepFinished: boolean): KitchenView[] {
  return keepFinished
    ? ['active', 'new', 'preparing', 'ready', 'delayed', 'completed']
    : ['active', 'new', 'preparing', 'delayed']
}

export function isUnfinished(item: KitchenProduct): boolean {
  return ['pending', 'preparing'].includes(item.status.id)
}

// Completed items must never keep a mixed ticket overdue. Waiting uses a visible,
// local threshold; preparation uses each item's existing recipe deadline.
export function orderDeadline(order: KitchenOrder, delayMinutes: number): number | null {
  if (['ready', 'completed'].includes(kitchenState(order))) return null
  const created = timestamp(order.created_at)
  const deadlines = order.products.filter(isUnfinished).flatMap((item) => {
    const started = timestamp(item.preparation_started_at)
    const expected = timestamp(item.expected_ready_at)
    const minutes = Number(item.prep_time_minutes) || delayMinutes
    const deadline =
      item.status.id === 'preparing'
        ? (expected ?? (started !== null ? started + minutes * 60_000 : null))
        : created !== null
          ? created + delayMinutes * 60_000
          : null
    return deadline === null ? [] : [deadline]
  })
  return deadlines.length ? Math.min(...deadlines) : null
}

export function isDelayed(order: KitchenOrder, now: number, delayMinutes: number): boolean {
  const deadline = orderDeadline(order, delayMinutes)
  return deadline !== null && now >= deadline
}

export function elapsedLabel(
  order: KitchenOrder,
  now: number,
  formatDays?: (days: number) => string,
): string {
  const start = timestamp(order.created_at)
  if (start === null) return '—'
  const state = kitchenState(order)
  const end = ['ready', 'completed'].includes(state) ? timestamp(order.prepared_at) : null
  const duration = Math.max(0, (end ?? now) - start)
  if (duration >= 86_400_000 && formatDays) return formatDays(Math.floor(duration / 86_400_000))
  return durationLabel(duration)
}

export function durationLabel(milliseconds: number): string {
  const seconds = Math.floor(milliseconds / 1000)
  const hours = Math.floor(seconds / 3600)
  const minutes = Math.floor((seconds % 3600) / 60)
  return (hours ? [hours, minutes, seconds % 60] : [minutes, seconds % 60])
    .map((part) => String(part).padStart(2, '0'))
    .join(':')
}

export function sortKitchenOrders(
  orders: KitchenOrder[],
  sort: KitchenSort,
  delayMinutes: number,
): KitchenOrder[] {
  return [...orders].sort((a, b) => {
    const oldest =
      (timestamp(a.created_at) ?? Number.MAX_SAFE_INTEGER) -
      (timestamp(b.created_at) ?? Number.MAX_SAFE_INTEGER)
    if (sort === 'oldest') return oldest || a.id - b.id
    if (sort === 'newest') return -oldest || b.id - a.id
    const priority =
      (orderDeadline(a, delayMinutes) ?? Number.MAX_SAFE_INTEGER) -
      (orderDeadline(b, delayMinutes) ?? Number.MAX_SAFE_INTEGER)
    return priority || oldest || a.id - b.id
  })
}

// Advance only the earliest unfinished stage, never serve ready items while
// other items still need cooking. The API remains the transition authority.
export function nextOrderAction(order: KitchenOrder): { stage: string; ids: number[] } | null {
  if (kitchenState(order) === 'completed') return null
  for (const stage of ['pending', 'preparing', 'ready']) {
    const items = order.products.filter((item) => item.status.id === stage)
    if (!items.length) continue
    const ids = items.filter((item) => item.next_status).map((item) => item.id)
    return ids.length ? { stage, ids } : null
  }
  return null
}
