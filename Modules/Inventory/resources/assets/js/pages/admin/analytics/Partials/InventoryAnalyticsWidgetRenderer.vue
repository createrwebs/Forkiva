<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type {
  InventoryAnalyticsChartData,
  InventoryAnalyticsListItem,
  InventoryAnalyticsMetricData,
  InventoryAnalyticsWidgetDefinition,
  InventoryAnalyticsWidgetMove,
} from '@/modules/inventory/contracts/inventoryAnalytics.ts'
import DashboardPanel from '@/modules/dashboard/pages/admin/dashboard/Partials/DashboardPanel.vue'
import InventoryAnalyticsChart from './InventoryAnalyticsChart.vue'
import InventoryAnalyticsDistribution from './InventoryAnalyticsDistribution.vue'
import InventoryAnalyticsList from './InventoryAnalyticsList.vue'
import InventoryAnalyticsMetricCard from './InventoryAnalyticsMetricCard.vue'

const props = defineProps<{
  editable: boolean
  definition: InventoryAnalyticsWidgetDefinition
  data: unknown
  loading: boolean
  minimized: boolean
  optionValue?: number
}>()

const emit = defineEmits<{
  hide: []
  minimize: []
  move: [direction: InventoryAnalyticsWidgetMove]
  'update:option': [value: number]
}>()

const { t, locale } = useI18n()
const title = computed(() =>
  t(`inventory::inventories.analytics.widgets.${props.definition.key}.title`),
)
const description = computed(() =>
  t(`inventory::inventories.analytics.widgets.${props.definition.key}.description`),
)
const metricData = computed(() => props.data as InventoryAnalyticsMetricData | null)
const chartData = computed(() => props.data as InventoryAnalyticsChartData | null)

function formatMoney(value: number, currency: string) {
  return new Intl.NumberFormat(locale.value, {
    style: 'currency',
    currency,
    maximumFractionDigits: 2,
  }).format(value)
}

const listItems = computed<InventoryAnalyticsListItem[]>(() => {
  const limit = props.optionValue ?? props.definition.filter?.default ?? 5

  if (props.definition.key === 'low_stock_ingredients') {
    return ((props.data as any[]) ?? []).slice(0, limit).map((item) => ({
      id: item.id,
      name: item.name,
      value: item.current_stock,
      secondary: `${t('inventory::inventories.analytics.reorder_level')}: ${item.alert_quantity}`,
      danger: true,
    }))
  }

  if (props.definition.key === 'ingredient_purchases') {
    return ((props.data as any[]) ?? []).slice(0, limit).map((item) => ({
      id: item.id,
      name: item.name,
      value: item.total_quantity,
    }))
  }

  const data = chartData.value
  if (!data) return []
  const values = data.datasets[0]?.data ?? []
  return data.labels.slice(0, limit).map((name, index) => ({
    id: `${name}-${index}`,
    name,
    value:
      props.definition.key === 'top_suppliers'
        ? formatMoney(Number(values[index] ?? 0), data.currency ?? 'JOD')
        : `${new Intl.NumberFormat(locale.value).format(Number(values[index] ?? 0))} ${data.unitSymbols?.[index] ?? ''}`.trim(),
  }))
})

const hasData = computed(() => {
  if (props.definition.kind === 'metric') return Boolean(metricData.value)
  if (props.definition.kind === 'list') return listItems.value.length > 0
  return Boolean(chartData.value?.labels.length && chartData.value.datasets[0]?.data.length)
})
</script>

<template>
  <InventoryAnalyticsMetricCard
    :editable="editable"
    v-if="definition.kind === 'metric'"
    :color="definition.color"
    :data="metricData"
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
    <template
      v-if="definition.filter"
      #filters
    >
      <VSelect
        :disabled="loading"
        :items="
          definition.filter.options.map((value) => ({
            title: t('inventory::inventories.analytics.top_count', { count: value }),
            value,
          }))
        "
        :model-value="optionValue"
        class="inventory-widget-filter"
        density="compact"
        hide-details
        variant="outlined"
        @update:model-value="emit('update:option', Number($event))"
      />
    </template>

    <div
      v-if="!hasData"
      class="inventory-widget-empty"
    >
      <VIcon
        icon="tabler-chart-off"
        size="30"
      />
      {{ t('inventory::inventories.analytics.no_data_available') }}
    </div>
    <InventoryAnalyticsChart
      v-else-if="definition.kind === 'trend' && chartData"
      :data="chartData"
      :limit="optionValue"
      type="line"
    />
    <InventoryAnalyticsDistribution
      v-else-if="definition.kind === 'distribution' && chartData"
      :data="chartData"
    />
    <InventoryAnalyticsList
      v-else-if="definition.kind === 'list'"
      :items="listItems"
    />
  </DashboardPanel>
</template>

<style lang="scss" scoped>
.inventory-widget-filter {
  inline-size: 112px;
}
.inventory-widget-filter :deep(.v-field) {
  border-radius: 10px;
  font-size: 0.75rem;
}
.inventory-widget-empty {
  display: grid;
  min-block-size: 220px;
  align-content: center;
  justify-items: center;
  gap: 8px;
  color: rgba(var(--v-theme-on-surface), 0.48);
  font-size: 0.76rem;
}
</style>
