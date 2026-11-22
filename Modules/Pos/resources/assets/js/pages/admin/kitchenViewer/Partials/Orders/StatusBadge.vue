<script lang="ts" setup>
import { computed } from 'vue'

const props = defineProps<{ status: string; label: string }>()
const state = computed(() =>
  props.status === 'pending' ? 'new' : props.status === 'served' ? 'completed' : props.status,
)
const icons: Record<string, string> = {
  new: 'tabler-sparkles',
  preparing: 'tabler-chef-hat',
  ready: 'tabler-circle-check',
  delayed: 'tabler-clock-exclamation',
  completed: 'tabler-checks',
  cancelled: 'tabler-circle-x',
  refunded: 'tabler-arrow-back-up',
}
const icon = computed(() => icons[state.value] || 'tabler-circle')
</script>

<template>
  <span
    class="kds-status-badge"
    :class="`kds-state--${state}`"
  >
    <VIcon
      :icon="icon"
      size="14"
    />
    {{ label }}
  </span>
</template>
