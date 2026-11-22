<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import type { DashboardStockItem } from '@/modules/dashboard/contracts/dashboard.ts'
import DashboardEmpty from './DashboardEmpty.vue'

defineProps<{
  items: DashboardStockItem[]
}>()

const { t, locale } = useI18n()
</script>

<template>
  <DashboardEmpty v-if="items.length === 0" />
  <div
    v-else
    class="dashboard-stock"
  >
    <div
      v-for="item in items"
      :key="item.id"
      class="dashboard-stock__row"
    >
      <span
        :class="item.out_of_stock ? 'danger' : 'warning'"
        class="dashboard-stock__icon"
      >
        <VIcon
          :icon="item.out_of_stock ? 'tabler-alert-circle' : 'tabler-alert-triangle'"
          size="19"
        />
      </span>
      <div class="dashboard-stock__identity min-w-0">
        <strong class="text-truncate">{{ item.name }}</strong>
        <span>
          {{ t('dashboard::dashboards.labels.reorder_level') }}:
          {{ new Intl.NumberFormat(locale).format(item.alert_quantity) }}
          {{ item.symbol }}
        </span>
      </div>
      <VChip
        :color="item.out_of_stock ? 'error' : 'warning'"
        label
        size="small"
        variant="tonal"
      >
        {{ new Intl.NumberFormat(locale).format(item.current_stock) }} {{ item.symbol }}
      </VChip>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.dashboard-stock {
  display: grid;
  gap: 2px;
}

.dashboard-stock__row {
  display: grid;
  min-block-size: 59px;
  grid-template-columns: 42px minmax(0, 1fr) auto;
  align-items: center;
  gap: 11px;
  padding-block: 7px;
  border-block-end: 1px solid rgba(var(--v-border-color), 0.08);
}

.dashboard-stock__row:last-child {
  border-block-end: 0;
}

.dashboard-stock__icon {
  display: grid;
  border-radius: 11px;
  block-size: 42px;
  inline-size: 42px;
  place-items: center;
}

.dashboard-stock__icon.warning {
  background: rgba(245, 124, 0, 0.11);
  color: #d97706;
}

.dashboard-stock__icon.danger {
  background: rgba(231, 76, 60, 0.11);
  color: #e74c3c;
}

.dashboard-stock__identity {
  display: grid;
  gap: 2px;
}

.dashboard-stock__identity strong {
  font-size: 0.82rem;
}

.dashboard-stock__identity span {
  color: rgba(var(--v-theme-on-surface), 0.5);
  font-size: 0.7rem;
}
</style>
