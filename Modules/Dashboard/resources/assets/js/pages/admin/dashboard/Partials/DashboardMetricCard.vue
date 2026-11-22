<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type {
  DashboardMetricData,
  DashboardMoney,
  DashboardWidgetMove,
} from '@/modules/dashboard/contracts/dashboard.ts'
import DashboardDragHandle from './DashboardDragHandle.vue'

const props = withDefaults(
  defineProps<{
    title: string
    description: string
    icon: string
    color: string
    data: DashboardMetricData | null
    minimized: boolean
    editable?: boolean
    loading: boolean
    inverseTrend?: boolean
    unavailableMessage?: string
  }>(),
  { editable: true },
)

const emit = defineEmits<{
  hide: []
  minimize: []
  move: [direction: DashboardWidgetMove]
}>()

const { t } = useI18n()

function isMoney(value: DashboardMoney | number | undefined): value is DashboardMoney {
  return typeof value === 'object' && value !== null && 'formatted' in value
}

const displayValue = computed(() => {
  const value = props.data?.value
  return isMoney(value) ? value.formatted : new Intl.NumberFormat().format(value ?? 0)
})

const comparisonImproves = computed(() => {
  if (!props.data?.comparison || props.data.comparison.direction === 0) return null

  return props.inverseTrend
    ? props.data.comparison.direction < 0
    : props.data.comparison.direction > 0
})

const comparisonTone = computed(() => {
  if (comparisonImproves.value === null) return 'dashboard-metric__comparison--neutral'

  return comparisonImproves.value
    ? 'dashboard-metric__comparison--positive'
    : 'dashboard-metric__comparison--negative'
})

const comparisonIcon = computed(() => {
  if (!props.data?.comparison || props.data.comparison.direction === 0) return 'tabler-minus'

  return props.data.comparison.direction > 0 ? 'tabler-trending-up' : 'tabler-trending-down'
})
</script>

<template>
  <VCard
    class="dashboard-metric h-100"
    :class="{ 'dashboard-metric--minimized': minimized }"
    elevation="0"
  >
    <div
      class="dashboard-metric__topline"
      :style="{ backgroundColor: color }"
    />

    <div class="dashboard-metric__header">
      <DashboardDragHandle
        v-if="editable"
        class="dashboard-metric__drag"
        :title="title"
        @move="emit('move', $event)"
      />
      <span
        class="dashboard-metric__icon"
        :style="{ color, backgroundColor: `${color}14` }"
      >
        <VIcon
          :icon="icon"
          size="22"
        />
      </span>

      <div class="dashboard-metric__identity">
        <div class="dashboard-metric__title text-truncate">{{ title }}</div>
      </div>

      <VSpacer />
      <div class="dashboard-metric__actions">
        <VBtn
          v-if="editable"
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
          v-if="editable"
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
        class="dashboard-metric__body"
      >
        <VSkeletonLoader
          v-if="loading"
          class="dashboard-metric__loading"
          type="heading, text"
        />
        <template v-else-if="unavailableMessage">
          <div class="dashboard-metric__value dashboard-metric__value--unavailable">—</div>
          <div class="dashboard-metric__comparison dashboard-metric__comparison--unavailable">
            <VIcon
              icon="tabler-settings-exclamation"
              size="15"
            />
            <span class="dashboard-metric__period">{{ unavailableMessage }}</span>
          </div>
        </template>
        <template v-else>
          <div class="dashboard-metric__value text-truncate">{{ displayValue }}</div>
          <div
            v-if="data?.live"
            class="dashboard-metric__comparison dashboard-metric__comparison--live"
          >
            <span class="dashboard-metric__delta">
              <span class="dashboard-live-dot" />
              {{ t('dashboard::dashboards.comparison.live') }}
            </span>
          </div>
          <div
            v-else-if="data?.comparison?.percent !== null && data?.comparison"
            class="dashboard-metric__comparison"
            :class="comparisonTone"
          >
            <span class="dashboard-metric__delta">
              <VIcon
                :icon="comparisonIcon"
                size="15"
              />
              <strong>{{ Math.abs(data.comparison.percent ?? 0) }}%</strong>
            </span>
            <span class="dashboard-metric__period">
              {{ t('dashboard::dashboards.comparison.previous_period') }}
            </span>
          </div>
          <div
            v-else
            class="dashboard-metric__comparison dashboard-metric__comparison--neutral"
          >
            <span class="dashboard-metric__period text-truncate">{{ description }}</span>
          </div>
        </template>
      </div>
    </VExpandTransition>
  </VCard>
