<script lang="ts" setup>
import { computed, provide, ref, watch } from 'vue'
import { useNow, useOnline } from '@vueuse/core'
import { useI18n } from 'vue-i18n'
import { useKitchenBoard } from '@/modules/pos/composables/useKitchenBoard'
import {
  kitchenClockKey,
  type KitchenSort,
  type KitchenView,
} from '@/modules/pos/contracts/kitchen'
import {
  isDelayed,
  isUnfinished,
  kitchenState,
  kitchenViews,
  sortKitchenOrders,
} from '@/modules/pos/utils/kitchenBoard'
import KitchenHeader from './Partials/KitchenHeader.vue'
import Orders from './Partials/Orders/Index.vue'
import './kitchen.scss'

const { t } = useI18n()
const {
  orders,
  keepFinishedOrders,
  orderEffects,
  filters,
  meta,
  hasActiveFilters,
  hasMore,
  lastSyncedAt,
  syncError,
  soundMuted,
  loading,
  loadingConfiguration,
  refreshing,
  reloadBranchData,
  processingCount,
  refresh,
  applyOrderUpdateResponse,
  onActionFailed,
  setProcessing,
  isPaused,
} = useKitchenBoard()
const dateNow = useNow({ interval: 1000 })
const now = computed(() => dateNow.value.getTime())
provide(kitchenClockKey, now)
const online = useOnline()
const view = ref<KitchenView>('active')
const sort = ref<KitchenSort>('priority')
const delayMinutes = ref(15)
const views = computed(() => kitchenViews(keepFinishedOrders.value))
watch(views, (availableViews) => {
  if (!availableViews.includes(view.value)) view.value = 'active'
})
const busy = computed(
  () =>
    loadingConfiguration.value ||
    loading.value ||
    reloadBranchData.value ||
    processingCount.value > 0,
)

const counts = computed(() => {
  const result = { active: 0, new: 0, preparing: 0, ready: 0, delayed: 0, completed: 0 }
  for (const order of orders.value) {
    const state = kitchenState(order)
    result[state] += 1
    if (state !== 'completed') result.active += 1
    if (isDelayed(order, now.value, delayMinutes.value)) result.delayed += 1
  }
  return result
})
const workload = computed(() =>
  orders.value
    .flatMap((order) => order.products)
    .filter(isUnfinished)
    .reduce((sum, item) => sum + Number(item.quantity), 0),
)
const visibleOrders = computed(() =>
  sortKitchenOrders(
    orders.value.filter((order) => {
      if (view.value === 'active') return kitchenState(order) !== 'completed'
      if (view.value === 'delayed') return isDelayed(order, now.value, delayMinutes.value)
      return kitchenState(order) === view.value
    }),
    sort.value,
    delayMinutes.value,
  ),
)
const connection = computed(() => {
  if (!online.value) return 'offline'
  if (syncError.value) return 'stale'
  if (!lastSyncedAt.value) return 'connecting'
  if (!meta.value.settings?.auto_refresh?.enabled) return 'manual'
  if (isPaused.value) return 'paused'
  const staleAfter = Math.max(30_000, Number(meta.value.settings.auto_refresh.interval || 5000) * 3)
  return now.value - lastSyncedAt.value > staleAfter ? 'stale' : 'live'
})
const syncedTime = computed(() =>
  lastSyncedAt.value ? Math.max(0, Math.floor((now.value - lastSyncedAt.value) / 1000)) : null,
)
const hasFilters = computed(() => hasActiveFilters.value || view.value !== 'active')
function clearFilters() {
  filters.value.searchQuery = null
  filters.value.type = null
  view.value = 'active'
}
const sortOptions = computed(() =>
  ['priority', 'oldest', 'newest'].map((value) => ({ value, title: t(`pos::kds.sorts.${value}`) })),
)
const delayOptions = computed(() =>
  [10, 15, 20, 30].map((value) => ({ value, title: t('pos::kds.minutes', { count: value }) })),
)
const soundLabel = computed(() =>
  t(
    !meta.value.settings?.sounds?.enabled
      ? 'pos::kds.sound_disabled'
      : soundMuted.value
        ? 'pos::kds.unmute'
        : 'pos::kds.mute',
  ),
)
</script>

