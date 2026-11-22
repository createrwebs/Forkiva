<script lang="ts" setup>
import { computed, inject, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { kitchenClockKey } from '@/modules/pos/contracts/kitchen'

const props = withDefaults(
  defineProps<{
    minutes?: number | null
    startedAt?: string | null
    expectedReadyAt?: string | null
    active?: boolean
    showEstimate?: boolean
  }>(),
  {
    minutes: 0,
    startedAt: null,
    expectedReadyAt: null,
    active: false,
    showEstimate: true,
  },
)

const { t } = useI18n()
const sharedClock = inject(kitchenClockKey, null)
const now = sharedClock ?? ref(Date.now())
let countdownInterval: number | undefined

const totalMinutes = computed(() => Math.max(0, Number(props.minutes) || 0))
const remainingSeconds = computed(() => {
  if (!props.active) return null

  if (props.expectedReadyAt) {
    const expectedReadyAt = Date.parse(props.expectedReadyAt)
    if (!Number.isNaN(expectedReadyAt)) {
      return Math.ceil((expectedReadyAt - now.value) / 1000)
    }
  }

  if (!props.startedAt || totalMinutes.value <= 0) return null

  const startedAt = Date.parse(props.startedAt)
  if (Number.isNaN(startedAt)) return null

  return Math.ceil((startedAt + totalMinutes.value * 60_000 - now.value) / 1000)
})
const isOverdue = computed(() => remainingSeconds.value !== null && remainingSeconds.value <= 0)
const isVisible = computed(
  () =>
    (totalMinutes.value > 0 || Boolean(props.expectedReadyAt)) &&
    (props.active || props.showEstimate),
)
const label = computed(() => {
  if (remainingSeconds.value === null) {
    return t('pos::pos_viewer.kitchen_prep_time.estimate', {
      minutes: totalMinutes.value,
    })
  }

  const totalSeconds = Math.abs(remainingSeconds.value)
  const hours = Math.floor(totalSeconds / 3600)
  const minutes = Math.floor((totalSeconds % 3600) / 60)
  const seconds = totalSeconds % 60
  const value = [hours, minutes, seconds]
    .filter((_, index) => hours > 0 || index > 0)
    .map((part) => String(part).padStart(2, '0'))
    .join(':')

  return isOverdue.value ? `+${value}` : value
})
const title = computed(() =>
  t(
    isOverdue.value
      ? 'pos::pos_viewer.kitchen_prep_time.overdue'
      : props.active
        ? 'pos::pos_viewer.kitchen_prep_time.remaining'
        : 'pos::pos_viewer.kitchen_prep_time.estimate_label',
  ),
)

onMounted(() => {
  if (sharedClock) return
  countdownInterval = window.setInterval(() => {
    now.value = Date.now()
  }, 1000)
})

onBeforeUnmount(() => {
  if (countdownInterval !== undefined) window.clearInterval(countdownInterval)
})
</script>

<template>
  <VChip
    v-if="isVisible"
    :color="isOverdue ? 'error' : active ? 'warning' : 'info'"
    data-testid="prep-time"
    :title="title"
    size="small"
    variant="tonal"
  >
    <VIcon
      icon="tabler-clock"
      start
    />
    {{ label }}
  </VChip>
</template>
