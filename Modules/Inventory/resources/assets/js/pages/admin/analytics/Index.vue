<script lang="ts" setup>
import 'gridstack/dist/gridstack.min.css'
import axios from 'axios'
import { type GridItemHTMLElement, GridStack } from 'gridstack'
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { useDashboardViewPreference } from '@/modules/userPreference/composables/dashboardViewPreference.ts'
import {
  findDashboardGridCollision,
  DashboardGridEngine,
  packDashboardGridUpwards,
} from '@/modules/dashboard/composables/dashboardGridEngine.ts'
import {
  getInventoryAnalytics,
  getInventoryAnalyticsMeta,
} from '@/modules/inventory/api/inventoryAnalytics.api.ts'
import {
  INVENTORY_ANALYTICS_COLUMNS,
  useInventoryAnalyticsLayout,
} from '@/modules/inventory/composables/inventoryAnalyticsLayout.ts'
import {
  inventoryAnalyticsWidgetMap,
  inventoryAnalyticsWidgets,
} from '@/modules/inventory/config/inventoryAnalytics.widgets.ts'
import type {
  InventoryAnalyticsBranch,
  InventoryAnalyticsFilters,
  InventoryAnalyticsLayoutItem,
  InventoryAnalyticsWidgetKey,
  InventoryAnalyticsWidgetMove,
} from '@/modules/inventory/contracts/inventoryAnalytics.ts'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import WidgetCustomizer from '@/modules/core/components/Dashboard/WidgetCustomizer.vue'
import DatePicker from '@/modules/core/components/Form/DatePicker.vue'
import type {
  WidgetCustomizerDefinition,
  WidgetCustomizerLabels,
} from '@/modules/core/contracts/WidgetCustomizer.ts'
import InventoryAnalyticsWidgetRenderer from './Partials/InventoryAnalyticsWidgetRenderer.vue'

const { t, locale } = useI18n()
const toast = useToast()
const { user } = useAuth()
const viewPreference = useDashboardViewPreference('inventoryAnalytics')
const today = () => new Date().toLocaleDateString('en-CA')
const daysAgo = (days: number) => {
  const date = new Date()
  date.setDate(date.getDate() - days)
  return date.toLocaleDateString('en-CA')
}

const filters = reactive<InventoryAnalyticsFilters>({
  branch_id: user?.assigned_to_branch ? Number(user.branch_id) : null,
  from: daysAgo(29),
  to: today(),
})
const metaLoading = ref(true)
const dashboardLoading = ref(true)
const branches = ref<InventoryAnalyticsBranch[]>([])
const selectedPreset = ref('last_30_days')
const dateMenuOpen = ref(false)
const customizerOpen = ref(false)
const editingLayout = ref(false)
const generatedAt = ref('')
const data = reactive<Partial<Record<InventoryAnalyticsWidgetKey, unknown>>>({})
const widgetLoading = reactive<Partial<Record<InventoryAnalyticsWidgetKey, boolean>>>({})
const widgetOptions = reactive<Partial<Record<InventoryAnalyticsWidgetKey, number>>>(
  Object.fromEntries(
    inventoryAnalyticsWidgets
      .filter((definition) => definition.filter)
      .map((definition) => [definition.key, definition.filter!.default]),
  ),
)
const savedWidgetOptions = viewPreference.restoreWidgetOptions({ ...widgetOptions })
for (const definition of inventoryAnalyticsWidgets) {
  const value = savedWidgetOptions[definition.key]
  if (definition.filter && typeof value === 'number' && definition.filter.options.includes(value)) {
    widgetOptions[definition.key] = value
  }
}
const { layout, visibleLayout, update, updatePositions, reset, repack } =
  useInventoryAnalyticsLayout(
    Number(user?.id ?? 0),
    inventoryAnalyticsWidgets,
    viewPreference.remoteLayout,
  )
const gridRoot = ref<HTMLElement | null>(null)
const draggingKey = ref<InventoryAnalyticsWidgetKey | null>(null)
const reorderMessage = ref('')
const dragGridHeight = ref<number | null>(null)

let requestController: AbortController | null = null
let metaController: AbortController | null = null
let dashboardGrid: GridStack | null = null
async function toggleLayoutEditing() {
  if (editingLayout.value) {
    dashboardGrid?.enableMove(false)
    editingLayout.value = false
    return
  }
  editingLayout.value = true
  await nextTick()
  dashboardGrid?.enableMove(true)
}
let reorderMessageTimer: ReturnType<typeof setTimeout> | null = null
let gridInitialization = 0

