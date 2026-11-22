<script lang="ts" setup>
import { computed } from 'vue'
import type { DashboardRankingItem } from '@/modules/dashboard/contracts/dashboard.ts'
import DashboardChart from './DashboardChart.vue'
import DashboardEmpty from './DashboardEmpty.vue'

const props = defineProps<{
  items: DashboardRankingItem[]
  currency: string
}>()

const datasets = computed(() => [
  {
    label: '',
    data: props.items.map((item) => item.value?.amount ?? 0),
    color: '#043A63',
  },
])
</script>

<template>
  <DashboardEmpty v-if="items.length === 0" />
  <DashboardChart
    v-else
    :currency="currency"
    :datasets="datasets"
    :height="270"
    :labels="items.map((item) => item.label ?? '')"
    type="bar"
  />
</template>
