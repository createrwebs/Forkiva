<script lang="ts" setup>
import { getApiErrorMessage } from '@/modules/core/api/errors'

import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import DatePicker from '@/modules/core/components/Form/DatePicker.vue'
import SearchSelect from '@/modules/core/components/Form/SearchSelect.vue'
import { useForm } from '@/modules/core/composables/form.ts'
import { generateUuid } from '@/modules/core/utils/uuid.ts'
import { useReservation } from '@/modules/reservation/composables/reservation.ts'

type DepositAction = 'collection' | 'application' | 'refund' | 'forfeiture' | 'reverse'

const props = defineProps<{
  action: DepositAction
  reservation: Record<string, any>
  transaction?: Record<string, any> | null
}>()
const emit = defineEmits<{
  saved: []
}>()
const dialogModel = defineModel<boolean>({ required: true })
const { t } = useI18n()
const { getReservationDepositFormMeta, recordReservationDeposit, reverseReservationDeposit } =
  useReservation()
const selectedOrder = ref<Record<string, any> | null>(null)
const ordersLoading = ref(false)
const orderSearchError = ref('')
const metaLoading = ref(false)
const metaLoadFailed = ref(false)
const meta = ref<Record<string, any>>({
  accounting_available: false,
  deposit_refundable: true,
  eligible_orders: [],
  financial_accounts: [],
  tender_methods: [],
})
const form = useForm({
  amount: null as string | null,
  tender_method: null as string | null,
  financial_account_key: null as string | null,
  economic_date: todayDate(),
  note: '',
  reason: '',
  idempotency_key: generateUuid(),
})

const isReverse = computed(() => props.action === 'reverse')
const requiresTender = computed(() => ['collection', 'refund'].includes(props.action))
const requiresOrder = computed(() => props.action === 'application')
const requiresFinancialAccount = computed(() => {
  return requiresTender.value && Boolean(meta.value.accounting_available)
})
const actionLoading = computed(() => {
  return form.loading.value || metaLoading.value || ordersLoading.value
})
const transactionAmount = computed(() => {
  return (
    props.transaction?.amount?.original?.amount ??
    props.transaction?.amount?.amount ??
    props.transaction?.amount ??
    null
  )
})
const unavailableMessage = computed(() => {
  if (
    props.action === 'application' &&
    !metaLoading.value &&
    !ordersLoading.value &&
    (meta.value.eligible_orders || []).length === 0
  ) {
    return t('reservation::frontend.deposit_no_eligible_orders')
  }

  if (props.action === 'refund' && !metaLoading.value && meta.value.deposit_refundable === false) {
    return t('reservation::frontend.deposit_refund_not_allowed')
  }

  return ''
})
const canSubmit = computed(() => {
  if (
    actionLoading.value ||
    metaLoadFailed.value ||
    orderSearchError.value ||
    unavailableMessage.value
  ) {
    return false
  }

  if (!form.state.economic_date) {
    return false
  }

  if (isReverse.value) {
    return Boolean(props.transaction && form.state.reason.trim())
  }

  if (Number(form.state.amount) <= 0 || !form.state.note.trim()) {
    return false
  }

  if (requiresTender.value && !form.state.tender_method) {
    return false
  }

  if (requiresFinancialAccount.value && !form.state.financial_account_key) {
    return false
  }

  return !requiresOrder.value || Boolean(selectedOrder.value)
})

function todayDate(): string {
  const now = new Date()
  const year = now.getFullYear()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')

  return `${year}-${month}-${day}`
}

function orderLabel(order: Record<string, any>): string {
  return [
    order.order_reference_no,
    order.order_number ? `#${order.order_number}` : null,
    order.customer_name,
    `${order.outstanding_amount} ${order.currency}`,
  ]
    .filter(Boolean)
    .join(' · ')
}

function reset() {
  form.state.amount = isReverse.value ? String(transactionAmount.value ?? '') : null
  form.state.tender_method = null
  form.state.financial_account_key = null
  form.state.economic_date = todayDate()
  form.state.note = ''
  form.state.reason = ''
  form.state.idempotency_key = generateUuid()
  selectedOrder.value = null
  orderSearchError.value = ''
  metaLoadFailed.value = false
  for (const key of Object.keys(form.errors.value)) {
    form.errors.value[key] = ''
  }
}

async function loadMeta() {
  if (isReverse.value) {
    return
  }

  metaLoading.value = true

  try {
    const response = await getReservationDepositFormMeta(props.reservation.id)
    meta.value = response.data.body
  } catch (error: unknown) {
    metaLoadFailed.value = true
    form.errors.value.general =
      getApiErrorMessage(error) || t('core::errors.an_unexpected_error_occurred')
  } finally {
    metaLoading.value = false
  }
}

function handleOrdersLoading(loading: boolean) {
  ordersLoading.value = loading
  if (loading) {
    orderSearchError.value = ''
  }
}

function handleOrderLoadError(error: any) {
  orderSearchError.value =
    error?.response?.data?.message || t('core::errors.an_unexpected_error_occurred')
}

function close() {
  if (!actionLoading.value) {
    dialogModel.value = false
  }
}

async function submit() {
  if (isReverse.value && !props.transaction) {
    return
  }

  const payload: Record<string, any> = {
    economic_date: form.state.economic_date,
    idempotency_key: form.state.idempotency_key,
  }

  if (isReverse.value) {
    payload.reason = form.state.reason
  } else {
    payload.type = props.action
    payload.amount = form.state.amount
    payload.note = form.state.note

    if (requiresTender.value) {
      payload.tender_method = form.state.tender_method
    }
    if (requiresFinancialAccount.value) {
      payload.financial_account_key = form.state.financial_account_key
    }
    if (requiresOrder.value && selectedOrder.value) {
      payload.order_id = selectedOrder.value.order_id
    }
  }

  const success = await form.submit(
    () =>
      isReverse.value
        ? reverseReservationDeposit(props.transaction!.id, payload)
        : recordReservationDeposit(props.reservation.id, payload),
    { showErrorToast: false },
  )

  if (success) {
    emit('saved')
    dialogModel.value = false
  }
}

