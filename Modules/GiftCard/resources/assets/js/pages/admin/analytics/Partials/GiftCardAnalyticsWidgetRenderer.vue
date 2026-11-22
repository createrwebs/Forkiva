<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type {
  GiftCardAnalyticsChartData,
  GiftCardAnalyticsMetricData,
  GiftCardAnalyticsWidgetDefinition,
  GiftCardAnalyticsWidgetMove,
} from '@/modules/giftcard/contracts/giftCardAnalytics.ts'
import DashboardMetricCard from '@/modules/dashboard/pages/admin/dashboard/Partials/DashboardMetricCard.vue'
import DashboardPanel from '@/modules/dashboard/pages/admin/dashboard/Partials/DashboardPanel.vue'
import GiftCardAnalyticsChart from './GiftCardAnalyticsChart.vue'
import GiftCardAnalyticsDetails from './GiftCardAnalyticsDetails.vue'

const props = defineProps<{
  editable: boolean
  definition: GiftCardAnalyticsWidgetDefinition
  data: unknown
  loading: boolean
  minimized: boolean
}>()

const emit = defineEmits<{
  hide: []
  minimize: []
  move: [direction: GiftCardAnalyticsWidgetMove]
}>()

const { t } = useI18n()
const title = computed(() =>
  t(`giftcard::gift_cards.analytics.widgets.${props.definition.key}.title`),
)
const description = computed(() =>
  t(`giftcard::gift_cards.analytics.widgets.${props.definition.key}.description`),
)
const metricData = computed(() => props.data as GiftCardAnalyticsMetricData | null)
const chartData = computed(() => props.data as GiftCardAnalyticsChartData | null)
const detailData = computed(() => (props.data as Record<string, unknown> | null) ?? {})
</script>

<template>
  <DashboardMetricCard
    :editable="editable"
    v-if="definition.kind === 'metric'"
    :color="definition.color"
    :data="metricData"
    :description="description"
    :icon="definition.icon"
    :loading="loading"
    :minimized="minimized"
    :title="title"
    @hide="emit('hide')"
    @minimize="emit('minimize')"
    @move="emit('move', $event)"
  />

  <DashboardPanel
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
    <GiftCardAnalyticsChart
      v-if="definition.kind === 'chart' && chartData"
      :color="definition.color"
      :data="chartData"
      :horizontal="definition.horizontal"
      :type="definition.chartType ?? 'line'"
    />
    <GiftCardAnalyticsDetails
      v-else-if="definition.kind !== 'chart'"
      :data="detailData"
      :kind="definition.kind"
    />
  </DashboardPanel>
</template>
