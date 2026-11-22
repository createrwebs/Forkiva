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
    label: t('reservation::attributes.reservations.external_reference'),
    value: props.item.external_reference,
  },
  { label: t('admin::admin.table.created_by'), value: props.item.created_by?.name },
  { label: t('admin::admin.table.created_at'), value: props.item.created_at },
  { label: t('admin::admin.table.updated_at'), value: props.item.updated_at },
])
</script>

<template>
  <BaseInfoCard
    :columns="4"
    :fields="fields"
    icon="tabler-database"
    :item="item"
    :title="t('reservation::frontend.system_information')"
  />
</template>