const visibleCount = computed(() => visibleLayout.value.length)
const branchItems = computed(() => [
  { id: 0, name: t('inventory::inventories.analytics.all_branches') },
  ...branches.value,
])
const scopeLabel = computed(() => {
  if (user?.assigned_to_branch) return t('inventory::inventories.analytics.assigned_branch')
  return branchItems.value.find((branch) => branch.id === (filters.branch_id ?? 0))?.name
})
const periodItems = computed(() => [
  { id: 'last_7_days', name: t('inventory::inventories.analytics.periods.last_7_days') },
  { id: 'last_30_days', name: t('inventory::inventories.analytics.periods.last_30_days') },
  { id: 'last_90_days', name: t('inventory::inventories.analytics.periods.last_90_days') },
  { id: 'this_year', name: t('inventory::inventories.analytics.periods.this_year') },
  { id: 'custom', name: t('inventory::inventories.analytics.periods.custom') },
])
const customizerLabels = computed<WidgetCustomizerLabels>(() => ({
  title: t('inventory::inventories.analytics.customizer.title'),
  subtitle: t('inventory::inventories.analytics.customizer.subtitle'),
  search: t('inventory::inventories.analytics.customizer.search'),
  visible: t('inventory::inventories.analytics.customizer.visible'),
  hidden: t('inventory::inventories.analytics.customizer.hidden'),
  dragHint: t('inventory::inventories.analytics.customizer.drag_hint'),
  empty: t('inventory::inventories.analytics.customizer.empty'),
  reset: t('inventory::inventories.analytics.actions.reset'),
  done: t('inventory::inventories.analytics.actions.done'),
  minimize: t('inventory::inventories.analytics.actions.minimize'),
  expand: t('inventory::inventories.analytics.actions.expand'),
}))

