<script lang="ts" setup>
import 'gridstack/dist/gridstack.min.css'
import axios from 'axios'
import { type GridItemHTMLElement, GridStack } from 'gridstack'
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { useDashboard } from '@/modules/dashboard/composables/dashboard.ts'
import {
  DASHBOARD_COLUMNS,
  getStoredDashboardFilters,
  normalizeDashboardFilters,
  persistDashboardFilters,
  useDashboardLayout,
} from '@/modules/dashboard/composables/dashboardLayout.ts'
import { DashboardGridEngine } from '@/modules/dashboard/composables/dashboardGridEngine.ts'
import {
  dashboardWidgetMap,
  dashboardWidgets,
} from '@/modules/dashboard/config/dashboard.widgets.ts'
import type {
  DashboardFilterOption,
  DashboardFilters,
  DashboardFiltersResponse,
  DashboardLayoutItem,
  DashboardResponse,
  DashboardWidgetKey,
  DashboardWidgetMove,
  DashboardWidgetOptions,
  DashboardWidgetOptionValue,
} from '@/modules/dashboard/contracts/dashboard.ts'
import { useAuthStore } from '@/modules/auth/stores/authStore.ts'
import { useUserPreferenceStore } from '@/modules/userPreference/stores/userPreferenceStore.ts'
import type { UserPreferenceValues } from '@/modules/userPreference/contracts/UserPreference.ts'
import WidgetCustomizer from '@/modules/core/components/Dashboard/WidgetCustomizer.vue'
import DatePicker from '@/modules/core/components/Form/DatePicker.vue'
import type {
  WidgetCustomizerDefinition,
  WidgetCustomizerLabels,
} from '@/modules/core/contracts/WidgetCustomizer.ts'
import DashboardEmptyState from './Partials/DashboardEmptyState.vue'
import DashboardNoAccessState from './Partials/DashboardNoAccessState.vue'
import DashboardWidgetRenderer from './Partials/DashboardWidgetRenderer.vue'

const { t, locale } = useI18n()
const toast = useToast()
const auth = useAuthStore()
const userPreferences = useUserPreferenceStore()
const { dashboard, dashboardFilters } = useDashboard()
const dashboardUserId = auth.getUser?.id ?? 0

const authorizedDefinitions = computed(() =>
  dashboardWidgets.filter((definition) => auth.can(definition.permission)),
)
const hasDashboardAccess = computed(() => authorizedDefinitions.value.length > 0)
const authorizedKeys = computed(
  () => new Set(authorizedDefinitions.value.map((definition) => definition.key)),
)
const { layout, visibleLayout, update, updatePositions, reset, prioritizeMetrics } =
  useDashboardLayout(
    dashboardUserId,
    dashboardWidgets,
    authorizedKeys,
    userPreferences.values?.dashboardLayout,
  )

const customizerOpen = ref(false)
const editingLayout = ref(false)
const layoutWritesReady = ref(false)
const dateMenuOpen = ref(false)
const filtersLoading = ref(true)
const dashboardLoading = ref(true)
const selectedPreset = ref('this_year')
const filters = reactive<DashboardFilters>({ branch_id: null, from: '', to: '' })
const filterMeta = ref<DashboardFiltersResponse | null>(null)
const response = ref<DashboardResponse | null>(null)
const widgetLoading = reactive<Partial<Record<DashboardWidgetKey, boolean>>>({})
const widgetOptions = reactive<DashboardWidgetOptions>(
  Object.fromEntries(
    dashboardWidgets
      .filter((definition) => definition.filter)
      .map((definition) => [
        definition.key,
        { [definition.filter!.key]: definition.filter!.default },
      ]),
  ),
)
const draggingKey = ref<DashboardWidgetKey | null>(null)
const reorderMessage = ref('')
const gridRoot = ref<HTMLElement | null>(null)
const dragGridHeight = ref<number | null>(null)

let filtersController: AbortController | null = null
let dashboardController: AbortController | null = null
let reorderMessageTimer: ReturnType<typeof setTimeout> | null = null
let dashboardGrid: GridStack | null = null
let dragStartPositions: string | null = null
const widgetControllers = new Map<DashboardWidgetKey, AbortController>()
let preferenceSaveFailed = false

async function saveDashboardPreference<K extends 'dashboardLayout' | 'dashboardFilters'>(
  name: K,
  value: UserPreferenceValues[K],
) {
  if (auth.getUser?.id !== dashboardUserId) return
  const saved = await userPreferences.save(name, value)
  if (saved) {
    preferenceSaveFailed = false
  } else if (!preferenceSaveFailed) {
    preferenceSaveFailed = true
    toast.error(t('dashboard::dashboards.errors.save_preferences'))
  }
}

