<script lang="ts" setup>
import type { AnalyticsWidgetMove } from '../types.ts'

defineProps<{ title: string }>()
const emit = defineEmits<{ move: [direction: AnalyticsWidgetMove] }>()

function handleKeydown(event: KeyboardEvent) {
  const direction: Partial<Record<string, AnalyticsWidgetMove>> = {
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
    class="analytics-drag"
    type="button"
    :aria-label="$t('reservation::frontend.analytics.customizer.drag_label', { widget: title })"
    :title="$t('reservation::frontend.analytics.customizer.drag_label', { widget: title })"
    @keydown="handleKeydown"
  >
    <VIcon
      icon="tabler-grip-vertical"
      size="18"
    />
  </button>
</template>

<style lang="scss" scoped>
.analytics-drag {
  display: grid;
  flex: 0 0 30px;
  border: 1px solid transparent;
  border-radius: 8px;
  background: transparent;
  block-size: 30px;
  color: rgba(var(--v-theme-on-surface), 0.38);
  cursor: grab;
  inline-size: 30px;
  place-items: center;
  touch-action: none;
  transition:
    background-color 160ms ease,
    border-color 160ms ease,
    color 160ms ease;
}

.analytics-drag:hover,
.analytics-drag:focus-visible {
  border-color: rgba(var(--v-theme-primary), 0.18);
  background: rgba(var(--v-theme-primary), 0.08);
  color: rgb(var(--v-theme-primary));
}

.analytics-drag:focus-visible {
  outline: 3px solid rgba(var(--v-theme-primary), 0.16);
}
.analytics-drag:active {
  cursor: grabbing;
}
.analytics-drag :deep(.v-icon) {
  pointer-events: none;
}

@media (pointer: coarse) {
  .analytics-drag {
    flex-basis: 40px;
    block-size: 44px;
    inline-size: 40px;
  }
}
</style>
