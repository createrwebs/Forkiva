import { computed, shallowRef, watch, type Ref } from 'vue'
import { localStorageEngine } from '@/modules/core/services/LocalStorageEngine.ts'

export const WIDGET_DASHBOARD_COLUMNS = 12

export interface WidgetDashboardDefinition<Key extends string = string> {
  key: Key
  kind: string
  cols: number
  defaultVisible: boolean
}

export interface WidgetDashboardLayoutItem<Key extends string = string> {
  key: Key
  visible: boolean
  minimized: boolean
  order: number
  x: number
  y: number
  w: number
  h: number
}

type LayoutSeed<Key extends string> = Pick<
  WidgetDashboardLayoutItem<Key>,
  'key' | 'visible' | 'minimized' | 'order'
> &
  Partial<Pick<WidgetDashboardLayoutItem<Key>, 'x' | 'y' | 'w' | 'h'>>

type DashboardPosition<Key extends string> = Pick<
  WidgetDashboardLayoutItem<Key>,
  'key' | 'x' | 'y' | 'w' | 'h'
>

function dimensionsFor<Key extends string>(
  definition: WidgetDashboardDefinition<Key>,
  minimized: boolean,
) {
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

function occupy(occupied: Set<string>, item: { x: number; y: number; w: number; h: number }) {
  for (let row = item.y; row < item.y + item.h; row += 1) {
    for (let column = item.x; column < item.x + item.w; column += 1) {
      occupied.add(`${column}:${row}`)
    }
  }
}

function firstAvailablePosition(occupied: Set<string>, w: number, h: number) {
  for (let y = 0; ; y += 1) {
    for (let x = 0; x <= WIDGET_DASHBOARD_COLUMNS - w; x += 1) {
      if (areaIsFree(occupied, x, y, w, h)) return { x, y }
    }
  }
}

export function packWidgetDashboardLayout<Key extends string>(
  items: LayoutSeed<Key>[],
  definitions: WidgetDashboardDefinition<Key>[],
): WidgetDashboardLayoutItem<Key>[] {
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
      item.x! + dimensions.w <= WIDGET_DASHBOARD_COLUMNS &&
      areaIsFree(occupied, item.x!, item.y!, dimensions.w, dimensions.h)
    const position = storedPositionIsValid
      ? { x: item.x!, y: item.y! }
      : firstAvailablePosition(occupied, dimensions.w, dimensions.h)
    const packed = { ...item, ...position, ...dimensions, order }

    if (item.visible) occupy(occupied, packed)
    return packed
  })
}

export function packWidgetDashboardMetricsFirst<Key extends string>(
  items: WidgetDashboardLayoutItem<Key>[],
  definitions: WidgetDashboardDefinition<Key>[],
): WidgetDashboardLayoutItem<Key>[] {
  const definitionMap = new Map(definitions.map((definition) => [definition.key, definition]))
  const metrics = items
    .filter((item) => definitionMap.get(item.key)?.kind === 'metric')
    .sort((left, right) => left.order - right.order)
  const panels = items
    .filter((item) => definitionMap.get(item.key)?.kind !== 'metric')
    .sort((left, right) => left.order - right.order)
  const seed = (item: WidgetDashboardLayoutItem<Key>): LayoutSeed<Key> => ({
    key: item.key,
    visible: item.visible,
    minimized: item.minimized,
    order: item.order,
  })
  const metricDefinitions = definitions.filter((definition) => definition.kind === 'metric')
  const panelDefinitions = definitions.filter((definition) => definition.kind !== 'metric')
  const metricLayout = packWidgetDashboardLayout(metrics.map(seed), metricDefinitions)
  const metricBandHeight = metricLayout.reduce(
    (height, item) => (item.visible ? Math.max(height, item.y + item.h) : height),
    0,
  )
  const panelLayout = packWidgetDashboardLayout(panels.map(seed), panelDefinitions)

  return [
    ...metricLayout,
    ...panelLayout.map((item) => ({ ...item, y: item.y + metricBandHeight })),
  ].map((item, order) => ({ ...item, order }))
}

function defaultLayout<Key extends string>(definitions: WidgetDashboardDefinition<Key>[]) {
  return packWidgetDashboardLayout(
    definitions.map((definition, order) => ({
      key: definition.key,
      visible: definition.defaultVisible,
      minimized: false,
      order,
    })),
    definitions,
  )
}

