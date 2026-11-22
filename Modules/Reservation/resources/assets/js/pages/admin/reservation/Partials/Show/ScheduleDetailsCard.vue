<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseInfoCard, { type InfoCardField } from './BaseInfoCard.vue'

const props = defineProps<{
  item: Record<string, unknown>
}>()

const { t } = useI18n()

function minuteValue(value: unknown) {
  return [null, undefined, ''].includes(value as any)
    ? '-'
    : t('reservation::frontend.minutes_value', { value })
}

const fields = computed<InfoCardField[]>(() => [
  {
    label: t('reservation::attributes.reservations.reservation_date'),
    value: props.item.reservation_date,
  },
  { label: t('reservation::attributes.reservations.start_time'), value: props.item.start_time },
  { label: t('reservation::attributes.reservations.end_time'), value: props.item.end_time },
  { label: t('reservation::attributes.reservations.start_at'), value: props.item.start_at },
  { label: t('reservation::attributes.reservations.end_at'), value: props.item.end_at },
  {
    label: t('reservation::attributes.reservations.duration_minutes'),
    value: minuteValue(props.item.duration_minutes),
  },
  { label: t('reservation::frontend.confirmed_at'), value: props.item.confirmed_at },
  { label: t('reservation::frontend.arrived_at'), value: props.item.arrived_at },
  { label: t('reservation::frontend.seated_at'), value: props.item.seated_at },
  { label: t('reservation::frontend.completed_at'), value: props.item.completed_at },
  { label: t('reservation::frontend.cancelled_at'), value: props.item.cancelled_at },
  { label: t('reservation::frontend.no_show_at'), value: props.item.no_show_at },
])
</script>

<template>
  <BaseInfoCard
    :columns="4"
    :fields="fields"
    icon="tabler-calendar-time"
    :item="item"
    :title="t('reservation::frontend.schedule_details')"
  />
</template>
