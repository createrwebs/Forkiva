<script lang="ts" setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import {
  ArcElement,
  BarController,
  BarElement,
  CategoryScale,
  Chart as ChartJs,
  DoughnutController,
  Filler,
  Legend,
  LineController,
  LineElement,
  LinearScale,
  PointElement,
  Tooltip,
} from 'chart.js'
import type { ChartConfiguration, ChartType, TooltipItem } from 'chart.js'
import { useI18n } from 'vue-i18n'
import { useTheme } from 'vuetify'
import { color as chartColor } from 'chart.js/helpers'
import { formatPrice } from '@/modules/core/utils/formatters.ts'

ChartJs.register(
  ArcElement,
  BarController,
  BarElement,
  CategoryScale,
  DoughnutController,
  Filler,
  Legend,
  LineController,
  LineElement,
  LinearScale,
  PointElement,
  Tooltip,
)

export interface AnalyticsChartDataset {
  label: string
  data: number[]
  color: string | string[]
  type?: 'line' | 'bar'
  dashed?: boolean
  fill?: boolean
  yAxisID?: 'money' | 'count'
}

const props = withDefaults(
  defineProps<{
    type: 'line' | 'bar' | 'doughnut'
    labels: string[]
    datasets: AnalyticsChartDataset[]
    currency?: string
    height?: number
    horizontal?: boolean
    showLegend?: boolean
    precision?: number
    rtl?: boolean
    unitSymbols?: string[]
  }>(),
  {
    currency: '',
    height: 270,
    horizontal: false,
    showLegend: false,
    rtl: false,
  },
)

const { locale } = useI18n()
const { current } = useTheme()
const canvas = ref<HTMLCanvasElement | null>(null)
let chart: ChartJs | null = null

function tooltipLabel(context: TooltipItem<ChartType>) {
  const value = Number(context.raw ?? 0)
  const dataset = props.datasets[context.datasetIndex]
  const prefix = dataset?.label ? `${dataset.label}: ` : ''

  if (dataset?.yAxisID === 'count' || !props.currency) {
    return `${prefix}${new Intl.NumberFormat(locale.value).format(value)}${props.unitSymbols?.[context.dataIndex] ? ` ${props.unitSymbols[context.dataIndex]}` : ''}`
  }

  return `${prefix}${formatPrice(value, props.currency, props.precision)}`
}

function resolveColor(value: string, alpha = 1): string {
  const resolved = current.value.colors[value] ?? value
  return chartColor(resolved).alpha(alpha).rgbString()
}

const styledDatasets = computed(() =>
  props.datasets.map((dataset) => ({
    ...dataset,
    color: Array.isArray(dataset.color)
      ? dataset.color.map((value) => resolveColor(value))
      : resolveColor(dataset.color),
  })),
)
const legendItems = computed(() =>
  props.type === 'doughnut'
    ? props.labels.map((label, index) => ({
        label,
        color: Array.isArray(styledDatasets.value[0]?.color)
          ? styledDatasets.value[0]!.color[index]
          : styledDatasets.value[0]?.color,
        dashed: false,
      }))
    : styledDatasets.value.map((dataset) => ({
        label: dataset.label,
        color: Array.isArray(dataset.color) ? dataset.color[0] : dataset.color,
        dashed: dataset.dashed,
      })),
)

const chartData = computed(() => ({
  labels: props.labels,
  datasets: styledDatasets.value.map((dataset) => ({
    type: dataset.type ?? props.type,
    label: dataset.label,
    data: dataset.data,
    ...(props.type !== 'doughnut' && !props.horizontal
      ? { yAxisID: dataset.yAxisID ?? 'money' }
      : {}),
    borderColor: dataset.color,
    backgroundColor:
      props.type === 'doughnut'
        ? dataset.color
        : Array.isArray(dataset.color)
          ? dataset.color.map((value) => resolveColor(value, dataset.fill ? 0.13 : 0.09))
          : resolveColor(dataset.color, dataset.fill ? 0.13 : 0.09),
    borderDash: dataset.dashed ? [6, 5] : [],
    borderWidth: props.type === 'doughnut' ? 0 : 2,
    borderRadius: props.type === 'bar' || dataset.type === 'bar' ? 7 : 0,
    barThickness: props.type === 'line' && dataset.type === 'bar' ? 8 : undefined,
    fill: dataset.fill ?? false,
    pointRadius: props.labels.length > 14 ? 0 : 2.5,
    pointHoverRadius: 4,
    tension: 0.36,
  })),
}))

