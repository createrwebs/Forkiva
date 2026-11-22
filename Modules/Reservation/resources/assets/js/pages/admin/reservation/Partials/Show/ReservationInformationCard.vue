<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseInfoCard, { type InfoCardField } from './BaseInfoCard.vue'

const props = defineProps<{
  item: Record<string, any>
}>()

const { t } = useI18n()

const fields = computed<InfoCardField[]>(() => [
  {
    label: t('reservation::reservations.table.reservation_number'),
    value: props.item.reservation_number,
  },
  {
    column: 'status',
    label: t('reservation::attributes.reservations.status'),
    type: 'tableStatus',
  },
  { label: t('branch::branches.table.branch'), value: props.item.branch?.name },
  {
    column: 'reservation_type',
    label: t('reservation::attributes.reservations.reservation_type'),
    type: 'tableEnum',
  },
  {
    column: 'table_assignment_status',
    label: t('reservation::attributes.reservations.table_assignment_status'),
    type: 'tableEnum',
  },
  {
    column: 'table_assignment_mode',
    label: t('reservation::attributes.reservations.table_assignment_mode'),
    type: 'tableEnum',
  },
])
</script>

<template>
  <BaseInfoCard
    :columns="4"
    :fields="fields"
    icon="tabler-calendar-event"
    :item="item"
    :title="t('reservation::frontend.reservation_information')"
  />
</template>
