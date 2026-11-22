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
import { useAuth } from '@/modules/auth/composables/auth.ts'
import WidgetCustomizer from '@/modules/core/components/Dashboard/WidgetCustomizer.vue'
import DatePicker from '@/modules/core/components/Form/DatePicker.vue'
import type {
  WidgetCustomizerDefinition,
  WidgetCustomizerLabels,
} from '@/modules/core/contracts/WidgetCustomizer.ts'
import DashboardEmptyState from '@/modules/dashboard/pages/admin/dashboard/Partials/DashboardEmptyState.vue'
import { useGiftCardAnalytics } from '@/modules/giftcard/composables/giftCardAnalytics.ts'
import {
  GIFT_CARD_ANALYTICS_COLUMNS,
  useGiftCardAnalyticsLayout,
} from '@/modules/giftcard/composables/giftCardAnalyticsLayout.ts'
import {
  giftCardAnalyticsWidgetMap,
  giftCardAnalyticsWidgets,
} from '@/modules/giftcard/config/giftCardAnalytics.widgets.ts'
import type {
  GiftCardAnalyticsFilters,
  GiftCardAnalyticsFiltersResponse,
  GiftCardAnalyticsLayoutItem,
  GiftCardAnalyticsResponse,
  GiftCardAnalyticsWidgetKey,
  GiftCardAnalyticsWidgetMove,
} from '@/modules/giftcard/contracts/giftCardAnalytics.ts'
import GiftCardAnalyticsWidgetRenderer from './Partials/GiftCardAnalyticsWidgetRenderer.vue'

const { t, locale } = useI18n()
const toast = useToast()
const { user } = useAuth()
const viewPreference = useDashboardViewPreference('giftCardAnalytics')
const { getGiftCardAnalytics, getGiftCardAnalyticsFilters } = useGiftCardAnalytics()

const filters = reactive<GiftCardAnalyticsFilters>({ branch_id: null, from: '', to: '' })
const filterMeta = ref<GiftCardAnalyticsFiltersResponse | null>(null)
const filtersLoading = ref(true)
const dashboardLoading = ref(true)
const selectedPreset = ref('this_year')
const dateMenuOpen = ref(false)
const customizerOpen = ref(false)
const editingLayout = ref(false)
const response = ref<GiftCardAnalyticsResponse | null>(null)
const widgetLoading = reactive<Partial<Record<GiftCardAnalyticsWidgetKey, boolean>>>({})
const draggingKey = ref<GiftCardAnalyticsWidgetKey | null>(null)
const reorderMessage = ref('')
const gridRoot = ref<HTMLElement | null>(null)
const dragGridHeight = ref<number | null>(null)
const { layout, visibleLayout, update, updatePositions, reset, repack } =
  useGiftCardAnalyticsLayout(
    Number(user?.id ?? 0),
    giftCardAnalyticsWidgets,
    viewPreference.remoteLayout,
  )

let filtersController: AbortController | null = null
let dashboardController: AbortController | null = null
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
const widgetControllers = new Map<GiftCardAnalyticsWidgetKey, AbortController>()

const periodItems = computed(() => [
  ...(filterMeta.value?.presets ?? []),
  {
    id: 'custom',
    name: t('giftcard::gift_cards.analytics.filters.custom'),
    from: filters.from,
    to: filters.to,
  },
])
const branchFilter = computed(() =>
  filterMeta.value?.filters.find((filter) => filter.key === 'branch_id'),
)
const branchItems = computed(() => [
  { id: 0, name: t('giftcard::gift_cards.analytics.filters.all_branches') },
  ...(branchFilter.value?.options ?? []),
])
const visibleCount = computed(() => visibleLayout.value.length)

const customizerLabels = computed<WidgetCustomizerLabels>(() => ({
  title: t('giftcard::gift_cards.analytics.customizer.title'),
  subtitle: t('giftcard::gift_cards.analytics.customizer.subtitle'),
  search: t('giftcard::gift_cards.analytics.customizer.search'),
  visible: t('giftcard::gift_cards.analytics.customizer.visible'),
  hidden: t('giftcard::gift_cards.analytics.customizer.hidden'),
  dragHint: t('giftcard::gift_cards.analytics.customizer.drag_hint'),
  empty: t('giftcard::gift_cards.analytics.customizer.empty'),
  reset: t('giftcard::gift_cards.analytics.actions.reset'),
  done: t('giftcard::gift_cards.analytics.actions.done'),
  minimize: t('giftcard::gift_cards.analytics.actions.minimize'),
  expand: t('giftcard::gift_cards.analytics.actions.expand'),
}))

