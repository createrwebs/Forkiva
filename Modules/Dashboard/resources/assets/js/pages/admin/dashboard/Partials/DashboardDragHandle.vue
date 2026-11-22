<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import type { DashboardWidgetMove } from '@/modules/dashboard/contracts/dashboard.ts'

defineProps<{
  title: string
}>()

const emit = defineEmits<{
  move: [direction: DashboardWidgetMove]
}>()

const { t } = useI18n()

function handleKeydown(event: KeyboardEvent) {
  const direction: Partial<Record<string, DashboardWidgetMove>> = {
    ArrowUp: 'up',
    ArrowLeft: 'left',
    ArrowDown: 'down',
    ArrowRight: 'right',
    Home: 'first',
    End: 'last',
  }
  const move = direction[event.key]
  if (!move) return

  event.preventDefault()
  emit('move', move)
}
</script>

<template>
  <button
    class="dashboard-widget__drag"
    type="button"
    :aria-label="t('dashboard::dashboards.customizer.drag_label_keyboard', { widget: title })"
    :title="t('dashboard::dashboards.customizer.drag_label_keyboard', { widget: title })"
    @keydown="handleKeydown"
  >
    <VIcon
      icon="tabler-grip-vertical"
      size="18"
    />
  </button>
</template>

<style lang="scss" scoped>
.dashboard-widget__drag {
  display: grid;
  flex: 0 0 30px;
  border: 1px solid rgba(var(--v-border-color), 0.14);
  border-radius: 8px;
  background: rgba(var(--v-theme-on-surface), 0.035);
  block-size: 30px;
  color: rgba(var(--v-theme-on-surface), 0.62);
  cursor: grab;
  inline-size: 30px;
  place-items: center;
  touch-action: none;
  transition:
    background-color 160ms ease,
    border-color 160ms ease,
    color 160ms ease;
}

.dashboard-widget__drag:hover {
  border-color: rgba(var(--v-theme-primary), 0.16);
  background: rgba(var(--v-theme-primary), 0.08);
  color: rgb(var(--v-theme-primary));
}

.dashboard-widget__drag:focus-visible {
  border-color: rgb(var(--v-theme-primary));
  outline: 3px solid rgba(var(--v-theme-primary), 0.16);
  color: rgb(var(--v-theme-primary));
}

.dashboard-widget__drag:active {
  cursor: grabbing;
}

.dashboard-widget__drag :deep(.v-icon) {
  pointer-events: none;
}

@media (pointer: coarse) {
  .dashboard-widget__drag {
    flex-basis: 40px;
    block-size: 44px;
    inline-size: 40px;
  }
}
</style>
