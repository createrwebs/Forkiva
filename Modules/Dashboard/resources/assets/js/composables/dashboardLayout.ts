import { computed, ref, watch, type Ref } from 'vue'
import type {
  DashboardFilters,
  DashboardLayoutItem,
  DashboardWidgetDefinition,
  DashboardWidgetKey,
} from '@/modules/dashboard/contracts/dashboard.ts'
import { localStorageEngine } from '@/modules/core/services/LocalStorageEngine.ts'

export const DASHBOARD_COLUMNS = 12
const STORAGE_VERSION = 5
const LEGACY_STORAGE_VERSION = 4
const FILTERS_STORAGE_VERSION = 1

function filtersStorageKey(userId: number) {
  return `forkiva.dashboard.filters.v${FILTERS_STORAGE_VERSION}.${userId}`
}

export function normalizeDashboardFilters(value: unknown): Partial<DashboardFilters> {
  if (typeof value !== 'object' || value === null || Array.isArray(value)) return {}
  const filters = value as Partial<DashboardFilters>
  const validDate = (date: unknown): date is string =>
    typeof date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(date)

  if (!validDate(filters.from) || !validDate(filters.to) || filters.from > filters.to) return {}

  return {
    from: filters.from,
    to: filters.to,
    branch_id: typeof filters.branch_id === 'number' ? filters.branch_id : null,
  }
}

export function getStoredDashboardFilters(
  userId: number,
  remote?: unknown,
): Partial<DashboardFilters> {
  const remoteFilters = normalizeDashboardFilters(remote)
  if (remoteFilters.from && remoteFilters.to) return remoteFilters
  const stored = localStorageEngine.getItem(filtersStorageKey(userId))
  if (!stored) return {}

  try {
    return normalizeDashboardFilters(JSON.parse(stored))
  } catch {
    return {}
  }
}

export function persistDashboardFilters(userId: number, filters: DashboardFilters) {
  localStorageEngine.setItem(filtersStorageKey(userId), JSON.stringify(filters))
}

type LayoutSeed = Pick<DashboardLayoutItem, 'key' | 'visible' | 'minimized' | 'order'> &
  Partial<Pick<DashboardLayoutItem, 'x' | 'y' | 'w' | 'h'>>

type DashboardPosition = Pick<DashboardLayoutItem, 'key' | 'x' | 'y' | 'w' | 'h'>

function dimensionsFor(
  definition: DashboardWidgetDefinition,
  minimized: boolean,
): Pick<DashboardLayoutItem, 'w' | 'h'> {
  return {
    w: definition.cols,
    h: minimized ? 1 : definition.kind === 'metric' ? 2 : 5,
  }
}

function areaIsFree(occupied: Set<string>, x: number, y: number, w: number, h: number) {
  for (let row = y; row < y + h; row += 1) {
    for (let column = x; column < x + w; column += 1) {
      if (occupied.has(`${column}:${row}`)) return false
    }
  }

  return true
}

function occupy(occupied: Set<string>, item: Pick<DashboardLayoutItem, 'x' | 'y' | 'w' | 'h'>) {
  for (let row = item.y; row < item.y + item.h; row += 1) {
    for (let column = item.x; column < item.x + item.w; column += 1) {
      occupied.add(`${column}:${row}`)
    }
  }
}

function firstAvailablePosition(occupied: Set<string>, w: number, h: number) {
  for (let y = 0; ; y += 1) {
    for (let x = 0; x <= DASHBOARD_COLUMNS - w; x += 1) {
      if (areaIsFree(occupied, x, y, w, h)) return { x, y }
    }
  }
}

