<script lang="ts" setup>
import type { TableAction, TableHeader } from '@/modules/core/contracts/Table.ts'
import type { RouteLocationRaw } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import SmartDataTable from '@/modules/core/components/Table/SmartDataTable.vue'
import { useStockTransfer } from '@/modules/inventory/composables/stockTransfer.ts'

const { t } = useI18n()
const toast = useToast()
const router = useRouter()
const { can } = useAuth()
const { approveStockTransfer, dispatchStockTransfer, showActionError } = useStockTransfer()
const table = ref<{ refresh: () => void } | null>(null)

const headers: TableHeader[] = [
  {
    title: t('inventory::stock_transfers.table.reference_number'),
    value: 'reference_number',
    sortable: true,
  },
  {
    title: t('inventory::stock_transfers.table.source_branch'),
    value: 'source_branch.name',
    sortable_key: 'source_branch_id',
    sortable: true,
  },
  {
    title: t('inventory::stock_transfers.table.destination_branch'),
    value: 'destination_branch.name',
    sortable_key: 'destination_branch_id',
    sortable: true,
  },
  { title: t('inventory::stock_transfers.table.status'), value: 'status', sortable: true },
  {
    title: t('inventory::stock_transfers.table.expected_at'),
    value: 'expected_at',
    sortable: true,
  },
  { title: t('inventory::stock_transfers.table.items_count'), value: 'items_count' },
  { title: t('admin::admin.table.created_at'), value: 'created_at', sortable: true },
]

async function runAction(callback: () => Promise<any>): Promise<void> {
  try {
    const response = await callback()
    if (response?.data?.message) toast.success(response.data.message)
    table.value?.refresh()
  } catch (error: unknown) {
    showActionError(error)
  }
}

const actions: TableAction[] = [
  { key: 'show' },
  { key: 'edit', hidden: (item) => item.allow_edit !== true },
  {
    key: 'approve',
    icon: 'tabler-check',
    color: 'success',
    label: t('inventory::stock_transfers.actions.approve'),
    hidden: (item) => item.allow_approve !== true,
    confirm: {
      message: t('inventory::stock_transfers.confirm.approve'),
      confirmButtonText: t('inventory::stock_transfers.actions.approve'),
    },
    onClick: (item) => runAction(() => approveStockTransfer(item.id)),
  },
  {
    key: 'dispatch',
    icon: 'tabler-truck-delivery',
    color: 'primary',
    label: t('inventory::stock_transfers.actions.dispatch'),
    hidden: (item) => item.allow_dispatch !== true,
    confirm: {
      message: t('inventory::stock_transfers.confirm.dispatch'),
      confirmButtonText: t('inventory::stock_transfers.actions.dispatch'),
    },
    onClick: (item) => runAction(() => dispatchStockTransfer(item.id)),
  },
]

function onRowClick(_event: MouseEvent, value: any): void {
  if (!can('admin.stock_transfers.show')) return
  router.push({
    name: 'admin.stock_transfers.show',
    params: { id: value.item.id },
  } as unknown as RouteLocationRaw)
}
</script>

<template>
  <SmartDataTable
    ref="table"
    :actions="actions"
    api-uri="/v1/stock-transfers"
    :header-actions="[{ key: 'create' }]"
    :headers="headers"
    module="inventory"
    name="stock_transfer"
    :on-row-click="can('admin.stock_transfers.show') ? onRowClick : null"
    resource="stock_transfers"
  />
</template>
