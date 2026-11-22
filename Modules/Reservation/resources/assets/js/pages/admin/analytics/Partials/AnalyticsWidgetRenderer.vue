<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type {
  AnalyticsPayload,
  AnalyticsWidgetDefinition,
  AnalyticsWidgetMove,
  UpcomingReservation,
} from '../types.ts'
import { enumColor, enumName } from '../types.ts'
import AnalyticsDemandChart from './AnalyticsDemandChart.vue'
import AnalyticsDistribution from './AnalyticsDistribution.vue'
import AnalyticsMetricCard from './AnalyticsMetricCard.vue'
import AnalyticsPanel from './AnalyticsPanel.vue'
import AnalyticsTrend from './AnalyticsTrend.vue'
import TableUtilizationCard from './TableUtilizationCard.vue'
import UpcomingReservationsCard from './UpcomingReservationsCard.vue'

const props = defineProps<{
  editable: boolean
  definition: AnalyticsWidgetDefinition
  payload: AnalyticsPayload
  loading: boolean
  minimized: boolean
  canShowReservation: boolean
}>()
const emit = defineEmits<{
  hide: []
  minimize: []
  move: [direction: AnalyticsWidgetMove]
  show: [reservation: UpcomingReservation]
}>()
const { t } = useI18n()
const title = computed(() =>
  props.definition.kind === 'metric'
    ? t(`reservation::frontend.analytics.summary.${props.definition.key}`)
    : t(`reservation::frontend.analytics.widgets.${props.definition.key}.title`),
)
const description = computed(() =>
  props.definition.kind === 'metric'
    ? ''
    : t(`reservation::frontend.analytics.widgets.${props.definition.key}.description`),
)
const distributionItems = computed(() => {
  const rows =
    props.definition.key === 'status_breakdown'
      ? props.payload.status_breakdown
      : props.payload.type_breakdown
  const valueKey = props.definition.key === 'status_breakdown' ? 'status' : 'type'
  return rows
    .filter((row) => Number(row.reservations_count || 0) > 0)
    .map((row, index) => {
      const value = row[valueKey]
      return {
        id: value?.id || String(index),
        label: enumName(value),
        value: Number(row.reservations_count || 0),
        color: enumColor(value, index),
      }
    })
})
</script>

<template>
  <AnalyticsMetricCard
    :editable="editable"
    v-if="definition.kind === 'metric'"
    :color="definition.color"
    :icon="definition.icon"
    :loading="loading"
    :minimized="minimized"
    :precision="definition.precision"
    :suffix="definition.suffix"
    :title="title"
    :value="payload.summary[definition.key]"
    @hide="emit('hide')"
    @minimize="emit('minimize')"
    @move="emit('move', $event)"
  />
  <AnalyticsPanel
    :editable="editable"
    v-else
    :color="definition.color"
    :description="description"
    :icon="definition.icon"
    :loading="loading"
    :minimized="minimized"
    :title="title"
    @hide="emit('hide')"
    @minimize="emit('minimize')"
    @move="emit('move', $event)"
  >
    <AnalyticsTrend
      v-if="definition.key === 'reservation_trend'"
      :rows="payload.reservation_trend"
    />
    <AnalyticsDistribution
      v-else-if="definition.key === 'status_breakdown' || definition.key === 'type_breakdown'"
      :items="distributionItems"
    />
    <AnalyticsDemandChart
      v-else-if="definition.key === 'peak_hours'"
      color="#F57C00"
      :rows="payload.peak_hours"
    />
    <AnalyticsDemandChart
      v-else-if="definition.key === 'weekday_demand'"
      color="#3867D6"
      :rows="payload.weekday_demand"
    />
    <AnalyticsDemandChart
      v-else-if="definition.key === 'party_size_distribution'"
      color="#0F766E"
      horizontal
      :rows="payload.party_size_distribution"
    />
    <TableUtilizationCard
      v-else-if="definition.key === 'table_utilization'"
      :rows="payload.table_utilization"
    />
    <UpcomingReservationsCard
      v-else-if="definition.key === 'upcoming_reservations'"
      :can-show-reservation="canShowReservation"
      :rows="payload.upcoming_reservations"
      @show="emit('show', $event)"
    />
  </AnalyticsPanel>
</template>
