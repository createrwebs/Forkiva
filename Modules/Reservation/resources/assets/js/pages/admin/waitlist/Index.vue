<script lang="ts" setup>
import type { TableAction, TableHeader } from '@/modules/core/contracts/Table.ts'
import type { RouteLocationRaw } from 'vue-router'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import SmartDataTable from '@/modules/core/components/Table/SmartDataTable.vue'
import { useForm } from '@/modules/core/composables/form.ts'
import { useReservationWaitlist } from '@/modules/reservation/composables/reservationWaitlist.ts'
import ConvertDialog from './Dialogs/ConvertDialog.vue'

const { t } = useI18n()
const { user, can } = useAuth()
const router = useRouter()
const { notifyReservationWaitlist, cancelReservationWaitlist } = useReservationWaitlist()
const smartTableRef = ref<any>(null)
const showConvertDialog = ref(false)
const selectedWaitlist = ref<Record<string, any> | null>(null)
const actionForm = useForm({ id: null as number | null })

const headers: TableHeader[] = [
  {
    title: t('branch::branches.table.branch'),
    value: 'branch.name',
    sortable_key: 'branch_id',
    sortable: true,
    hidden: user?.assigned_to_branch,
  },
  {
    title: t('reservation::reservation_waitlists.table.customer_name'),
    value: 'customer_name',
    sortable: true,
  },
  {
    title: t('reservation::reservation_waitlists.table.customer_phone'),
    value: 'customer_phone',
  },
  {
    title: t('reservation::reservation_waitlists.table.preferred_date'),
    value: 'preferred_date',
    sortable: true,
  },
  {
    title: t('reservation::reservation_waitlists.table.preferred_start_time'),
    value: 'preferred_start_time',
    sortable: true,
  },
  {
    title: t('reservation::reservation_waitlists.table.preferred_end_time'),
    value: 'preferred_end_time',
    sortable: true,
  },
  {
    title: t('reservation::reservation_waitlists.table.guest_count'),
    value: 'guest_count',
    sortable: true,
  },
  {
    title: t('reservation::reservation_waitlists.table.status'),
    value: 'status',
    sortable_key: 'status',
    sortable: true,
  },
  { title: t('admin::admin.table.created_at'), value: 'created_at', sortable: true },
]

function openConvertDialog(item: Record<string, any>) {
  if (!can('admin.reservation_waitlists.convert_to_reservation')) {
    return
  }

  selectedWaitlist.value = item
  showConvertDialog.value = true
}

async function onClickNotify(item: Record<string, any>) {
  if (!can('admin.reservation_waitlists.notify')) {
    return
  }

  if (await actionForm.submit(() => notifyReservationWaitlist(item.id))) {
    smartTableRef.value?.refresh()
  }
}

async function onClickCancel(item: Record<string, any>) {
  if (!can('admin.reservation_waitlists.cancel')) {
    return
  }

  if (await actionForm.submit(() => cancelReservationWaitlist(item.id, item.internal_notes))) {
    smartTableRef.value?.refresh()
  }
}

const actions: TableAction[] = [
  { key: 'show' },
  {
    key: 'edit',
    hidden: (item) => item.status?.id === 'cancelled',
  },
  {
    key: 'notify',
    icon: 'tabler-bell',
    label: t('reservation::frontend.notify'),
    onClick: onClickNotify,
    hidden: (item) => !['waiting', 'notified'].includes(item.status?.id),
    confirm: {
      message: t('reservation::frontend.notify_confirmation_message'),
      confirmButtonText: t('admin::admin.buttons.confirm'),
    },
  },
  {
    key: 'convert_to_reservation',
    color: 'info',
    icon: 'tabler-calendar-plus',
    label: t('reservation::frontend.convert_to_reservation'),
    onClick: openConvertDialog,
    hidden: (item) => ['converted', 'cancelled', 'expired'].includes(item.status?.id),
  },
  {
    key: 'cancel',
    color: 'error',
    icon: 'tabler-x',
    label: t('reservation::frontend.cancel'),
    onClick: onClickCancel,
    hidden: (item) => ['converted', 'cancelled', 'expired'].includes(item.status?.id),
    confirm: {
      message: t('reservation::frontend.cancel_confirmation_message'),
      confirmButtonText: t('admin::admin.buttons.confirm'),
    },
  },
  {
    key: 'destroy',
    hidden: (item) => item.status?.id === 'cancelled',
    confirm: {
      message: t('admin::admin.delete.confirmation_message'),
      confirmButtonText: t('admin::admin.delete.confirm_button_text'),
    },
  },
]

function onConverted() {
  smartTableRef.value?.refresh()
  selectedWaitlist.value = null
}

function onToggleConvertDialog(value: boolean) {
  showConvertDialog.value = value
  if (!value) {
    selectedWaitlist.value = null
  }
}

function onRowClick(event: MouseEvent, value: any) {
  if (can('admin.reservation_waitlists.show')) {
    router.push({
      name: 'admin.reservation_waitlists.show',
      params: { id: value.item.id },
    } as unknown as RouteLocationRaw)
  }
}
</script>

<template>
  <SmartDataTable
    ref="smartTableRef"
    :actions="actions"
    api-uri="/v1/reservation-waitlists"
    :header-actions="[{ key: 'create' }]"
    :headers="headers"
    module="reservation"
    name="reservation_waitlist"
    :on-row-click="can('admin.reservation_waitlists.show') ? onRowClick : null"
    resource="reservation_waitlists"
  />

  <ConvertDialog
    v-if="
      can('admin.reservation_waitlists.convert_to_reservation') &&
      showConvertDialog &&
      selectedWaitlist
    "
    :item="selectedWaitlist"
    :model-value="showConvertDialog"
    @saved="onConverted"
    @update:model-value="onToggleConvertDialog"
  />
</template>