watch(
  layout,
  (value) => {
    if (layoutWritesReady.value) void saveDashboardPreference('dashboardLayout', value)
  },
  { deep: true, flush: 'post' },
)

const periodItems = computed(() => [
  ...(filterMeta.value?.presets ?? []),
  {
    id: 'custom',
    name: t('dashboard::dashboards.filters.custom'),
    from: filters.from,
    to: filters.to,
  },
])
const branchFilter = computed(() =>
  filterMeta.value?.filters.find((filter) => filter.key === 'branch_id'),
)
const branchItems = computed<DashboardFilterOption[]>(() => [
  { id: 0, name: t('dashboard::dashboards.filters.all_branches') },
  ...(branchFilter.value?.options ?? []),
])
const visibleCount = computed(() => visibleLayout.value.length)

const widgetCustomizerLabels = computed<WidgetCustomizerLabels>(() => ({
  title: t('dashboard::dashboards.customizer.title'),
  subtitle: t('dashboard::dashboards.customizer.subtitle'),
  search: t('dashboard::dashboards.customizer.search'),
  visible: t('dashboard::dashboards.customizer.visible'),
  hidden: t('dashboard::dashboards.customizer.hidden'),
  dragHint: t('dashboard::dashboards.customizer.drag_hint'),
  empty: t('dashboard::dashboards.customizer.empty'),
  reset: t('dashboard::dashboards.actions.reset'),
  done: t('dashboard::dashboards.actions.done'),
  minimize: t('dashboard::dashboards.actions.minimize'),
  expand: t('dashboard::dashboards.actions.expand'),
}))

function customizerWidgetTitle(definition: WidgetCustomizerDefinition) {
  return t(`dashboard::dashboards.widgets.${definition.key}.title`)
}

function customizerCategoryTitle(definition: WidgetCustomizerDefinition) {
  return t(`dashboard::dashboards.categories.${definition.category}`)
}

function customizerDragLabel(title: string) {
  return t('dashboard::dashboards.customizer.drag_label', { widget: title })
}

function definitionFor(key: DashboardWidgetKey) {
  return dashboardWidgetMap.get(key)!
}

function widgetData(key: DashboardWidgetKey) {
  return response.value?.widgets[key]?.data ?? null
}

function widgetOption(key: DashboardWidgetKey) {
  const definition = definitionFor(key)
  if (!definition.filter) return undefined

  return widgetOptions[key]?.[definition.filter.key]
}

function isWidgetLoading(key: DashboardWidgetKey) {
  return dashboardLoading.value || Boolean(widgetLoading[key])
}

