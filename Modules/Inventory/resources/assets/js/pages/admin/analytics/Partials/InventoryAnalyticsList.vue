<script lang="ts" setup>
import type { InventoryAnalyticsListItem } from '@/modules/inventory/contracts/inventoryAnalytics.ts'

defineProps<{ items: InventoryAnalyticsListItem[] }>()
</script>

<template>
  <div class="inventory-list">
    <div
      v-for="(item, index) in items"
      :key="item.id"
      class="inventory-list__row"
    >
      <span class="inventory-list__rank">{{ index + 1 }}</span>
      <div class="inventory-list__identity min-w-0">
        <strong class="text-truncate">{{ item.name }}</strong>
        <span
          v-if="item.secondary"
          class="text-truncate"
        >
          {{ item.secondary }}
        </span>
      </div>
      <strong :class="{ 'text-error': item.danger }">{{ item.value }}</strong>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.inventory-list {
  display: grid;
}
.inventory-list__row {
  display: grid;
  min-block-size: 42px;
  grid-template-columns: 28px minmax(0, 1fr) auto;
  align-items: center;
  gap: 10px;
  border-block-end: 1px solid rgba(var(--v-border-color), 0.08);
}
.inventory-list__row:last-child {
  border-block-end: 0;
}
.inventory-list__rank {
  display: grid;
  border-radius: 8px;
  background: rgba(var(--v-theme-primary), 0.08);
  block-size: 25px;
  color: rgb(var(--v-theme-primary));
  font-size: 0.68rem;
  font-weight: 800;
  inline-size: 25px;
  place-items: center;
}
.inventory-list__identity {
  display: grid;
  gap: 1px;
}
.inventory-list__identity strong {
  font-size: 0.76rem;
  font-weight: 700;
}
.inventory-list__identity span {
  color: rgba(var(--v-theme-on-surface), 0.5);
  font-size: 0.66rem;
}
.inventory-list__row > strong {
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.73rem;
  font-variant-numeric: tabular-nums;
}
</style>
