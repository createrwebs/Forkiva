<script lang="ts" setup>
import type { AxiosError } from 'axios'
import type { useQintrix } from '@/modules/printer/composables/qintrix.ts'
import { computed, nextTick, onBeforeMount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { useDisplay, useTheme } from 'vuetify'
import { getContrast } from 'vuetify/lib/util/colorUtils.js'
import { validatePayment } from './validation'
import { useForm } from '@/modules/core/composables/form.ts'
import { useOrder } from '@/modules/order/composables/order.ts'
import FinalizePayment from './FinalizePayment/Index.vue'
import OrderSummary from './OrderSummary/Index.vue'

const props = defineProps<{
  modelValue: boolean
  orderId: string | number
  qintrix: ReturnType<typeof useQintrix>
  registerId: number
  sessionId: number
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (e: 'payment-added'): void
}>()

const { getPaymentMeta, printPreview, storePayment } = useOrder()
const toast = useToast()
const { t } = useI18n()
const { smAndDown } = useDisplay()
const theme = useTheme()
const primaryText = computed(() => {
  const primary = theme.current.value.colors.primary
  return getContrast(primary, '#000000') > getContrast(primary, '#ffffff') ? '#000000' : '#ffffff'
})
const dialogContent = ref<HTMLElement | null>(null)
const errorSummary = ref<HTMLElement | null>(null)
const visibleErrors = computed(() =>
  Object.entries(form.errors.value).filter(([, message]) => !!message),
)

const summaryErrors = computed(() => visibleErrors.value.filter(([path]) => !hasInlineError(path)))

function hasInlineError(path: string) {
  if (path === 'payment_mode') return true
  if (['customer_given_amount', 'change_return'].includes(path)) return hasCashPayment.value
  const match = path.match(/^payments\.(\d+)\.(.+)$/)
  if (!match) return false
  const payment = form.state.payments[Number(match[1])]
  if (!payment) return false
  if (['method', 'amount'].includes(match[2]!)) return true
  if (match[2] === 'gift_card_code') return payment.method === 'gift_card'
  return (
    match[2] === 'transaction_id' &&
    ['card', 'bank_transfer', 'mobile_wallet'].includes(payment.method ?? '')
  )
}

function fieldLabel(path: string) {
  const match = path.match(/^payments\.(\d+)\.(.+)$/)
  if (match)
    return `${t('order::orders.payments')} ${Number(match[1]) + 1} — ${t(`order::attributes.payments.payments.*.${match[2]}`)}`
  if (path === 'customer_given_amount') return t('order::orders.cash_received')
  if (['payments', 'payment_mode', 'change_return'].includes(path))
    return t(`order::orders.${path}`)
  return t('order::orders.finalize_payment')
}

async function focusError(path: string) {
  await nextTick()
  const wrapper = Array.from(
    dialogContent.value?.querySelectorAll<HTMLElement>('[data-payment-field]') ?? [],
  ).find((element) => element.dataset.paymentField === path)
  const target =
    wrapper?.querySelector<HTMLElement>('input, button, [tabindex]') ??
    wrapper ??
    errorSummary.value
  target?.scrollIntoView({ block: 'center', behavior: 'auto' })
  target?.focus({ preventScroll: true })
}

const form = useForm<{
  payments: {
    method: string | null
    amount: number | string | null
    transaction_id: string | null
    gift_card_code: string | null
  }[]
  register_id: number
  session_id: number
  payment_mode: string
  customer_given_amount: string
  change_return: string
  with_print: boolean
}>({
  payments: [],
  register_id: props.registerId,
  session_id: props.sessionId,
  payment_mode: 'full',
  customer_given_amount: '',
  change_return: '',
  with_print: false,
})

const loading = ref(false)
const submittingAttempt = ref(false)
const meta = ref<{
  paymentMethods: { id: string; name: string }[]
  paymentModes: any[]
  quickPayAmounts: number[]
  order: Record<string, any>
}>({
  paymentMethods: [],
  paymentModes: [],
  quickPayAmounts: [],
  order: {},
})

const defaultQuickPayAmounts = [10, 20, 50, 100]
const activeInputPath = ref<string | null>(null)

const dialogModel = computed({
  get: () => props.modelValue,
  set: (val: boolean) => emit('update:modelValue', val),
})

const close = () => emit('update:modelValue', false)

async function loadMeta() {
  try {
    loading.value = true
    const response = (await getPaymentMeta(props.orderId)).data.body
    meta.value.paymentMethods = response.payment_methods
    meta.value.paymentModes = response.payment_modes
    meta.value.quickPayAmounts = Array.isArray(response.quick_pay_amounts)
      ? response.quick_pay_amounts
      : defaultQuickPayAmounts
    meta.value.order = response.order
    addPayment(Number.parseFloat(response.order.due_amount.amount.toFixed(3)))
    activeInputPath.value = 'payments.0.amount'
  } catch (error) {
    toast.error(
      (
        error as AxiosError<{
          message?: string
        }>
      ).response?.data?.message || t('core::errors.an_unexpected_error_occurred'),
    )
    close()
  } finally {
    loading.value = false
  }
}

onBeforeMount(async () => {
  await loadMeta()
})

const finalDueAmount = computed(() => {
  const factor = 10 ** (meta.value.order?.precision ?? 3)
  const paid = form.state.payments.reduce(
    (total, payment) => total + Math.round((Number(payment.amount) || 0) * factor),
    0,
  )
  return (Math.round((meta.value.order?.due_amount?.amount ?? 0) * factor) - paid) / factor
})

const totalCashPayment = computed(() => {
  return form.state.payments
    .filter((p) => p.method === 'cash')
    .reduce((total, p) => total + (Number(p.amount) || 0), 0)
})

watch([() => form.state.customer_given_amount, totalCashPayment], ([given, cash]) => {
  const amount = Number(given)
  form.state.change_return =
    Number.isFinite(amount) && amount >= cash
      ? (amount - cash).toFixed(meta.value.order?.precision ?? 3)
      : '0'
})

const hasCashPayment = computed(() => totalCashPayment.value > 0)

const currentTotalPaid = computed(() => {
  const factor = 10 ** (meta.value.order?.precision ?? 3)
  return (
    form.state.payments.reduce(
      (total, p) => total + Math.round((Number(p.amount) || 0) * factor),
      0,
    ) / factor
  )
})

watch(
  () => form.state.payments,
  () => {
    if (!hasCashPayment.value) {
      form.state.customer_given_amount = ''
    }
  },
  { deep: true },
)

function addPayment(defaultAmount: number | null = null) {
  const unusedMethod = meta.value.paymentMethods.find(
    (method) => !form.state.payments.some((payment) => payment.method === method.id),
  )
  form.state.payments.push({
    method: unusedMethod?.id ?? meta.value.paymentMethods[0]?.id ?? null,
    amount: defaultAmount ?? (finalDueAmount.value > 0 ? finalDueAmount.value : null),
    transaction_id: null,
    gift_card_code: null,
  })
  activeInputPath.value = `payments.${form.state.payments.length - 1}.amount`
}

function resolveActiveInputPath() {
  if (activeInputPath.value) {
    const paymentAmountPathMatch = activeInputPath.value.match(/^payments\.(\d+)\.amount$/)
    if (activeInputPath.value === 'customer_given_amount' && hasCashPayment.value) {
      return activeInputPath.value
    }
    if (paymentAmountPathMatch) {
      const index = Number(paymentAmountPathMatch[1])
      if (form.state.payments[index]) {
        return activeInputPath.value
      }
    }
  }

  if (form.state.payments.length > 0) {
    return 'payments.0.amount'
  }

  return hasCashPayment.value ? 'customer_given_amount' : null
}

function getValueByPath(path: string): string {
  if (path === 'customer_given_amount') {
    return `${form.state.customer_given_amount || ''}`
  }

  const paymentAmountPathMatch = path.match(/^payments\.(\d+)\.amount$/)
  if (paymentAmountPathMatch) {
    const index = Number(paymentAmountPathMatch[1])
    return `${form.state.payments[index]?.amount ?? ''}`
  }

  return ''
}

function setValueByPath(path: string, value: string) {
  if (path === 'customer_given_amount') {
    form.state.customer_given_amount = value
    return
  }

  const paymentAmountPathMatch = path.match(/^payments\.(\d+)\.amount$/)
  if (paymentAmountPathMatch) {
    const index = Number(paymentAmountPathMatch[1])
    if (form.state.payments[index]) {
      form.state.payments[index].amount = value === '' ? null : value
    }
  }
}

function appendKeyToActiveInput(key: string) {
  const path = resolveActiveInputPath()
  if (!path || form.loading.value) {
    return
  }

  if ((key || '').toUpperCase() === 'C') {
    setValueByPath(path, '')
    return
  }

  const currentValue = getValueByPath(path)
  if (key === '.' && currentValue.includes('.')) {
    return
  }

  const baseValue = currentValue === '0' && key !== '.' ? '' : currentValue
  const nextValue =
    key === '.' && (baseValue === '' || baseValue === null || baseValue === undefined)
      ? '0.'
      : `${baseValue}${key}`
  setValueByPath(path, nextValue)
}

function applyQuickPayToActiveInput(amount: number) {
  const path = resolveActiveInputPath()
  if (!path || form.loading.value) {
    return
  }

  const currentValue = Number.parseFloat(getValueByPath(path) || '0')
  const nextAmount = (Number.isNaN(currentValue) ? 0 : currentValue) + amount
  const precision = meta.value.order?.precision ?? 3
  setValueByPath(path, nextAmount.toFixed(precision))
}

function applyCalculatorResultToActiveInput(amount: number) {
  const path = resolveActiveInputPath()
  if (!path || form.loading.value) {
    return
  }

  const precision = meta.value.order?.precision ?? 3
  setValueByPath(path, amount.toFixed(precision))
}

watch(
  () => form.state,
  () => {
    if (!form.loading.value) form.resetErrors()
  },
  { deep: true },
)

function resolvePrintType() {
  return form.state.payment_mode === 'full' ? 'invoice' : 'bill'
}

async function openPrintPreview(type: string) {
  let printWindow: Window | null = null

  try {
    const response = await printPreview(props.orderId, type, undefined, props.registerId, 'html')

    const html = response.data.body.content

    printWindow = window.open('', '_blank', 'width=800,height=600')
    if (!printWindow) return

    const doc = printWindow.document

    doc.open()
    doc.append(doc.createElement('html'))
    doc.documentElement.innerHTML = html
    doc.close()

    const closeAfterPrint = () => {
      printWindow?.close()
    }

    printWindow.addEventListener('afterprint', closeAfterPrint)

    printWindow.addEventListener('load', () => {
      printWindow!.focus()
      printWindow!.print()
    })
  } catch (error) {
    toast.error(
      (
        error as AxiosError<{
          message?: string
        }>
      ).response?.data?.message || t('core::errors.an_unexpected_error_occurred'),
    )

    printWindow?.close()
  }
}

async function printAfterSubmit() {
  const type = resolvePrintType()

  if (!props.qintrix.isSetup.value) {
    await openPrintPreview(type)
    return
  }

  try {
    const response = (await printPreview(props.orderId, type, undefined, props.registerId, 'pdf'))
      .data.body

    if (response.printer?.qintrix_id && response.printer?.paper_size) {
      const job = await props.qintrix.createJob(
        response.content,
        response.printer.qintrix_id,
        response.printer.paper_size,
        `${props.orderId}`,
        `order-payment-${type}`,
        'pdf',
        false,
      )

      if (job) {
        return
      }
    }
  } catch {
    // Fall back to browser preview when direct printing is unavailable.
  }

  await openPrintPreview(type)
}

async function submit(withPrint = false) {
  if (loading.value || form.loading.value || submittingAttempt.value) return
  submittingAttempt.value = true
  try {
    form.state.with_print = withPrint
    form.resetErrors()
    const errors = validatePayment(
      form.state.payments,
      form.state.payment_mode,
      form.state.customer_given_amount,
      meta.value.order.due_amount.amount,
      meta.value.paymentMethods.map((method) => method.id),
      meta.value.order.precision ?? 3,
    )
    // Flush state watchers before assigning errors so the click feedback stays visible.
    await nextTick()
    for (const [path, key] of Object.entries(errors))
      form.errors.value[path] = t(`order::messages.${key}`)
    if (Object.keys(errors).length) {
      await focusError(Object.keys(errors)[0]!)
      return
    }
    if (
      await form.submit(() => storePayment(props.orderId, form.state), { showErrorToast: false })
    ) {
      emit('payment-added')
      close()
      if (withPrint) await printAfterSubmit()
    } else {
      await focusError(visibleErrors.value[0]?.[0] ?? 'general')
    }
  } finally {
    submittingAttempt.value = false
  }
}
</script>

<template>
  <VDialog
    v-model="dialogModel"
    :aria-label="t('order::orders.finalize_payment')"
    :fullscreen="smAndDown"
    max-width="1280"
    persistent
    scrollable
    width="calc(100vw - 48px)"
  >
    <VCard
      class="payment-dialog-card"
      :style="{ '--payment-primary-text': primaryText }"
    >
      <div
        v-if="loading"
        class="d-flex h-100 justify-center align-center py-16"
      >
        <VProgressCircular
          color="primary"
          indeterminate
          size="50"
        />
      </div>
      <div
        v-else
        ref="dialogContent"
        class="payment-dialog-content"
      >
        <VRow
          class="payment-layout-row"
          no-gutters
        >
          <VCol
            class="finalize-col"
            cols="12"
            md="8"
          >
            <div
              v-if="summaryErrors.length"
              ref="errorSummary"
              class="payment-error-summary"
              role="alert"
              tabindex="-1"
            >
              <strong>{{ t('order::messages.payment_review_errors') }}</strong>
              <ul>
                <li
                  v-for="[path, message] in summaryErrors"
                  :key="path"
                >
                  <button
                    type="button"
                    @click="focusError(path)"
                  >
                    {{ fieldLabel(path) }}: {{ message }}
                  </button>
                </li>
              </ul>
            </div>
            <FinalizePayment
              :active-input-path="activeInputPath"
              :final-due-amount="finalDueAmount"
              :form="form"
              :has-cash-payment="hasCashPayment"
              :meta="meta"
              :order-id="orderId"
              :quick-pay-amounts="
                meta.quickPayAmounts.length > 0 ? meta.quickPayAmounts : defaultQuickPayAmounts
              "
              @add-payment="addPayment"
              @calculator-apply="applyCalculatorResultToActiveInput"
              @keypad-input="appendKeyToActiveInput"
              @quick-pay="applyQuickPayToActiveInput"
              @set-active-input="activeInputPath = $event"
            />
          </VCol>
          <VCol
            class="summary-col"
            cols="12"
            md="4"
          >
            <OrderSummary
              :change-return="form.state.change_return"
              :current-total-paid="currentTotalPaid"
              :customer-given-amount="form.state.customer_given_amount"
              :final-due-amount="finalDueAmount"
              :form="form"
              :has-cash-payment="hasCashPayment"
              :order="meta.order"
              @close="close"
              @submit="submit"
            />
          </VCol>
        </VRow>
      </div>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.payment-dialog-card {
  border-radius: 18px !important;
  overflow: hidden;
  max-height: 92dvh;
  color: rgb(var(--v-theme-on-surface));

  :deep(.v-btn.v-btn--disabled),
  :deep(.payment-mode:disabled) {
    opacity: 1 !important;
    background: color-mix(
      in srgb,
      rgb(var(--v-theme-on-surface)) 7%,
      rgb(var(--v-theme-surface))
    ) !important;
    color: color-mix(
      in srgb,
      rgb(var(--v-theme-on-surface)) 65%,
      rgb(var(--v-theme-surface))
    ) !important;
    border: 1px solid rgba(var(--v-theme-on-surface), 0.12) !important;
    box-shadow: none !important;
    transform: none;
  }

  :deep(.v-btn--disabled .v-btn__content),
  :deep(.v-btn--disabled .v-btn__prepend),
  :deep(.v-btn--disabled .v-btn__append) {
    opacity: 1;
  }

  :deep(.v-btn--disabled .v-btn__overlay),
  :deep(.v-btn--disabled .v-btn__underlay) {
    opacity: 0;
  }
}
.payment-dialog-content {
  display: flex;
  flex-direction: column;
  min-height: 0;
  overflow: hidden;
}
.payment-layout-row {
  flex: 1;
  min-height: 0;
  overflow: hidden;
  flex-wrap: nowrap;
}
.finalize-col {
  overflow-y: auto;
  min-height: 0;
}
.summary-col {
  border-inline-start: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  background: rgba(var(--v-theme-on-surface), 0.025);
  overflow: hidden;
}
.payment-error-summary {
  margin: 20px 24px 0;
  padding: 12px 16px;
  border: 1px solid rgb(var(--v-theme-error));
  border-radius: 10px;
  background: rgba(var(--v-theme-error), 0.08);
  font-size: 13px;
  ul {
    padding-inline-start: 18px;
    margin-top: 6px;
  }
  button {
    text-align: start;
    text-decoration: underline;
    color: inherit;
  }
}
@media (max-width: 959px) {
  .payment-dialog-card {
    max-height: 100dvh;
    border-radius: 0 !important;
  }
  .payment-dialog-content {
    height: 100dvh;
  }
  .payment-layout-row {
    overflow-y: auto;
    display: block;
  }
  .finalize-col {
    overflow: visible;
  }
  .summary-col {
    border-inline-start: 0;
    border-top: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  }
}
</style>