function formatFilterDate(value: string) {
  if (!value) return '—'
  const date = new Date(`${value}T00:00:00`)
  if (Number.isNaN(date.getTime())) return value

  return new Intl.DateTimeFormat(locale.value, {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(date)
}

async function loadFilters(): Promise<boolean> {
  if (!hasDashboardAccess.value) {
    filtersLoading.value = false
    dashboardLoading.value = false
    return false
  }

  filtersController?.abort()
  filtersController = new AbortController()
  filtersLoading.value = true
  const savedFilters = getStoredDashboardFilters(
    dashboardUserId,
    userPreferences.values?.dashboardFilters,
  )
  const remoteFilters = normalizeDashboardFilters(userPreferences.values?.dashboardFilters)

  try {
    const result = await dashboardFilters(savedFilters, filtersController.signal)
    filterMeta.value = result.data.body
    Object.assign(filters, result.data.body.defaults)
    persistDashboardFilters(dashboardUserId, filters)
    if (
      userPreferences.serverLoaded &&
      savedFilters.from &&
      (remoteFilters.from !== filters.from ||
        remoteFilters.to !== filters.to ||
        remoteFilters.branch_id !== filters.branch_id)
    ) {
      void saveDashboardPreference('dashboardFilters', { ...filters })
    }
    widgetOptions.hourly_sales_trend = { date: filters.to }
    selectedPreset.value =
      result.data.body.presets.find(
        (preset) => preset.from === filters.from && preset.to === filters.to,
      )?.id ?? 'custom'
    return true
  } catch (error) {
    if (!axios.isCancel(error)) {
      toast.error(t('dashboard::dashboards.errors.load'))
      dashboardLoading.value = false
    }
    return false
  } finally {
    filtersLoading.value = false
  }
}

async function loadDashboard(force = false) {
  if (!hasDashboardAccess.value) {
    dashboardLoading.value = false
    return
  }

  cancelWidgetLoads()
  dashboardController?.abort()
  const controller = new AbortController()
  dashboardController = controller
  const keys = visibleLayout.value.map((item) => item.key)
  if (keys.length === 0) {
    response.value = null
    dashboardLoading.value = false
    return
  }

  dashboardLoading.value = true

  try {
    const result = await dashboard(
      {
        ...filters,
        branch_id: filters.branch_id || null,
        widgets: keys,
        widget_options: widgetOptions,
        refresh: force || undefined,
      },
      controller.signal,
    )
    response.value = result.data.body
  } catch (error) {
    if (!axios.isCancel(error)) {
      toast.error(t('dashboard::dashboards.errors.load'))
    }
  } finally {
    if (dashboardController === controller) dashboardLoading.value = false
  }
}

function cancelWidgetLoad(key: DashboardWidgetKey) {
  widgetControllers.get(key)?.abort()
  widgetControllers.delete(key)
  delete widgetLoading[key]
}

function cancelWidgetLoads() {
  for (const key of widgetControllers.keys()) cancelWidgetLoad(key)
}

async function loadWidget(key: DashboardWidgetKey) {
  if (!hasDashboardAccess.value || !authorizedKeys.value.has(key)) return

  cancelWidgetLoad(key)
  const controller = new AbortController()
  widgetControllers.set(key, controller)
  widgetLoading[key] = true

  try {
    const result = await dashboard(
      {
        ...filters,
        branch_id: filters.branch_id || null,
        widgets: [key],
        widget_options: widgetOptions[key] ? { [key]: widgetOptions[key] } : {},
      },
      controller.signal,
    )
    const body = result.data.body
    response.value = response.value
      ? {
          meta: body.meta,
          widgets: {
            ...response.value.widgets,
            ...body.widgets,
          },
        }
      : body
  } catch (error) {
    if (!axios.isCancel(error)) toast.error(t('dashboard::dashboards.errors.load'))
  } finally {
    if (widgetControllers.get(key) === controller) {
      widgetControllers.delete(key)
      delete widgetLoading[key]
    }
  }
}

async function applyPreset(id: string) {
  selectedPreset.value = id
  const preset = filterMeta.value?.presets.find((item) => item.id === id)
  if (!preset) {
    dateMenuOpen.value = true
    return
  }

  filters.from = preset.from
  filters.to = preset.to
  widgetOptions.hourly_sales_trend = { date: filters.to }
  persistDashboardFilters(dashboardUserId, filters)
  await Promise.all([saveDashboardPreference('dashboardFilters', { ...filters }), loadDashboard()])
}

async function applyDateRange() {
  if (!filters.from || !filters.to) return
  selectedPreset.value = 'custom'
  widgetOptions.hourly_sales_trend = { date: filters.to }
  dateMenuOpen.value = false
  persistDashboardFilters(dashboardUserId, filters)
  await Promise.all([saveDashboardPreference('dashboardFilters', { ...filters }), loadDashboard()])
}

async function changeBranch(value: number | string | null) {
  filters.branch_id = Number(value) || null
  persistDashboardFilters(dashboardUserId, filters)
  await Promise.all([saveDashboardPreference('dashboardFilters', { ...filters }), loadDashboard()])
}

function widgetKeyFromElement(element: GridItemHTMLElement) {
  return element.getAttribute('gs-id') as DashboardWidgetKey | null
}

function persistGridPositions() {
  if (!dashboardGrid || dashboardGrid.getColumn() !== DASHBOARD_COLUMNS) return

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
    .filter((position): position is NonNullable<typeof position> => position !== null)

  if (
    positions.every((position) => {
      const item = layout.value.find((candidate) => candidate.key === position.key)
      return (
        item?.x === position.x &&
        item.y === position.y &&
        item.w === position.w &&
        item.h === position.h
      )
    })
  )
    return

  updatePositions(positions)
}

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

function gridWidgetKeys() {
  return (dashboardGrid?.getGridItems() ?? [])
    .sort(
      (left, right) =>
        (left.gridstackNode?.y ?? 0) - (right.gridstackNode?.y ?? 0) ||
        (left.gridstackNode?.x ?? 0) - (right.gridstackNode?.x ?? 0),
    )
    .map(widgetKeyFromElement)
    .filter((key): key is DashboardWidgetKey => key !== null)
}

function gridPositionsSnapshot(grid: GridStack) {
  return grid
    .getGridItems()
    .map((element) => ({
      key: widgetKeyFromElement(element),
      x: element.gridstackNode?.x,
      y: element.gridstackNode?.y,
    }))
    .sort((left, right) => (left.key ?? '').localeCompare(right.key ?? ''))
    .map(({ key, x, y }) => `${key}:${x}:${y}`)
    .join('|')
}

async function persistWidgetMove() {
  if (!dashboardGrid) return
  packGridUpwards(dashboardGrid)
  if (dashboardGrid.getColumn() === DASHBOARD_COLUMNS) {
    persistGridPositions()
    return
  }

  // Responsive coordinates cannot be saved as desktop coordinates. Save the
  // visual order using the canonical card sizes, then rebuild the responsive grid.
  await reorderVisible(gridWidgetKeys())
}

function packGridUpwards(grid: GridStack) {
  // Gravity runs once after an edit, so dragging stays free and columns remain fixed.
  grid.float(false)
  grid.float(true)
}

function announcePosition(key: DashboardWidgetKey) {
  const element = gridRoot.value?.querySelector<GridItemHTMLElement>(`[gs-id="${key}"]`)
  const node = element?.gridstackNode
  if (!node || node.x === undefined || node.y === undefined) return

  reorderMessage.value = t('dashboard::dashboards.customizer.position', {
    widget: t(`dashboard::dashboards.widgets.${key}.title`),
    column: node.x + 1,
    row: node.y + 1,
  })
  if (reorderMessageTimer) clearTimeout(reorderMessageTimer)
  reorderMessageTimer = setTimeout(() => {
    reorderMessage.value = ''
    reorderMessageTimer = null
  }, 2400)
}

function destroyDashboardGrid() {
  dashboardGrid?.offAll()
  dashboardGrid?.destroy(false)
  dashboardGrid = null
  draggingKey.value = null
  dragGridHeight.value = null
  dragStartPositions = null
}

async function initializeDashboardGrid() {
  await nextTick()
  if (!gridRoot.value) return

  destroyDashboardGrid()
  // GridStack mutates these attributes outside Vue. Restore even coordinates
  // Vue considers unchanged before rebuilding from the saved desktop layout.
  for (const item of visibleLayout.value) {
    const element = gridRoot.value.querySelector(`[gs-id="${item.key}"]`)
    for (const dimension of ['x', 'y', 'w', 'h'] as const) {
      element?.setAttribute(`gs-${dimension}`, String(item[dimension]))
    }
  }
  const grid = GridStack.init(
    {
      animate: true,
      cellHeight: 88,
      column: DASHBOARD_COLUMNS,
      columnOpts: {
        breakpoints: [
          { w: 600, c: 1, layout: 'list' },
          { w: 1200, c: 6, layout: 'moveScale' },
        ],
        layout: 'moveScale',
      },
      disableResize: true,
      disableDrag: !editingLayout.value,
      draggable: {
        handle: '.dashboard-widget__drag',
        cancel:
          '.dashboard-panel__actions, .dashboard-metric__actions, a, input, textarea, select, button:not(.dashboard-widget__drag), [contenteditable="true"]',
        scroll: true,
      },
      // Keep nearby cards stable while dragging; remove vertical gaps on drop.
      float: true,
      engineClass: DashboardGridEngine,
      handle: '.dashboard-widget__drag',
      margin: 9,
      rtl: getComputedStyle(gridRoot.value).direction === 'rtl',
    },
    gridRoot.value,
  )
  if (!grid) return

  dashboardGrid = grid
  packGridUpwards(grid)
  grid.on('dragstart', (_event, element) => {
    if (!editingLayout.value) return
    reorderMessage.value = ''
    dragStartPositions = gridPositionsSnapshot(grid)
    dragGridHeight.value = gridRoot.value?.getBoundingClientRect().height ?? null
    draggingKey.value = widgetKeyFromElement(element)
  })
  grid.on('dragstop', async (_event, element) => {
    if (!editingLayout.value) return
    const movedKey = widgetKeyFromElement(element) ?? draggingKey.value
    // GridStack finishes its change event and DOM ordering after dragstop returns.
    await nextTick()
    if (dashboardGrid !== grid) return

    const changed = dragStartPositions !== gridPositionsSnapshot(grid)
    dragStartPositions = null
    draggingKey.value = null
    dragGridHeight.value = null
    if (!changed) return
    await persistWidgetMove()
    if (movedKey) announcePosition(movedKey)
  })
  persistGridPositions()
}

async function setVisible(key: DashboardWidgetKey, visible: boolean) {
  destroyDashboardGrid()
  update(key, { visible, ...(visible ? { minimized: false } : {}) })
  prioritizeMetrics()
  await initializeDashboardGrid()
  if (visible) await loadWidget(key)
  else cancelWidgetLoad(key)
}

function setCustomizerVisibility(key: string, visible: boolean) {
  return setVisible(key as DashboardWidgetKey, visible)
}

function minimizeCustomizerWidget(key: string) {
  return toggleMinimized(key as DashboardWidgetKey)
}

function reorderCustomizerWidgets(keys: string[]) {
  return reorderVisible(keys as DashboardWidgetKey[])
}

async function toggleMinimized(key: DashboardWidgetKey) {
  const item = layout.value.find((candidate) => candidate.key === key)
  if (!item) return

  update(key, { minimized: !item.minimized })
  await nextTick()
  const element = gridRoot.value?.querySelector<GridItemHTMLElement>(`[gs-id="${key}"]`)
  const updated = layout.value.find((candidate) => candidate.key === key)
  if (element && updated && dashboardGrid) {
    dashboardGrid.update(element, { h: updated.h })
    packGridUpwards(dashboardGrid)
    persistGridPositions()
  }
}

async function reorderVisible(keys: DashboardWidgetKey[]) {
  destroyDashboardGrid()
  const itemMap = new Map(visibleLayout.value.map((item) => [item.key, item]))
  visibleLayout.value = keys
    .map((key) => itemMap.get(key))
    .filter((item): item is DashboardLayoutItem => Boolean(item))
  await initializeDashboardGrid()
}

async function moveWidget(key: DashboardWidgetKey, move: DashboardWidgetMove) {
  if (!editingLayout.value) return
  const element = gridRoot.value?.querySelector<GridItemHTMLElement>(`[gs-id="${key}"]`)
  const node = element?.gridstackNode
  if (!element || !node || !dashboardGrid) return

  const columns = dashboardGrid.getColumn()
  if (columns === 1) {
    if (move === 'left' || move === 'right') return
    const keys = gridWidgetKeys()
    const index = keys.indexOf(key)
    if (index === -1) return
    const targetIndex =
      move === 'first'
        ? 0
        : move === 'last'
          ? keys.length - 1
          : Math.max(0, Math.min(keys.length - 1, index + (move === 'up' ? -1 : 1)))
    if (index === targetIndex) return
    keys.splice(index, 1)
    keys.splice(targetIndex, 0, key)
    await reorderVisible(keys)
    announcePosition(key)
    gridRoot.value
      ?.querySelector<HTMLButtonElement>(`[gs-id="${key}"] .dashboard-widget__drag`)
      ?.focus({ preventScroll: true })
    return
  }
  const x = node.x ?? 0
  const y = node.y ?? 0
  const w = node.w ?? 1
  if (dashboardGrid.opts.rtl && (move === 'left' || move === 'right')) {
    move = move === 'left' ? 'right' : 'left'
  }
  const target =
    move === 'up'
      ? { x, y: Math.max(0, y - 1) }
      : move === 'down'
        ? { x, y: y + 1 }
        : move === 'left'
          ? { x: Math.max(0, x - 1), y }
          : move === 'right'
            ? { x: Math.min(columns - w, x + 1), y }
            : move === 'first'
              ? { x: 0, y }
              : { x: columns - w, y }

  if (target.x === x && target.y === y) return

  dashboardGrid.update(element, target)
  await persistWidgetMove()
  announcePosition(key)
  await nextTick()
  gridRoot.value
    ?.querySelector<HTMLButtonElement>(`[gs-id="${key}"] .dashboard-widget__drag`)
    ?.focus({ preventScroll: true })
}

async function resetDashboard() {
  destroyDashboardGrid()
  reset()
  await initializeDashboardGrid()
  await loadDashboard()
}

async function updateWidgetOption(key: DashboardWidgetKey, value: DashboardWidgetOptionValue) {
  const filter = definitionFor(key).filter
  if (!filter) return

  widgetOptions[key] = { [filter.key]: value }
  await loadWidget(key)
}

onMounted(async () => {
  if (!hasDashboardAccess.value) {
    filtersLoading.value = false
    dashboardLoading.value = false
    return
  }

  await initializeDashboardGrid()
  layoutWritesReady.value = true
  if (
    userPreferences.serverLoaded &&
    JSON.stringify(layout.value) !== JSON.stringify(userPreferences.values?.dashboardLayout)
  ) {
    void saveDashboardPreference('dashboardLayout', layout.value)
  }
  if (await loadFilters()) await loadDashboard()
})

onBeforeUnmount(() => {
  filtersController?.abort()
  dashboardController?.abort()
  cancelWidgetLoads()
  destroyDashboardGrid()
  if (reorderMessageTimer) clearTimeout(reorderMessageTimer)
})
</script>

<template>
  <div class="admin-dashboard">
    <header class="admin-dashboard__header">
      <div>
        <h1>{{ t('dashboard::dashboards.page.title') }}</h1>
        <p>{{ t('dashboard::dashboards.page.subtitle') }}</p>
      </div>

      <div
        v-if="hasDashboardAccess"
        class="admin-dashboard__header-actions"
      >
        <VBtn
          :aria-label="t('dashboard::dashboards.actions.refresh')"
          :loading="dashboardLoading && Boolean(response)"
          color="secondary"
          prepend-icon="tabler-refresh"
          variant="tonal"
          @click="loadDashboard(true)"
        >
          <span class="d-none d-sm-inline">
            {{ t('dashboard::dashboards.filters.refresh') }}
          </span>
        </VBtn>
        <VBtn
          v-if="visibleLayout.length"
          :aria-pressed="editingLayout"
          :color="editingLayout ? 'primary' : 'secondary'"
          :prepend-icon="editingLayout ? 'tabler-check' : 'tabler-arrows-move'"
          class="admin-dashboard__edit-toggle"
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
          color="primary"
          prepend-icon="tabler-layout-dashboard"
          @click="customizerOpen = true"
        >
          {{ t('dashboard::dashboards.actions.customize') }}
          <span
            :aria-label="
              t('dashboard::dashboards.customizer.visible_count', {
                count: visibleCount,
              })
            "
            class="admin-dashboard__customize-count"
          >
            {{ visibleCount }}
          </span>
        </VBtn>
      </div>
    </header>

    <VCard
      v-if="hasDashboardAccess"
      class="admin-dashboard__filters"
      elevation="0"
    >
      <VSelect
        :disabled="filtersLoading"
        :items="periodItems"
        :label="t('dashboard::dashboards.filters.date')"
        :model-value="selectedPreset"
        hide-details
        item-title="name"
        item-value="id"
        prepend-inner-icon="tabler-calendar-stats"
        variant="outlined"
        @update:model-value="applyPreset"
      />

      <VSelect
        v-if="branchFilter"
        :disabled="filtersLoading || filterMeta?.meta.branch_locked"
        :items="branchItems"
        :label="t('dashboard::dashboards.filters.branch_id')"
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
            class="admin-dashboard__range"
            color="primary"
            prepend-icon="tabler-calendar-event"
            v-bind="menuProps"
            variant="tonal"
          >
            <span>{{ formatFilterDate(filters.from) }}</span>
            <VIcon
              class="admin-dashboard__range-arrow"
              icon="tabler-arrow-right"
              size="15"
            />
            <span>{{ formatFilterDate(filters.to) }}</span>
          </VBtn>
        </template>
        <VCard
          class="admin-dashboard__date-menu"
          elevation="8"
        >
          <div class="admin-dashboard__date-menu-header">
            <span class="admin-dashboard__date-menu-icon">
              <VIcon
                icon="tabler-calendar-event"
                size="20"
              />
            </span>
            <div>
              <strong>{{ t('dashboard::dashboards.filters.custom') }}</strong>
              <span>
                {{ formatFilterDate(filters.from) }}
                <VIcon
                  icon="tabler-arrow-right"
                  size="13"
                />
                {{ formatFilterDate(filters.to) }}
              </span>
            </div>
            <VBtn
              :aria-label="t('dashboard::dashboards.actions.done')"
              density="comfortable"
              icon="tabler-x"
              size="small"
              variant="text"
              @click="dateMenuOpen = false"
            />
          </div>

          <VDivider />

          <VCardText class="admin-dashboard__date-menu-body">
            <div class="admin-dashboard__date-fields">
              <div>
                <DatePicker
                  v-model="filters.from"
                  :label="t('dashboard::dashboards.filters.from')"
                  :max="filters.to || undefined"
                  density="comfortable"
                  hide-details
                />
              </div>
              <div>
                <DatePicker
                  v-model="filters.to"
                  :label="t('dashboard::dashboards.filters.to')"
                  :min="filters.from || undefined"
                  density="comfortable"
                  hide-details
                />
              </div>
            </div>
            <VBtn
              block
              color="primary"
              size="large"
              @click="applyDateRange"
            >
              {{ t('dashboard::dashboards.filters.apply') }}
            </VBtn>
          </VCardText>
        </VCard>
      </VMenu>

      <VSpacer />
      <div class="admin-dashboard__scope">
        <VIcon
          icon="tabler-info-circle"
          size="17"
        />
        {{
          branchFilter && filters.branch_id
            ? branchItems.find((item) => item.id === filters.branch_id)?.name
            : t('dashboard::dashboards.filters.all_branches')
        }}
      </div>
    </VCard>

    <div
      v-if="hasDashboardAccess && visibleLayout.length"
      ref="gridRoot"
      :data-editing="editingLayout ? true : undefined"
      :data-sorting="draggingKey ? true : undefined"
      :style="{ minHeight: dragGridHeight ? `${dragGridHeight}px` : undefined }"
      class="admin-dashboard__grid grid-stack"
    >
      <section
        v-for="item in visibleLayout"
        :key="item.key"
        :data-kind="definitionFor(item.key).kind"
        :gs-h="item.h"
        :gs-id="item.key"
        :gs-no-resize="definitionFor(item.key).kind === 'metric' || undefined"
        :gs-w="item.w"
        :gs-x="item.x"
        :gs-y="item.y"
        class="admin-dashboard__widget grid-stack-item"
      >
        <div class="grid-stack-item-content">
          <DashboardWidgetRenderer
            :currency="response?.meta.display_currency ?? 'JOD'"
            :data="widgetData(item.key)"
            :definition="definitionFor(item.key)"
            :editable="editingLayout"
            :loading="isWidgetLoading(item.key)"
            :minimized="item.minimized"
            :option-value="widgetOption(item.key)"
            @hide="setVisible(item.key, false)"
            @minimize="toggleMinimized(item.key)"
            @move="moveWidget(item.key, $event)"
            @update:option="updateWidgetOption(item.key, $event)"
          />
        </div>
      </section>
    </div>

    <Transition name="dashboard-reorder-status">
      <div
        v-if="draggingKey || reorderMessage"
        aria-live="polite"
        class="admin-dashboard__reorder-status"
        role="status"
      >
        <VIcon
          :icon="draggingKey ? 'tabler-arrows-sort' : 'tabler-circle-check'"
          size="18"
        />
        {{ draggingKey ? t('dashboard::dashboards.customizer.drop_hint') : reorderMessage }}
      </div>
    </Transition>

    <DashboardNoAccessState v-if="!hasDashboardAccess" />

    <DashboardEmptyState
      v-else-if="!visibleLayout.length"
      @customize="customizerOpen = true"
    />

    <WidgetCustomizer
      v-if="hasDashboardAccess"
      v-model="customizerOpen"
      :category-title="customizerCategoryTitle"
      :definitions="authorizedDefinitions"
      :drag-label="customizerDragLabel"
      :labels="widgetCustomizerLabels"
      :layout="layout"
      :widget-title="customizerWidgetTitle"
      @minimize="minimizeCustomizerWidget"
      @reorder="reorderCustomizerWidgets"
      @reset="resetDashboard"
      @set-visible="setCustomizerVisibility"
    />
  </div>
</template>

<style lang="scss" scoped>
.admin-dashboard {
  min-block-size: calc(100vh - 120px);
  padding: 4px 2px 28px;
}

.admin-dashboard__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 22px;
  margin-block-end: 20px;
}

.admin-dashboard__header h1 {
  font-size: clamp(1.65rem, 2.8vw, 2.1rem);
  font-weight: 800;
  letter-spacing: -0.045em;
  line-height: 1.1;
}

.admin-dashboard__header p {
  margin-block-start: 5px;
  color: rgba(var(--v-theme-on-surface), 0.55);
  font-size: 0.86rem;
}

.admin-dashboard__header-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}

