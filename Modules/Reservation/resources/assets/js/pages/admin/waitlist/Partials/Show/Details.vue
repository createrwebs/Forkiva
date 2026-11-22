<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BlockInfo from '@/modules/core/components/BlockInfo.vue'
import TableStatus from '@/modules/core/components/Table/Partials/TableStatus.vue'

const props = defineProps<{
  item: Record<string, any>
}>()

const { t } = useI18n()

type DetailField = {
  cols?: number
  label: string
  type?: 'status'
  value?: unknown
}

type DetailSection = {
  columns: number
  fields: DetailField[]
  icon: string
  title: string
}

function displayValue(value: unknown) {
  return [null, undefined, ''].includes(value as any) ? '-' : value
}

const detailSections = computed<DetailSection[]>(() => [
  {
    title: t('reservation::frontend.waitlist_information'),
    icon: 'tabler-user',
    columns: 6,
    fields: [
      {
        label: t('reservation::attributes.reservation_waitlists.customer_name'),
        value: props.item.customer_name,
      },
      { label: t('reservation::reservation_waitlists.table.status'), type: 'status' },
      { label: t('branch::branches.table.branch'), value: props.item.branch?.name },
      { label: t('reservation::frontend.linked_customer'), value: props.item.customer?.name },
      {
        label: t('reservation::attributes.reservation_waitlists.customer_phone'),
        value: props.item.customer_phone,
      },
      {
        label: t('reservation::attributes.reservation_waitlists.customer_national_phone'),
        value: props.item.customer_national_phone,
      },
      {
        label: t('reservation::attributes.reservation_waitlists.customer_email'),
        value: props.item.customer_email,
      },
    ],
  },
  {
    title: t('reservation::frontend.waitlist_preferences'),
    icon: 'tabler-calendar-time',
    columns: 6,
    fields: [
      {
        label: t('reservation::attributes.reservation_waitlists.preferred_date'),
        value: props.item.preferred_date,
      },
      {
        label: t('reservation::attributes.reservation_waitlists.preferred_start_time'),
        value: props.item.preferred_start_time,
      },
      {
        label: t('reservation::attributes.reservation_waitlists.preferred_end_time'),
        value: props.item.preferred_end_time,
      },
      {
        label: t('reservation::attributes.reservation_waitlists.guest_count'),
        value: props.item.guest_count,
      },
      {
        label: t('reservation::attributes.reservation_waitlists.estimated_wait_minutes'),
        value: props.item.estimated_wait_minutes,
      },
      {
        label: t('reservation::attributes.reservation_waitlists.occasion'),
        value: props.item.occasion,
      },
      {
        label: t('reservation::attributes.reservation_waitlists.special_requests'),
        value: props.item.special_requests,
        cols: 12,
      },
      {
        label: t('reservation::attributes.reservation_waitlists.internal_notes'),
        value: props.item.internal_notes,
        cols: 12,
      },
    ],
  },
  {
    title: t('reservation::frontend.waitlist_actions'),
    icon: 'tabler-history',
    columns: 6,
    fields: [
      {
        label: t('reservation::attributes.reservation_waitlists.notified_at'),
        value: props.item.notified_at,
      },
      {
        label: t('reservation::attributes.reservation_waitlists.converted_at'),
        value: props.item.converted_at,
      },
      {
        label: t('reservation::attributes.reservation_waitlists.cancelled_at'),
        value: props.item.cancelled_at,
      },
      {
        label: t('reservation::attributes.reservation_waitlists.expires_at'),
        value: props.item.expires_at,
      },
      {
        label: t('reservation::attributes.reservation_waitlists.expired_at'),
        value: props.item.expired_at,
      },
      {
        label: t('reservation::frontend.linked_reservation'),
        value: props.item.reservation?.number,
      },
    ],
  },
  {
    title: t('reservation::frontend.system_information'),
    icon: 'tabler-database',
    columns: 4,
    fields: [
      { label: t('admin::admin.table.created_by'), value: props.item.created_by?.name },
      { label: t('admin::admin.table.created_at'), value: props.item.created_at },
      { label: t('admin::admin.table.updated_at'), value: props.item.updated_at },
    ],
  },
])
</script>

<template>
  <VCol
    v-for="section in detailSections"
    :key="section.title"
    cols="12"
    md="6"
  >
    <VCard>
      <VCardTitle class="d-flex align-center">
        <VIcon
          class="me-2"
          :icon="section.icon"
          size="18"
        />
        <span>{{ section.title }}</span>
      </VCardTitle>

      <VCardText>
        <VRow>
          <VCol
            v-for="field in section.fields"
            :key="`${section.title}-${field.label}`"
            cols="12"
            :md="field.cols || section.columns"
          >
            <BlockInfo :title="field.label">
              <TableStatus
                v-if="field.type === 'status'"
                :item="item"
              />

              <template v-else>
                {{ displayValue(field.value) }}
              </template>
            </BlockInfo>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>
  </VCol>
</template>
