<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import type { DashboardWidgetMove } from '@/modules/dashboard/contracts/dashboard.ts'
import DashboardDragHandle from './DashboardDragHandle.vue'

withDefaults(
  defineProps<{
    title: string
    description?: string
    icon: string
    color: string
    minimized?: boolean
    loading?: boolean
    editable?: boolean
  }>(),
  {
    description: '',
    minimized: false,
    loading: false,
    editable: true,
  },
)

const emit = defineEmits<{
  hide: []
  minimize: []
  move: [direction: DashboardWidgetMove]
}>()

const { t } = useI18n()
</script>

<template>
  <VCard
    class="dashboard-panel h-100"
    elevation="0"
  >
    <div class="dashboard-panel__header">
      <div class="dashboard-panel__identity">
        <DashboardDragHandle
          v-if="editable"
          class="dashboard-panel__drag"
          :title="title"
          @move="emit('move', $event)"
        />

        <span
          class="dashboard-panel__icon"
          :style="{ color, backgroundColor: `${color}14` }"
        >
          <VIcon
            :icon="icon"
            size="20"
          />
        </span>

        <div class="min-w-0">
          <div class="dashboard-panel__title text-truncate">{{ title }}</div>
          <div
            v-if="description && !minimized"
            class="dashboard-panel__description text-truncate"
          >
            {{ description }}
          </div>
        </div>
      </div>

      <div class="dashboard-panel__actions">
        <slot name="filters" />

        <VBtn
          v-if="editable"
          :aria-label="
            minimized
              ? t('dashboard::dashboards.actions.expand')
              : t('dashboard::dashboards.actions.minimize')
          "
          density="comfortable"
          :icon="minimized ? 'tabler-chevron-down' : 'tabler-minus'"
          size="small"
          variant="text"
          @click="emit('minimize')"
        />
        <VBtn
          v-if="editable"
          :aria-label="t('dashboard::dashboards.actions.hide')"
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
        class="dashboard-panel__body"
      >
        <div
          v-if="loading"
          class="dashboard-panel__loading"
        >
          <VSkeletonLoader type="image, list-item-two-line" />
        </div>
        <slot v-else />
      </div>
    </VExpandTransition>
  </VCard>
</template>

<style lang="scss" scoped>
.dashboard-panel {
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

.dashboard-panel:hover {
  box-shadow: 0 13px 38px rgba(0, 0, 0, 0.08) !important;
}

.dashboard-panel__header {
  display: flex;
  min-block-size: 72px;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 16px 18px;
  border-block-end: 1px solid rgba(var(--v-border-color), 0.08);
}

.dashboard-panel__identity,
.dashboard-panel__actions {
  display: flex;
  align-items: center;
}

.dashboard-panel__identity {
  min-inline-size: 0;
  gap: 10px;
}

.dashboard-panel__actions {
  flex: 0 0 auto;
  gap: 2px;
}

.dashboard-panel__icon {
  display: grid;
  flex: 0 0 36px;
  border-radius: 11px;
  block-size: 36px;
  inline-size: 36px;
  place-items: center;
}

.dashboard-panel__title {
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.94rem;
  font-weight: 700;
  letter-spacing: -0.012em;
}

.dashboard-panel__description {
  max-inline-size: 430px;
  margin-block-start: 2px;
  color: rgba(var(--v-theme-on-surface), 0.58);
  font-size: 0.76rem;
}

.dashboard-panel__body {
  overflow: auto;
  flex: 1 1 auto;
  min-block-size: 190px;
  padding: 18px;
}

.dashboard-panel__loading {
  opacity: 0.65;
}

@media (max-width: 600px) {
  .dashboard-panel__header {
    padding: 14px;
  }

  .dashboard-panel__body {
    padding: 14px;
  }

  .dashboard-panel__description {
    display: none;
  }
}
</style>