.admin-dashboard__customize-count {
  display: inline-grid;
  min-inline-size: 25px;
  padding: 2px 7px;
  border: 1px solid rgba(255, 255, 255, 0.24);
  border-radius: 999px;
  margin-inline-start: 9px;
  background: rgba(255, 255, 255, 0.2);
  color: #fff;
  font-size: 0.7rem;
  font-weight: 800;
  line-height: 1.25rem;
  place-items: center;
}

.admin-dashboard__filters {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-block-end: 20px;
  padding: 12px;
  border: 1px solid rgba(var(--v-border-color), 0.1);
  border-radius: 16px;
  box-shadow: 0 7px 24px rgba(0, 0, 0, 0.045) !important;
}

.admin-dashboard__filters :deep(.v-input) {
  max-inline-size: 220px;
}

.admin-dashboard__filters :deep(.v-field) {
  border-radius: 11px;
}

.admin-dashboard__range {
  block-size: 37px;
  border: 1px solid rgba(var(--v-theme-primary), 0.14);
  border-radius: 11px;
  font-size: 0.76rem;
  letter-spacing: 0;
}

.admin-dashboard__range-arrow {
  margin-inline: 7px;
}

.admin-dashboard__date-menu {
  overflow: hidden;
  inline-size: min(420px, calc(100vw - 32px));
  border: 1px solid rgba(var(--v-border-color), 0.12);
  border-radius: 16px !important;
  background: rgb(var(--v-theme-surface));
}

