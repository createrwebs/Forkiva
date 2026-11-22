<script lang="ts" setup>
import type { ChartType } from 'chart.js'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AnalyticsChart from '@/modules/core/components/Charts/AnalyticsChart.vue'
import type { GiftCardAnalyticsChartData } from '@/modules/giftcard/contracts/giftCardAnalytics.ts'

const props = withDefaults(
  defineProps<{
    data: GiftCardAnalyticsChartData
    type: ChartType
    color: string
    horizontal?: boolean
  }>(),
  { horizontal: false },
)
const { t } = useI18n()
const palette = ['#F57C00', '#0F766E', '#3867D6', '#E74C3C', '#7C3AED', '#0891B2', '#D97706']
const hasData = computed(
  () => props.data.labels.length > 0 && props.data.series.some((value) => Number(value) !== 0),
)
const datasets = computed(() => [
  {
    label: '',
    data: props.data.series,
    color:
      props.type === 'doughnut'
        ? props.data.labels.map((_, index) => palette[index % palette.length]!)
        : props.color,
    fill: props.type === 'line',
  },
])
</script>

<template>
  <div
    v-if="!hasData"
    class="gift-card-chart__empty"
  >
    <VIcon
      icon="tabler-chart-bar-off"
      size="30"
    />
    {{ t('giftcard::gift_cards.analytics.no_data_available') }}
  </div>
  <AnalyticsChart
    v-else
    :type="type === 'doughnut' ? 'doughnut' : type === 'bar' ? 'bar' : 'line'"
    :labels="data.labels"
    :datasets="datasets"
    :currency="data.currency"
    :precision="data.precision ?? 2"
    :horizontal="horizontal"
    :show-legend="type === 'doughnut' || datasets.length > 1"
  />
</template>

<style lang="scss" scoped>
.gift-card-chart__empty {
  display: grid;
  min-block-size: 230px;
  align-content: center;
  justify-items: center;
  gap: 8px;
  color: rgba(var(--v-theme-on-surface), 0.48);
  font-size: 0.76rem;
}
</style>