watch(
  dialogModel,
  (open) => {
    if (open) {
      reset()
      void loadMeta()
    }
  },
  { immediate: true },
)
</script>

<template>
  <VDialog
    v-model="dialogModel"
    class="reservation-deposit-dialog"
    max-width="680"
    persistent
    scrollable
  >
    <VCard class="reservation-deposit-dialog__card">
      <VCardTitle class="d-flex align-center gap-2 border-b">
        <VIcon
          :color="isReverse ? 'warning' : 'primary'"
          :icon="isReverse ? 'tabler-arrow-back-up' : 'tabler-cash'"
        />
        {{ t(`reservation::frontend.deposit_actions.${action}`) }}
      </VCardTitle>

      <VCardText class="reservation-deposit-dialog__body pt-6">
        <div
          v-if="metaLoading"
          class="reservation-deposit-dialog__loader d-flex align-center justify-center"
        >
          <VProgressCircular
            color="primary"
            indeterminate
            size="48"
            width="4"
          />
        </div>

        <template v-else>
          <VAlert
            v-if="form.errors.value.general"
            class="mb-4"
            type="error"
            variant="tonal"
          >
            {{ form.errors.value.general }}
          </VAlert>

          <VAlert
            v-if="unavailableMessage"
            class="mb-4"
            type="warning"
            variant="tonal"
          >
            {{ unavailableMessage }}
          </VAlert>

          <VAlert
            v-if="orderSearchError"
            class="mb-4"
            type="error"
            variant="tonal"
          >
            {{ orderSearchError }}
          </VAlert>

          <VRow>
            <VCol
              v-if="!isReverse"
              cols="12"
              :md="requiresTender ? 6 : 12"
            >
              <VTextField
                v-model="form.state.amount"
                v-decimal-en
                :error-messages="form.errors.value.amount"
                :label="t('reservation::attributes.reservation_deposit_transactions.amount')"
                :prefix="reservation.deposit_currency"
              />
            </VCol>

            <VCol
              v-if="requiresOrder"
              cols="12"
            >
              <SearchSelect
                v-model="selectedOrder"
                :error="!!form.errors.value.order_id"
                :error-messages="form.errors.value.order_id"
                :item-title="orderLabel"
                item-value="key"
                :items="meta.eligible_orders || []"
                :label="t('reservation::frontend.deposit_order')"
                :list-max-height="120"
                :no-data-text="t('reservation::frontend.deposit_no_order_matches')"
                :per-page="20"
                query-param-path="search"
                return-object
                :url="`/v1/reservation-deposits/reservation/${reservation.id}/eligible-orders`"
                @load-error="handleOrderLoadError"
                @loading="handleOrdersLoading"
              />
            </VCol>

            <VCol
              v-if="requiresTender"
              cols="12"
              md="6"
            >
              <VSelect
                v-model="form.state.tender_method"
                :error-messages="form.errors.value.tender_method"
                item-title="name"
                item-value="id"
                :items="meta.tender_methods || []"
                :label="t('reservation::attributes.reservation_deposit_transactions.tender_method')"
              />
            </VCol>

            <VCol
              v-if="requiresFinancialAccount"
              cols="12"
              md="6"
            >
              <VSelect
                v-model="form.state.financial_account_key"
                :error-messages="form.errors.value.financial_account_key"
                item-title="label"
                item-value="key"
                :items="meta.financial_accounts || []"
                :label="
                  t(
                    'reservation::attributes.reservation_deposit_transactions.financial_account_key',
                  )
                "
              />
            </VCol>

            <VCol
              cols="12"
              :md="requiresFinancialAccount ? 6 : 12"
            >
              <DatePicker
                v-model="form.state.economic_date"
                :error-messages="form.errors.value.economic_date"
                :label="t('reservation::attributes.reservation_deposit_transactions.economic_date')"
              />
            </VCol>

            <VCol cols="12">
              <VTextarea
                v-if="isReverse"
                v-model="form.state.reason"
                :error-messages="form.errors.value.reason"
                :label="t('reservation::frontend.deposit_reversal_reason')"
                rows="3"
              />

              <VTextarea
                v-else
                v-model="form.state.note"
                :error-messages="form.errors.value.note"
                :label="t('reservation::attributes.reservation_deposit_transactions.note')"
                rows="3"
              />
            </VCol>
          </VRow>
        </template>
      </VCardText>

      <VCardActions class="reservation-deposit-dialog__footer justify-end border-t pa-4">
        <VBtn
          :disabled="actionLoading"
          variant="text"
          @click="close"
        >
          {{ t('admin::admin.buttons.cancel') }}
        </VBtn>

        <VBtn
          :color="isReverse ? 'warning' : 'primary'"
          :disabled="!canSubmit"
          :loading="actionLoading"
          @click="submit"
        >
          {{ t('admin::admin.buttons.save') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.reservation-deposit-dialog__card {
  max-block-size: calc(100dvh - 48px);
}

.reservation-deposit-dialog__body {
  min-block-size: 0;
  overflow-y: auto;
  overscroll-behavior: contain;
}

.reservation-deposit-dialog__loader {
  min-block-size: 240px;
}

.reservation-deposit-dialog__footer {
  position: relative;
  z-index: 2;
  flex-shrink: 0;
  background: rgb(var(--v-theme-surface));
}
</style>
