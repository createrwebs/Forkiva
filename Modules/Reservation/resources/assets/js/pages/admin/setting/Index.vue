<script lang="ts" setup>
import type { TableAction, TableHeader } from '@/modules/core/contracts/Table.ts'
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import TableBoolean from '@/modules/core/components/Table/Partials/TableBoolean.vue'
import TableMinutes from '@/modules/core/components/Table/Partials/TableMinutes.vue'
import SmartDataTable from '@/modules/core/components/Table/SmartDataTable.vue'

const { t } = useI18n()
const { user } = useAuth()

const headers: TableHeader[] = [
  {
    title: t('branch::branches.table.branch'),
    value: 'branch.name',
    sortable_key: 'branch_id',
    sortable: true,
    hidden: user?.assigned_to_branch,
  },
  {
    title: t('reservation::reservation_settings.table.reservations_enabled'),
    value: 'reservations_enabled',
    sortable: true,
  },
  {
    title: t('reservation::reservation_settings.table.default_duration_minutes'),
    value: 'default_duration_minutes',
    sortable: true,
  },
  { title: t('admin::admin.table.updated_at'), value: 'updated_at', sortable: true },
]

const actions: TableAction[] = [
  { key: 'edit' },
  {
    key: 'destroy',
    confirm: {
      message: t('admin::admin.delete.confirmation_message'),
      confirmButtonText: t('admin::admin.delete.confirm_button_text'),
    },
  },
]

const cellComponents: Record<string, unknown> = {
  reservations_enabled: { component: TableBoolean, props: { column: 'reservations_enabled' } },
  default_duration_minutes: {
    component: TableMinutes,
    props: { column: 'default_duration_minutes' },
  },
}
</script>

<template>
  <SmartDataTable
    :actions="actions"
    api-uri="/v1/reservation-settings"
    :cell-components="cellComponents"
    :header-actions="[{ key: 'create' }]"
    :headers="headers"
    module="reservation"
    name="reservation_setting"
    resource="reservation_settings"
  />
</template>
