import { computed, ref, watch, type Ref } from 'vue'
import { localStorageEngine } from '@/modules/core/services/LocalStorageEngine.ts'
import type { AnalyticsLayoutItem, AnalyticsWidgetDefinition, AnalyticsWidgetKey } from './types.ts'

export const ANALYTICS_COLUMNS = 12
// Version 1 could persist GridStack's transient first-render coordinates before
// the admin shell finished sizing, leaving otherwise untouched dashboards stacked.
const STORAGE_VERSION = 3
const LEGACY_STORAGE_VERSION = 2

type LayoutSeed = Pick<AnalyticsLayoutItem, 'key' | 'visible' | 'minimized' | 'order'> &
  Partial<Pick<AnalyticsLayoutItem, 'x' | 'y' | 'w' | 'h'>>
type LayoutPosition = Pick<AnalyticsLayoutItem, 'key' | 'x' | 'y' | 'w' | 'h'>

function dimensionsFor(definition: AnalyticsWidgetDefinition, minimized: boolean) {
  return {
    w: definition.cols,
    h: minimized
      ? 1
      : definition.kind === 'metric'
        ? 2
        : definition.key === 'upcoming_reservations'
          ? 5
          : 4,
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

function occupy(occupied: Set<string>, item: Pick<AnalyticsLayoutItem, 'x' | 'y' | 'w' | 'h'>) {
  for (let row = item.y; row < item.y + item.h; row += 1) {
    for (let column = item.x; column < item.x + item.w; column += 1)
      occupied.add(`${column}:${row}`)
  }
}

function firstAvailablePosition(occupied: Set<string>, w: number, h: number) {
  for (let y = 0; ; y += 1) {
    for (let x = 0; x <= ANALYTICS_COLUMNS - w; x += 1) {
      if (areaIsFree(occupied, x, y, w, h)) return { x, y }
    }
  }
}

export function packAnalyticsLayout(
  items: LayoutSeed[],
  definitions: AnalyticsWidgetDefinition[],
): AnalyticsLayoutItem[] {
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
      item.x! + dimensions.w <= ANALYTICS_COLUMNS &&
      areaIsFree(occupied, item.x!, item.y!, dimensions.w, dimensions.h)
    const position = storedPositionIsValid
      ? { x: item.x!, y: item.y! }
      : firstAvailablePosition(occupied, dimensions.w, dimensions.h)
    const packed = { ...item, ...position, ...dimensions, order }
    if (item.visible) occupy(occupied, packed)
    return packed
  })
}

export function packAnalyticsMetricsFirst(
  items: AnalyticsLayoutItem[],
  definitions: AnalyticsWidgetDefinition[],
) {
  const definitionMap = new Map(definitions.map((definition) => [definition.key, definition]))
  const metrics = items
    .filter((item) => definitionMap.get(item.key)?.kind === 'metric')
    .sort((left, right) => left.order - right.order)
  const panels = items
    .filter((item) => definitionMap.get(item.key)?.kind !== 'metric')
    .sort((left, right) => left.order - right.order)
  const seed = (item: AnalyticsLayoutItem) => ({
    key: item.key,
    visible: item.visible,
    minimized: item.minimized,
    order: item.order,
  })
  const metricLayout = packAnalyticsLayout(
    metrics.map(seed),
    definitions.filter((definition) => definition.kind === 'metric'),
  )
  const metricBandHeight = metricLayout.reduce(
    (height, item) => (item.visible ? Math.max(height, item.y + item.h) : height),
    0,
  )
  const panelLayout = packAnalyticsLayout(
    panels.map(seed),
    definitions.filter((definition) => definition.kind !== 'metric'),
  )

  return [
    ...metricLayout,
    ...panelLayout.map((item) => ({ ...item, y: item.y + metricBandHeight })),
  ].map((item, order) => ({ ...item, order }))
}

function defaultLayout(definitions: AnalyticsWidgetDefinition[]) {
  return packAnalyticsLayout(
    definitions.map((definition, order) => ({
      key: definition.key,
      visible: definition.defaultVisible,
      minimized: false,
      order,
    })),
    definitions,
  )
}

export function normalizeAnalyticsLayout(value: unknown, definitions: AnalyticsWidgetDefinition[]) {
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
  const orderedStored = [...storedMap.values()].sort(
    (left, right) =>
      (left.order ?? Number.MAX_SAFE_INTEGER) - (right.order ?? Number.MAX_SAFE_INTEGER),
  )
  const missing = defaults.filter((item) => !storedMap.has(item.key))
  return packAnalyticsLayout([...orderedStored, ...missing], definitions)
}

export function useAnalyticsLayout(
  userId: number,
  definitions: AnalyticsWidgetDefinition[],
  enabledKeys: Ref<Set<AnalyticsWidgetKey>>,
  remoteLayout?: unknown,
) {
  const storageKey = `forkiva.reservation-analytics.layout.v${STORAGE_VERSION}.${userId}`
  const legacyStorageKey = `forkiva.reservation-analytics.layout.v${LEGACY_STORAGE_VERSION}.${userId}`
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

  const normalized = normalizeAnalyticsLayout(
    Array.isArray(remoteLayout) ? remoteLayout : parsed,
    definitions,
  )
  const layout = ref(
    currentStored || Array.isArray(remoteLayout)
      ? normalized
      : packAnalyticsMetricsFirst(normalized, definitions),
  )
  const visibleLayout = computed({
    get: () =>
      layout.value
        .filter((item) => item.visible && enabledKeys.value.has(item.key))
        .sort((left, right) => left.order - right.order),
    set: (items: AnalyticsLayoutItem[]) => {
      const reorderedKeys = items.map((item) => item.key)
      const remainder = layout.value
        .filter((item) => !reorderedKeys.includes(item.key))
        .sort((left, right) => left.order - right.order)
      layout.value = packAnalyticsLayout(
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

  function update(key: AnalyticsWidgetKey, changes: Partial<AnalyticsLayoutItem>) {
    layout.value = layout.value.map((item) => {
      if (item.key !== key) return item
      const updated = { ...item, ...changes }
      if (changes.minimized !== undefined && changes.h === undefined)
        updated.h = dimensionsFor(
          definitions.find((definition) => definition.key === key)!,
          changes.minimized,
        ).h
      return updated
    })
  }

  function updatePositions(positions: LayoutPosition[]) {
    const positionMap = new Map(positions.map((position) => [position.key, position]))
    const positioned = layout.value.map((item) => ({ ...item, ...positionMap.get(item.key) }))
    const visibleKeys = positioned
      .filter((item) => item.visible && enabledKeys.value.has(item.key))
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
    layout.value = packAnalyticsMetricsFirst(layout.value, definitions)
  }
  watch(layout, (value) => localStorageEngine.setItem(storageKey, JSON.stringify(value)), {
    deep: true,
    flush: 'sync',
    immediate: true,
  })
  return { layout, visibleLayout, update, updatePositions, reset, prioritizeMetrics }
}
