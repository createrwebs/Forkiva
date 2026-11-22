<script lang="ts" setup>
import { getApiErrorMessage } from '@/modules/core/api/errors'

import { computed, onBeforeMount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { type RouteLocationRaw, useRouter } from 'vue-router'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import DatePicker from '@/modules/core/components/Form/DatePicker.vue'
import TimePicker from '@/modules/core/components/Form/TimePicker.vue'
import { useForm } from '@/modules/core/composables/form.ts'
import { useAppStore } from '@/modules/core/stores/appStore.ts'
import { formatPrice } from '@/modules/core/utils/formatters.ts'
import { useReservation } from '@/modules/reservation/composables/reservation.ts'
import TableAssignmentCard from './TableAssignmentCard.vue'

const props = defineProps<{
  item?: Record<string, any> | null
  action: 'update' | 'create'
}>()

const { t } = useI18n()
const { user, can } = useAuth()
const appStore = useAppStore()
const router = useRouter()
const {
  getReservationFormMeta,
  storeReservation,
  updateReservation,
  checkAvailability,
  suggestedTables,
} = useReservation()
const moneyAmount = (value: any) => value?.original?.amount ?? value?.amount ?? value ?? 0

const form = useForm({
  branch_id: user?.assigned_to_branch ? user.branch_id : props.item?.branch?.id,
  customer_id: props.item?.customer?.id,
  phone_country_iso_code:
    props.item?.phone_country_iso_code || props.item?.customer?.phone_country_iso_code || 'JO',
  reservation_type: props.item?.reservation_type?.id || 'standard',
  status: props.item?.status?.id || 'pending',
  table_assignment_mode: props.item?.table_assignment_mode?.id || 'assign_later',
  reservation_date: props.item?.reservation_date || '',
  start_time: props.item?.start_time || '',
  duration_minutes: props.item?.duration_minutes || 90,
  guest_count: props.item?.guest_count || 2,
  adult_count: props.item?.adult_count,
  child_count: props.item?.child_count,
  customer_name: props.item?.customer_name || '',
  customer_phone: props.item?.customer_national_phone || props.item?.customer_phone || '',
  customer_email: props.item?.customer_email || '',
  occasion: props.item?.occasion || '',
  special_requests: props.item?.special_requests || '',
  internal_notes: props.item?.internal_notes || '',
  table_ids: (props.item?.tables || [])
    .map((assignment: Record<string, any>) => assignment.table?.id)
    .filter(Boolean),
  deposit_required: props.item?.deposit_required || false,
  deposit_amount: moneyAmount(props.item?.deposit_amount),
  deposit_paid_amount: moneyAmount(props.item?.deposit_paid_amount),
})

const meta = ref<Record<string, any>>({
  branches: [],
  countries: [],
  statuses: [],
  types: [],
  table_assignment_modes: [],
  tables: [],
})
const availability = ref<Record<string, any> | null>(null)
const availabilityMessage = ref('')
const suggested = ref<Record<string, any>[]>([])
const loadingBranchMeta = ref(false)
const checkingAvailability = ref(false)
const selectedTableIds = computed(() => form.state.table_ids || [])
const initialStatuses = computed(() => {
  return (meta.value.initial_statuses || meta.value.statuses || []).filter(
    (status: Record<string, any>) => {
      return status.id !== 'confirmed' || can('admin.reservations.confirm')
    },
  )
})
const isAssignNow = computed(() => form.state.table_assignment_mode === 'assign_now')
const isAutoAssign = computed(() => form.state.table_assignment_mode === 'auto_assign')
type DepositNotice = {
  type: 'info' | 'success' | 'warning'
  message: string
}
type DepositPaymentStatus = 'not_required' | 'unpaid' | 'partially_paid' | 'paid'

const selectedBranch = computed(() => {
  return (meta.value.branches || []).find(
    (branch: Record<string, any>) => branch.id === form.state.branch_id,
  )
})
const reservationSettings = computed<Record<string, any> | null>(() => meta.value.settings || null)
const depositPolicyEnabled = computed(() => Boolean(reservationSettings.value?.deposit_enabled))
const largePartyDepositRequired = computed(() => {
  if (!depositPolicyEnabled.value || !reservationSettings.value?.deposit_required_for_large_party) {
    return false
  }

  return (
    numericAmount(form.state.guest_count) >=
    numericAmount(reservationSettings.value.large_party_threshold)
  )
})
const policyDepositAmount = computed(() => numericAmount(reservationSettings.value?.deposit_amount))
const depositRequired = computed(() => Boolean(form.state.deposit_required))
const depositAmount = computed(() => numericAmount(form.state.deposit_amount))
const depositDueAmount = computed(() => (depositRequired.value ? depositAmount.value : 0))
const depositPaidAmount = computed(() => numericAmount(form.state.deposit_paid_amount))
const depositCurrency = computed(
  () => props.item?.deposit_currency || selectedBranch.value?.currency || appStore.currency,
)
const depositCurrencyRate = computed(() => props.item?.deposit_currency_rate || 1)
const depositRemainingAmount = computed(() =>
  depositRequired.value ? Math.max(depositDueAmount.value - depositPaidAmount.value, 0) : 0,
)
const depositOverpaidAmount = computed(() =>
  depositRequired.value ? Math.max(depositPaidAmount.value - depositDueAmount.value, 0) : 0,
)
const depositPaymentStatus = computed<DepositPaymentStatus>(() => {
  if (!depositRequired.value) {
    return 'not_required'
  }

  if (depositPaidAmount.value <= 0) {
    return 'unpaid'
  }

  if (depositPaidAmount.value < depositDueAmount.value) {
    return 'partially_paid'
  }

  return 'paid'
})
const depositPaymentStatusColor = computed(() => {
  return (
    {
      not_required: 'secondary',
      unpaid: 'warning',
      partially_paid: 'info',
      paid: 'success',
    }[depositPaymentStatus.value] || 'secondary'
  )
})
const depositPaymentStatusName = computed(() =>
  t(`reservation::frontend.deposit_payment_statuses.${depositPaymentStatus.value}`),
)
const depositNotice = computed<DepositNotice>(() => {
  if (!depositRequired.value) {
    return {
      type: 'info',
      message: t('reservation::frontend.deposit_not_required'),
    }
  }

  if (depositOverpaidAmount.value > 0) {
    return {
      type: 'warning',
      message: t('reservation::frontend.deposit_overpaid_message', {
        value: formattedDepositAmount(depositOverpaidAmount.value),
      }),
    }
  }

  if (depositRemainingAmount.value > 0) {
    return {
      type: 'warning',
      message: t('reservation::frontend.deposit_remaining_message', {
        value: formattedDepositAmount(depositRemainingAmount.value),
      }),
    }
  }

  return {
    type: 'success',
    message: t('reservation::frontend.deposit_fully_paid'),
  }
})
const depositDetails = computed(() => [
  {
    icon: 'tabler-cash',
    label: t('reservation::attributes.reservations.deposit_amount'),
    value: formattedDepositAmount(depositDueAmount.value),
    color: 'primary',
  },
  {
    icon: 'tabler-cash-banknote',
    label: t('reservation::attributes.reservations.deposit_paid_amount'),
    value: formattedDepositAmount(depositPaidAmount.value),
    color: 'success',
  },
  {
    icon: depositOverpaidAmount.value > 0 ? 'tabler-arrow-big-up-lines' : 'tabler-receipt',
    label:
      depositOverpaidAmount.value > 0
        ? t('reservation::frontend.deposit_overpaid_amount')
        : t('reservation::frontend.deposit_remaining_amount'),
    value: formattedDepositAmount(
      depositOverpaidAmount.value > 0 ? depositOverpaidAmount.value : depositRemainingAmount.value,
    ),
    color:
      depositOverpaidAmount.value > 0 || depositRemainingAmount.value > 0 ? 'warning' : 'success',
  },
])
const canViewAvailability = computed(
  () => can('admin.reservations.view_availability') && (isAssignNow.value || isAutoAssign.value),
)
const canRunAvailability = computed(() => {
  const hasBaseRequirements =
    Boolean(form.state.branch_id) &&
    Boolean(form.state.reservation_date) &&
    Boolean(form.state.start_time) &&
    Boolean(form.state.guest_count)

  return (
    hasBaseRequirements &&
    (isAutoAssign.value || (isAssignNow.value && selectedTableIds.value.length > 0))
  )
})

function numericAmount(value: unknown): number {
  const number = Number.parseFloat(String(value ?? '').replace(/,/g, ''))

  return Number.isFinite(number) ? number : 0
}

function formattedDepositAmount(value: number): string {
  return formatPrice(value, depositCurrency.value || appStore.currency, 3)
}

function clearDepositFields(): void {
  form.state.deposit_required = false
  form.state.deposit_amount = 0
  if (props.action === 'create') {
    form.state.deposit_paid_amount = 0
  }
}

function applyDepositPolicy(): void {
  if (!depositPolicyEnabled.value) {
    clearDepositFields()
    return
  }

  if (largePartyDepositRequired.value) {
    form.state.deposit_required = true
    form.state.deposit_amount = policyDepositAmount.value
  }

  if (!form.state.deposit_required && props.action === 'create') {
    form.state.deposit_paid_amount = 0
  }
}

async function submit() {
  applyDepositPolicy()
  const payload = { ...form.state }
  delete payload.deposit_paid_amount
  if (payload.table_assignment_mode !== 'assign_now') {
    payload.table_ids = []
  }

  if (props.action === 'update') {
    delete payload.branch_id
    delete payload.status
  }

  if (
    !form.loading.value &&
    (await form.submit(() =>
      props.action === 'create'
        ? storeReservation(payload)
        : updateReservation(props.item?.id, payload),
    ))
  ) {
    await router.push({ name: 'admin.reservations.index' } as unknown as RouteLocationRaw)
  }
}

async function loadBaseFormData() {
  try {
    const response = (await getReservationFormMeta()).data.body
    meta.value = {
      ...meta.value,
      ...response,
    }
    applyDepositPolicy()
  } catch {
    /* Empty */
  }
}

async function loadBranchFormData(branchId?: number | null) {
  if (!branchId) {
    meta.value = {
      ...meta.value,
      tables: [],
      settings: null,
    }
    applyDepositPolicy()
    return
  }

  meta.value = {
    ...meta.value,
    tables: [],
    settings: null,
  }
  applyDepositPolicy()

  try {
    loadingBranchMeta.value = true
    const response = (await getReservationFormMeta(branchId)).data.body
    meta.value = {
      ...meta.value,
      ...response,
    }
  } catch {
    /* Empty */
  } finally {
    loadingBranchMeta.value = false
  }
}

async function runAvailability() {
  if (!canViewAvailability.value) {
    return
  }

  if (!canRunAvailability.value) {
    availability.value = null
    suggested.value = []
    availabilityMessage.value = isAssignNow.value
      ? t('reservation::frontend.availability_requirements_assign_now')
      : t('reservation::frontend.availability_requirements')
    return
  }

  const payload = {
    branch_id: form.state.branch_id,
    reservation_date: form.state.reservation_date,
    start_time: form.state.start_time,
    duration_minutes: form.state.duration_minutes,
    guest_count: form.state.guest_count,
    table_assignment_mode: form.state.table_assignment_mode,
    table_ids: isAssignNow.value ? form.state.table_ids : [],
    exclude_reservation_id: props.item?.id,
  }

  try {
    checkingAvailability.value = true
    availabilityMessage.value = ''
    const response = await checkAvailability(payload)
    availability.value = response.data.body
    suggested.value = (await suggestedTables(payload)).data.body
    if (availability.value?.available) {
      availabilityMessage.value = t('reservation::frontend.available_tables_found')
    }
  } catch (error: unknown) {
    availability.value = null
    suggested.value = []
    availabilityMessage.value =
      getApiErrorMessage(error) || t('core::errors.an_unexpected_error_occurred')
  } finally {
    checkingAvailability.value = false
  }
}

function resetAvailabilityState() {
  availability.value = null
  availabilityMessage.value = ''
  suggested.value = []
}

onBeforeMount(async () => {
  await loadBaseFormData()
  await loadBranchFormData(form.state.branch_id)
})

watch(
  () => form.state.branch_id,
  (branchId) => {
    form.state.table_ids = []
    resetAvailabilityState()
    loadBranchFormData(branchId)
  },
)

watch(
  () => form.state.table_assignment_mode,
  (mode) => {
    if (mode !== 'assign_now') {
      form.state.table_ids = []
    }
  },
)

watch(
  () => [
    form.state.reservation_date,
    form.state.start_time,
    form.state.duration_minutes,
    form.state.guest_count,
    form.state.table_assignment_mode,
    selectedTableIds.value.join(','),
  ],
  () => resetAvailabilityState(),
)

watch(
  () => [
    reservationSettings.value?.deposit_enabled,
    reservationSettings.value?.deposit_required_for_large_party,
    reservationSettings.value?.deposit_amount,
    reservationSettings.value?.large_party_threshold,
    form.state.guest_count,
  ],
  () => applyDepositPolicy(),
)

watch(
  () => form.state.deposit_required,
  (required) => {
    if (!required) {
      if (props.action === 'create') {
        form.state.deposit_paid_amount = 0
      }
      return
    }

    if (largePartyDepositRequired.value) {
      form.state.deposit_amount = policyDepositAmount.value
    } else if (
      depositPolicyEnabled.value &&
      numericAmount(form.state.deposit_amount) <= 0 &&
      policyDepositAmount.value > 0
    ) {
      form.state.deposit_amount = policyDepositAmount.value
    }
  },
)
</script>

<template>
  <BaseForm
    :action="action"
    :loading="form.loading.value"
    resource="reservations"
    @submit="submit"
  >
    <VRow>
      <VCol
        cols="12"
        md="8"
      >
        <VRow>
          <VCol cols="12">
            <VCard>
              <VCardTitle class="d-flex align-center">
                <VIcon
                  class="me-2"
                  icon="tabler-calendar-event"
                  size="20"
                />
                <span>
                  {{ t('reservation::frontend.reservation_information') }}
                </span>
              </VCardTitle>

              <VCardText>
                <VRow>
                  <VCol cols="12">
                    <VRow>
                      <VCol
                        v-if="action === 'create' && !user?.assigned_to_branch"
                        cols="12"
                        md="4"
                      >
                        <VSelect
                          v-model="form.state.branch_id"
                          :error="!!form.errors.value?.branch_id"
                          :error-messages="form.errors.value?.branch_id"
                          item-title="name"
                          item-value="id"
                          :items="meta.branches"
                          :label="t('reservation::attributes.reservations.branch_id')"
                        />
                      </VCol>

                      <VCol
                        cols="12"
                        md="4"
                      >
                        <VSelect
                          v-model="form.state.reservation_type"
                          item-title="name"
                          item-value="id"
                          :items="meta.types"
                          :label="t('reservation::attributes.reservations.reservation_type')"
                        />
                      </VCol>

                      <VCol
                        v-if="action === 'create'"
                        cols="12"
                        md="4"
                      >
                        <VSelect
                          v-model="form.state.status"
                          item-title="name"
                          item-value="id"
                          :items="initialStatuses"
                          :label="t('reservation::attributes.reservations.status')"
                        />
                      </VCol>
                    </VRow>
                  </VCol>

                  <VCol
                    cols="12"
                    md="4"
                  >
                    <DatePicker
                      v-model="form.state.reservation_date"
                      :error="!!form.errors.value?.reservation_date"
                      :error-messages="form.errors.value?.reservation_date"
                      :label="t('reservation::attributes.reservations.reservation_date')"
                      required
                    />
                  </VCol>

                  <VCol
                    cols="12"
                    md="4"
                  >
                    <TimePicker
                      v-model="form.state.start_time"
                      :error="!!form.errors.value?.start_time"
                      :error-messages="form.errors.value?.start_time"
                      :label="t('reservation::attributes.reservations.start_time')"
                      required
                    />
                  </VCol>

                  <VCol
                    cols="12"
                    md="4"
                  >
                    <VTextField
                      v-model="form.state.duration_minutes"
                      v-integer-en
                      :error="!!form.errors.value?.duration_minutes"
                      :error-messages="form.errors.value?.duration_minutes"
                      :label="t('reservation::attributes.reservations.duration_minutes')"
                    />
                  </VCol>

                  <VCol
                    cols="12"
                    md="4"
                  >
                    <VTextField
                      v-model="form.state.guest_count"
                      v-integer-en
                      :error="!!form.errors.value?.guest_count"
                      :error-messages="form.errors.value?.guest_count"
                      :label="t('reservation::attributes.reservations.guest_count')"
                    />
                  </VCol>

                  <VCol
                    cols="12"
                    md="4"
                  >
                    <VTextField
                      v-model="form.state.adult_count"
                      v-integer-en
                      :error="!!form.errors.value?.adult_count"
                      :error-messages="form.errors.value?.adult_count"
                      :label="t('reservation::attributes.reservations.adult_count')"
                    />
                  </VCol>

                  <VCol
                    cols="12"
                    md="4"
                  >
                    <VTextField
                      v-model="form.state.child_count"
                      v-integer-en
                      :error="!!form.errors.value?.child_count"
                      :error-messages="form.errors.value?.child_count"
                      :label="t('reservation::attributes.reservations.child_count')"
                    />
                  </VCol>
                </VRow>
              </VCardText>
            </VCard>
          </VCol>

          <VCol cols="12">
            <VCard>
              <VCardTitle class="d-flex align-center">
                <VIcon
                  class="me-2"
                  icon="tabler-user"
                  size="20"
                />
                <span>{{ t('reservation::frontend.customer_information') }}</span>
              </VCardTitle>

              <VCardText>
                <VRow>
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <VTextField
                      v-model="form.state.customer_name"
                      :error="!!form.errors.value?.customer_name"
                      :error-messages="form.errors.value?.customer_name"
                      :label="t('reservation::attributes.reservations.customer_name')"
                    />
                  </VCol>

                  <VCol
                    cols="12"
                    md="6"
                  >
                    <VTextField
                      v-model="form.state.customer_email"
                      :error="!!form.errors.value?.customer_email"
                      :error-messages="form.errors.value?.customer_email"
                      :label="t('reservation::attributes.reservations.customer_email')"
                    />
                  </VCol>

                  <VCol
                    cols="12"
                    md="6"
                  >
                    <VSelect
                      v-model="form.state.phone_country_iso_code"
                      :error="!!form.errors.value?.phone_country_iso_code"
                      :error-messages="form.errors.value?.phone_country_iso_code"
                      item-title="name"
                      item-value="id"
                      :items="meta.countries"
                      :label="t('reservation::attributes.reservations.phone_country_iso_code')"
                    />
                  </VCol>

                  <VCol
                    cols="12"
                    md="6"
                  >
                    <VTextField
                      v-model="form.state.customer_phone"
                      :error="!!form.errors.value?.customer_phone"
                      :error-messages="form.errors.value?.customer_phone"
                      :label="t('reservation::attributes.reservations.customer_phone')"
                    />
                  </VCol>

                  <VCol
                    cols="12"
                    md="6"
                  >
                    <VTextField
                      v-model="form.state.occasion"
                      :label="t('reservation::attributes.reservations.occasion')"
                    />
                  </VCol>

                  <VCol cols="12">
                    <VTextarea
                      v-model="form.state.special_requests"
                      :label="t('reservation::attributes.reservations.special_requests')"
                      rows="3"
                    />
                  </VCol>

                  <VCol cols="12">
                    <VTextarea
                      v-model="form.state.internal_notes"
                      :label="t('reservation::attributes.reservations.internal_notes')"
                      rows="3"
                    />
                  </VCol>
                </VRow>
              </VCardText>
            </VCard>
          </VCol>

          <VCol
            v-if="depositPolicyEnabled"
            cols="12"
          >
            <VCard>
              <VCardTitle class="d-flex align-center">
                <VIcon
                  class="me-2"
                  icon="tabler-cash"
                  size="20"
                />
                <span>{{ t('reservation::frontend.deposit') }}</span>
              </VCardTitle>

              <VCardText>
                <VRow>
                  <VCol
                    class="d-flex align-center"
                    cols="12"
                    md="4"
                  >
                    <VCheckbox
                      v-model="form.state.deposit_required"
                      :disabled="largePartyDepositRequired"
                      :label="t('reservation::attributes.reservations.deposit_required')"
                    />
                  </VCol>

                  <VCol
                    cols="12"
                    md="4"
                  >
                    <VTextField
                      v-model="form.state.deposit_amount"
                      v-decimal-en
                      :disabled="!depositRequired || largePartyDepositRequired"
                      :error="!!form.errors.value?.deposit_amount"
                      :error-messages="form.errors.value?.deposit_amount"
                      :label="t('reservation::attributes.reservations.deposit_amount')"
                    />
                  </VCol>

                  <VCol
                    cols="12"
                    md="4"
                  >
                    <VTextField
                      v-model="form.state.deposit_paid_amount"
                      v-decimal-en
                      disabled
                      :label="t('reservation::attributes.reservations.deposit_paid_amount')"
                    />
                  </VCol>
                </VRow>

                <VAlert
                  v-if="largePartyDepositRequired"
                  class="mt-4"
                  density="comfortable"
                  type="info"
                  variant="tonal"
                >
                  {{ t('reservation::frontend.deposit_large_party_policy_applied') }}
                </VAlert>

                <div class="deposit-details mt-4">
                  <VSheet
                    v-for="detail in depositDetails"
                    :key="detail.label"
                    border
                    class="deposit-details__item"
                    rounded="lg"
                  >
                    <div class="deposit-details__header">
                      <VIcon
                        :color="detail.color"
                        :icon="detail.icon"
                        size="18"
                      />

                      <span>{{ detail.label }}</span>
                    </div>

                    <div class="deposit-details__value">
                      {{ detail.value }}
                    </div>
                  </VSheet>
                </div>

                <div class="deposit-details__footer mt-4">
                  <VChip
                    :color="depositPaymentStatusColor"
                    size="small"
                    variant="tonal"
                  >
                    {{ depositPaymentStatusName }}
                  </VChip>

                  <span class="text-body-2 text-medium-emphasis">
                    {{
                      t('reservation::frontend.deposit_currency_context', {
                        currency: depositCurrency,
                        rate: depositCurrencyRate,
                      })
                    }}
                  </span>
                </div>

                <VAlert
                  class="mt-4"
                  density="comfortable"
                  :type="depositNotice.type"
                  variant="tonal"
                >
                  {{ depositNotice.message }}
                </VAlert>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </VCol>

      <VCol
        cols="12"
        md="4"
      >
        <VRow>
          <VCol cols="12">
            <TableAssignmentCard
              :availability="availability"
              :availability-message="availabilityMessage"
              :can-run-availability="canRunAvailability"
              :can-view-availability="canViewAvailability"
              :checking-availability="checkingAvailability"
              :form="form"
              :loading-branch-meta="loadingBranchMeta"
              :meta="meta"
              :on-check-availability="runAvailability"
              :suggested="suggested"
            />
          </VCol>
        </VRow>
      </VCol>
    </VRow>
  </BaseForm>
</template>

<style lang="scss" scoped>
.deposit-details {
  display: grid;
  gap: 10px;
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.deposit-details__item {
  border-style: dashed;
  padding: 12px 14px;
}

.deposit-details__header {
  align-items: center;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  display: flex;
  font-size: 0.82rem;
  font-weight: 600;
  gap: 8px;
  margin-bottom: 6px;
}

.deposit-details__value {
  color: rgb(var(--v-theme-on-surface));
  font-weight: 700;
  line-height: 1.4;
}

.deposit-details__footer {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

@media (max-width: 959px) {
  .deposit-details {
    grid-template-columns: 1fr;
  }
}
</style>