function definitionFor(key: GiftCardAnalyticsWidgetKey) {
  return giftCardAnalyticsWidgetMap.get(key)!
}

function widgetData(key: GiftCardAnalyticsWidgetKey) {
  return response.value?.widgets[key]?.data ?? null
}

function isWidgetLoading(key: GiftCardAnalyticsWidgetKey) {
  return dashboardLoading.value || Boolean(widgetLoading[key])
}

function customizerWidgetTitle(definition: WidgetCustomizerDefinition) {
  return t(`giftcard::gift_cards.analytics.widgets.${definition.key}.title`)
}

function customizerCategoryTitle(definition: WidgetCustomizerDefinition) {
  return t(`giftcard::gift_cards.analytics.categories.${definition.category}`)
}

function customizerDragLabel(title: string) {
  return t('giftcard::gift_cards.analytics.customizer.drag_label', { widget: title })
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
  filtersController?.abort()
  filtersController = new AbortController()
  filtersLoading.value = true

  try {
    const result = await getGiftCardAnalyticsFilters({}, filtersController.signal)
    filterMeta.value = result.data.body
    Object.assign(filters, viewPreference.restoreFilters(result.data.body.defaults))
    selectedPreset.value =
      result.data.body.presets.find(
        (preset) => preset.from === filters.from && preset.to === filters.to,
      )?.id ?? 'custom'
    return true
  } catch (error) {
    if (!axios.isCancel(error)) {
      toast.error(t('giftcard::gift_cards.analytics.errors.load_filters'))
      dashboardLoading.value = false
    }
    return false
  } finally {
    filtersLoading.value = false
  }
}

function cancelWidgetLoad(key: GiftCardAnalyticsWidgetKey) {
  widgetControllers.get(key)?.abort()
  widgetControllers.delete(key)
  delete widgetLoading[key]
}

function cancelWidgetLoads() {
  for (const key of widgetControllers.keys()) cancelWidgetLoad(key)
}

async function loadDashboard(force = false) {
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
    const result = await getGiftCardAnalytics(
      {
        ...filters,
        branch_id: filters.branch_id || null,
        widgets: keys,
        refresh: force || undefined,
      },
      controller.signal,
    )
    response.value = result.data.body
  } catch (error) {
    if (!axios.isCancel(error)) toast.error(t('giftcard::gift_cards.analytics.errors.load'))
  } finally {
    if (dashboardController === controller) dashboardLoading.value = false
  }
}

async function loadWidget(key: GiftCardAnalyticsWidgetKey) {
  cancelWidgetLoad(key)
  const controller = new AbortController()
  widgetControllers.set(key, controller)
  widgetLoading[key] = true

  try {
    const result = await getGiftCardAnalytics(
      { ...filters, branch_id: filters.branch_id || null, widgets: [key] },
      controller.signal,
    )
    const body = result.data.body
    response.value = response.value
      ? {
          meta: body.meta,
          widgets: { ...response.value.widgets, ...body.widgets },
        }
      : body
  } catch (error) {
    if (!axios.isCancel(error)) toast.error(t('giftcard::gift_cards.analytics.errors.load'))
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
  return element.getAttribute('gs-id') as GiftCardAnalyticsWidgetKey | null
}

function persistGridPositions() {
  if (!dashboardGrid || dashboardGrid.getColumn() !== GIFT_CARD_ANALYTICS_COLUMNS) return
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
    .filter((key): key is GiftCardAnalyticsWidgetKey => key !== null)
}

function announcePosition(key: GiftCardAnalyticsWidgetKey) {
  const element = gridRoot.value?.querySelector<GridItemHTMLElement>(`[gs-id="${key}"]`)
  const node = element?.gridstackNode
  if (!node || node.x === undefined || node.y === undefined) return

  reorderMessage.value = t('giftcard::gift_cards.analytics.customizer.position', {
    widget: t(`giftcard::gift_cards.analytics.widgets.${key}.title`),
    column: node.x + 1,
    row: node.y + 1,
  })
  if (reorderMessageTimer) clearTimeout(reorderMessageTimer)
  reorderMessageTimer = setTimeout(() => {
    reorderMessage.value = ''
    reorderMessageTimer = null
  }, 2400)
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
    for (const dimension of ['x', 'y', 'w', 'h'] as const) {
      element?.setAttribute(`gs-${dimension}`, String(item[dimension]))
    }
  }

  const grid = GridStack.init(
    {
      animate: true,
      cellHeight: 88,
      column: GIFT_CARD_ANALYTICS_COLUMNS,
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
          '.dashboard-panel__actions, .dashboard-metric__actions, input, select, button:not(.dashboard-widget__drag)',
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
    if (dashboardGrid !== grid) return

    draggingKey.value = null
    dragGridHeight.value = null
    if (grid.getColumn() === GIFT_CARD_ANALYTICS_COLUMNS) persistGridPositions()
    else await reorderVisible(gridWidgetKeys())
    if (key) announcePosition(key)
  })
  persistGridPositions()
}

