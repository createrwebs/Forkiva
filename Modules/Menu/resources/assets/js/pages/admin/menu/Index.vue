<script lang="ts" setup>
import { type RouteLocationRaw, useRouter } from 'vue-router'
import type { TableAction, TableHeader } from '@/modules/core/contracts/Table.ts'
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import { ref } from 'vue'
import ImportWizard from '@/modules/import/Partials/ImportWizard.vue'
import { useImportRefresh } from '@/modules/import/composables/useImportRefresh.ts'

const { t } = useI18n()
const { user, can } = useAuth()
const importOpen = ref(false)
const menuTable = ref<{ refresh: () => void } | null>(null)
const router = useRouter()

function refreshMenus() {
  menuTable.value?.refresh()
}

useImportRefresh('menus', refreshMenus)

const headers: TableHeader[] = [
  { title: t('admin::admin.table.id'), value: 'id', sortable: false },
  { title: t('menu::menus.table.name'), value: 'name', sortable: true },
  {
    title: t('branch::branches.table.branch'),
    value: 'branch.name',
    sortable_key: 'branch_id',
    sortable: true,
    hidden: user?.assigned_to_branch,
  },
  { title: t('admin::admin.table.activation'), value: 'is_active', sortable: true },
  { title: t('admin::admin.table.created_at'), value: 'created_at', sortable: true },
  { title: t('admin::admin.table.updated_at'), value: 'updated_at', sortable: true },
]

const actions: TableAction[] = [
  {
    key: 'categories',
    icon: 'tabler-folders',
    permission: 'admin.categories.index',
    color: 'info',
    label: t('category::categories.categories'),
    onClick: (item) => {
      router.push({
        name: 'admin.menus.categories',
        params: { id: item.id },
      } as unknown as RouteLocationRaw)
    },
  },
  {
    key: 'products',
    icon: 'tabler-package',
    permission: 'admin.products.index',
    color: 'secondary',
    label: t('product::products.products'),
    onClick: (item) => {
      router.push({
        name: 'admin.menus.products.index',
        params: { menuId: item.id },
      } as unknown as RouteLocationRaw)
    },
  },
  { key: 'edit' },
  {
    key: 'destroy',
    confirm: {
      message: t('admin::admin.delete.confirmation_message'),
      confirmButtonText: t('admin::admin.delete.confirm_button_text'),
    },
  },
]

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
    permission: 'admin.menus.import',
    onClick: () => {
      importOpen.value = true
    },
  },
]
</script>

<template>
  <div>
    <SmartDataTable
      ref="menuTable"
      :actions="actions"
      :bulk-actions="bulkActions"
      :header-actions="headerActions"
      :headers="headers"
      api-uri="/v1/menus"
      module="menu"
      name="menu"
      resource="menus"
    />
    <ImportWizard
      v-if="can('admin.menus.import') && importOpen"
      v-model="importOpen"
      resource="menus"
    />
  </div>
</template>
