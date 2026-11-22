<script lang="ts" setup>
import type { TableAction, TableHeader } from '@/modules/core/contracts/Table.ts'
import { useI18n } from 'vue-i18n'
import { ref } from 'vue'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import ImportWizard from '@/modules/import/Partials/ImportWizard.vue'
import { useImportRefresh } from '@/modules/import/composables/useImportRefresh.ts'
import TableName from './Partials/TableName.vue'

const { t } = useI18n()
const { user, can } = useAuth()
const importOpen = ref(false)
const table = ref<{ refresh: () => void } | null>(null)

useImportRefresh('users', () => table.value?.refresh())

const headers: TableHeader[] = [
  { title: t('admin::admin.table.id'), value: 'id', sortable: false },
  { title: t('user::users.table.name'), value: 'name', sortable: true },
  {
    title: t('branch::branches.table.branch'),
    value: 'branch.name',
    sortable_key: 'branch_id',
    sortable: true,
    hidden: user?.assigned_to_branch,
  },
  { title: t('user::users.table.email'), value: 'email', sortable: true },
  { title: t('admin::admin.table.status'), value: 'is_active', sortable: false },
  { title: t('admin::admin.table.created_at'), value: 'created_at', sortable: true },
  { title: t('admin::admin.table.updated_at'), value: 'updated_at', sortable: true },
]

const actions: TableAction[] = [
  { key: 'edit' },
  {
    key: 'destroy',
    hidden: (item) => item.is_main_user,
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
    permission: 'admin.users.import',
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
      :bulk-actions="bulkActions"
      :cell-components="cellComponents"
      :header-actions="headerActions"
      :headers="headers"
      api-uri="/v1/users"
      module="user"
      name="user"
      resource="users"
    />
    <ImportWizard
      v-if="can('admin.users.import') && importOpen"
      v-model="importOpen"
      resource="users"
    />
  </div>
</template>
