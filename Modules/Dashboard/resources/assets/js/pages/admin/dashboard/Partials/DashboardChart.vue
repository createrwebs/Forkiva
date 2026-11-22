<script lang="ts" setup>
import AnalyticsChart from '@/modules/core/components/Charts/AnalyticsChart.vue'
import type { AnalyticsChartDataset } from '@/modules/core/components/Charts/AnalyticsChart.vue'
export type DashboardChartDataset = AnalyticsChartDataset

defineProps<{
  type: 'line' | 'bar' | 'doughnut'
  labels: string[]
  datasets: AnalyticsChartDataset[]
  currency?: string
  height?: number
  precision?: number
  showLegend?: boolean
}>()
</script>

<template>
  <AnalyticsChart v-bind="$props" />
</template>
