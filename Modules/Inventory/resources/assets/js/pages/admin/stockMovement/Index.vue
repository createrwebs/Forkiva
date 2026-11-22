<script lang="ts" setup>
import type { TableAction, TableHeader } from '@/modules/core/contracts/Table.ts'
import type { RouteLocationRaw } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import TableEnum from '@/modules/core/components/Table/Partials/TableEnum.vue'
import TableMoney from '@/modules/core/components/Table/Partials/TableMoney.vue'
import SmartDataTable from '@/modules/core/components/Table/SmartDataTable.vue'
import CorrectionDialog from './Partials/CorrectionDialog.vue'

const { t } = useI18n()
const { user, can } = useAuth()
const router = useRouter()
const table = ref<any>(null)
const correctionDialog = ref(false)
const selectedMovement = ref<Record<string, unknown> | null>(null)

const headers: TableHeader[] = [
  {
    title: t('branch::branches.table.branch'),
    value: 'branch.name',
    sortable_key: 'branch_id',
    sortable: true,
    hidden: user?.assigned_to_branch,
  },
  {
    title: t('inventory::stock_movements.table.ingredient_name'),
    value: 'ingredient.name',
    sortable_key: 'ingredient_id',
    sortable: true,
  },
  {
    title: t('inventory::stock_movements.table.type'),
    value: 'type',
    sortable_key: 'type',
    sortable: true,
  },
  {
    title: t('inventory::stock_movements.table.quantity'),
    value: 'display_quantity',
    sortable_key: 'quantity',
    sortable: true,
  },
  { title: t('inventory::stock_movements.table.unit_cost'), value: 'unit_cost', sortable: true },
  {
    title: t('inventory::stock_movements.table.total_cost'),
    value: 'total_cost',
    sortable: true,
  },
  {
    title: t('inventory::stock_movements.table.source_name'),
    value: 'source.name',
    sortable: false,
  },
  { title: t('admin::admin.table.created_at'), value: 'created_at', sortable: true },
]

function showMovement(item: Record<string, unknown>): void {
  if (!can('admin.stock_movements.show')) return
  router.push({
    name: 'admin.stock_movements.show',
    params: { id: item.id },
  } as unknown as RouteLocationRaw)
}

function handleRowClick(_event: Event, value: { item: Record<string, unknown> }): void {
  showMovement(value.item)
}

function openCorrection(item: Record<string, unknown>): void {
  selectedMovement.value = item
  correctionDialog.value = true
}

const actions: TableAction[] = [
  { key: 'show' },
  {
    key: 'correct',
    label: t('inventory::stock_movements.actions.correct'),
    icon: 'tabler-replace',
    color: 'warning',
    permission: 'admin.stock_movements.correct',
    onClick: openCorrection,
  },
]

const cellComponents: Record<string, unknown> = {
  type: { component: TableEnum, props: { column: 'type' } },
  unit_cost: { component: TableMoney, props: { column: 'unit_cost' } },
  total_cost: { component: TableMoney, props: { column: 'total_cost' } },
}

function handleCorrected(): void {
  correctionDialog.value = false
  selectedMovement.value = null
  table.value?.refresh()
}
</script>

<template>
  <SmartDataTable
    ref="table"
    :actions="actions"
    api-uri="/v1/stock-movements"
    :cell-components="cellComponents"
    :header-actions="[{ key: 'create' }]"
    :headers="headers"
    module="inventory"
    name="stock_movement"
    :on-row-click="can('admin.stock_movements.show') ? handleRowClick : null"
    resource="stock_movements"
  />

  <CorrectionDialog
    v-if="selectedMovement && can('admin.stock_movements.correct')"
    v-model="correctionDialog"
    :movement="selectedMovement"
    @corrected="handleCorrected"
  />
</template>
