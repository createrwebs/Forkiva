<script lang="ts" setup>
import { computed, onBeforeMount, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import PageStateWrapper from '@/modules/core/components/PageStateWrapper.vue'
import { useForm } from '@/modules/core/composables/form.ts'
import { useReservation } from '@/modules/reservation/composables/reservation.ts'
import CustomerInformationCard from '@/modules/reservation/pages/admin/reservation/Partials/Show/CustomerInformationCard.vue'
import NotesCard from '@/modules/reservation/pages/admin/reservation/Partials/Show/NotesCard.vue'
import PaymentDetailsCard from '@/modules/reservation/pages/admin/reservation/Partials/Show/PaymentDetailsCard.vue'
import ReservationInformationCard from '@/modules/reservation/pages/admin/reservation/Partials/Show/ReservationInformationCard.vue'
import ScheduleDetailsCard from '@/modules/reservation/pages/admin/reservation/Partials/Show/ScheduleDetailsCard.vue'
import SystemInformationCard from '@/modules/reservation/pages/admin/reservation/Partials/Show/SystemInformationCard.vue'
import DepositActionDialog from './Dialogs/DepositActionDialog.vue'
import StatusDialog from './Dialogs/StatusDialog.vue'
import Actions from './Partials/Show/Actions.vue'
import DepositLedgerCard from './Partials/Show/DepositLedgerCard.vue'
import StatusHistory from './Partials/Show/StatusHistory.vue'
import TableAssignment from './Partials/Show/TableAssignment.vue'

type ActiveAction = 'status' | 'assign' | 'auto_assign' | null
type DepositAction = 'collection' | 'application' | 'refund' | 'forfeiture' | 'reverse'

const route = useRoute()
const { can } = useAuth()
const {
  getShowData,
  getReservationFormMeta,
  changeReservationStatus,
  assignReservationTables,
  autoAssignReservationTables,
} = useReservation()

const loading = ref(false)
const isNotFound = ref(false)
const item = ref<Record<string, any> | null>(null)
const meta = ref<Record<string, any>>({ tables: [], statuses: [], table_assignment_modes: [] })
const activeAction = ref<ActiveAction>(null)
const depositAction = ref<DepositAction>('collection')
const selectedDepositTransaction = ref<Record<string, any> | null>(null)
const showDepositDialog = ref(false)
const showStatusDialog = ref(false)

const statusForm = useForm({ status: '', reason: '', note: '' })
const assignmentForm = useForm({ table_ids: [] as number[] })

const statusPermissions: Record<string, string> = {
  confirmed: 'admin.reservations.confirm',
  rejected: 'admin.reservations.reject',
  cancelled: 'admin.reservations.cancel',
  arrived: 'admin.reservations.mark_arrived',
  seated: 'admin.reservations.seat',
  completed: 'admin.reservations.complete',
  no_show: 'admin.reservations.mark_no_show',
}

const hasActiveAction = computed(() => activeAction.value !== null)
const canEditCurrentStatus = computed(() => item.value?.abilities?.can_edit === true)
const canAssignCurrentStatus = computed(() => item.value?.abilities?.can_assign_tables === true)
const canChangeCurrentStatus = computed(() => item.value?.abilities?.can_change_status === true)
const allowedStatusIds = computed(() => item.value?.abilities?.allowed_statuses || [])
const canAssignTables = computed(
  () => canAssignCurrentStatus.value && can('admin.reservations.assign_table'),
)
const canAutoAssignTables = computed(
  () => canAssignCurrentStatus.value && can('admin.reservations.auto_assign_table'),
)
const canShowEdit = computed(() => canEditCurrentStatus.value && can('admin.reservations.edit'))
const canShowDeposit = computed(() => can('admin.reservation_deposit_transactions.show'))
const canCreateDeposit = computed(() => can('admin.reservation_deposit_transactions.create'))
const canEditDeposit = computed(() => can('admin.reservation_deposit_transactions.edit'))
const canUseDepositLedger = computed(() => {
  return (
    canShowDeposit.value ||
    canCreateDeposit.value ||
    canEditDeposit.value ||
    Boolean(item.value?.deposit_transactions?.length)
  )
})
const canChangeAnyStatus = computed(
  () =>
    canChangeCurrentStatus.value &&
    allowedStatusIds.value.some((status: string) => canChangeToStatus(status)),
)
const shouldLoadFormMeta = computed(() => {
  return canChangeAnyStatus.value || canAssignTables.value || canAutoAssignTables.value
})
const availableStatuses = computed(() => {
  const currentStatus = item.value?.status?.id

  return (meta.value.statuses || []).filter((status: Record<string, any>) => {
    return (
      status.id !== currentStatus &&
      allowedStatusIds.value.includes(status.id) &&
      canChangeToStatus(status.id)
    )
  })
})
const hasStatusActions = computed(() => availableStatuses.value.length > 0)

function canChangeToStatus(status: string): boolean {
  return Boolean(statusPermissions[status] && can(statusPermissions[status]))
}

function syncFormsFromItem(reservation: Record<string, any>) {
  statusForm.state.status = ''
  statusForm.state.reason = ''
  statusForm.state.note = ''
  assignmentForm.state.table_ids = (reservation.tables || [])
    .map((assignment: Record<string, any>) => assignment.table?.id)
    .filter(Boolean)
}

async function loadFormMeta(reservation: Record<string, any>) {
  if (!shouldLoadFormMeta.value) {
    meta.value = { tables: [], statuses: [], table_assignment_modes: [] }
    return
  }

  const baseMetaResponse = await getReservationFormMeta()
  const branchMetaResponse = reservation.branch?.id
    ? await getReservationFormMeta(reservation.branch.id)
    : null

  meta.value = {
    ...baseMetaResponse.data.body,
    ...branchMetaResponse?.data.body,
  }
}

async function loadData(withLoader = true) {
  if (withLoader) {
    loading.value = true
  }

  try {
    const response = await getShowData((route.params as Record<string, any>).id)

    if (response.status === 200) {
      item.value = response.data
      isNotFound.value = false
      syncFormsFromItem(response.data)
      await loadFormMeta(response.data)
    } else if (response.status === 404) {
      item.value = null
      isNotFound.value = true
    }
  } finally {
    if (withLoader) {
      loading.value = false
    }
  }
}

function openDepositAction(action: Exclude<DepositAction, 'reverse'>) {
  selectedDepositTransaction.value = null
  depositAction.value = action
  showDepositDialog.value = true
}

function openDepositReversal(transaction: Record<string, any>) {
  selectedDepositTransaction.value = transaction
  depositAction.value = 'reverse'
  showDepositDialog.value = true
}

function openStatusDialog() {
  statusForm.state.status = ''
  statusForm.state.reason = ''
  statusForm.state.note = ''
  for (const key of Object.keys(statusForm.errors.value)) {
    statusForm.errors.value[key] = ''
  }
  showStatusDialog.value = true
}

function onToggleStatusDialog(value: boolean) {
  if (activeAction.value === 'status') {
    return
  }

  showStatusDialog.value = value
}

async function submitStatus() {
  if (
    !statusForm.state.status ||
    !allowedStatusIds.value.includes(statusForm.state.status) ||
    !canChangeToStatus(statusForm.state.status)
  ) {
    return
  }

  activeAction.value = 'status'

  try {
    if (await statusForm.submit(() => changeReservationStatus(item.value?.id, statusForm.state))) {
      showStatusDialog.value = false
      await loadData(false)
    }
  } finally {
    activeAction.value = null
  }
}

async function submitAssignment() {
  if (!canAssignTables.value) {
    return
  }

  activeAction.value = 'assign'

  try {
    if (
      await assignmentForm.submit(() =>
        assignReservationTables(item.value?.id, assignmentForm.state.table_ids),
      )
    ) {
      await loadData(false)
    }
  } finally {
    activeAction.value = null
  }
}

async function submitAutoAssign() {
  if (!canAutoAssignTables.value) {
    return
  }

  activeAction.value = 'auto_assign'

  try {
    if (await assignmentForm.submit(() => autoAssignReservationTables(item.value?.id))) {
      await loadData(false)
    }
  } finally {
    activeAction.value = null
  }
}

onBeforeMount(loadData)
</script>

<template>
  <PageStateWrapper
    :loading="loading"
    :not-found="isNotFound"
  >
    <VRow v-if="item">
      <Actions
        :active-action="activeAction"
        :can-show-edit="canShowEdit"
        :has-active-action="hasActiveAction"
        :has-status-actions="hasStatusActions"
        :item="item"
        @change-status="openStatusDialog"
      />

      <VCol
        cols="12"
        md="6"
      >
        <VRow>
          <VCol cols="12">
            <ReservationInformationCard :item="item" />
          </VCol>

          <VCol cols="12">
            <CustomerInformationCard :item="item" />
          </VCol>

          <VCol cols="12">
            <SystemInformationCard :item="item" />
          </VCol>

          <VCol cols="12">
            <TableAssignment
              v-if="canAssignTables || canAutoAssignTables || item.tables?.length"
              :active-action="activeAction"
              :can-assign-tables="canAssignTables"
              :can-auto-assign-tables="canAutoAssignTables"
              :form="assignmentForm"
              :has-active-action="hasActiveAction"
              :item="item"
              :meta="meta"
              @assign="submitAssignment"
              @auto-assign="submitAutoAssign"
            />
          </VCol>
        </VRow>
      </VCol>

      <VCol
        cols="12"
        md="6"
      >
        <VRow>
          <VCol cols="12">
            <ScheduleDetailsCard :item="item" />
          </VCol>

          <VCol cols="12">
            <PaymentDetailsCard :item="item" />
          </VCol>

          <VCol
            v-if="canUseDepositLedger"
            cols="12"
          >
            <DepositLedgerCard
              :can-create="canCreateDeposit"
              :can-edit="canEditDeposit"
              :can-show="canShowDeposit"
              :item="item"
              @action="openDepositAction"
              @reverse="openDepositReversal"
            />
          </VCol>

          <VCol cols="12">
            <NotesCard :item="item" />
          </VCol>

          <VCol cols="12">
            <StatusHistory :item="item" />
          </VCol>
        </VRow>
      </VCol>
    </VRow>

    <StatusDialog
      v-if="item && showStatusDialog"
      :form="statusForm"
      :loading="activeAction === 'status'"
      :model-value="showStatusDialog"
      :statuses="availableStatuses"
      @submit="submitStatus"
      @update:model-value="onToggleStatusDialog"
    />

    <DepositActionDialog
      v-if="item && showDepositDialog"
      v-model="showDepositDialog"
      :action="depositAction"
      :reservation="item"
      :transaction="selectedDepositTransaction"
      @saved="loadData(false)"
    />
  </PageStateWrapper>
</template>