async function setVisible(key: GiftCardAnalyticsWidgetKey, visible: boolean) {
  destroyGrid()
  update(key, { visible, ...(visible ? { minimized: false } : {}) })
  repack()
  await initializeGrid()
  if (visible) await loadWidget(key)
  else cancelWidgetLoad(key)
}

async function toggleMinimized(key: GiftCardAnalyticsWidgetKey) {
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

async function reorderVisible(keys: GiftCardAnalyticsWidgetKey[]) {
  destroyGrid()
  const itemMap = new Map(visibleLayout.value.map((item) => [item.key, item]))
  visibleLayout.value = keys
    .map((key) => itemMap.get(key))
    .filter((item): item is GiftCardAnalyticsLayoutItem => Boolean(item))
  await initializeGrid()
}

async function moveWidget(key: GiftCardAnalyticsWidgetKey, move: GiftCardAnalyticsWidgetMove) {
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
    return
  }

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
  announcePosition(key)
}

async function resetDashboard() {
  destroyGrid()
  reset()
  await initializeGrid()
  await loadDashboard()
}

function setCustomizerVisibility(key: string, visible: boolean) {
  return setVisible(key as GiftCardAnalyticsWidgetKey, visible)
}

function minimizeCustomizerWidget(key: string) {
  return toggleMinimized(key as GiftCardAnalyticsWidgetKey)
}

function reorderCustomizerWidgets(keys: string[]) {
  return reorderVisible(keys as GiftCardAnalyticsWidgetKey[])
}

onMounted(async () => {
  await initializeGrid()
  if (await loadFilters()) {
    await loadDashboard()
    viewPreference.track(layout, () => ({ ...filters }))
  }
})

onBeforeUnmount(() => {
  filtersController?.abort()
  dashboardController?.abort()
  cancelWidgetLoads()
  destroyGrid()
  if (reorderMessageTimer) clearTimeout(reorderMessageTimer)
})
</script>

