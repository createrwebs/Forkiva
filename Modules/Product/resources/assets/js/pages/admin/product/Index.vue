<script lang="ts" setup>
import type { TableAction, TableHeader } from '@/modules/core/contracts/Table.ts'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { type RouteLocationRaw, useRoute, useRouter } from 'vue-router'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import ImportWizard from '@/modules/import/Partials/ImportWizard.vue'
import { useImportRefresh } from '@/modules/import/composables/useImportRefresh.ts'
import TableName from './Partials/TableName.vue'
import TablePrice from './Partials/TablePrice.vue'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { hasPermission } = useAuth()
const smartTableRef = ref<any>(null)
const menuId = ref((route.params as Record<string, unknown>)?.menuId)
const importMenuId = ref<number | null>(Number(menuId.value) || null)
const importOpen = ref(false)

useImportRefresh(
  'products',
  () => smartTableRef.value?.refresh(),
  () => ({ menu_id: importMenuId.value }),
)

function onProductsLoaded(body: Record<string, unknown>) {
  const defaults = body.default_filters as Record<string, unknown> | undefined
  importMenuId.value = Number(defaults?.menu_id) || null
}

const headerActions = computed<TableAction[]>(() => [
  { key: 'create', onClick: create },
  ...(hasPermission('admin.products.import') && importMenuId.value
    ? [
        {
          key: 'import',
          label: t('import::imports.title'),
          icon: 'tabler-database-import',
          color: 'primary',
          permission: 'admin.products.import',
          onClick: () => {
            importOpen.value = true
          },
        },
      ]
    : []),
])

async function edit(item: any) {
  await (menuId.value
    ? router.push({
        name: 'admin.menus.products.edit',
        params: { id: item.id, menuId: smartTableRef.value?.defaultFilters.menu_id },
      } as unknown as RouteLocationRaw)
    : router.push({
        name: 'admin.products.edit',
        params: { id: item.id },
      } as unknown as RouteLocationRaw))
}

async function create() {
  await (menuId.value
    ? router.push({
        name: 'admin.menus.products.create',
        params: { menuId: smartTableRef.value?.defaultFilters.menu_id },
      } as unknown as RouteLocationRaw)
    : router.push({ name: 'admin.products.create' } as unknown as RouteLocationRaw))
}

const headers: TableHeader[] = [
  { title: t('admin::admin.table.id'), value: 'id', sortable: false },
  { title: t('product::products.table.thumbnail'), value: 'thumbnail', sortable: false },
  { title: t('product::products.table.name'), value: 'name', sortable: true },
  { title: t('product::products.table.price'), value: 'price', sortable: true },
  { title: t('admin::admin.table.activation'), value: 'is_active', sortable: true },
  { title: t('admin::admin.table.created_at'), value: 'created_at', sortable: true },
  { title: t('admin::admin.table.updated_at'), value: 'updated_at', sortable: true },
]

const actions: TableAction[] = [
  { key: 'edit', onClick: edit },
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

const cellComponents: Record<string, unknown> = {
  name: {
    component: TableName,
  },
  price: {
    component: TablePrice,
  },
}
</script>

<template>
  <div>
    <SmartDataTable
      ref="smartTableRef"
      :actions="actions"
      :bulk-actions="bulkActions"
      :cell-components="cellComponents"
      :default-filters="{ menu_id: menuId }"
      :header-actions="headerActions"
      :headers="headers"
      :on-loaded="onProductsLoaded"
      api-uri="/v1/products"
      module="product"
      name="product"
      resource="products"
    />
    <ImportWizard
      v-if="hasPermission('admin.products.import') && importMenuId && importOpen"
      v-model="importOpen"
      :context="{ menu_id: importMenuId }"
      resource="products"
    />
  </div>
</template>