.admin-dashboard__date-menu-header {
  display: grid;
  grid-template-columns: 38px minmax(0, 1fr) 34px;
  align-items: center;
  gap: 11px;
  padding: 16px;
}

.admin-dashboard__date-menu-icon {
  display: grid;
  border-radius: 11px;
  background: rgba(var(--v-theme-primary), 0.1);
  block-size: 38px;
  color: rgb(var(--v-theme-primary));
  inline-size: 38px;
  place-items: center;
}

.admin-dashboard__date-menu-header > div {
  display: grid;
  min-inline-size: 0;
  gap: 2px;
}

.admin-dashboard__date-menu-header strong {
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.9rem;
}

.admin-dashboard__date-menu-header div > span {
  display: flex;
  align-items: center;
  gap: 5px;
  color: rgba(var(--v-theme-on-surface), 0.55);
  font-size: 0.7rem;
}

.admin-dashboard__date-menu-body {
  display: grid;
  gap: 18px;
  padding: 18px !important;
}

.admin-dashboard__date-fields {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.admin-dashboard__date-fields > div {
  display: grid;
  min-inline-size: 0;
}

.admin-dashboard__date-fields :deep(.v-field) {
  border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.025);
}

.admin-dashboard__scope {
  display: flex;
  align-items: center;
  gap: 6px;
  padding-inline: 8px;
  color: rgba(var(--v-theme-on-surface), 0.5);
  font-size: 0.72rem;
}

