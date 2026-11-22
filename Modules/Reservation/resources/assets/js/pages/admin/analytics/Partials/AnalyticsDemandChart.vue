<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { DemandRow, PeakHourRow } from '../types.ts'
import AnalyticsChart from './AnalyticsChart.vue'
import AnalyticsEmpty from './AnalyticsEmpty.vue'

const props = withDefaults(
  defineProps<{ rows: Array<DemandRow | PeakHourRow>; color: string; horizontal?: boolean }>(),
  { horizontal: false },
)
const { t } = useI18n()
const visibleRows = computed(() =>
  props.rows.filter((row) => Number(row.reservations_count || 0) > 0),
)
const labels = computed(() =>
  visibleRows.value.map((row) =>
    'day' in row || 'id' in row
      ? (row as DemandRow).label
      : (row as PeakHourRow).hour_label || `${(row as PeakHourRow).hour}:00`,
  ),
)
const datasets = computed(() => [
  {
    label: t('reservation::frontend.analytics.reservations'),
    data: visibleRows.value.map((row) => Number(row.reservations_count || 0)),
    color: props.color,
  },
])
</script>

<template>
  <AnalyticsEmpty v-if="visibleRows.length === 0" />
  <AnalyticsChart
    v-else
    :datasets="datasets"
    :height="220"
    :horizontal="horizontal"
    :labels="labels"
    type="bar"
  />
</template>
