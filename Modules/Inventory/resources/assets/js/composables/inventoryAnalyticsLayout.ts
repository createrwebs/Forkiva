import { computed, ref, watch } from 'vue'
import type {
  InventoryAnalyticsLayoutItem,
  InventoryAnalyticsWidgetDefinition,
  InventoryAnalyticsWidgetKey,
} from '../contracts/inventoryAnalytics.ts'
import { localStorageEngine } from '@/modules/core/services/LocalStorageEngine.ts'

export const INVENTORY_ANALYTICS_COLUMNS = 12
const STORAGE_VERSION = 3

type LayoutSeed = Pick<InventoryAnalyticsLayoutItem, 'key' | 'visible' | 'minimized' | 'order'> &
  Partial<Pick<InventoryAnalyticsLayoutItem, 'x' | 'y' | 'w' | 'h'>>

function dimensionsFor(
  definition: InventoryAnalyticsWidgetDefinition,
  minimized: boolean,
): Pick<InventoryAnalyticsLayoutItem, 'w' | 'h'> {
  return {
    w: definition.cols,
    h: minimized
      ? 1
      : (definition.rows ??
        (definition.kind === 'metric' ? 2 : definition.kind === 'list' ? 4 : 5)),
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

function occupy(
  occupied: Set<string>,
  item: Pick<InventoryAnalyticsLayoutItem, 'x' | 'y' | 'w' | 'h'>,
) {
  for (let row = item.y; row < item.y + item.h; row += 1) {
    for (let column = item.x; column < item.x + item.w; column += 1) {
      occupied.add(`${column}:${row}`)
    }
  }
}

function firstAvailablePosition(occupied: Set<string>, w: number, h: number) {
  for (let y = 0; ; y += 1) {
    for (let x = 0; x <= INVENTORY_ANALYTICS_COLUMNS - w; x += 1) {
      if (areaIsFree(occupied, x, y, w, h)) return { x, y }
    }
  }
}

export function packInventoryAnalyticsLayout(
  items: LayoutSeed[],
  definitions: InventoryAnalyticsWidgetDefinition[],
): InventoryAnalyticsLayoutItem[] {
  const definitionMap = new Map(definitions.map((definition) => [definition.key, definition]))
  const occupied = new Set<string>()

  return items.map((item, order) => {
    const dimensions = dimensionsFor(definitionMap.get(item.key)!, item.minimized)
    const storedPositionIsValid =
      Number.isInteger(item.x) &&
      Number.isInteger(item.y) &&
      item.x! >= 0 &&
      item.y! >= 0 &&
      item.x! + dimensions.w <= INVENTORY_ANALYTICS_COLUMNS &&
      areaIsFree(occupied, item.x!, item.y!, dimensions.w, dimensions.h)
    const position = storedPositionIsValid
      ? { x: item.x!, y: item.y! }
      : firstAvailablePosition(occupied, dimensions.w, dimensions.h)
    const packed = { ...item, ...position, ...dimensions, order }

    if (item.visible) occupy(occupied, packed)
    return packed
  })
}

function defaultLayout(definitions: InventoryAnalyticsWidgetDefinition[]) {
  return packInventoryAnalyticsLayout(
    definitions.map((definition, order) => ({
      key: definition.key,
      visible: definition.defaultVisible,
      minimized: false,
      order,
    })),
    definitions,
  )
}

function normalizeLayout(value: unknown, definitions: InventoryAnalyticsWidgetDefinition[]) {
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
  const ordered = [...storedMap.values()].sort((left, right) => left.order - right.order)
  const missing = defaults.filter((item) => !storedMap.has(item.key))

  return packInventoryAnalyticsLayout([...ordered, ...missing], definitions)
}

function migrateLegacyLayout(
  value: unknown,
  definitions: InventoryAnalyticsWidgetDefinition[],
): unknown {
  if (!Array.isArray(value)) return value

  const stored = new Map(
    value
      .filter((item): item is LayoutSeed => typeof item === 'object' && item !== null)
      .map((item) => [item.key, item]),
  )

  return definitions.map((definition, order) => {
    const item = stored.get(definition.key)
    return {
      key: definition.key,
      visible: typeof item?.visible === 'boolean' ? item.visible : definition.defaultVisible,
      minimized: typeof item?.minimized === 'boolean' ? item.minimized : false,
      order,
    }
  })
}

export function useInventoryAnalyticsLayout(
  userId: number,
  definitions: InventoryAnalyticsWidgetDefinition[],
  remoteLayout?: unknown,
) {
  const storageKey = `forkiva.inventory-analytics.layout.v${STORAGE_VERSION}.${userId}`
  let parsed: unknown = null
  const currentStored = localStorageEngine.getItem(storageKey)
  const legacyStored = currentStored
    ? null
    : (localStorageEngine.getItem(`forkiva.inventory-analytics.layout.v2.${userId}`) ??
      localStorageEngine.getItem(`forkiva.inventory-analytics.layout.v1.${userId}`))
  const stored = currentStored ?? legacyStored
  if (stored) {
    try {
      parsed = JSON.parse(stored)
      if (legacyStored) parsed = migrateLegacyLayout(parsed, definitions)
    } catch {
      parsed = null
    }
  }

  const layout = ref(
    normalizeLayout(Array.isArray(remoteLayout) ? remoteLayout : parsed, definitions),
  )
  const visibleLayout = computed({
    get: () =>
      layout.value.filter((item) => item.visible).sort((left, right) => left.order - right.order),
    set: (items: InventoryAnalyticsLayoutItem[]) => {
      const keys = items.map((item) => item.key)
      const remainder = layout.value
        .filter((item) => !keys.includes(item.key))
        .sort((left, right) => left.order - right.order)
      layout.value = packInventoryAnalyticsLayout(
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

  function update(
    key: InventoryAnalyticsWidgetKey,
    changes: Partial<InventoryAnalyticsLayoutItem>,
  ) {
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

  function updatePositions(
    positions: Array<Pick<InventoryAnalyticsLayoutItem, 'key' | 'x' | 'y' | 'w' | 'h'>>,
  ) {
    const positionMap = new Map(positions.map((position) => [position.key, position]))
    const positioned = layout.value.map((item) => ({ ...item, ...positionMap.get(item.key) }))
    const visibleKeys = positioned
      .filter((item) => item.visible)
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

  function repack() {
    layout.value = packInventoryAnalyticsLayout(
      [...layout.value]
        .sort((left, right) => left.order - right.order)
        .map(({ key, visible, minimized, order }) => ({ key, visible, minimized, order })),
      definitions,
    )
  }

  watch(layout, (value) => localStorageEngine.setItem(storageKey, JSON.stringify(value)), {
    deep: true,
    flush: 'sync',
    immediate: true,
  })

  return { layout, visibleLayout, update, updatePositions, reset, repack }
}
