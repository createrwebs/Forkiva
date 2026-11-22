<script lang="ts" setup>
import { computed } from 'vue'
import SharedAnalyticsChart from '@/modules/core/components/Charts/AnalyticsChart.vue'
import type { AnalyticsChartDataset as SharedDataset } from '@/modules/core/components/Charts/AnalyticsChart.vue'

export type AnalyticsChartDataset = Omit<SharedDataset, 'yAxisID'> & {
  yAxisID?: 'reservations' | 'guests'
}
const props = withDefaults(
  defineProps<{
    type: 'line' | 'bar' | 'doughnut'
    labels: string[]
    datasets: AnalyticsChartDataset[]
    height?: number
    horizontal?: boolean
    showLegend?: boolean
  }>(),
  { height: 225, horizontal: false, showLegend: false },
)
const datasets = computed<SharedDataset[]>(() =>
  props.datasets.map((dataset) => ({
    ...dataset,
    yAxisID: dataset.yAxisID === 'guests' ? 'count' : 'money',
  })),
)
</script>

<template>
  <SharedAnalyticsChart
    :type="type"
    :labels="labels"
    :datasets="datasets"
    :height="height"
    :horizontal="horizontal"
    :show-legend="showLegend"
  />
</template>