<template>
  <section
    class="kds-workstation"
    :aria-label="t('pos::kds.title')"
  >
    <div class="kds-controls">
      <KitchenHeader
        :filters="filters"
        :meta="meta"
        :disabled="busy"
        :refreshing="refreshing"
        :reload-branch-data="reloadBranchData"
        @refresh="refresh"
      />
      <div class="kds-toolbar">
        <nav
          class="kds-tabs"
          :aria-label="t('pos::kds.status_filter')"
        >
          <button
            v-for="state in views"
            :key="state"
            type="button"
            class="kds-tab"
            :class="[{ 'is-active': view === state }, `kds-state--${state}`]"
            :aria-pressed="view === state"
            :disabled="processingCount > 0"
            @click="view = state"
          >
            <span
              v-if="state !== 'active'"
              class="kds-dot"
            />
            {{ t(`pos::kds.states.${state}`) }}
            <span class="kds-tab__count">{{ counts[state] }}</span>
          </button>
        </nav>
        <div class="kds-toolbar__actions">
          <VSelect
            v-model="sort"
            class="kds-sort"
            :items="sortOptions"
            :aria-label="t('pos::kds.sort')"
            :title="t(`pos::kds.sort_hints.${sort}`)"
            density="compact"
            hide-details
            prepend-inner-icon="tabler-arrows-sort"
            variant="solo-filled"
          />
          <VSelect
            v-model="delayMinutes"
            class="kds-delay"
            :items="delayOptions"
            :aria-label="t('pos::kds.wait_limit')"
            :title="`${t('pos::kds.wait_limit')} · ${t('pos::kds.wait_hint')}`"
            density="compact"
            hide-details
            prepend-inner-icon="tabler-alarm"
            variant="solo-filled"
          />
          <VBtn
            :icon="
              soundMuted || !meta.settings?.sounds?.enabled ? 'tabler-volume-off' : 'tabler-volume'
            "
            :disabled="!meta.settings?.sounds?.enabled"
            :aria-label="soundLabel"
            :title="soundLabel"
            :aria-pressed="!soundMuted && Boolean(meta.settings?.sounds?.enabled)"
            color="default"
            variant="text"
            size="40"
            @click="soundMuted = !soundMuted"
          />
        </div>
      </div>
    </div>

    <div
      v-if="connection === 'offline' || connection === 'stale'"
      class="kds-notice kds-notice--error"
      role="alert"
    >
      <VIcon
        icon="tabler-wifi-off"
        size="20"
      />
      <span>
        {{ t(connection === 'offline' ? 'pos::kds.offline_notice' : 'pos::kds.stale_notice') }}
      </span>
      <button
        type="button"
        :disabled="busy || refreshing || !online"
        @click="refresh"
      >
        {{ t('pos::kds.retry') }}
      </button>
    </div>
    <div
      v-if="hasMore"
      class="kds-notice"
      role="status"
    >
      <VIcon
        icon="tabler-info-circle"
        size="20"
      />
      {{ t('pos::kds.limited', { count: orders.length }) }}
    </div>

    <div
      class="kds-board"
      :aria-busy="busy || refreshing"
    >
      <h2 class="kds-sr-only">{{ t(`pos::kds.headings.${view}`) }}</h2>
      <div
        v-if="loadingConfiguration || loading || reloadBranchData"
        class="kds-loading"
        role="status"
      >
        <VProgressCircular
          indeterminate
          size="32"
          color="#475569"
        />
        {{ t('pos::kds.loading') }}
      </div>
      <Orders
        v-else
        :orders="visibleOrders"
        :order-effects="orderEffects"
        :has-active-filters="hasFilters"
        :now="now"
        :delay-minutes="delayMinutes"
        :disabled="!online || connection === 'stale' || busy || refreshing"
        @order-updated="applyOrderUpdateResponse"
        @processing="setProcessing"
        @action-failed="onActionFailed"
        @clear-filters="clearFilters"
      />
    </div>
    <footer class="kds-footer">
      <span>
        {{
          syncedTime === null
            ? t('pos::kds.not_synced')
            : t('pos::kds.last_synced', { seconds: syncedTime })
        }}
        <span class="kds-footer__mode">· {{ t(`pos::kds.connection.${connection}`) }}</span>
      </span>
      <span class="kds-workload">
        <VIcon
          icon="tabler-tools-kitchen-2"
          size="16"
        />
        {{ t('pos::kds.workload', { count: workload }) }}
      </span>
    </footer>
  </section>
</template>
