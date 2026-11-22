<script lang="ts" setup>
import { computed } from 'vue'
import { formatNumber } from '../types.ts'
import AnalyticsChart from './AnalyticsChart.vue'
import AnalyticsEmpty from './AnalyticsEmpty.vue'

const props = defineProps<{
  items: Array<{ id: string; label: string; value: number; color: string }>
}>()
const total = computed(() => props.items.reduce((sum, item) => sum + item.value, 0))
const datasets = computed(() => [
  {
    label: '',
    data: props.items.map((item) => item.value),
    color: props.items.map((item) => item.color),
  },
])
</script>

<template>
  <AnalyticsEmpty v-if="items.length === 0 || total === 0" />
  <div
    v-else
    class="analytics-distribution"
  >
    <div class="analytics-distribution__chart">
      <AnalyticsChart
        :datasets="datasets"
        :height="185"
        :labels="items.map((item) => item.label)"
        type="doughnut"
      />
      <div class="analytics-distribution__total">
        <strong>{{ formatNumber(total) }}</strong>
        <span>{{ $t('reservation::frontend.analytics.total') }}</span>
      </div>
    </div>
    <div class="analytics-distribution__legend">
      <div
        v-for="item in items"
        :key="item.id"
        class="analytics-distribution__row"
      >
        <span>
          <i :style="{ backgroundColor: item.color }" />
          <span class="text-truncate">{{ item.label }}</span>
        </span>
        <strong>{{ formatNumber(item.value) }}</strong>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.analytics-distribution {
  display: grid;
  grid-template-columns: minmax(140px, 0.9fr) minmax(145px, 1.1fr);
  align-items: center;
  gap: 16px;
}
.analytics-distribution__chart {
  position: relative;
}
.analytics-distribution__total {
  position: absolute;
  inset: 50% auto auto 50%;
  display: grid;
  justify-items: center;
  transform: translate(-50%, -50%);
}
.analytics-distribution__total strong {
  font-size: 1.25rem;
  font-weight: 800;
  line-height: 1.1;
}
.analytics-distribution__total span {
  color: rgba(var(--v-theme-on-surface), 0.52);
  font-size: 0.67rem;
}
.analytics-distribution__legend {
  display: grid;
  gap: 10px;
}
.analytics-distribution__row,
.analytics-distribution__row > span {
  display: flex;
  min-inline-size: 0;
  align-items: center;
}
.analytics-distribution__row {
  justify-content: space-between;
  gap: 9px;
  font-size: 0.74rem;
}
.analytics-distribution__row > span {
  gap: 7px;
}
.analytics-distribution__row i {
  flex: 0 0 8px;
  border-radius: 50%;
  block-size: 8px;
  inline-size: 8px;
}
@media (max-width: 500px) {
  .analytics-distribution {
    grid-template-columns: 1fr;
  }
}
</style>