export function packDashboardLayout(
  items: LayoutSeed[],
  definitions: DashboardWidgetDefinition[],
): DashboardLayoutItem[] {
  const definitionMap = new Map(definitions.map((definition) => [definition.key, definition]))
  const occupied = new Set<string>()

  return items.map((item, order) => {
    const definition = definitionMap.get(item.key)!
    const dimensions = dimensionsFor(definition, item.minimized)
    const storedPositionIsValid =
      Number.isInteger(item.x) &&
      Number.isInteger(item.y) &&
      item.x! >= 0 &&
      item.y! >= 0 &&
      item.x! + dimensions.w <= DASHBOARD_COLUMNS &&
      areaIsFree(occupied, item.x!, item.y!, dimensions.w, dimensions.h)
    const position = storedPositionIsValid
      ? { x: item.x!, y: item.y! }
      : firstAvailablePosition(occupied, dimensions.w, dimensions.h)
    const packed = {
      ...item,
      ...position,
      ...dimensions,
      order,
    }

    // Hidden widgets keep their preferred position without reserving visible grid space.
    if (item.visible) occupy(occupied, packed)
    return packed
  })
}

function packVisibleLayoutUpwards(items: DashboardLayoutItem[]): DashboardLayoutItem[] {
  const occupied = new Set<string>()
  const positions = new Map<DashboardWidgetKey, { y: number }>()

  for (const item of [...items]
    .filter((candidate) => candidate.visible)
    .sort((left, right) => left.y - right.y || left.x - right.x)) {
    let y = item.y
    while (y > 0 && areaIsFree(occupied, item.x, y - 1, item.w, item.h)) y -= 1
    positions.set(item.key, { y })
    occupy(occupied, { ...item, y })
  }

  return items.map((item) => ({ ...item, ...positions.get(item.key) }))
}

export function packDashboardMetricsFirst(
  items: DashboardLayoutItem[],
  definitions: DashboardWidgetDefinition[],
): DashboardLayoutItem[] {
  const definitionMap = new Map(definitions.map((definition) => [definition.key, definition]))
  const metrics = items
    .filter((item) => definitionMap.get(item.key)?.kind === 'metric')
    .sort((left, right) => left.order - right.order)
  const panels = items
    .filter((item) => definitionMap.get(item.key)?.kind !== 'metric')
    .sort((left, right) => left.order - right.order)
  const seed = (item: DashboardLayoutItem) => ({
    key: item.key,
    visible: item.visible,
    minimized: item.minimized,
    order: item.order,
  })
  const metricLayout = packDashboardLayout(
    metrics.map(seed),
    definitions.filter((definition) => definition.kind === 'metric'),
  )
  const metricBandHeight = metricLayout.reduce(
    (height, item) => (item.visible ? Math.max(height, item.y + item.h) : height),
    0,
  )
  const panelLayout = packDashboardLayout(
    panels.map(seed),
    definitions.filter((definition) => definition.kind !== 'metric'),
  )

  return [
    ...metricLayout,
    ...panelLayout.map((item) => ({ ...item, y: item.y + metricBandHeight })),
  ].map((item, order) => ({ ...item, order }))
}

function defaultLayout(definitions: DashboardWidgetDefinition[]): DashboardLayoutItem[] {
  return packDashboardLayout(
    definitions.map((definition, order) => ({
      key: definition.key,
      visible: definition.defaultVisible,
      minimized: false,
      order,
    })),
    definitions,
  )
}

export function normalizeDashboardLayout(
  value: unknown,
  definitions: DashboardWidgetDefinition[],
): DashboardLayoutItem[] {
  const defaults = defaultLayout(definitions)
  if (!Array.isArray(value)) return defaults

  const allowedKeys = new Set(definitions.map((definition) => definition.key))
  const stored = value.filter(
    (item): item is LayoutSeed =>
      typeof item === 'object' &&
      item !== null &&
      allowedKeys.has((item as LayoutSeed).key) &&
      typeof (item as LayoutSeed).visible === 'boolean' &&
      typeof (item as LayoutSeed).minimized === 'boolean',
  )
  const storedMap = new Map(stored.map((item) => [item.key, item]))
  const storedKeys = new Set(storedMap.keys())
  const orderedStored = [...storedMap.values()].sort((left, right) => {
    const leftOrder = Number.isFinite(left.order) ? left.order : Number.MAX_SAFE_INTEGER
    const rightOrder = Number.isFinite(right.order) ? right.order : Number.MAX_SAFE_INTEGER
    return leftOrder - rightOrder
  })
  const missing = defaults.filter((item) => !storedKeys.has(item.key))

  return packVisibleLayoutUpwards(packDashboardLayout([...orderedStored, ...missing], definitions))
}

