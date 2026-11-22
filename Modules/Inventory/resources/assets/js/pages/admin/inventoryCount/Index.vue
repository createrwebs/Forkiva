<script lang="ts" setup>
import type { TableAction, TableHeader } from '@/modules/core/contracts/Table.ts'
import type { RouteLocationRaw } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import SmartDataTable from '@/modules/core/components/Table/SmartDataTable.vue'
import { useInventoryCount } from '@/modules/inventory/composables/inventoryCount.ts'

const { t } = useI18n()
const toast = useToast()
const router = useRouter()
const { user, can } = useAuth()
const { approveInventoryCount, cancelInventoryCount } = useInventoryCount()
const table = ref<{ refresh: () => void } | null>(null)

const headers: TableHeader[] = [
  {
    title: t('inventory::inventory_counts.table.reference_number'),
    value: 'reference_number',
    sortable: true,
  },
  {
    title: t('branch::branches.table.branch'),
    value: 'branch.name',
    sortable_key: 'branch_id',
    sortable: true,
    hidden: user?.assigned_to_branch,
  },
  {
    title: t('inventory::inventory_counts.table.count_date'),
    value: 'count_date',
    sortable: true,
  },
  { title: t('inventory::inventory_counts.table.status'), value: 'status', sortable: true },
  {
    title: t('inventory::inventory_counts.table.items_count'),
    value: 'items_count',
    sortable: false,
  },
  { title: t('admin::admin.table.created_at'), value: 'created_at', sortable: true },
  { title: t('admin::admin.table.updated_at'), value: 'updated_at', sortable: true },
]

async function runAction(callback: () => Promise<any>): Promise<void> {
  const response = await callback()
  if (response?.data?.message) toast.success(response.data.message)
  table.value?.refresh()
}

const actions: TableAction[] = [
  { key: 'show' },
  { key: 'edit', hidden: (item) => item.allow_edit !== true },
  {
    key: 'approve',
    icon: 'tabler-check',
    color: 'success',
    label: t('inventory::inventory_counts.actions.approve'),
    hidden: (item) => item.allow_approve !== true,
    confirm: {
      message: t('inventory::inventory_counts.confirm.approve'),
      confirmButtonText: t('inventory::inventory_counts.actions.approve'),
    },
    onClick: (item) => runAction(() => approveInventoryCount(item.id)),
  },
  {
    key: 'cancel',
    icon: 'tabler-ban',
    color: 'error',
    label: t('inventory::inventory_counts.actions.cancel'),
    hidden: (item) => item.allow_cancel !== true,
    confirm: {
      message: t('inventory::inventory_counts.confirm.cancel'),
      confirmButtonText: t('inventory::inventory_counts.actions.cancel'),
    },
    onClick: (item) => runAction(() => cancelInventoryCount(item.id)),
  },
  {
    key: 'destroy',
    hidden: (item) => item.allow_edit !== true,
    confirm: {
      message: t('admin::admin.delete.confirmation_message'),
      confirmButtonText: t('admin::admin.delete.confirm_button_text'),
    },
  },
]

function onRowClick(_event: MouseEvent, value: any): void {
  if (!can('admin.inventory_counts.show')) return
  router.push({
    name: 'admin.inventory_counts.show',
    params: { id: value.item.id },
  } as unknown as RouteLocationRaw)
}
</script>

<template>
  <SmartDataTable
    ref="table"
    :actions="actions"
    api-uri="/v1/inventory-counts"
    :header-actions="[{ key: 'create' }]"
    :headers="headers"
    module="inventory"
    name="inventory_count"
    :on-row-click="can('admin.inventory_counts.show') ? onRowClick : null"
    resource="inventory_counts"
  />
</template>
