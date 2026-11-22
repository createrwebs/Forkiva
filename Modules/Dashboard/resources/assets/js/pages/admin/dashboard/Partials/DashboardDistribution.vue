<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { DashboardDistributionItem } from '@/modules/dashboard/contracts/dashboard.ts'
import DashboardChart from './DashboardChart.vue'
import DashboardEmpty from './DashboardEmpty.vue'

const props = defineProps<{
  items: DashboardDistributionItem[]
  totalLabel?: string
  totalValue?: number
  totalDisplay?: string
  secondary?: Array<{ label: string; value: string }>
}>()

const { t, locale } = useI18n()
const total = computed(
  () => props.totalValue ?? props.items.reduce((sum, item) => sum + item.value, 0),
)
const totalText = computed(
  () => props.totalDisplay ?? new Intl.NumberFormat(locale.value).format(total.value),
)
const totalSize = computed(() => {
  if (totalText.value.length > 15) return 'small'
  if (totalText.value.length > 9) return 'medium'

  return 'large'
})
const datasets = computed(() => [
  {
    label: '',
    data: props.items.map((item) => item.value),
    color: props.items.map((item) => item.color),
  },
])
</script>

<template>
  <DashboardEmpty v-if="items.length === 0 || total === 0" />
  <div
    v-else
    class="dashboard-distribution"
  >
    <div class="dashboard-distribution__chart">
      <DashboardChart
        :datasets="datasets"
        :height="190"
        :labels="items.map((item) => item.label)"
        type="doughnut"
      />
      <div
        class="dashboard-distribution__total"
        :class="`dashboard-distribution__total--${totalSize}`"
      >
        <strong>{{ totalText }}</strong>
        <span>{{ totalLabel ?? t('dashboard::dashboards.labels.total') }}</span>
      </div>
    </div>

    <div class="dashboard-distribution__legend">
      <div
        v-for="item in items"
        :key="item.id"
        class="dashboard-distribution__row"
      >
        <span class="dashboard-distribution__label">
          <i :style="{ backgroundColor: item.color }" />
          <span class="text-truncate">{{ item.label }}</span>
        </span>
        <strong>
          {{ item.formatted?.formatted ?? new Intl.NumberFormat(locale).format(item.value) }}
        </strong>
      </div>

      <VDivider
        v-if="secondary?.length"
        class="my-2"
      />
      <div
        v-for="item in secondary"
        :key="item.label"
        class="dashboard-distribution__row secondary"
      >
        <span>{{ item.label }}</span>
        <strong>{{ item.value }}</strong>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.dashboard-distribution {
  display: grid;
  grid-template-columns: minmax(150px, 0.9fr) minmax(160px, 1.1fr);
  align-items: center;
  gap: 18px;
}

.dashboard-distribution__chart {
  position: relative;
}

.dashboard-distribution__total {
  position: absolute;
  display: grid;
  max-inline-size: 68%;
  inset: 50% auto auto 50%;
  justify-items: center;
  text-align: center;
  transform: translate(-50%, -50%);
}

.dashboard-distribution__total strong {
  color: rgb(var(--v-theme-on-surface));
  font-size: 1.35rem;
  font-weight: 800;
  line-height: 1.15;
  white-space: nowrap;
}

.dashboard-distribution__total--medium strong {
  font-size: 1.05rem;
}

.dashboard-distribution__total--small strong {
  font-size: 0.85rem;
}

.dashboard-distribution__total span {
  color: rgba(var(--v-theme-on-surface), 0.52);
  font-size: 0.7rem;
}

.dashboard-distribution__legend {
  display: grid;
  gap: 11px;
}

.dashboard-distribution__row,
.dashboard-distribution__label {
  display: flex;
  min-inline-size: 0;
  align-items: center;
}

.dashboard-distribution__row {
  justify-content: space-between;
  gap: 10px;
  font-size: 0.76rem;
}

.dashboard-distribution__label {
  gap: 7px;
}

.dashboard-distribution__label i {
  flex: 0 0 8px;
  border-radius: 50%;
  block-size: 8px;
  inline-size: 8px;
}

.dashboard-distribution__row.secondary {
  color: rgba(var(--v-theme-on-surface), 0.6);
}

@media (max-width: 500px) {
  .dashboard-distribution {
    grid-template-columns: 1fr;
  }
}
</style>
