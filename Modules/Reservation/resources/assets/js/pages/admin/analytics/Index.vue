<script lang="ts" setup>
import 'gridstack/dist/gridstack.min.css'
import axios from 'axios'
import { type GridItemHTMLElement, GridStack } from 'gridstack'
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { type RouteLocationRaw, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { useDashboardViewPreference } from '@/modules/userPreference/composables/dashboardViewPreference.ts'
import {
  findDashboardGridCollision,
  packDashboardGridUpwards,
} from '@/modules/dashboard/composables/dashboardGridEngine.ts'
import WidgetCustomizer from '@/modules/core/components/Dashboard/WidgetCustomizer.vue'
import DatePicker from '@/modules/core/components/Form/DatePicker.vue'
import type {
  WidgetCustomizerDefinition,
  WidgetCustomizerLabels,
} from '@/modules/core/contracts/WidgetCustomizer.ts'
import { useAuthStore } from '@/modules/auth/stores/authStore.ts'
import { useReservation } from '@/modules/reservation/composables/reservation.ts'
import { analyticsWidgetMap, analyticsWidgets } from './config.ts'
import { AnalyticsGridEngine } from './gridEngine.ts'
import { ANALYTICS_COLUMNS, useAnalyticsLayout } from './layout.ts'
import type {
  AnalyticsFilters,
  AnalyticsFiltersResponse,
  AnalyticsLayoutItem,
  AnalyticsWidgetKey,
  AnalyticsWidgetMove,
  UpcomingReservation,
} from './types.ts'
import { emptyAnalyticsPayload, normalizeAnalytics } from './types.ts'
import AnalyticsWidgetRenderer from './Partials/AnalyticsWidgetRenderer.vue'

const router = useRouter()
const { t, locale } = useI18n()
const toast = useToast()
const auth = useAuthStore()
const viewPreference = useDashboardViewPreference('reservationAnalytics')
const { getReservationAnalytics, getReservationAnalyticsFilters } = useReservation()
const enabledKeys = computed(() => new Set(analyticsWidgets.map((definition) => definition.key)))
const { layout, visibleLayout, update, updatePositions, reset, prioritizeMetrics } =
  useAnalyticsLayout(
    auth.getUser?.id ?? 0,
    analyticsWidgets,
    enabledKeys,
    viewPreference.remoteLayout,
  )

const filters = reactive<AnalyticsFilters>({ branch_id: null, from: '', to: '' })
const filterMeta = ref<AnalyticsFiltersResponse | null>(null)
const analytics = ref(emptyAnalyticsPayload())
const filtersLoading = ref(true)
const loading = ref(true)
const hasLoaded = ref(false)
const selectedPreset = ref('this_month')
const dateMenuOpen = ref(false)
const customizerOpen = ref(false)
const editingLayout = ref(false)
const draggingKey = ref<AnalyticsWidgetKey | null>(null)
const reorderMessage = ref('')
const gridRoot = ref<HTMLElement | null>(null)
const dragGridHeight = ref<number | null>(null)
let filtersController: AbortController | null = null
let analyticsController: AbortController | null = null
let reorderMessageTimer: ReturnType<typeof setTimeout> | null = null
let analyticsGrid: GridStack | null = null
async function toggleLayoutEditing() {
  if (editingLayout.value) {
    analyticsGrid?.enableMove(false)
    editingLayout.value = false
    return
  }
  editingLayout.value = true
  await nextTick()
  analyticsGrid?.enableMove(true)
}
let gridResizeFrame: number | null = null

const canShowReservation = computed(() => auth.can('admin.reservations.show'))
const periodItems = computed(() => [
  ...(filterMeta.value?.presets ?? []),
  {
    id: 'custom',
    name: t('reservation::frontend.analytics.custom_range'),
    from: filters.from,
    to: filters.to,
  },
])
const branchFilter = computed(() =>
  filterMeta.value?.filters.find((item) => item.key === 'branch_id'),
)
const branchItems = computed(() => [
  { id: 0, name: t('reservation::frontend.analytics.all_branches') },
  ...(branchFilter.value?.options ?? []),
])
const widgetCustomizerLabels = computed<WidgetCustomizerLabels>(() => ({
  title: t('reservation::frontend.analytics.customizer.title'),
  subtitle: t('reservation::frontend.analytics.customizer.subtitle'),
  search: t('reservation::frontend.analytics.customizer.search'),
  visible: t('reservation::frontend.analytics.customizer.visible'),
  hidden: t('reservation::frontend.analytics.customizer.hidden'),
  dragHint: t('reservation::frontend.analytics.customizer.drag_hint'),
  empty: t('reservation::frontend.analytics.customizer.empty'),
  reset: t('reservation::frontend.analytics.reset'),
  done: t('reservation::frontend.analytics.done'),
  minimize: t('reservation::frontend.analytics.minimize'),
  expand: t('reservation::frontend.analytics.expand'),
}))

function customizerWidgetTitle(definition: WidgetCustomizerDefinition) {
  const key = definition.key as AnalyticsWidgetKey
  return definition.kind === 'metric'
    ? t(`reservation::frontend.analytics.summary.${key}`)
    : t(`reservation::frontend.analytics.widgets.${key}.title`)
}

function customizerCategoryTitle(definition: WidgetCustomizerDefinition) {
  return t(`reservation::frontend.analytics.categories.${definition.category}`)
}

function customizerDragLabel(title: string) {
  return t('reservation::frontend.analytics.customizer.drag_label', { widget: title })
}

function definitionFor(key: AnalyticsWidgetKey) {
  return analyticsWidgetMap.get(key)!
}

function formatFilterDate(value: string) {
  if (!value) return '—'
  const date = new Date(`${value}T00:00:00`)
  return Number.isNaN(date.getTime())
    ? value
    : new Intl.DateTimeFormat(locale.value, {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
      }).format(date)
}

async function loadFilters() {
  filtersController?.abort()
  const controller = new AbortController()
  filtersController = controller
  filtersLoading.value = true
  try {
    const response = await getReservationAnalyticsFilters({}, controller.signal)
    filterMeta.value = response.data.body
    Object.assign(filters, viewPreference.restoreFilters(response.data.body.defaults))
    selectedPreset.value =
      response.data.body.presets.find(
        (preset: { from: string; to: string }) =>
          preset.from === filters.from && preset.to === filters.to,
      )?.id ?? 'custom'
    return true
  } catch (error) {
    if (!axios.isCancel(error)) toast.error(t('core::errors.an_unexpected_error_occurred'))
    return false
  } finally {
    if (filtersController === controller) filtersLoading.value = false
  }
}

async function loadAnalytics() {
  analyticsController?.abort()
  const controller = new AbortController()
  analyticsController = controller
  loading.value = true
  const keys = visibleLayout.value.map((item) => item.key)
  try {
    if (keys.length === 0) {
      analytics.value = emptyAnalyticsPayload()
      return
    }
    const response = await getReservationAnalytics({ ...filters, widgets: keys }, controller.signal)
    analytics.value = normalizeAnalytics(response.data.body)
    hasLoaded.value = true
  } catch (error) {
    if (!axios.isCancel(error)) toast.error(t('core::errors.an_unexpected_error_occurred'))
  } finally {
    if (analyticsController === controller) loading.value = false
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
  await loadAnalytics()
}

async function applyDateRange() {
  if (!filters.from || !filters.to) return
  selectedPreset.value = 'custom'
  dateMenuOpen.value = false
  await loadAnalytics()
}

async function changeBranch(value: number | string | null) {
  filters.branch_id = Number(value) || null
  await loadAnalytics()
}

async function goToReservation(reservation: UpcomingReservation) {
  if (!canShowReservation.value) return
  await router.push({
    name: 'admin.reservations.show',
    params: { id: reservation.id },
  } as unknown as RouteLocationRaw)
}

function widgetKeyFromElement(element: GridItemHTMLElement) {
  return element.getAttribute('gs-id') as AnalyticsWidgetKey | null
}

function persistGridPositions() {
  if (!analyticsGrid || analyticsGrid.getColumn() !== ANALYTICS_COLUMNS) return
  packDashboardGridUpwards(analyticsGrid)
  const positions = analyticsGrid
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
  return (analyticsGrid?.getGridItems() ?? [])
    .sort(
      (left, right) =>
        (left.gridstackNode?.y ?? 0) - (right.gridstackNode?.y ?? 0) ||
        (left.gridstackNode?.x ?? 0) - (right.gridstackNode?.x ?? 0),
    )
    .map(widgetKeyFromElement)
    .filter((key): key is AnalyticsWidgetKey => key !== null)
}

async function persistWidgetMove() {
  if (!analyticsGrid) return
  if (analyticsGrid.getColumn() === ANALYTICS_COLUMNS) persistGridPositions()
  else await reorderVisible(gridWidgetKeys())
}

function announcePosition(key: AnalyticsWidgetKey) {
  const element = gridRoot.value?.querySelector<GridItemHTMLElement>(`[gs-id="${key}"]`)
  const node = element?.gridstackNode
  if (!node || node.x === undefined || node.y === undefined) return
  const definition = definitionFor(key)
  const title =
    definition.kind === 'metric'
      ? t(`reservation::frontend.analytics.summary.${key}`)
      : t(`reservation::frontend.analytics.widgets.${key}.title`)
  reorderMessage.value = t('reservation::frontend.analytics.customizer.position', {
    widget: title,
    column: node.x + 1,
    row: node.y + 1,
  })
  if (reorderMessageTimer) clearTimeout(reorderMessageTimer)
  reorderMessageTimer = setTimeout(() => {
    reorderMessage.value = ''
    reorderMessageTimer = null
  }, 2400)
}

function destroyGrid() {
  if (gridResizeFrame !== null) {
    cancelAnimationFrame(gridResizeFrame)
    gridResizeFrame = null
  }
  analyticsGrid?.offAll()
  analyticsGrid?.destroy(false)
  analyticsGrid = null
  draggingKey.value = null
  dragGridHeight.value = null
}

async function initializeGrid() {
  await nextTick()
  if (!gridRoot.value) return
  destroyGrid()
  for (const item of visibleLayout.value) {
    const element = gridRoot.value.querySelector(`[gs-id="${item.key}"]`)
    for (const dimension of ['x', 'y', 'w', 'h'] as const)
      element?.setAttribute(`gs-${dimension}`, String(item[dimension]))
  }
  const grid = GridStack.init(
    {
      animate: true,
      cellHeight: 88,
      column: ANALYTICS_COLUMNS,
      columnOpts: {
        breakpointForWindow: true,
        breakpoints: [
          { w: 600, c: 1, layout: 'list' },
          { w: 1200, c: 6, layout: 'compact' },
        ],
        layout: 'moveScale',
      },
      disableDrag: !editingLayout.value,
      disableResize: true,
      engineClass: AnalyticsGridEngine,
      float: true,
      draggable: {
        handle: '.analytics-drag',
        cancel: '.analytics-panel__actions, .analytics-metric__actions, a, input, textarea, select',
        scroll: true,
      },
      handle: '.analytics-drag',
      margin: 9,
      rtl: getComputedStyle(gridRoot.value).direction === 'rtl',
    },
    gridRoot.value,
  )
  if (!grid) return
  analyticsGrid = grid
  grid.on('dragstart', (_event, element) => {
    reorderMessage.value = ''
    dragGridHeight.value = gridRoot.value?.getBoundingClientRect().height ?? null
    draggingKey.value = widgetKeyFromElement(element)
  })
  grid.on('dragstop', async (_event, element) => {
    const movedKey = widgetKeyFromElement(element) ?? draggingKey.value
    await nextTick()
    if (analyticsGrid !== grid) return
    draggingKey.value = null
    dragGridHeight.value = null
    await persistWidgetMove()
    if (movedKey) announcePosition(movedKey)
  })
  // The admin shell can still be completing its sidebar/layout transition when
  // this route mounts. Re-evaluate once it has painted, but do not persist those
  // startup coordinates; only explicit user layout changes should be saved.
  gridResizeFrame = requestAnimationFrame(() => {
    gridResizeFrame = null
    if (analyticsGrid !== grid) return
    grid.onResize()
    if (grid.getColumn() < ANALYTICS_COLUMNS) grid.compact('compact')
  })
}

async function setVisible(key: AnalyticsWidgetKey, visible: boolean) {
  destroyGrid()
  update(key, { visible, ...(visible ? { minimized: false } : {}) })
  prioritizeMetrics()
  await initializeGrid()
  if (visible) await loadAnalytics()
}

function setCustomizerVisibility(key: string, visible: boolean) {
  return setVisible(key as AnalyticsWidgetKey, visible)
}

function minimizeCustomizerWidget(key: string) {
  return toggleMinimized(key as AnalyticsWidgetKey)
}

function reorderCustomizerWidgets(keys: string[]) {
  return reorderVisible(keys as AnalyticsWidgetKey[])
}

async function toggleMinimized(key: AnalyticsWidgetKey) {
  const item = layout.value.find((candidate) => candidate.key === key)
  if (!item) return
  update(key, { minimized: !item.minimized })
  await nextTick()
  const element = gridRoot.value?.querySelector<GridItemHTMLElement>(`[gs-id="${key}"]`)
  const updated = layout.value.find((candidate) => candidate.key === key)
  if (element && updated && analyticsGrid) {
    analyticsGrid.update(element, { h: updated.h })
    persistGridPositions()
  }
}

async function reorderVisible(keys: AnalyticsWidgetKey[]) {
  destroyGrid()
  const itemMap = new Map(visibleLayout.value.map((item) => [item.key, item]))
  visibleLayout.value = keys
    .map((key) => itemMap.get(key))
    .filter((item): item is AnalyticsLayoutItem => Boolean(item))
  await initializeGrid()
}

async function moveWidget(key: AnalyticsWidgetKey, move: AnalyticsWidgetMove) {
  if (!editingLayout.value) return
  const element = gridRoot.value?.querySelector<GridItemHTMLElement>(`[gs-id="${key}"]`)
  const node = element?.gridstackNode
  if (!element || !node || !analyticsGrid) return
  const columns = analyticsGrid.getColumn()
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
    return
  }
  const x = node.x ?? 0
  const y = node.y ?? 0
  const w = node.w ?? 1
  const h = node.h ?? 1
  if (analyticsGrid.opts.rtl && (move === 'left' || move === 'right'))
    move = move === 'left' ? 'right' : 'left'
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
  const occupant = findDashboardGridCollision(analyticsGrid, element, target, { w, h })
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
  analyticsGrid.update(element, target)
  await persistWidgetMove()
  announcePosition(key)
}

async function resetAnalytics() {
  destroyGrid()
  reset()
  await initializeGrid()
}

onMounted(async () => {
  await initializeGrid()
  if (await loadFilters()) {
    await loadAnalytics()
    viewPreference.track(layout, () => ({ ...filters }))
  }
})
onBeforeUnmount(() => {
  filtersController?.abort()
  analyticsController?.abort()
  destroyGrid()
  if (reorderMessageTimer) clearTimeout(reorderMessageTimer)
})
</script>

<template>
  <div class="reservation-analytics">
    <header class="reservation-analytics__header">
      <div>
        <h1>{{ t('reservation::frontend.analytics.title') }}</h1>
        <p>{{ t('reservation::frontend.analytics.subtitle') }}</p>
      </div>
      <div class="reservation-analytics__header-actions">
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
          :aria-label="t('reservation::frontend.analytics.refresh')"
          :loading="loading && hasLoaded"
          color="secondary"
          prepend-icon="tabler-refresh"
          variant="tonal"
          @click="loadAnalytics"
        >
          <span class="d-none d-sm-inline">{{ t('reservation::frontend.analytics.refresh') }}</span>
        </VBtn>
        <VBtn
          color="primary"
          prepend-icon="tabler-layout-dashboard"
          @click="customizerOpen = true"
        >
          {{ t('reservation::frontend.analytics.customize') }}
          <span class="reservation-analytics__count">{{ visibleLayout.length }}</span>
        </VBtn>
      </div>
    </header>

    <VCard
      class="reservation-analytics__filters"
      elevation="0"
    >
      <VSelect
        :disabled="filtersLoading"
        :items="periodItems"
        :label="t('reservation::frontend.analytics.date')"
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
        :label="t('reservation::frontend.analytics.branch')"
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
            class="reservation-analytics__range"
            color="primary"
            prepend-icon="tabler-calendar-event"
            v-bind="menuProps"
            variant="tonal"
          >
            <span>{{ formatFilterDate(filters.from) }}</span>
            <VIcon
              class="mx-2"
              icon="tabler-arrow-right"
              size="15"
            />
            <span>{{ formatFilterDate(filters.to) }}</span>
          </VBtn>
        </template>
        <VCard
          class="reservation-analytics__date-menu"
          elevation="8"
        >
          <div class="reservation-analytics__date-menu-header">
            <span>
              <VIcon
                icon="tabler-calendar-event"
                size="20"
              />
            </span>
            <div>
              <strong>{{ t('reservation::frontend.analytics.custom_range') }}</strong>
              <small>
                {{ formatFilterDate(filters.from) }} – {{ formatFilterDate(filters.to) }}
              </small>
            </div>
            <VBtn
              density="comfortable"
              icon="tabler-x"
              size="small"
              variant="text"
              @click="dateMenuOpen = false"
            />
          </div>
          <VDivider />
          <VCardText class="reservation-analytics__date-menu-body">
            <div class="reservation-analytics__date-fields">
              <DatePicker
                v-model="filters.from"
                :label="t('reservation::frontend.analytics.from')"
                :max="filters.to || undefined"
                density="comfortable"
                hide-details
              />
              <DatePicker
                v-model="filters.to"
                :label="t('reservation::frontend.analytics.to')"
                :min="filters.from || undefined"
                density="comfortable"
                hide-details
              />
            </div>
            <VBtn
              block
              color="primary"
              size="large"
              @click="applyDateRange"
            >
              {{ t('reservation::frontend.analytics.apply') }}
            </VBtn>
          </VCardText>
        </VCard>
      </VMenu>
      <VSpacer />
      <div class="reservation-analytics__scope">
        <VIcon
          icon="tabler-info-circle"
          size="17"
        />
        {{
          branchFilter
            ? filters.branch_id
              ? branchItems.find((item) => item.id === filters.branch_id)?.name
              : t('reservation::frontend.analytics.all_branches')
            : t('reservation::frontend.analytics.assigned_branch')
        }}
      </div>
    </VCard>

    <div
      v-if="visibleLayout.length"
      ref="gridRoot"
      :data-sorting="draggingKey ? true : undefined"
      :style="{ minHeight: dragGridHeight ? `${dragGridHeight}px` : undefined }"
      class="reservation-analytics__grid grid-stack"
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
        class="reservation-analytics__widget grid-stack-item"
      >
        <div class="grid-stack-item-content">
          <AnalyticsWidgetRenderer
            :editable="editingLayout"
            :can-show-reservation="canShowReservation"
            :definition="definitionFor(item.key)"
            :loading="loading || !hasLoaded"
            :minimized="item.minimized"
            :payload="analytics"
            @hide="setVisible(item.key, false)"
            @minimize="toggleMinimized(item.key)"
            @move="moveWidget(item.key, $event)"
            @show="goToReservation"
          />
        </div>
      </section>
    </div>

    <VCard
      v-else
      class="reservation-analytics__empty"
      elevation="0"
    >
      <VIcon
        icon="tabler-layout-dashboard"
        size="36"
      />
      <strong>{{ t('reservation::frontend.analytics.no_data_title') }}</strong>
      <span>{{ t('reservation::frontend.analytics.no_data_subtitle') }}</span>
      <VBtn
        color="primary"
        @click="customizerOpen = true"
      >
        {{ t('reservation::frontend.analytics.customize') }}
      </VBtn>
    </VCard>
    <Transition name="analytics-status">
      <div
        v-if="draggingKey || reorderMessage"
        aria-live="polite"
        class="reservation-analytics__status"
        role="status"
      >
        <VIcon
          :icon="draggingKey ? 'tabler-arrows-sort' : 'tabler-circle-check'"
          size="18"
        />
        {{
          draggingKey ? t('reservation::frontend.analytics.customizer.drop_hint') : reorderMessage
        }}
      </div>
    </Transition>
    <WidgetCustomizer
      v-model="customizerOpen"
      :category-title="customizerCategoryTitle"
      :definitions="analyticsWidgets"
      :drag-label="customizerDragLabel"
      :labels="widgetCustomizerLabels"
      :layout="layout"
      :widget-title="customizerWidgetTitle"
      @minimize="minimizeCustomizerWidget"
      @reorder="reorderCustomizerWidgets"
      @reset="resetAnalytics"
      @set-visible="setCustomizerVisibility"
    />
  </div>
</template>

<style lang="scss" scoped>
.reservation-analytics {
  min-block-size: calc(100vh - 120px);
  padding: 4px 2px 28px;
}

.reservation-analytics__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 22px;
  margin-block-end: 20px;
}

