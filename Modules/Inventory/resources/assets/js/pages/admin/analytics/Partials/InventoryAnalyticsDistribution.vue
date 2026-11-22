<script lang="ts" setup>
import { computed } from 'vue'
import DashboardDistribution from '@/modules/dashboard/pages/admin/dashboard/Partials/DashboardDistribution.vue'
import type { InventoryAnalyticsChartData } from '@/modules/inventory/contracts/inventoryAnalytics.ts'
const props = defineProps<{ data: InventoryAnalyticsChartData }>()
const items = computed(() => {
  const dataset = props.data.datasets[0]
  const colors = Array.isArray(dataset?.backgroundColor) ? dataset.backgroundColor : []
  return props.data.labels.map((label, index) => ({
    id: String(index),
    label,
    value: Number(dataset?.data[index] ?? 0),
    color: colors[index] ?? '#94A3B8',
  }))
})
</script>

<template>
  <DashboardDistribution
    :items="items"
    :total-label="$t('inventory::inventories.analytics.total')"
  />
</template>
