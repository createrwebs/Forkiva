<script lang="ts" setup>
import type { TableAction, TableHeader } from '@/modules/core/contracts/Table.ts'
import { useI18n } from 'vue-i18n'
import { ref } from 'vue'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import ImportWizard from '@/modules/import/Partials/ImportWizard.vue'
import { useImportRefresh } from '@/modules/import/composables/useImportRefresh.ts'
import TableName from './Partials/TableName.vue'

const { t } = useI18n()
const { can } = useAuth()
const importOpen = ref(false)
const table = ref<{ refresh: () => void } | null>(null)

useImportRefresh('customers', () => table.value?.refresh())

const headers: TableHeader[] = [
  { title: t('admin::admin.table.id'), value: 'id', sortable: false },
  { title: t('user::customers.table.name'), value: 'name', sortable: true },
  {
    title: t('user::customers.table.phone'),
    value: 'phone',
    sortable_key: 'phone',
    sortable: true,
  },
  { title: t('admin::admin.table.status'), value: 'is_active', sortable: false },
  { title: t('admin::admin.table.created_at'), value: 'created_at', sortable: true },
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
  name: {
    component: TableName,
  },
}

const bulkActions: TableAction[] = [
  {
    key: 'destroy',
    confirm: {
      message: t('admin::admin.delete.confirmation_message'),
      confirmButtonText: t('admin::admin.delete.confirm_button_text'),
    },
  },
]

const headerActions: TableAction[] = [
  { key: 'create' },
  {
    key: 'import',
    label: t('import::imports.title'),
    icon: 'tabler-database-import',
    color: 'primary',
    permission: 'admin.customers.import',
    onClick: () => {
      importOpen.value = true
    },
  },
]
</script>

<template>
  <div>
    <SmartDataTable
      ref="table"
      :actions="actions"
      api-uri="/v1/customers"
      :bulk-actions="bulkActions"
      :cell-components="cellComponents"
      :header-actions="headerActions"
      :headers="headers"
      module="user"
      name="customer"
      resource="customers"
    />
    <ImportWizard
      v-if="can('admin.customers.import') && importOpen"
      v-model="importOpen"
      resource="customers"
    />
  </div>
</template>