function definitionFor(key: InventoryAnalyticsWidgetKey) {
  return inventoryAnalyticsWidgetMap.get(key)!
}
function customizerWidgetTitle(definition: WidgetCustomizerDefinition) {
  return t(`inventory::inventories.analytics.widgets.${definition.key}.title`)
}
function customizerCategoryTitle(definition: WidgetCustomizerDefinition) {
  return t(`inventory::inventories.analytics.categories.${definition.category}`)
}
function customizerDragLabel(title: string) {
  return t('inventory::inventories.analytics.customizer.drag_label', { widget: title })
}
function formatFilterDate(value: string) {
  const date = new Date(`${value}T00:00:00`)
  if (Number.isNaN(date.getTime())) return value
  return new Intl.DateTimeFormat(locale.value, {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(date)
}

async function loadMeta(): Promise<boolean> {
  metaController?.abort()
  metaController = new AbortController()
  metaLoading.value = true
  try {
    const response = await getInventoryAnalyticsMeta(metaController.signal)
    branches.value = response.data.body.branches ?? []
    Object.assign(filters, viewPreference.restoreFilters(response.data.body.defaults))
    return true
  } catch (error) {
    if (!axios.isCancel(error))
      toast.error(t('inventory::inventories.analytics.errors.load_filters'))
    return false
  } finally {
    metaLoading.value = false
  }
}

async function loadDashboard(_force = false) {
  requestController?.abort()
  const controller = new AbortController()
  requestController = controller
  dashboardLoading.value = true
  const keys = visibleLayout.value.map((item) => item.key)
  for (const key of keys) widgetLoading[key] = true

  try {
    if (keys.length === 0) return
    const response = await getInventoryAnalytics({ ...filters, widgets: keys }, controller.signal)
    generatedAt.value = response.data.body.meta.generated_at
    for (const key of keys) data[key] = response.data.body.widgets[key]?.data ?? null
  } catch (error) {
    if (!axios.isCancel(error)) toast.error(t('inventory::inventories.analytics.errors.load'))
  } finally {
    if (requestController === controller) {
      dashboardLoading.value = false
      for (const key of keys) delete widgetLoading[key]
    }
  }
}

async function loadWidget(key: InventoryAnalyticsWidgetKey) {
  widgetLoading[key] = true
  try {
    const response = await getInventoryAnalytics({ ...filters, widgets: [key] })
    generatedAt.value = response.data.body.meta.generated_at
    data[key] = response.data.body.widgets[key]?.data ?? null
  } catch {
    toast.error(t('inventory::inventories.analytics.errors.load'))
  } finally {
    delete widgetLoading[key]
  }
}

async function applyPreset(id: string) {
  selectedPreset.value = id
  if (id === 'custom') {
    dateMenuOpen.value = true
    return
  }
  const ranges: Record<string, [string, string]> = {
    last_7_days: [daysAgo(6), today()],
    last_30_days: [daysAgo(29), today()],
    last_90_days: [daysAgo(89), today()],
    this_year: [`${new Date().getFullYear()}-01-01`, today()],
  }
  const range = ranges[id]
  if (!range) return
  ;[filters.from, filters.to] = range
  await loadDashboard()
}
async function applyDateRange() {
  if (!filters.from || !filters.to) return
  selectedPreset.value = 'custom'
  dateMenuOpen.value = false
  await loadDashboard()
}
async function changeBranch(value: number | string | null) {
  filters.branch_id = Number(value) || null
  await loadDashboard()
}

function widgetKeyFromElement(element: GridItemHTMLElement) {
  return element.getAttribute('gs-id') as InventoryAnalyticsWidgetKey | null
}
function persistGridPositions() {
  if (!dashboardGrid || dashboardGrid.getColumn() !== INVENTORY_ANALYTICS_COLUMNS) return
  packDashboardGridUpwards(dashboardGrid)
  const positions = dashboardGrid
    .getGridItems()
    .map((element) => {
      const key = widgetKeyFromElement(element)
      const node = element.gridstackNode
      if (
        !key ||
        node?.x === undefined ||
        node.y === undefined ||
        node.w === undefined ||
        node.h === undefined
      )
        return null
      return { key, x: node.x, y: node.y, w: node.w, h: node.h }
    })
    .filter((item): item is NonNullable<typeof item> => item !== null)
  updatePositions(positions)
}
function gridWidgetKeys() {
  return (dashboardGrid?.getGridItems() ?? [])
    .sort(
      (left, right) =>
        (left.gridstackNode?.y ?? 0) - (right.gridstackNode?.y ?? 0) ||
        (left.gridstackNode?.x ?? 0) - (right.gridstackNode?.x ?? 0),
    )
    .map(widgetKeyFromElement)
    .filter((key): key is InventoryAnalyticsWidgetKey => key !== null)
}
function announcePosition(key: InventoryAnalyticsWidgetKey) {
  const element = gridRoot.value?.querySelector<GridItemHTMLElement>(`[gs-id="${key}"]`)
  const node = element?.gridstackNode
  if (!node || node.x === undefined || node.y === undefined) return
  reorderMessage.value = t('inventory::inventories.analytics.customizer.position', {
    widget: t(`inventory::inventories.analytics.widgets.${key}.title`),
    column: node.x + 1,
    row: node.y + 1,
  })
  if (reorderMessageTimer) clearTimeout(reorderMessageTimer)
  reorderMessageTimer = setTimeout(() => (reorderMessage.value = ''), 2400)
}
function destroyGrid(invalidatePendingInitialization = true) {
  if (invalidatePendingInitialization) gridInitialization += 1
  dashboardGrid?.offAll()
  dashboardGrid?.destroy(false)
  dashboardGrid = null
  draggingKey.value = null
  dragGridHeight.value = null
}

async function initializeGrid() {
  const initialization = ++gridInitialization
  await nextTick()
  if (initialization !== gridInitialization || !gridRoot.value) return
  destroyGrid(false)
  for (const item of visibleLayout.value) {
    const element = gridRoot.value.querySelector(`[gs-id="${item.key}"]`)
    for (const dimension of ['x', 'y', 'w', 'h'] as const)
      element?.setAttribute(`gs-${dimension}`, String(item[dimension]))
  }
  const grid = GridStack.init(
    {
      animate: true,
      cellHeight: 88,
      column: INVENTORY_ANALYTICS_COLUMNS,
      columnOpts: {
        breakpoints: [
          { w: 600, c: 1, layout: 'list' },
          { w: 1200, c: 6, layout: 'moveScale' },
        ],
        layout: 'moveScale',
      },
      disableDrag: !editingLayout.value,
      disableResize: true,
      draggable: {
        handle: '.dashboard-widget__drag',
        cancel:
          '.dashboard-panel__actions, .inventory-metric__actions, input, select, button:not(.dashboard-widget__drag)',
        scroll: true,
      },
      engineClass: DashboardGridEngine,
      float: true,
      margin: 9,
      rtl: getComputedStyle(gridRoot.value).direction === 'rtl',
    },
    gridRoot.value,
  )
  if (!grid) return
  dashboardGrid = grid
  grid.on('dragstart', (_event, element) => {
    dragGridHeight.value = gridRoot.value?.getBoundingClientRect().height ?? null
    draggingKey.value = widgetKeyFromElement(element)
  })
  grid.on('dragstop', async (_event, element) => {
    const key = widgetKeyFromElement(element) ?? draggingKey.value
    await nextTick()
    draggingKey.value = null
    dragGridHeight.value = null
    if (grid.getColumn() === INVENTORY_ANALYTICS_COLUMNS) persistGridPositions()
    else await reorderVisible(gridWidgetKeys())
    if (key) announcePosition(key)
  })
  persistGridPositions()
}

async function setVisible(key: InventoryAnalyticsWidgetKey, visible: boolean) {
  destroyGrid()
  update(key, { visible, ...(visible ? { minimized: false } : {}) })
  repack()
  await initializeGrid()
  if (visible && data[key] === undefined) await loadWidget(key)
}
async function toggleMinimized(key: InventoryAnalyticsWidgetKey) {
  const item = layout.value.find((candidate) => candidate.key === key)
  if (!item) return
  update(key, { minimized: !item.minimized })
  await nextTick()
  const element = gridRoot.value?.querySelector<GridItemHTMLElement>(`[gs-id="${key}"]`)
  const changed = layout.value.find((candidate) => candidate.key === key)
  if (element && changed && dashboardGrid) {
    dashboardGrid.update(element, { h: changed.h })
    persistGridPositions()
  }
}
async function reorderVisible(keys: InventoryAnalyticsWidgetKey[]) {
  destroyGrid()
  const itemMap = new Map(visibleLayout.value.map((item) => [item.key, item]))
  visibleLayout.value = keys
    .map((key) => itemMap.get(key))
    .filter((item): item is InventoryAnalyticsLayoutItem => Boolean(item))
  await initializeGrid()
}
async function moveWidget(key: InventoryAnalyticsWidgetKey, move: InventoryAnalyticsWidgetMove) {
  if (!editingLayout.value) return
  const element = gridRoot.value?.querySelector<GridItemHTMLElement>(`[gs-id="${key}"]`)
  const node = element?.gridstackNode
  if (!element || !node || !dashboardGrid) return
  const columns = dashboardGrid.getColumn()
  if (columns === 1) {
    if (move === 'left' || move === 'right') return
    const keys = gridWidgetKeys()
    const index = keys.indexOf(key)
    const target =
      move === 'first'
        ? 0
        : move === 'last'
          ? keys.length - 1
          : Math.max(0, Math.min(keys.length - 1, index + (move === 'up' ? -1 : 1)))
    if (index === target) return
    keys.splice(index, 1)
    keys.splice(target, 0, key)
    await reorderVisible(keys)
  } else {
    const x = node.x ?? 0
    const y = node.y ?? 0
    const w = node.w ?? 1
    const h = node.h ?? 1
    if (dashboardGrid.opts.rtl && (move === 'left' || move === 'right')) {
      move = move === 'left' ? 'right' : 'left'
    }
    const target =
      move === 'up'
        ? { x, y: Math.max(0, y - h) }
        : move === 'down'
          ? { x, y: y + h }
          : move === 'left'
            ? { x: Math.max(0, x - w), y }
            : move === 'right'
              ? { x: Math.min(columns - w, x + w), y }
              : move === 'first'
                ? { x: 0, y }
                : { x: columns - w, y }
    if (target.x === x && target.y === y) return
    const occupant = findDashboardGridCollision(dashboardGrid, element, target, { w, h })
    if (occupant) {
      const other = occupant.gridstackNode
      const otherKey = widgetKeyFromElement(occupant)
      if (
        !otherKey ||
        !other ||
        other.x !== target.x ||
        other.y !== target.y ||
        other.w !== w ||
        other.h !== h
      )
        return
      updatePositions([
        { key, x: target.x, y: target.y, w, h },
        { key: otherKey, x, y, w, h },
      ])
      await initializeGrid()
      announcePosition(key)
      return
    }
    dashboardGrid.update(element, target)
    persistGridPositions()
  }
  announcePosition(key)
}
async function resetDashboard() {
  destroyGrid()
  reset()
  await initializeGrid()
  await loadDashboard()
}

onMounted(async () => {
  const [metaLoaded] = await Promise.all([loadMeta(), initializeGrid()])
  await loadDashboard()
  if (metaLoaded) {
    viewPreference.track(
      layout,
      () => ({ ...filters }),
      () => ({ ...widgetOptions }) as Record<string, unknown>,
    )
  }
})
onBeforeUnmount(() => {
  metaController?.abort()
  requestController?.abort()
  destroyGrid()
  if (reorderMessageTimer) clearTimeout(reorderMessageTimer)
})
</script>

<template>
  <div class="inventory-analytics">
    <header class="inventory-analytics__header">
      <div>
        <h1>{{ t('inventory::inventories.analytics.page.title') }}</h1>
        <p>{{ t('inventory::inventories.analytics.page.subtitle') }}</p>
      </div>
      <div class="inventory-analytics__header-actions">
        <VBtn
          :aria-pressed="editingLayout"
          :color="editingLayout ? 'primary' : 'secondary'"
          :prepend-icon="editingLayout ? 'tabler-check' : 'tabler-arrows-move'"
          variant="tonal"
          @click="toggleLayoutEditing"
        >
          {{
            t(
              editingLayout
                ? 'dashboard::dashboards.actions.finish_editing'
                : 'dashboard::dashboards.actions.edit_layout',
            )
          }}
        </VBtn>
        <VBtn
          :loading="dashboardLoading && Boolean(generatedAt)"
          color="secondary"
          prepend-icon="tabler-refresh"
          variant="tonal"
          @click="loadDashboard(true)"
        >
          {{ t('inventory::inventories.analytics.actions.refresh') }}
        </VBtn>
        <VBtn
          color="primary"
          prepend-icon="tabler-layout-dashboard"
          @click="customizerOpen = true"
        >
          {{ t('inventory::inventories.analytics.actions.customize') }}
          <span class="inventory-analytics__count">{{ visibleCount }}</span>
        </VBtn>
      </div>
    </header>

    <VCard
      class="inventory-analytics__filters"
      elevation="0"
    >
      <VSelect
        :items="periodItems"
        :label="t('inventory::inventories.analytics.date')"
        :model-value="selectedPreset"
        hide-details
        item-title="name"
        item-value="id"
        prepend-inner-icon="tabler-calendar-stats"
        variant="outlined"
        @update:model-value="applyPreset"
      />
      <VSelect
        v-if="!user?.assigned_to_branch"
        :disabled="metaLoading"
        :items="branchItems"
        :label="t('inventory::inventories.analytics.branch')"
        :model-value="filters.branch_id ?? 0"
        hide-details
        item-title="name"
        item-value="id"
        prepend-inner-icon="tabler-building-store"
        variant="outlined"
        @update:model-value="changeBranch"
      />
      <VMenu
        v-model="dateMenuOpen"
        :close-on-content-click="false"
        :offset="8"
        location="bottom start"
      >
        <template #activator="{ props: menuProps }">
          <VBtn
            class="inventory-analytics__range"
            color="primary"
            prepend-icon="tabler-calendar-event"
            v-bind="menuProps"
            variant="tonal"
          >
            {{ formatFilterDate(filters.from) }}
            <VIcon
              class="mx-2"
              icon="tabler-arrow-right"
              size="15"
            />
            {{ formatFilterDate(filters.to) }}
          </VBtn>
        </template>
        <VCard class="inventory-analytics__date-menu">
          <VCardTitle>{{ t('inventory::inventories.analytics.periods.custom') }}</VCardTitle>
          <VDivider />
          <VCardText class="inventory-analytics__date-body">
            <div class="inventory-analytics__date-fields">
              <DatePicker
                v-model="filters.from"
                :label="t('inventory::inventories.analytics.from')"
                :max="filters.to"
                hide-details
              />
              <DatePicker
                v-model="filters.to"
                :label="t('inventory::inventories.analytics.to')"
                :min="filters.from"
                :max="today()"
                hide-details
              />
            </div>
            <VBtn
              block
              color="primary"
              @click="applyDateRange"
            >
              {{ t('inventory::inventories.analytics.actions.apply') }}
            </VBtn>
          </VCardText>
        </VCard>
      </VMenu>
      <VSpacer />
      <div class="inventory-analytics__scope">
        <VIcon
          icon="tabler-info-circle"
          size="17"
        />
        {{ scopeLabel }}
      </div>
    </VCard>

    <div
      v-if="visibleLayout.length"
      ref="gridRoot"
      :data-sorting="draggingKey ? true : undefined"
      :style="{ minHeight: dragGridHeight ? `${dragGridHeight}px` : undefined }"
      class="inventory-analytics__grid grid-stack"
    >
      <section
        v-for="item in visibleLayout"
        :key="item.key"
        :data-kind="definitionFor(item.key).kind"
        :gs-h="item.h"
        :gs-id="item.key"
        :gs-no-resize="true"
        :gs-w="item.w"
        :gs-x="item.x"
        :gs-y="item.y"
        class="inventory-analytics__widget grid-stack-item"
      >
        <div class="grid-stack-item-content">
          <InventoryAnalyticsWidgetRenderer
            :editable="editingLayout"
            :data="data[item.key] ?? null"
            :definition="definitionFor(item.key)"
            :loading="dashboardLoading || Boolean(widgetLoading[item.key])"
            :minimized="item.minimized"
            :option-value="widgetOptions[item.key]"
            @hide="setVisible(item.key, false)"
            @minimize="toggleMinimized(item.key)"
            @move="moveWidget(item.key, $event)"
            @update:option="widgetOptions[item.key] = $event"
          />
        </div>
      </section>
    </div>

    <VCard
      v-else
      class="inventory-analytics__empty"
      elevation="0"
    >
      <VIcon
        color="primary"
        icon="tabler-layout-dashboard"
        size="42"
      />
      <h2>{{ t('inventory::inventories.analytics.empty.title') }}</h2>
      <p>{{ t('inventory::inventories.analytics.empty.subtitle') }}</p>
      <VBtn
        color="primary"
        @click="customizerOpen = true"
      >
        {{ t('inventory::inventories.analytics.actions.customize') }}
      </VBtn>
    </VCard>

    <Transition name="inventory-status">
      <div
        v-if="draggingKey || reorderMessage"
        aria-live="polite"
        class="inventory-analytics__status"
        role="status"
      >
        <VIcon
          :icon="draggingKey ? 'tabler-arrows-sort' : 'tabler-circle-check'"
          size="18"
        />
        {{
          draggingKey ? t('inventory::inventories.analytics.customizer.drop_hint') : reorderMessage
        }}
      </div>
    </Transition>

    <WidgetCustomizer
      v-model="customizerOpen"
      :category-title="customizerCategoryTitle"
      :definitions="inventoryAnalyticsWidgets"
      :drag-label="customizerDragLabel"
      :labels="customizerLabels"
      :layout="layout"
      :widget-title="customizerWidgetTitle"
      @minimize="(key) => toggleMinimized(key as InventoryAnalyticsWidgetKey)"
      @reorder="(keys) => reorderVisible(keys as InventoryAnalyticsWidgetKey[])"
      @reset="resetDashboard"
      @set-visible="(key, visible) => setVisible(key as InventoryAnalyticsWidgetKey, visible)"
    />
  </div>
</template>

<style lang="scss" scoped>
.inventory-analytics {
  min-block-size: calc(100vh - 120px);
  padding: 4px 2px 28px;
}
.inventory-analytics__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 22px;
  margin-block-end: 20px;
}
.inventory-analytics__header h1 {
  font-size: clamp(1.65rem, 2.8vw, 2.1rem);
  font-weight: 800;
  letter-spacing: -0.045em;
  line-height: 1.1;
}
.inventory-analytics__header p {
  margin-block-start: 5px;
  color: rgba(var(--v-theme-on-surface), 0.55);
  font-size: 0.86rem;
}
.inventory-analytics__header-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}
.inventory-analytics__count {
  display: inline-grid;
  min-inline-size: 25px;
  padding: 2px 7px;
  border: 1px solid rgba(255, 255, 255, 0.24);
  border-radius: 999px;
  margin-inline-start: 9px;
  background: rgba(255, 255, 255, 0.2);
  font-size: 0.7rem;
  font-weight: 800;
  place-items: center;
}
.inventory-analytics__filters {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-block-end: 20px;
  padding: 12px;
  border: 1px solid rgba(var(--v-border-color), 0.1);
  border-radius: 16px;
  box-shadow: 0 7px 24px rgba(0, 0, 0, 0.045) !important;
}
.inventory-analytics__filters :deep(.v-input) {
  max-inline-size: 220px;
}
.inventory-analytics__filters :deep(.v-field) {
  border-radius: 11px;
}
.inventory-analytics__range {
  block-size: 37px;
  border-radius: 11px;
  font-size: 0.76rem;
}
.inventory-analytics__scope {
  display: flex;
  align-items: center;
  gap: 6px;
  padding-inline: 8px;
  color: rgba(var(--v-theme-on-surface), 0.5);
  font-size: 0.72rem;
}
.inventory-analytics__date-menu {
  inline-size: min(430px, calc(100vw - 32px));
  border-radius: 16px !important;
}
.inventory-analytics__date-body {
  display: grid;
  gap: 18px;
}
.inventory-analytics__date-fields {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}
.inventory-analytics__grid {
  margin: -9px;
}
.inventory-analytics__grid[data-sorting] {
  user-select: none;
}
.inventory-analytics__widget {
  min-inline-size: 0;
}
.inventory-analytics__widget > .grid-stack-item-content {
  display: flex;
  overflow: visible;
  border-radius: 18px;
}
.inventory-analytics__widget > .grid-stack-item-content > * {
  min-inline-size: 0;
  flex: 1 1 auto;
}
.inventory-analytics__grid[data-sorting] :deep(.dashboard-widget__drag) {
  cursor: grabbing;
}
.inventory-analytics__grid :deep(.grid-stack-placeholder > .placeholder-content) {
  border: 2px dashed rgb(var(--v-theme-primary));
  border-radius: 18px;
  background: rgba(var(--v-theme-primary), 0.08);
}
.inventory-analytics__empty {
  display: grid;
  min-block-size: 320px;
  align-content: center;
  justify-items: center;
  gap: 8px;
  border: 1px dashed rgba(var(--v-border-color), 0.2);
  border-radius: 18px;
}
.inventory-analytics__empty h2 {
  font-size: 1.05rem;
}
.inventory-analytics__empty p {
  margin-block-end: 8px;
  color: rgba(var(--v-theme-on-surface), 0.55);
  font-size: 0.78rem;
}
.inventory-analytics__status {
  position: fixed;
  z-index: 2500;
  inset-block-end: 28px;
  inset-inline-start: 50%;
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 11px 16px;
  border: 1px solid rgba(var(--v-theme-primary), 0.2);
  border-radius: 12px;
  background: rgb(var(--v-theme-surface));
  box-shadow: 0 14px 42px rgba(0, 0, 0, 0.16);
  font-size: 0.78rem;
  font-weight: 650;
  transform: translateX(-50%);
}
.inventory-status-enter-active,
.inventory-status-leave-active {
  transition:
    opacity 160ms ease,
    transform 160ms ease;
}
.inventory-status-enter-from,
.inventory-status-leave-to {
  opacity: 0;
  transform: translate(-50%, 8px);
}
@media (max-width: 840px) {
  .inventory-analytics__header {
    align-items: flex-start;
    flex-direction: column;
  }
  .inventory-analytics__header-actions {
    inline-size: 100%;
  }
  .inventory-analytics__scope {
    display: none;
  }
  .inventory-analytics__header-actions .v-btn:last-child {
    margin-inline-start: auto;
  }
  .inventory-analytics__filters {
    flex-wrap: wrap;
  }
}
@media (max-width: 600px) {
  .inventory-analytics__header-actions .v-btn:last-child {
    flex: 1;
  }
  .inventory-analytics__filters :deep(.v-input) {
    max-inline-size: none;
    flex: 1 1 145px;
  }
  .inventory-analytics__range {
    inline-size: 100%;
  }
  .inventory-analytics__date-fields {
    grid-template-columns: 1fr;
  }
}
@media (prefers-reduced-motion: reduce) {
  .inventory-analytics__grid :deep(.grid-stack-item),
  .inventory-status-enter-active,
  .inventory-status-leave-active {
    transition: none !important;
  }
}
</style>