</template>

<style lang="scss" scoped>
.dashboard-metric {
  position: relative;
  isolation: isolate;
  overflow: hidden;
  min-block-size: 166px;
  padding: 15px 16px 14px;
  border: 1px solid rgba(var(--v-border-color), 0.12);
  border-radius: 18px;
  background:
    radial-gradient(circle at 92% 2%, rgba(var(--v-theme-on-surface), 0.025), transparent 31%),
    rgb(var(--v-theme-surface));
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.045) !important;
  transition:
    border-color 180ms ease,
    box-shadow 180ms ease,
    transform 180ms ease;
}

.dashboard-metric:hover {
  border-color: rgba(var(--v-border-color), 0.2);
  box-shadow: 0 13px 38px rgba(0, 0, 0, 0.075) !important;
}

.dashboard-metric--minimized {
  min-block-size: 0;
}

.dashboard-metric__topline {
  position: absolute;
  inset-block-start: 0;
  inset-inline: 0;
  block-size: 4px;
}

.dashboard-metric__header {
  display: flex;
  min-inline-size: 0;
  align-items: center;
  gap: 9px;
}

.dashboard-metric__icon {
  display: grid;
  flex: 0 0 42px;
  border-radius: 12px;
  block-size: 42px;
  inline-size: 42px;
  place-items: center;
}

.dashboard-metric__identity {
  min-inline-size: 0;
}

.dashboard-metric__title {
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.78rem;
  font-weight: 720;
  letter-spacing: -0.012em;
}

.dashboard-metric__actions {
  display: flex;
  flex: 0 0 auto;
  gap: 0;
  margin-inline-end: -7px;
}

.dashboard-metric__actions :deep(.v-btn) {
  color: rgba(var(--v-theme-on-surface), 0.44);
}

.dashboard-metric__body {
  margin-block-start: 13px;
}

.dashboard-metric__loading {
  margin-block-start: 4px;
}

.dashboard-metric__value {
  color: rgb(var(--v-theme-on-surface));
  font-size: clamp(1.45rem, 2vw, 1.78rem);
  font-weight: 800;
  letter-spacing: -0.045em;
  line-height: 1.08;
  font-variant-numeric: tabular-nums;
}

.dashboard-metric__comparison {
  display: flex;
  min-block-size: 27px;
  align-items: center;
  gap: 7px;
  margin-block-start: 9px;
  color: rgba(var(--v-theme-on-surface), 0.52);
  font-size: 0.67rem;
}

.dashboard-metric__delta {
  display: inline-flex;
  min-block-size: 26px;
  align-items: center;
  gap: 3px;
  padding-inline: 8px;
  border-radius: 8px;
  background: rgba(var(--v-theme-on-surface), 0.055);
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.dashboard-metric__comparison--positive .dashboard-metric__delta {
  background: rgba(var(--v-theme-success), 0.11);
  color: rgb(var(--v-theme-success));
}

.dashboard-metric__comparison--negative .dashboard-metric__delta,
.dashboard-metric__comparison--live .dashboard-metric__delta {
  background: rgba(var(--v-theme-error), 0.1);
  color: rgb(var(--v-theme-error));
}

.dashboard-metric__value--unavailable,
.dashboard-metric__comparison--unavailable {
  color: rgba(var(--v-theme-on-surface), 0.5);
}

.dashboard-metric__period {
  min-inline-size: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.dashboard-live-dot {
  border-radius: 50%;
  background: currentcolor;
  block-size: 7px;
  box-shadow: 0 0 0 4px rgba(234, 84, 85, 0.12);
  inline-size: 7px;
}

.dashboard-metric__drag {
  margin-inline-start: -7px;
}

@media (max-width: 600px) {
  .dashboard-metric {
    padding: 14px 13px 13px;
  }

  .dashboard-metric__drag {
    margin-inline-start: -5px;
  }

  .dashboard-metric__value {
    font-size: clamp(1.05rem, 5vw, 1.25rem);
    letter-spacing: -0.035em;
  }

  .dashboard-metric__comparison {
    gap: 5px;
  }

  .dashboard-metric__delta {
    padding-inline: 6px;
  }
}
</style>
