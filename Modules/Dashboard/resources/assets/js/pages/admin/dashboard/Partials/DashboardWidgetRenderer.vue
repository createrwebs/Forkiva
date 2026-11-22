<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type {
  DashboardDistributionItem,
  DashboardMetricData,
  DashboardMoney,
  DashboardRankingItem,
  DashboardStockItem,
  DashboardTrendData,
  DashboardWidgetDefinition,
  DashboardWidgetMove,
  DashboardWidgetOptionValue,
} from '@/modules/dashboard/contracts/dashboard.ts'
import DatePicker from '@/modules/core/components/Form/DatePicker.vue'
import DashboardBar from './DashboardBar.vue'
import DashboardDistribution from './DashboardDistribution.vue'
import DashboardInsight from './DashboardInsight.vue'
import DashboardMetricCard from './DashboardMetricCard.vue'
import DashboardPanel from './DashboardPanel.vue'
import DashboardRanking from './DashboardRanking.vue'
import DashboardStock from './DashboardStock.vue'
import DashboardTrend from './DashboardTrend.vue'

type CustomerMixData = {
  items: DashboardDistributionItem[]
  unique_customers: number
  repeat_customers: number
  repeat_rate: number
}

type InsightData =
  | {
      gross_profit: DashboardMoney
      cost: DashboardMoney
      margin_percent: number
      comparison: { percent: number | null; direction: -1 | 0 | 1 }
    }
  | {
      average_service_minutes: number
      average_completion_minutes: number
      cancelled_or_refunded: number
      completion_rate: number
    }

const props = defineProps<{
  definition: DashboardWidgetDefinition
  editable: boolean
  data: unknown
  minimized: boolean
  loading: boolean
  optionValue?: DashboardWidgetOptionValue
  currency: string
}>()

const emit = defineEmits<{
  hide: []
  minimize: []
  move: [direction: DashboardWidgetMove]
  'update:option': [value: DashboardWidgetOptionValue]
}>()

const { t, locale } = useI18n()
const metricData = computed(() => props.data as DashboardMetricData | null)
const trendData = computed(() => props.data as DashboardTrendData | null)
const rankingData = computed(() => (props.data as DashboardRankingItem[] | null) ?? [])
const stockData = computed(() => (props.data as DashboardStockItem[] | null) ?? [])
const insightData = computed(() => props.data as InsightData | null)
const title = computed(() => t(`dashboard::dashboards.widgets.${props.definition.key}.title`))
const description = computed(() =>
  t(`dashboard::dashboards.widgets.${props.definition.key}.description`),
)
const distributionItems = computed(() => {
  if (props.definition.key === 'customer_mix') {
    return (props.data as CustomerMixData | null)?.items ?? []
  }

  return (props.data as DashboardDistributionItem[] | null) ?? []
})
const customerMix = computed(() =>
  props.definition.key === 'customer_mix' ? (props.data as CustomerMixData | null) : null,
)
const distributionTotal = computed(() =>
  distributionItems.value.reduce((sum, item) => sum + item.value, 0),
)
const distributionTotalDisplay = computed(() => {
  if (!['payments_overview', 'cash_movements_overview'].includes(props.definition.key))
    return undefined

  return new Intl.NumberFormat(locale.value, {
    style: 'currency',
    currency: props.currency,
    minimumFractionDigits: distributionItems.value[0]?.formatted?.precision ?? 2,
    maximumFractionDigits: distributionItems.value[0]?.formatted?.precision ?? 2,
  }).format(distributionTotal.value)
})
const secondaryItems = computed(() => {
  if (!customerMix.value) return undefined

  return [
    {
      label: t('dashboard::dashboards.labels.unique_customers'),
      value: new Intl.NumberFormat(locale.value).format(customerMix.value.unique_customers),
    },
    {
      label: t('dashboard::dashboards.labels.repeat_customers'),
      value: `${new Intl.NumberFormat(locale.value).format(customerMix.value.repeat_customers)} (${customerMix.value.repeat_rate}%)`,
    },
  ]
})

function optionTitle(titleKey: string) {
  return titleKey.includes('::') ? t(titleKey) : titleKey
}
</script>

<template>
  <DashboardMetricCard
    v-if="definition.kind === 'metric'"
    :color="definition.color"
    :data="metricData"
    :editable="editable"
    :description="description"
    :icon="definition.icon"
    :inverse-trend="definition.inverseTrend"
    :loading="loading"
    :minimized="minimized"
    :title="title"
    @hide="emit('hide')"
    @minimize="emit('minimize')"
    @move="emit('move', $event)"
  />

  <DashboardPanel
    v-else
    :color="definition.color"
    :description="description"
    :editable="editable"
    :icon="definition.icon"
    :loading="loading"
    :minimized="minimized"
    :title="title"
    @hide="emit('hide')"
    @minimize="emit('minimize')"
    @move="emit('move', $event)"
  >
    <template
      v-if="definition.filter"
      #filters
    >
      <VSelect
        v-if="definition.filter.type === 'select'"
        :disabled="loading"
        :items="
          definition.filter.options?.map((item) => ({
            title: optionTitle(item.titleKey),
            value: item.value,
          }))
        "
        :model-value="optionValue"
        :title="t(`dashboard::dashboards.filters.${definition.filter.key}`)"
        class="dashboard-widget-filter"
        density="compact"
        hide-details
        variant="outlined"
        @update:model-value="emit('update:option', $event as DashboardWidgetOptionValue)"
      />
      <div
        v-else
        class="dashboard-widget-filter dashboard-widget-filter--date"
      >
        <DatePicker
          :disabled="loading"
          :model-value="typeof optionValue === 'string' ? optionValue : ''"
          density="compact"
          hide-details
          @update:model-value="emit('update:option', $event as DashboardWidgetOptionValue)"
        />
      </div>
    </template>

    <DashboardTrend
      v-if="definition.kind === 'trend' && trendData"
      :data="trendData"
      :hourly="definition.key === 'hourly_sales_trend'"
    />
    <DashboardDistribution
      v-else-if="definition.kind === 'distribution'"
      :items="distributionItems"
      :secondary="secondaryItems"
      :total-display="distributionTotalDisplay"
      :total-label="
        ['payments_overview', 'cash_movements_overview'].includes(definition.key)
          ? t('dashboard::dashboards.total_amount')
          : undefined
      "
    />
    <DashboardRanking
      v-else-if="definition.kind === 'ranking'"
      :items="rankingData"
      :products="definition.key === 'top_selling_products'"
    />
    <DashboardStock
      v-else-if="definition.kind === 'stock'"
      :items="stockData"
    />
    <DashboardBar
      v-else-if="definition.kind === 'bar'"
      :currency="currency"
      :items="rankingData"
    />
    <DashboardInsight
      v-else-if="definition.kind === 'insight' && insightData"
      :data="insightData"
      :gross-profit="definition.key === 'gross_profit_margin'"
    />
  </DashboardPanel>
</template>

<style lang="scss" scoped>
.dashboard-widget-filter {
  inline-size: 132px;
}

.dashboard-widget-filter--date {
  inline-size: 184px;
}

.dashboard-widget-filter :deep(.v-field) {
  border-radius: 10px;
  font-size: 0.75rem;
}

.dashboard-widget-filter--date :deep(.v-input) {
  inline-size: 100%;
}

@media (max-width: 600px) {
  .dashboard-widget-filter {
    inline-size: 100px;
  }

  .dashboard-widget-filter--date {
    inline-size: 164px;
  }
}
</style>