.admin-dashboard__grid {
  margin: -9px;
}

.admin-dashboard__grid[data-sorting] {
  user-select: none;
}

.admin-dashboard__grid.grid-stack-animate > .grid-stack-item:not(.ui-draggable-dragging) {
  transition-duration: 180ms;
  transition-timing-function: cubic-bezier(0.2, 0.8, 0.2, 1);
}

.admin-dashboard__grid[data-sorting] :deep(.dashboard-widget__drag) {
  cursor: grabbing;
}

.admin-dashboard__widget {
  min-inline-size: 0;
}

.admin-dashboard__widget > .grid-stack-item-content {
  display: flex;
  overflow: visible;
  border-radius: 18px;
}

.admin-dashboard__widget > .grid-stack-item-content > * {
  min-inline-size: 0;
  flex: 1 1 auto;
}

.admin-dashboard__widget[data-kind='metric'] :deep(.ui-resizable-handle) {
  display: none !important;
}

.admin-dashboard__grid :deep(.grid-stack-placeholder > .placeholder-content) {
  border: 2px dashed rgb(var(--v-theme-primary));
  border-radius: 18px;
  background: rgba(var(--v-theme-primary), 0.08);
  box-shadow: inset 0 0 0 4px rgba(var(--v-theme-primary), 0.035);
}

