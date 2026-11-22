<script lang="ts" setup>
import { computed } from 'vue'
import AnalyticsChart from '@/modules/core/components/Charts/AnalyticsChart.vue'
import type { InventoryAnalyticsChartData } from '@/modules/inventory/contracts/inventoryAnalytics.ts'

const props = defineProps<{
  data: InventoryAnalyticsChartData
  type: 'line' | 'bar'
  limit?: number
}>()
const datasets = computed(() =>
  props.data.datasets.slice(0, props.limit ?? props.data.datasets.length).map((dataset) => ({
    label: dataset.label,
    data: dataset.data,
    color: dataset.borderColor ?? dataset.backgroundColor ?? '#F57C00',
    fill: dataset.fill,
  })),
)
</script>

<template>
  <AnalyticsChart
    :type="type"
    :labels="data.labels"
    :datasets="datasets"
    :currency="data.currency"
    :unit-symbols="data.unitSymbols"
    show-legend
  />
</template>
