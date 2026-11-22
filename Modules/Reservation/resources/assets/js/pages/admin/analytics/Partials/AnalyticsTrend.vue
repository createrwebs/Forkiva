<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { TrendRow } from '../types.ts'
import AnalyticsChart from './AnalyticsChart.vue'
import AnalyticsEmpty from './AnalyticsEmpty.vue'

const props = defineProps<{ rows: TrendRow[] }>()
const { t } = useI18n()
const datasets = computed(() => [
  {
    label: t('reservation::frontend.analytics.reservations'),
    data: props.rows.map((row) => Number(row.reservations_count || 0)),
    color: '#F57C00',
    type: 'bar' as const,
    yAxisID: 'reservations' as const,
  },
  {
    label: t('reservation::frontend.analytics.guests'),
    data: props.rows.map((row) => Number(row.guests_count || 0)),
    color: '#0F766E',
    type: 'line' as const,
    yAxisID: 'guests' as const,
    fill: true,
  },
])
</script>

<template>
  <AnalyticsEmpty v-if="rows.length === 0" />
  <div
    v-else
    class="analytics-trend"
  >
    <AnalyticsChart
      show-legend
      :datasets="datasets"
      :height="215"
      :labels="rows.map((row) => row.label)"
      type="bar"
    />
  </div>
</template>