<template>
  <div class="gift-card-dashboard">
    <header class="gift-card-dashboard__header">
      <div>
        <h1>{{ t('giftcard::gift_cards.analytics.page.title') }}</h1>
        <p>{{ t('giftcard::gift_cards.analytics.page.subtitle') }}</p>
      </div>

      <div class="gift-card-dashboard__header-actions">
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
          :aria-label="t('giftcard::gift_cards.analytics.actions.refresh')"
          :loading="dashboardLoading && Boolean(response)"
          color="secondary"
          prepend-icon="tabler-refresh"
          variant="tonal"
          @click="loadDashboard(true)"
        >
          <span class="d-none d-sm-inline">
            {{ t('giftcard::gift_cards.analytics.filters.refresh') }}
          </span>
        </VBtn>
        <VBtn
          color="primary"
          prepend-icon="tabler-layout-dashboard"
          @click="customizerOpen = true"
        >
          {{ t('giftcard::gift_cards.analytics.actions.customize') }}
          <span
            :aria-label="
              t('giftcard::gift_cards.analytics.customizer.visible_count', {
                count: visibleCount,
              })
            "
            class="gift-card-dashboard__customize-count"
          >
            {{ visibleCount }}
          </span>
        </VBtn>
      </div>
    </header>

    <VCard
      class="gift-card-dashboard__filters"
      elevation="0"
    >
      <VSelect
        :disabled="filtersLoading"
        :items="periodItems"
        :label="t('giftcard::gift_cards.analytics.filters.date')"
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
        :label="t('giftcard::gift_cards.analytics.filters.branch_id')"
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
            class="gift-card-dashboard__range"
            color="primary"
            prepend-icon="tabler-calendar-event"
            v-bind="menuProps"
            variant="tonal"
          >
            <span>{{ formatFilterDate(filters.from) }}</span>
            <VIcon
              class="gift-card-dashboard__range-arrow"
              icon="tabler-arrow-right"
              size="15"
            />
            <span>{{ formatFilterDate(filters.to) }}</span>
          </VBtn>
        </template>
        <VCard
          class="gift-card-dashboard__date-menu"
          elevation="8"
        >
          <div class="gift-card-dashboard__date-menu-header">
            <span class="gift-card-dashboard__date-menu-icon">
              <VIcon
                icon="tabler-calendar-event"
                size="20"
              />
            </span>
            <div>
              <strong>{{ t('giftcard::gift_cards.analytics.filters.custom') }}</strong>
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
              :aria-label="t('giftcard::gift_cards.analytics.actions.done')"
              density="comfortable"
              icon="tabler-x"
              size="small"
              variant="text"
              @click="dateMenuOpen = false"
            />
          </div>

          <VDivider />
          <VCardText class="gift-card-dashboard__date-menu-body">
            <div class="gift-card-dashboard__date-fields">
              <DatePicker
                v-model="filters.from"
                :label="t('giftcard::gift_cards.analytics.filters.from')"
                :max="filters.to || undefined"
                density="comfortable"
                hide-details
              />
              <DatePicker
                v-model="filters.to"
                :label="t('giftcard::gift_cards.analytics.filters.to')"
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
              {{ t('giftcard::gift_cards.analytics.filters.apply') }}
            </VBtn>
          </VCardText>
        </VCard>
      </VMenu>

      <VSpacer />
      <div class="gift-card-dashboard__scope">
        <VIcon
          icon="tabler-info-circle"
          size="17"
        />
        {{
          branchFilter && filters.branch_id
            ? branchItems.find((item) => Number(item.id) === filters.branch_id)?.name
            : t('giftcard::gift_cards.analytics.filters.all_branches')
        }}
      </div>
    </VCard>

    <div
      v-if="visibleLayout.length"
      ref="gridRoot"
      :data-sorting="draggingKey ? true : undefined"
      :style="{ minHeight: dragGridHeight ? `${dragGridHeight}px` : undefined }"
      class="gift-card-dashboard__grid grid-stack"
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
        class="gift-card-dashboard__widget grid-stack-item"
      >
        <div class="grid-stack-item-content">
          <GiftCardAnalyticsWidgetRenderer
            :editable="editingLayout"
            :data="widgetData(item.key)"
            :definition="definitionFor(item.key)"
            :loading="isWidgetLoading(item.key)"
            :minimized="item.minimized"
            @hide="setVisible(item.key, false)"
            @minimize="toggleMinimized(item.key)"
            @move="moveWidget(item.key, $event)"
          />
        </div>
      </section>
    </div>

    <Transition name="gift-card-reorder-status">
      <div
        v-if="draggingKey || reorderMessage"
        aria-live="polite"
        class="gift-card-dashboard__reorder-status"
        role="status"
      >
        <VIcon
          :icon="draggingKey ? 'tabler-arrows-sort' : 'tabler-circle-check'"
          size="18"
        />
        {{
          draggingKey ? t('giftcard::gift_cards.analytics.customizer.drop_hint') : reorderMessage
        }}
      </div>
    </Transition>

    <DashboardEmptyState
      v-if="!visibleLayout.length"
      @customize="customizerOpen = true"
    />

    <WidgetCustomizer
      v-model="customizerOpen"
      :category-title="customizerCategoryTitle"
      :definitions="giftCardAnalyticsWidgets"
      :drag-label="customizerDragLabel"
      :labels="customizerLabels"
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
.gift-card-dashboard {
  min-block-size: calc(100vh - 120px);
  padding: 4px 2px 28px;
}

.gift-card-dashboard__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 22px;
  margin-block-end: 20px;
}

.gift-card-dashboard__header h1 {
  font-size: clamp(1.65rem, 2.8vw, 2.1rem);
  font-weight: 800;
  letter-spacing: -0.045em;
  line-height: 1.1;
}

.gift-card-dashboard__header p {
  margin-block-start: 5px;
  color: rgba(var(--v-theme-on-surface), 0.55);
  font-size: 0.86rem;
}

.gift-card-dashboard__header-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}