export function useDashboardLayout(
  userId: number,
  definitions: DashboardWidgetDefinition[],
  authorizedKeys: Ref<Set<DashboardWidgetKey>>,
  remoteLayout: unknown = null,
) {
  const storageKey = `forkiva.dashboard.layout.v${STORAGE_VERSION}.${userId}`
  const legacyStorageKey = `forkiva.dashboard.layout.v${LEGACY_STORAGE_VERSION}.${userId}`
  const currentStored = localStorageEngine.getItem(storageKey)
  const legacyStored = localStorageEngine.getItem(legacyStorageKey)
  const stored = currentStored ?? legacyStored
  let parsed: unknown = null

  if (stored) {
    try {
      parsed = JSON.parse(stored)
    } catch {
      parsed = null
    }
  }

  const hasRemoteLayout = Array.isArray(remoteLayout)
  const normalized = normalizeDashboardLayout(hasRemoteLayout ? remoteLayout : parsed, definitions)
  const layout = ref(
    hasRemoteLayout || currentStored
      ? normalized
      : packDashboardMetricsFirst(normalized, definitions),
  )
  const visibleLayout = computed({
    get: () =>
      layout.value
        .filter((item) => item.visible && authorizedKeys.value.has(item.key))
        .sort((left, right) => left.order - right.order),
    set: (items: DashboardLayoutItem[]) => {
      const reorderedKeys = items.map((item) => item.key)
      const remainder = layout.value
        .filter((item) => !reorderedKeys.includes(item.key))
        .sort((left, right) => left.order - right.order)
      layout.value = packDashboardLayout(
        [...items, ...remainder].map(({ key, visible, minimized, order }) => ({
          key,
          visible,
          minimized,
          order,
        })),
        definitions,
      )
    },
  })

  function update(key: DashboardWidgetKey, changes: Partial<DashboardLayoutItem>) {
    layout.value = layout.value.map((item) => {
      if (item.key !== key) return item

      const updated = { ...item, ...changes }
      if (changes.minimized !== undefined && changes.h === undefined) {
        updated.h = dimensionsFor(
          definitions.find((definition) => definition.key === key)!,
          changes.minimized,
        ).h
      }

      return updated
    })
  }

  function updatePositions(positions: DashboardPosition[]) {
    const positionMap = new Map(positions.map((position) => [position.key, position]))
    const positioned = layout.value.map((item) => ({
      ...item,
      ...positionMap.get(item.key),
    }))
    const visibleKeys = positioned
      .filter((item) => item.visible && authorizedKeys.value.has(item.key))
      .sort((left, right) => left.y - right.y || left.x - right.x)
      .map((item) => item.key)
    const orderedKeys = [
      ...visibleKeys,
      ...positioned
        .filter((item) => !visibleKeys.includes(item.key))
        .sort((left, right) => left.order - right.order)
        .map((item) => item.key),
    ]
    const orderMap = new Map(orderedKeys.map((key, order) => [key, order]))

    layout.value = positioned.map((item) => ({ ...item, order: orderMap.get(item.key)! }))
  }

  function reset() {
    layout.value = defaultLayout(definitions)
  }

  function prioritizeMetrics() {
    layout.value = packDashboardMetricsFirst(layout.value, definitions)
  }

  watch(layout, (value) => localStorageEngine.setItem(storageKey, JSON.stringify(value)), {
    deep: true,
    flush: 'sync',
    immediate: true,
  })

  return {
    layout,
    visibleLayout,
    update,
    updatePositions,
    reset,
    prioritizeMetrics,
  }
}
