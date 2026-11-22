<script lang="ts" setup>
defineProps<{ table: Record<string, any>; selected?: boolean }>()
defineEmits<{ (e: 'show-details', tableId: number): void }>()
</script>

<template>
  <button
    type="button"
    class="pos-table-card"
    :class="{ 'pos-table-card--selected': selected }"
    :style="{ '--table-status-color': table.status.color }"
    :aria-pressed="selected"
    @click="$emit('show-details', table.id)"
  >
    <div
      class="pos-table-symbol"
      aria-hidden="true"
    >
      <span class="pos-table-top" />
      <VIcon
        icon="tabler-armchair"
        size="23"
      />
      <VIcon
        icon="tabler-armchair"
        size="23"
      />
    </div>
    <strong>{{ table.name }}</strong>
    <span class="pos-table-location">
      {{ table.zone?.name }}
      <span v-if="table.floor?.name">· {{ table.floor.name }}</span>
    </span>
    <span class="pos-table-status">
      <span class="pos-status-dot" />
      {{ table.status.name }}
    </span>
  </button>
</template>