.gift-card-dashboard__customize-count {
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

.gift-card-dashboard__filters {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-block-end: 20px;
  padding: 12px;
  border: 1px solid rgba(var(--v-border-color), 0.1);
  border-radius: 16px;
  box-shadow: 0 7px 24px rgba(0, 0, 0, 0.045) !important;
}

.gift-card-dashboard__filters :deep(.v-input) {
  max-inline-size: 220px;
}

.gift-card-dashboard__filters :deep(.v-field) {
  border-radius: 11px;
}

.gift-card-dashboard__range {
  block-size: 37px;
  border: 1px solid rgba(var(--v-theme-primary), 0.14);
  border-radius: 11px;
  font-size: 0.76rem;
  letter-spacing: 0;
}

.gift-card-dashboard__range-arrow {
  margin-inline: 7px;
}

.gift-card-dashboard__date-menu {
  overflow: hidden;
  inline-size: min(420px, calc(100vw - 32px));
  border: 1px solid rgba(var(--v-border-color), 0.12);
  border-radius: 16px !important;
  background: rgb(var(--v-theme-surface));
}

.gift-card-dashboard__date-menu-header {
  display: grid;
  grid-template-columns: 38px minmax(0, 1fr) 34px;
  align-items: center;
  gap: 11px;
  padding: 16px;
}

.gift-card-dashboard__date-menu-icon {
  display: grid;
  border-radius: 11px;
  background: rgba(var(--v-theme-primary), 0.1);
  block-size: 38px;
  color: rgb(var(--v-theme-primary));
  inline-size: 38px;
  place-items: center;
}

.gift-card-dashboard__date-menu-header > div {
  display: grid;
  min-inline-size: 0;
  gap: 2px;
}

.gift-card-dashboard__date-menu-header strong {
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.9rem;
}

.gift-card-dashboard__date-menu-header div > span {
  display: flex;
  align-items: center;
  gap: 5px;
  color: rgba(var(--v-theme-on-surface), 0.55);
  font-size: 0.7rem;
}

.gift-card-dashboard__date-menu-body {
  display: grid;
  gap: 18px;
  padding: 18px !important;
}

.gift-card-dashboard__date-fields {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.gift-card-dashboard__date-fields :deep(.v-field) {
  border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.025);
}

.gift-card-dashboard__scope {
  display: flex;
  align-items: center;
  gap: 6px;
  padding-inline: 8px;
  color: rgba(var(--v-theme-on-surface), 0.5);
  font-size: 0.72rem;
}

.gift-card-dashboard__grid {
  margin: -9px;
}

.gift-card-dashboard__grid[data-sorting] {
  user-select: none;
}

.gift-card-dashboard__grid.grid-stack-animate > .grid-stack-item:not(.ui-draggable-dragging) {
  transition-duration: 180ms;
  transition-timing-function: cubic-bezier(0.2, 0.8, 0.2, 1);
}

.gift-card-dashboard__grid :deep(.dashboard-panel__actions),
.gift-card-dashboard__grid :deep(.dashboard-metric__actions) {
  cursor: auto;
}

.gift-card-dashboard__grid[data-sorting] :deep(.dashboard-widget__drag) {
  cursor: grabbing;
}

.gift-card-dashboard__widget {
  min-inline-size: 0;
}

.gift-card-dashboard__widget > .grid-stack-item-content {
  display: flex;
  overflow: visible;
  border-radius: 18px;
}

.gift-card-dashboard__widget > .grid-stack-item-content > * {
  min-inline-size: 0;
  flex: 1 1 auto;
}

.gift-card-dashboard__widget[data-kind='metric'] :deep(.ui-resizable-handle) {
  display: none !important;
}

.gift-card-dashboard__grid :deep(.grid-stack-placeholder > .placeholder-content) {
  border: 2px dashed rgb(var(--v-theme-primary));
  border-radius: 18px;
  background: rgba(var(--v-theme-primary), 0.08);
}

.gift-card-dashboard__reorder-status {
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

.gift-card-reorder-status-enter-active,
.gift-card-reorder-status-leave-active {
  transition:
    opacity 160ms ease,
    transform 160ms ease;
}

.gift-card-reorder-status-enter-from,
.gift-card-reorder-status-leave-to {
  opacity: 0;
  transform: translate(-50%, 8px);
}

@media (prefers-reduced-motion: reduce) {
  .gift-card-dashboard__grid.grid-stack-animate > .grid-stack-item,
  .gift-card-reorder-status-enter-active,
  .gift-card-reorder-status-leave-active {
    transition: none !important;
  }
}

@media (max-width: 840px) {
  .gift-card-dashboard__header {
    align-items: flex-start;
    flex-direction: column;
  }

  .gift-card-dashboard__header-actions {
    inline-size: 100%;
  }

  .gift-card-dashboard__scope {
    display: none;
  }

  .gift-card-dashboard__header-actions .v-btn:last-child {
    margin-inline-start: auto;
  }

  .gift-card-dashboard__filters {
    flex-wrap: wrap;
  }
}

@media (max-width: 600px) {
  .gift-card-dashboard__header-actions .v-btn:last-child {
    flex: 1;
  }

  .gift-card-dashboard__filters :deep(.v-input) {
    max-inline-size: none;
    flex: 1 1 145px;
  }

  .gift-card-dashboard__range {
    inline-size: 100%;
  }

  .gift-card-dashboard__date-fields {
    grid-template-columns: 1fr;
  }
}
</style>
