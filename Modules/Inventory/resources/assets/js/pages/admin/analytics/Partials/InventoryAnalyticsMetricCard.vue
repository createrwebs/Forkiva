<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type {
  InventoryAnalyticsMetricData,
  InventoryAnalyticsMoney,
  InventoryAnalyticsWidgetMove,
} from '@/modules/inventory/contracts/inventoryAnalytics.ts'
import DashboardDragHandle from '@/modules/dashboard/pages/admin/dashboard/Partials/DashboardDragHandle.vue'

const props = defineProps<{
  editable: boolean
  title: string
  description: string
  icon: string
  color: string
  data: InventoryAnalyticsMetricData | null
  minimized: boolean
  loading: boolean
  inverseTrend?: boolean
}>()

const emit = defineEmits<{
  hide: []
  minimize: []
  move: [direction: InventoryAnalyticsWidgetMove]
}>()

const { t, locale } = useI18n()

function isMoney(
  value: InventoryAnalyticsMoney | number | undefined,
): value is InventoryAnalyticsMoney {
  return typeof value === 'object' && value !== null && 'formatted' in value
}

const displayValue = computed(() => {
  const value = props.data?.value
  const formatted = isMoney(value)
    ? value.formatted
    : new Intl.NumberFormat(locale.value).format(value ?? 0)
  return `${formatted}${props.data?.suffix ?? ''}`
})
const comparisonImproves = computed(() => {
  if (!props.data?.comparison || props.data.comparison.direction === 0) return null
  return props.inverseTrend
    ? props.data.comparison.direction < 0
    : props.data.comparison.direction > 0
})
const comparisonTone = computed(() =>
  comparisonImproves.value === null
    ? 'inventory-metric__comparison--neutral'
    : comparisonImproves.value
      ? 'inventory-metric__comparison--positive'
      : 'inventory-metric__comparison--negative',
)
const comparisonIcon = computed(() =>
  (props.data?.comparison?.direction ?? 0) > 0 ? 'tabler-trending-up' : 'tabler-trending-down',
)
</script>

<template>
  <VCard
    class="inventory-metric h-100"
    :class="{ 'inventory-metric--minimized': minimized }"
    elevation="0"
  >
    <div
      class="inventory-metric__topline"
      :style="{ backgroundColor: color }"
    />
    <div class="inventory-metric__header dashboard-widget__drag-surface">
      <DashboardDragHandle
        v-if="editable"
        :title="title"
        @move="emit('move', $event)"
      />
      <span
        class="inventory-metric__icon"
        :style="{ color, backgroundColor: `${color}14` }"
      >
        <VIcon
          :icon="icon"
          size="22"
        />
      </span>
      <div class="inventory-metric__title text-truncate">{{ title }}</div>
      <VSpacer />
      <div
        class="inventory-metric__actions"
        v-if="editable"
      >
        <VBtn
          :aria-label="
            minimized
              ? t('dashboard::dashboards.actions.expand')
              : t('dashboard::dashboards.actions.minimize')
          "
          density="compact"
          :icon="minimized ? 'tabler-chevron-down' : 'tabler-minus'"
          size="small"
          variant="text"
          @click="emit('minimize')"
        />
        <VBtn
          :aria-label="t('dashboard::dashboards.actions.hide')"
          density="compact"
          icon="tabler-eye-off"
          size="small"
          variant="text"
          @click="emit('hide')"
        />
      </div>
    </div>

    <VExpandTransition>
      <div
        v-show="!minimized"
        class="inventory-metric__body"
      >
        <VSkeletonLoader
          v-if="loading"
          type="heading, text"
        />
        <template v-else>
          <div class="inventory-metric__value text-truncate">{{ displayValue }}</div>
          <div
            v-if="data?.comparison && data.comparison.percent !== null"
            class="inventory-metric__comparison"
            :class="comparisonTone"
          >
            <span class="inventory-metric__delta">
              <VIcon
                :icon="comparisonIcon"
                size="15"
              />
              <strong>{{ Math.abs(data.comparison.percent) }}%</strong>
            </span>
            <span>{{ t('inventory::inventories.analytics.previous_period') }}</span>
          </div>
          <div
            v-else
            class="inventory-metric__comparison inventory-metric__comparison--neutral"
          >
            {{ description }}
          </div>
        </template>
      </div>
    </VExpandTransition>
  </VCard>
</template>

<style lang="scss" scoped>
.inventory-metric {
  position: relative;
  isolation: isolate;
  overflow: hidden;
  min-block-size: 166px;
  padding: 15px 16px 14px;
  border: 1px solid rgba(var(--v-border-color), 0.12);
  border-radius: 18px;
  background: rgb(var(--v-theme-surface));
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.045) !important;
  transition:
    border-color 180ms ease,
    box-shadow 180ms ease;
}
.inventory-metric:hover {
  border-color: rgba(var(--v-border-color), 0.2);
  box-shadow: 0 13px 38px rgba(0, 0, 0, 0.075) !important;
}
.inventory-metric--minimized {
  min-block-size: 0;
}
.inventory-metric__topline {
  position: absolute;
  inset-block-start: 0;
  inset-inline: 0;
  block-size: 4px;
}
.inventory-metric__header {
  display: flex;
  min-inline-size: 0;
  align-items: center;
  gap: 9px;
}
.inventory-metric__icon {
  display: grid;
  flex: 0 0 42px;
  border-radius: 12px;
  block-size: 42px;
  inline-size: 42px;
  place-items: center;
}
.inventory-metric__title {
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.78rem;
  font-weight: 720;
}
.inventory-metric__actions {
  display: flex;
  margin-inline-end: -7px;
}
.inventory-metric__actions :deep(.v-btn) {
  color: rgba(var(--v-theme-on-surface), 0.44);
}
.inventory-metric__body {
  margin-block-start: 13px;
}
.inventory-metric__value {
  color: rgb(var(--v-theme-on-surface));
  font-size: clamp(1.45rem, 2vw, 1.78rem);
  font-weight: 800;
  letter-spacing: -0.045em;
  line-height: 1.08;
  font-variant-numeric: tabular-nums;
}
.inventory-metric__comparison {
  display: flex;
  min-block-size: 27px;
  align-items: center;
  gap: 7px;
  margin-block-start: 9px;
  overflow: hidden;
  color: rgba(var(--v-theme-on-surface), 0.52);
  font-size: 0.67rem;
  white-space: nowrap;
}
.inventory-metric__delta {
  display: inline-flex;
  min-block-size: 26px;
  align-items: center;
  gap: 3px;
  padding-inline: 8px;
  border-radius: 8px;
}
.inventory-metric__comparison--positive .inventory-metric__delta {
  background: rgba(var(--v-theme-success), 0.11);
  color: rgb(var(--v-theme-success));
}
.inventory-metric__comparison--negative .inventory-metric__delta {
  background: rgba(var(--v-theme-error), 0.1);
  color: rgb(var(--v-theme-error));
}
</style>