const chartOptions = computed<ChartConfiguration['options']>(() => {
  const isDoughnut = props.type === 'doughnut'
  const mutedColor = current.value.dark ? '#AAB1CB' : '#778195'
  const gridColor = current.value.dark ? 'rgba(230, 230, 241, .10)' : 'rgba(34, 48, 62, .08)'
  const onlyCounts =
    props.datasets.length > 0 && props.datasets.every((dataset) => dataset.yAxisID === 'count')
  const valueScale = {
    axis: props.horizontal ? ('x' as const) : ('y' as const),
    beginAtZero: true,
    border: { display: false },
    grid: { color: gridColor },
    ticks: {
      color: mutedColor,
      callback: (value: string | number) =>
        new Intl.NumberFormat(locale.value, {
          notation: 'compact',
          maximumFractionDigits: 1,
        }).format(Number(value)),
    },
  }
  const categoryScale = {
    border: { display: false },
    grid: { display: false },
    ticks: {
      color: mutedColor,
      maxRotation: 0,
      autoSkipPadding: 22,
      ...(props.horizontal
        ? {
            autoSkip: false,
            callback: (_value: string | number, index: number) => {
              const label = props.labels[index] ?? ''
              return label.length > 24 ? `${label.slice(0, 23)}…` : label
            },
          }
        : {}),
    },
  }

  return {
    responsive: true,
    maintainAspectRatio: false,
    indexAxis: props.horizontal ? 'y' : 'x',
    animation: { duration: 350 },
    interaction: { intersect: false, mode: 'index' },
    cutout: isDoughnut ? '72%' : undefined,
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: current.value.dark ? '#171725' : '#1F2D3D',
        bodyColor: '#F8FAFC',
        displayColors: true,
        padding: 10,
        titleColor: '#F8FAFC',
        rtl: props.rtl,
        textDirection: props.rtl ? 'rtl' : 'ltr',
        callbacks: { label: tooltipLabel },
      },
    },
    scales: isDoughnut
      ? undefined
      : props.horizontal
        ? { x: valueScale, y: categoryScale }
        : {
            x: { ...categoryScale, reverse: props.rtl },
            money: { ...valueScale, display: !onlyCounts },
            count: {
              ...valueScale,
              display: onlyCounts,
              position: onlyCounts ? 'left' : 'right',
            },
          },
  }
})

function renderChart() {
  if (!canvas.value) return

  chart?.destroy()
  chart = new ChartJs(canvas.value, {
    type: props.type,
    data: chartData.value,
    options: chartOptions.value,
  } as ChartConfiguration<ChartType>)
}

onMounted(renderChart)
onBeforeUnmount(() => chart?.destroy())
watch([chartData, chartOptions], renderChart, { deep: true })
</script>

<template>
  <div class="analytics-chart">
    <div
      v-if="showLegend"
      class="analytics-chart__legend"
      :class="{ 'analytics-chart__legend--distribution': type === 'doughnut' }"
    >
      <span
        v-for="(item, index) in legendItems"
        :key="index"
      >
        <i
          :class="{ dashed: item.dashed }"
          :style="{
            borderColor: item.color,
            backgroundColor: type === 'doughnut' ? item.color : undefined,
          }"
        />
        {{ item.label }}
      </span>
    </div>
    <div
      class="analytics-chart__canvas"
      :style="{ height: `${height}px` }"
    >
      <canvas ref="canvas" />
    </div>
  </div>
</template>

<style scoped>
.analytics-chart,
.analytics-chart__canvas {
  position: relative;
  min-inline-size: 0;
  inline-size: 100%;
}
.analytics-chart__legend {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 14px;
  margin-block-end: 6px;
  color: rgba(var(--v-theme-on-surface), 0.58);
  font-size: 0.72rem;
}
.analytics-chart__legend span {
  display: flex;
  min-inline-size: 0;
  align-items: center;
  gap: 6px;
  overflow-wrap: anywhere;
}
.analytics-chart__legend i {
  flex: 0 0 18px;
  border-block-start: 2px solid;
  inline-size: 18px;
}
.analytics-chart__legend i.dashed {
  border-block-start-style: dashed;
}
.analytics-chart__legend--distribution {
  justify-content: center;
}
.analytics-chart__legend--distribution i {
  flex-basis: 8px;
  border: 0;
  border-radius: 50%;
  block-size: 8px;
  inline-size: 8px;
}
</style>