.reservation-analytics__header h1 {
  font-size: clamp(1.65rem, 2.8vw, 2.1rem);
  font-weight: 800;
  letter-spacing: -0.045em;
  line-height: 1.1;
}

.reservation-analytics__header p {
  margin-block-start: 5px;
  color: rgba(var(--v-theme-on-surface), 0.55);
  font-size: 0.86rem;
}

.reservation-analytics__header-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}

.reservation-analytics__count {
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

.reservation-analytics__filters {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-block-end: 20px;
  padding: 12px;
  border: 1px solid rgba(var(--v-border-color), 0.1);
  border-radius: 16px;
  box-shadow: 0 7px 24px rgba(0, 0, 0, 0.045) !important;
}

.reservation-analytics__filters :deep(.v-input) {
  max-inline-size: 220px;
}

.reservation-analytics__filters :deep(.v-field) {
  border-radius: 11px;
}

.reservation-analytics__range {
  block-size: 37px;
  border: 1px solid rgba(var(--v-theme-primary), 0.14);
  border-radius: 11px;
  font-size: 0.76rem;
  letter-spacing: 0;
}

.reservation-analytics__scope {
  display: flex;
  align-items: center;
  gap: 6px;
  padding-inline: 8px;
  color: rgba(var(--v-theme-on-surface), 0.5);
  font-size: 0.72rem;
}

.reservation-analytics__date-menu {
  overflow: hidden;
  inline-size: min(420px, calc(100vw - 32px));
  border: 1px solid rgba(var(--v-border-color), 0.12);
  border-radius: 16px !important;
  background: rgb(var(--v-theme-surface));
}

.reservation-analytics__date-menu-header {
  display: grid;
  grid-template-columns: 38px minmax(0, 1fr) 34px;
  align-items: center;
  gap: 11px;
  padding: 16px;
}

.reservation-analytics__date-menu-header > span {
  display: grid;
  border-radius: 11px;
  background: rgba(var(--v-theme-primary), 0.1);
  block-size: 38px;
  color: rgb(var(--v-theme-primary));
  inline-size: 38px;
  place-items: center;
}

.reservation-analytics__date-menu-header div {
  display: grid;
  gap: 2px;
}

.reservation-analytics__date-menu-header strong {
  font-size: 0.9rem;
}

.reservation-analytics__date-menu-header small {
  color: rgba(var(--v-theme-on-surface), 0.55);
  font-size: 0.7rem;
}

.reservation-analytics__date-menu-body {
  display: grid;
  gap: 18px;
  padding: 18px !important;
}

.reservation-analytics__date-fields {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.reservation-analytics__grid {
  margin: -9px;
}

.reservation-analytics__grid[data-sorting] {
  user-select: none;
}

.reservation-analytics__grid.grid-stack-animate > .grid-stack-item:not(.ui-draggable-dragging) {
  transition-duration: 180ms;
  transition-timing-function: cubic-bezier(0.2, 0.8, 0.2, 1);
}

.reservation-analytics__grid[data-sorting] :deep(.analytics-drag) {
  cursor: grabbing;
}

.reservation-analytics__widget {
  min-inline-size: 0;
}

.reservation-analytics__widget > .grid-stack-item-content {
  display: flex;
  overflow: visible;
  border-radius: 18px;
}

.reservation-analytics__widget > .grid-stack-item-content > * {
  min-inline-size: 0;
  flex: 1 1 auto;
}

.reservation-analytics__grid :deep(.grid-stack-placeholder > .placeholder-content) {
  border: 2px dashed rgb(var(--v-theme-primary));
  border-radius: 18px;
  background: rgba(var(--v-theme-primary), 0.08);
}

.reservation-analytics__widget.ui-draggable-dragging :deep(.v-card) {
  border-color: rgba(var(--v-theme-primary), 0.34) !important;
  box-shadow: 0 22px 52px rgba(0, 0, 0, 0.16) !important;
}

.reservation-analytics__empty {
  display: grid;
  min-block-size: 360px;
  align-content: center;
  gap: 9px;
  padding: 28px;
  border: 1px dashed rgba(var(--v-border-color), 0.16);
  border-radius: 18px;
  color: rgba(var(--v-theme-on-surface), 0.55);
  justify-items: center;
  text-align: center;
}

.reservation-analytics__empty strong {
  color: rgb(var(--v-theme-on-surface));
  font-size: 1rem;
}

.reservation-analytics__empty .v-btn {
  margin-block-start: 8px;
}

.reservation-analytics__status {
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
  font-size: 0.78rem;
  font-weight: 650;
  transform: translateX(-50%);
  pointer-events: none;
}

.analytics-status-enter-active,
.analytics-status-leave-active {
  transition:
    opacity 160ms ease,
    transform 160ms ease;
}

.analytics-status-enter-from,
.analytics-status-leave-to {
  opacity: 0;
  transform: translate(-50%, 8px);
}

@media (prefers-reduced-motion: reduce) {
  .reservation-analytics__grid.grid-stack-animate > .grid-stack-item,
  .reservation-analytics__grid :deep(.v-card) {
    transition: none !important;
  }
}

@media (max-width: 840px) {
  .reservation-analytics__header {
    align-items: flex-start;
    flex-direction: column;
  }
  .reservation-analytics__header-actions {
    inline-size: 100%;
  }
  .reservation-analytics__scope {
    display: none;
  }
  .reservation-analytics__header-actions .v-btn:last-child {
    margin-inline-start: auto;
  }
  .reservation-analytics__filters {
    flex-wrap: wrap;
  }
}

@media (max-width: 600px) {
  .reservation-analytics__header-actions .v-btn:last-child {
    flex: 1;
  }
  .reservation-analytics__filters :deep(.v-input) {
    max-inline-size: none;
    flex: 1 1 145px;
  }
  .reservation-analytics__range {
    inline-size: 100%;
  }
  .reservation-analytics__date-fields {
    grid-template-columns: 1fr;
  }
}
</style>
