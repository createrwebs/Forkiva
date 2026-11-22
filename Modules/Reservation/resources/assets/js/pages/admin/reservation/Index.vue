<script lang="ts" setup>
import type { TableAction, TableHeader } from '@/modules/core/contracts/Table.ts'
import type { RouteLocationRaw } from 'vue-router'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import TableEnum from '@/modules/core/components/Table/Partials/TableEnum.vue'
import TableStatus from '@/modules/core/components/Table/Partials/TableStatus.vue'
import SmartDataTable from '@/modules/core/components/Table/SmartDataTable.vue'
import {
  canApplyReservationDeposit,
  canCollectReservationDeposit,
  canForfeitReservationDeposit,
  canRefundReservationDeposit,
  type ReservationDepositAction,
} from '@/modules/reservation/utils/depositActions.ts'
import DepositActionDialog from './Dialogs/DepositActionDialog.vue'

const { t } = useI18n()
const router = useRouter()
const { user, can } = useAuth()
const table = ref<InstanceType<typeof SmartDataTable> | null>(null)
const showDepositDialog = ref(false)
const selectedReservation = ref<Record<string, unknown> | null>(null)
const depositAction = ref<ReservationDepositAction>('collection')

const headers: TableHeader[] = [
  {
    title: t('reservation::reservations.table.reservation_number'),
    value: 'reservation_number',
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
    title: t('reservation::reservations.table.customer_name'),
    value: 'customer_name',
    sortable: true,
  },
  {
    title: t('reservation::reservations.table.customer_phone'),
    value: 'customer_phone',
    sortable: true,
  },
  {
    title: t('reservation::reservations.table.reservation_date'),
    value: 'reservation_date',
    sortable: true,
  },
  { title: t('reservation::reservations.table.start_time'), value: 'start_time', sortable: true },
  {
    title: t('reservation::reservations.table.guest_count'),
    value: 'guest_count',
    sortable: true,
  },
  {
    title: t('reservation::reservations.table.status'),
    value: 'status',
    sortable_key: 'status',
    sortable: true,
  },
  {
    title: t('reservation::reservations.table.table_assignment_status'),
    value: 'table_assignment_status',
    sortable_key: 'table_assignment_status',
    sortable: true,
  },
  { title: t('admin::admin.table.created_at'), value: 'created_at', sortable: true },
]

const actions: TableAction[] = [
  { key: 'show' },
  {
    key: 'edit',
    hidden: (item) => item.abilities?.can_edit !== true,
  },
  {
    key: 'collect_deposit',
    label: t('reservation::frontend.deposit_actions.collection'),
    icon: 'tabler-cash',
    color: 'primary',
    permission: 'admin.reservation_deposit_transactions.create',
    hidden: (item) => !canCollectDeposit(item),
    onClick: (item) => openDepositAction('collection', item),
  },
  {
    key: 'apply_deposit',
    label: t('reservation::frontend.deposit_actions.application'),
    icon: 'tabler-file-invoice',
    color: 'info',
    permission: 'admin.reservation_deposit_transactions.create',
    hidden: (item) => !canApplyReservationDeposit(item),
    onClick: (item) => openDepositAction('application', item),
  },
  {
    key: 'refund_deposit',
    label: t('reservation::frontend.deposit_actions.refund'),
    icon: 'tabler-cash-banknote',
    color: 'success',
    permission: 'admin.reservation_deposit_transactions.create',
    hidden: (item) => !canRefundReservationDeposit(item),
    onClick: (item) => openDepositAction('refund', item),
  },
  {
    key: 'forfeit_deposit',
    label: t('reservation::frontend.deposit_actions.forfeiture'),
    icon: 'tabler-lock',
    color: 'warning',
    permission: 'admin.reservation_deposit_transactions.create',
    hidden: (item) => !canForfeitReservationDeposit(item),
    onClick: (item) => openDepositAction('forfeiture', item),
  },
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
    key: 'calendar',
    label: t('reservation::sidebar.reservation_calendar'),
    icon: 'tabler-calendar',
    color: 'info',
    permission: 'admin.reservations.index',
    onClick: async () => {
      await router.push({ name: 'admin.reservations.calendar' })
    },
  },
]

const cellComponents: Record<string, unknown> = {
  status: {
    component: TableStatus,
  },
  table_assignment_status: {
    component: TableEnum,
    props: { column: 'table_assignment_status' },
  },
}

function onRowClick(_event: MouseEvent, value: any) {
  if (can('admin.reservations.show')) {
    router.push({
      name: 'admin.reservations.show',
      params: { id: value.item.id },
    } as unknown as RouteLocationRaw)
  }
}

function canCollectDeposit(reservation: Record<string, unknown>): boolean {
  return canCollectReservationDeposit(reservation)
}

function openDepositAction(action: ReservationDepositAction, reservation: Record<string, unknown>) {
  depositAction.value = action
  selectedReservation.value = reservation
  showDepositDialog.value = true
}

function onDepositSaved() {
  showDepositDialog.value = false
  table.value?.refresh()
}
</script>

<template>
  <SmartDataTable
    ref="table"
    :actions="actions"
    api-uri="/v1/reservations"
    :bulk-actions="bulkActions"
    :cell-components="cellComponents"
    :header-actions="headerActions"
    :headers="headers"
    module="reservation"
    name="reservation"
    :on-row-click="can('admin.reservations.show') ? onRowClick : null"
    resource="reservations"
  />

  <DepositActionDialog
    v-if="selectedReservation && showDepositDialog"
    v-model="showDepositDialog"
    :action="depositAction"
    :reservation="selectedReservation"
    @saved="onDepositSaved"
  />
</template>
