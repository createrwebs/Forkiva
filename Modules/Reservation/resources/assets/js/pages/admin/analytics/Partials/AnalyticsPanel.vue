<script lang="ts" setup>
import type { AnalyticsWidgetMove } from '../types.ts'
import AnalyticsDragHandle from './AnalyticsDragHandle.vue'

withDefaults(
  defineProps<{
    editable: boolean
    title: string
    description?: string
    icon: string
    color: string
    minimized?: boolean
    loading?: boolean
  }>(),
  {
    description: '',
    minimized: false,
    loading: false,
  },
)
const emit = defineEmits<{ hide: []; minimize: []; move: [direction: AnalyticsWidgetMove] }>()
</script>

<template>
  <VCard
    class="analytics-panel h-100"
    elevation="0"
  >
    <div class="analytics-panel__header analytics-widget__drag-surface">
      <div class="analytics-panel__identity">
        <AnalyticsDragHandle
          v-if="editable"
          class="analytics-panel__drag"
          :title="title"
          @move="emit('move', $event)"
        />
        <span
          class="analytics-panel__icon"
          :style="{ color, backgroundColor: `${color}14` }"
        >
          <VIcon
            :icon="icon"
            size="20"
          />
        </span>
        <div class="min-w-0">
          <div class="analytics-panel__title text-truncate">{{ title }}</div>
          <div
            v-if="description && !minimized"
            class="analytics-panel__description text-truncate"
          >
            {{ description }}
          </div>
        </div>
      </div>
      <div
        class="analytics-panel__actions"
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
          density="comfortable"
          :icon="minimized ? 'tabler-chevron-down' : 'tabler-minus'"
          size="small"
          variant="text"
          @click="emit('minimize')"
        />
        <VBtn
          :aria-label="$t('reservation::frontend.analytics.hide')"
          density="comfortable"
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
        class="analytics-panel__body"
      >
        <VSkeletonLoader
          v-if="loading"
          class="analytics-panel__loading"
          type="image, list-item-two-line"
        />
        <slot v-else />
      </div>
    </VExpandTransition>
  </VCard>
</template>

<style lang="scss" scoped>
.analytics-panel {
  display: flex;
  overflow: hidden;
  flex-direction: column;
  border: 1px solid rgba(var(--v-border-color), 0.11);
  border-radius: 18px;
  background: rgb(var(--v-theme-surface));
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.045) !important;
  transition:
    box-shadow 180ms ease,
    transform 180ms ease;
}
.analytics-panel:hover {
  box-shadow: 0 13px 38px rgba(0, 0, 0, 0.08) !important;
}
.analytics-panel__header {
  display: flex;
  min-block-size: 72px;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 16px 18px;
  border-block-end: 1px solid rgba(var(--v-border-color), 0.08);
}
.analytics-panel__identity,
.analytics-panel__actions {
  display: flex;
  align-items: center;
}
.analytics-panel__identity {
  min-inline-size: 0;
  gap: 10px;
}
.analytics-panel__actions {
  flex: 0 0 auto;
  gap: 2px;
}
.analytics-panel__icon {
  display: grid;
  flex: 0 0 36px;
  border-radius: 11px;
  block-size: 36px;
  inline-size: 36px;
  place-items: center;
}
.analytics-panel__title {
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.94rem;
  font-weight: 700;
  letter-spacing: -0.012em;
}
.analytics-panel__description {
  max-inline-size: 430px;
  margin-block-start: 2px;
  color: rgba(var(--v-theme-on-surface), 0.58);
  font-size: 0.76rem;
}
.analytics-panel__body {
  overflow: auto;
  flex: 1 1 auto;
  min-block-size: 190px;
  padding: 18px;
}
.analytics-panel__loading {
  opacity: 0.65;
}

@media (max-width: 600px) {
  .analytics-panel__header,
  .analytics-panel__body {
    padding: 14px;
  }
  .analytics-panel__description {
    display: none;
  }
}
</style>
