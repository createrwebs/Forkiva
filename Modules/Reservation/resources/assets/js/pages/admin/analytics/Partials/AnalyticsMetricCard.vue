<script lang="ts" setup>
import { computed } from 'vue'
import { formatNumber, type AnalyticsWidgetMove } from '../types.ts'
import AnalyticsDragHandle from './AnalyticsDragHandle.vue'

const props = withDefaults(
  defineProps<{
    editable: boolean
    title: string
    icon: string
    color: string
    value?: number
    suffix?: string
    precision?: number
    minimized: boolean
    loading: boolean
  }>(),
  {
    value: 0,
    suffix: '',
    precision: 0,
  },
)
const emit = defineEmits<{ hide: []; minimize: []; move: [direction: AnalyticsWidgetMove] }>()
const displayValue = computed(() => formatNumber(props.value, props.suffix, props.precision))
</script>

<template>
  <VCard
    class="analytics-metric h-100"
    :class="{ 'analytics-metric--minimized': minimized }"
    elevation="0"
  >
    <div
      class="analytics-metric__topline"
      :style="{ backgroundColor: color }"
    />
    <div class="analytics-metric__header analytics-widget__drag-surface">
      <AnalyticsDragHandle
        v-if="editable"
        class="analytics-metric__drag"
        :title="title"
        @move="emit('move', $event)"
      />
      <span
        class="analytics-metric__icon"
        :style="{ color, backgroundColor: `${color}14` }"
      >
        <VIcon
          :icon="icon"
          size="22"
        />
      </span>
      <div class="analytics-metric__title text-truncate">{{ title }}</div>
      <VSpacer />
      <div
        class="analytics-metric__actions"
        v-if="editable"
      >
        <VBtn
          :aria-label="
            $t(
              minimized
                ? 'reservation::frontend.analytics.expand'
                : 'reservation::frontend.analytics.minimize',
            )
          "
          density="compact"
          :icon="minimized ? 'tabler-chevron-down' : 'tabler-minus'"
          size="small"
          variant="text"
          @click="emit('minimize')"
        />
        <VBtn
          :aria-label="$t('reservation::frontend.analytics.hide')"
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
        class="analytics-metric__body"
      >
        <VSkeletonLoader
          v-if="loading"
          type="heading, text"
        />
        <template v-else>
          <div class="analytics-metric__value text-truncate">{{ displayValue }}</div>
          <div class="analytics-metric__context">
            <VIcon
              icon="tabler-calendar-stats"
              size="14"
            />
            {{ $t('reservation::frontend.analytics.overview') }}
          </div>
        </template>
      </div>
    </VExpandTransition>
  </VCard>
</template>

<style lang="scss" scoped>
.analytics-metric {
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
    box-shadow 180ms ease;
}
.analytics-metric:hover {
  border-color: rgba(var(--v-border-color), 0.2);
  box-shadow: 0 13px 38px rgba(0, 0, 0, 0.075) !important;
}
.analytics-metric--minimized {
  min-block-size: 0;
}
.analytics-metric__topline {
  position: absolute;
  inset-block-start: 0;
  inset-inline: 0;
  block-size: 4px;
}
.analytics-metric__header {
  display: flex;
  min-inline-size: 0;
  align-items: center;
  gap: 9px;
}
.analytics-metric__icon {
  display: grid;
  flex: 0 0 42px;
  border-radius: 12px;
  block-size: 42px;
  inline-size: 42px;
  place-items: center;
}
.analytics-metric__title {
  min-inline-size: 0;
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.78rem;
  font-weight: 720;
  letter-spacing: -0.012em;
}
.analytics-metric__actions {
  display: flex;
  flex: 0 0 auto;
  margin-inline-end: -7px;
}
.analytics-metric__actions :deep(.v-btn) {
  color: rgba(var(--v-theme-on-surface), 0.44);
}
.analytics-metric__body {
  margin-block-start: 13px;
}
.analytics-metric__value {
  color: rgb(var(--v-theme-on-surface));
  font-size: clamp(1.45rem, 2vw, 1.78rem);
  font-weight: 800;
  letter-spacing: -0.045em;
  line-height: 1.08;
  font-variant-numeric: tabular-nums;
}
.analytics-metric__context {
  display: flex;
  min-block-size: 27px;
  align-items: center;
  gap: 5px;
  margin-block-start: 9px;
  color: rgba(var(--v-theme-on-surface), 0.52);
  font-size: 0.67rem;
}
.analytics-metric__drag {
  margin-inline-start: -7px;
}
@media (max-width: 600px) {
  .analytics-metric {
    padding: 14px 13px 13px;
  }
  .analytics-metric__value {
    font-size: 1.25rem;
  }
}
</style>
