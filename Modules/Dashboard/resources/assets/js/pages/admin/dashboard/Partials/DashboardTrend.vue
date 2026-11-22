<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useTheme } from 'vuetify'
import type { DashboardTrendData } from '@/modules/dashboard/contracts/dashboard.ts'
import DashboardChart from './DashboardChart.vue'
import DashboardEmpty from './DashboardEmpty.vue'

const props = defineProps<{
  data: DashboardTrendData
  hourly: boolean
}>()

const { t, locale } = useI18n()
const { current } = useTheme()

const formattedLabels = computed(() =>
  props.data.labels.map((label) => {
    const value = new Date(label.replace(' ', 'T'))
    if (Number.isNaN(value.getTime())) return label

    if (props.hourly || props.data.granularity === 'hour') {
      return new Intl.DateTimeFormat(locale.value, {
        hour: 'numeric',
      }).format(value)
    }

    if (props.data.granularity === 'month') {
      return new Intl.DateTimeFormat(locale.value, {
        month: 'short',
      }).format(value)
    }

    return new Intl.DateTimeFormat(locale.value, {
      day: 'numeric',
      month: 'short',
    }).format(value)
  }),
)

const hasData = computed(() => props.data.sales.some((value) => value > 0))
const datasets = computed(() => [
  {
    label: `${t('dashboard::dashboards.labels.sales')} · ${t('dashboard::dashboards.labels.current')}`,
    data: props.data.sales,
    color: '#F57C00',
    type: 'line' as const,
    fill: true,
  },
  ...(!props.hourly && props.data.previous_sales
    ? [
        {
          label: `${t('dashboard::dashboards.labels.sales')} · ${t('dashboard::dashboards.labels.previous')}`,
          data: props.data.previous_sales,
          color: '#94A3B8',
          type: 'line' as const,
          dashed: true,
        },
      ]
    : []),
  ...(!props.hourly
    ? [
        {
          label: t('dashboard::dashboards.labels.orders'),
          data: props.data.orders,
          color: current.value.dark ? '#60A5FA' : '#043A63',
          type: 'bar' as const,
          yAxisID: 'count' as const,
        },
      ]
    : []),
])
</script>

<template>
  <DashboardEmpty v-if="!hasData" />
  <div v-else>
    <DashboardChart
      show-legend
      :currency="data.currency"
      :datasets="datasets"
      :height="280"
      :labels="formattedLabels"
      type="line"
    />
  </div>
</template>