function normalizeLayout<Key extends string>(
  value: unknown,
  definitions: WidgetDashboardDefinition<Key>[],
) {
  const defaults = defaultLayout(definitions)
  if (!Array.isArray(value)) return defaults

  const allowedKeys = new Set<string>(definitions.map((definition) => definition.key))
  const stored = value.filter(
    (item): item is LayoutSeed<Key> =>
      typeof item === 'object' &&
      item !== null &&
      allowedKeys.has(String((item as LayoutSeed<Key>).key)) &&
      typeof (item as LayoutSeed<Key>).visible === 'boolean' &&
      typeof (item as LayoutSeed<Key>).minimized === 'boolean',
  )
  const storedMap = new Map(stored.map((item) => [item.key, item]))
  const orderedStored = [...storedMap.values()].sort(
    (left, right) =>
      (Number.isFinite(left.order) ? left.order : Number.MAX_SAFE_INTEGER) -
      (Number.isFinite(right.order) ? right.order : Number.MAX_SAFE_INTEGER),
  )

  return packWidgetDashboardLayout(
    [...orderedStored, ...defaults.filter((item) => !storedMap.has(item.key))],
    definitions,
  )
}

export function useWidgetDashboardLayout<Key extends string>(
  storageNamespace: string,
  storageVersion: number,
  userId: number,
  definitions: WidgetDashboardDefinition<Key>[],
  authorizedKeys: Ref<Set<Key>>,
  legacyStorageVersion?: number,
  remoteLayout?: unknown,
) {
  const storageKey = `forkiva.${storageNamespace}.layout.v${storageVersion}.${userId}`
  const legacyStorageKey = legacyStorageVersion
    ? `forkiva.${storageNamespace}.layout.v${legacyStorageVersion}.${userId}`
    : null
  const currentStored = localStorageEngine.getItem(storageKey)
  const legacyStored = legacyStorageKey ? localStorageEngine.getItem(legacyStorageKey) : null
  const stored = currentStored ?? legacyStored
  let parsed: unknown

  try {
    parsed = JSON.parse(stored ?? 'null')
  } catch {
    parsed = null
  }

  const normalized = normalizeLayout(
    Array.isArray(remoteLayout) ? remoteLayout : parsed,
    definitions,
  )
  const layout = shallowRef<WidgetDashboardLayoutItem<Key>[]>(
    legacyStored && !currentStored && !Array.isArray(remoteLayout)
      ? packWidgetDashboardMetricsFirst(normalized, definitions)
      : normalized,
  )
  const visibleLayout = computed({
    get: () =>
      layout.value
        .filter((item) => item.visible && authorizedKeys.value.has(item.key))
        .sort((left, right) => left.order - right.order),
    set: (items: WidgetDashboardLayoutItem<Key>[]) => {
      const reorderedKeys = items.map((item) => item.key)
      const remainder = layout.value.filter((item) => !reorderedKeys.includes(item.key))
      layout.value = packWidgetDashboardLayout([...items, ...remainder], definitions)
    },
  })

  function update(key: Key, changes: Partial<WidgetDashboardLayoutItem<Key>>) {
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

  function updatePositions(positions: DashboardPosition<Key>[]) {
    const positionMap = new Map(positions.map((position) => [position.key, position]))
    const positioned = layout.value.map((item) => ({ ...item, ...positionMap.get(item.key) }))
    const visibleKeys = positioned
      .filter((item) => item.visible && authorizedKeys.value.has(item.key))
      .sort((left, right) => left.y - right.y || left.x - right.x)
      .map((item) => item.key)
    const order = new Map(
      [
        ...visibleKeys,
        ...positioned.map((item) => item.key).filter((key) => !visibleKeys.includes(key)),
      ].map((key, index) => [key, index]),
    )
    layout.value = positioned.map((item) => ({ ...item, order: order.get(item.key)! }))
  }

  function reset() {
    layout.value = defaultLayout(definitions)
  }

  function prioritizeMetrics() {
    layout.value = packWidgetDashboardMetricsFirst(layout.value, definitions)
  }

  watch(layout, (value) => localStorageEngine.setItem(storageKey, JSON.stringify(value)), {
    deep: true,
    flush: 'sync',
    immediate: true,
  })

  return { layout, visibleLayout, update, updatePositions, reset, prioritizeMetrics }
}
