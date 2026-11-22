<script lang="ts" setup>
import type { TableAction, TableHeader } from '@/modules/core/contracts/Table.ts'
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/modules/auth/composables/auth.ts'

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
    title: t('reservation::reservation_table_blocks.table.table'),
    value: 'table.name',
    sortable_key: 'table_id',
    sortable: true,
  },
  {
    title: t('reservation::reservation_table_blocks.table.title'),
    value: 'title',
    sortable: true,
  },
  {
    title: t('reservation::reservation_table_blocks.table.start_at'),
    value: 'start_at',
    sortable: true,
  },
  {
    title: t('reservation::reservation_table_blocks.table.end_at'),
    value: 'end_at',
    sortable: true,
  },
  {
    title: t('admin::admin.table.created_by'),
    value: 'created_by.name',
    sortable_key: 'created_by',
    sortable: true,
  },
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
</script>

<template>
  <SmartDataTable
    :actions="actions"
    api-uri="/v1/reservation-table-blocks"
    :header-actions="[{ key: 'create' }]"
    :headers="headers"
    module="reservation"
    name="reservation_table_block"
    resource="reservation_table_blocks"
  />
</template>
