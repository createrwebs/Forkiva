<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseInfoCard, { type InfoCardField } from './BaseInfoCard.vue'

const props = defineProps<{
  item: Record<string, unknown>
}>()

const { t } = useI18n()

const fields = computed<InfoCardField[]>(() => [
  {
    label: t('reservation::attributes.reservations.customer_name'),
    value: props.item.customer_name,
  },
  {
    label: t('reservation::attributes.reservations.customer_phone'),
    value: props.item.customer_phone,
  },
  {
    label: t('reservation::attributes.reservations.customer_national_phone'),
    value: props.item.customer_national_phone,
  },
  {
    label: t('reservation::attributes.reservations.customer_email'),
    value: props.item.customer_email,
  },
  {
    label: t('reservation::attributes.reservations.guest_count'),
    value: props.item.guest_count,
  },
  {
    label: t('reservation::attributes.reservations.adult_count'),
    value: props.item.adult_count,
  },
  {
    label: t('reservation::attributes.reservations.child_count'),
    value: props.item.child_count,
  },
  { label: t('reservation::attributes.reservations.occasion'), value: props.item.occasion },
])
</script>

<template>
  <BaseInfoCard
    :columns="4"
    :fields="fields"
    icon="tabler-user"
    :item="item"
    :title="t('reservation::frontend.customer_information')"
  />
</template>