.admin-dashboard__widget.ui-draggable-dragging :deep(.v-card) {
  border-color: rgba(var(--v-theme-primary), 0.34) !important;
  box-shadow: 0 22px 52px rgba(0, 0, 0, 0.16) !important;
}

.admin-dashboard__reorder-status {
  position: fixed;
  z-index: 2500;
  inset-block-end: 28px;
  inset-inline-start: 50%;
  display: flex;
  max-inline-size: min(440px, calc(100vw - 32px));
  align-items: center;
  gap: 9px;
  padding: 11px 16px;
  border: 1px solid rgba(var(--v-theme-primary), 0.2);
  border-radius: 12px;
  background: rgb(var(--v-theme-surface));
  box-shadow: 0 14px 42px rgba(0, 0, 0, 0.16);
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.78rem;
  font-weight: 650;
  transform: translateX(-50%);
  pointer-events: none;
}

.admin-dashboard__reorder-status .v-icon {
  color: rgb(var(--v-theme-primary));
}

.dashboard-reorder-status-enter-active,
.dashboard-reorder-status-leave-active {
  transition:
    opacity 160ms ease,
    transform 160ms ease;
}

.dashboard-reorder-status-enter-from,
.dashboard-reorder-status-leave-to {
  opacity: 0;
  transform: translate(-50%, 8px);
}

@media (prefers-reduced-motion: reduce) {
  .admin-dashboard__grid.grid-stack-animate > .grid-stack-item,
  .admin-dashboard__grid :deep(.v-card),
  .dashboard-reorder-status-enter-active,
  .dashboard-reorder-status-leave-active {
    transition: none !important;
  }
}

@media (max-width: 840px) {
  .admin-dashboard__header {
    align-items: flex-start;
    flex-direction: column;
  }

  .admin-dashboard__header-actions {
    inline-size: 100%;
  }

  .admin-dashboard__header-actions .v-btn:last-child {
    margin-inline-start: auto;
  }

  .admin-dashboard__filters {
    flex-wrap: wrap;
  }

  .admin-dashboard__scope {
    display: none;
  }
}

@media (max-width: 600px) {
  .admin-dashboard__header-actions .v-btn:last-child {
    flex: 1;
  }

  .admin-dashboard__filters :deep(.v-input) {
    max-inline-size: none;
    flex: 1 1 145px;
  }

  .admin-dashboard__range {
    inline-size: 100%;
  }

  .admin-dashboard__date-fields {
    grid-template-columns: 1fr;
  }
}
</style>
