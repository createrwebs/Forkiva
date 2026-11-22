<script lang="ts" setup>
import { useConfirmDialog } from '@/modules/core/composables/confirmDialog'
import { submitDraft } from '@/modules/cart/services/submitDraft'
import { DraftConflict } from '@/modules/cart/services/draftStorage'
import { quote } from '@/modules/order/api/order.api'
import type { Cart, UseCart } from '@/modules/cart/composables/cart.ts'
import type { PosCustomerLoadOptions, PosForm, PosMeta } from '@/modules/pos/contracts/posViewer.ts'
import type { useQintrix } from '@/modules/printer/composables/qintrix.ts'
import { computed, nextTick, ref, toRefs, watch } from 'vue'
import { enqueueOrderPrint, pendingOrderPrints } from '@/modules/pos/services/orderPrintQueue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import { useOrder } from '@/modules/order/composables/order.ts'
import { usePosSound } from '@/modules/pos/composables/posSound'
import { PosSoundAction } from '@/modules/pos/enums/posSound'
import PosExtensionRenderer from '@/modules/pos/components/PosExtensionRenderer.vue'
import AdditionalInformation from '@/modules/pos/pages/admin/viewer/Pos/OrderPanel/AdditionalInformation.vue'
import CartItems from '@/modules/pos/pages/admin/viewer/Pos/OrderPanel/CartItems/Index.vue'
import Discount from '@/modules/pos/pages/admin/viewer/Pos/OrderPanel/Discount.vue'
import Header from '@/modules/pos/pages/admin/viewer/Pos/OrderPanel/Header.vue'
import Invoice from '@/modules/pos/pages/admin/viewer/Pos/OrderPanel/Invoice.vue'
import OrderTypes from '@/modules/pos/pages/admin/viewer/Pos/OrderPanel/OrderTypes.vue'
import RefundPaymentMethod from '@/modules/pos/pages/admin/viewer/Pos/OrderPanel/RefundPaymentMethod.vue'
import TableInfo from '@/modules/pos/pages/admin/viewer/Pos/OrderPanel/TableInfo.vue'

const props = defineProps<{
  form: PosForm
  meta: PosMeta
  cart: UseCart
  qintrix: ReturnType<typeof useQintrix>
  loadCustomers: (options?: PosCustomerLoadOptions) => Promise<void>
}>()

const emit = defineEmits<{
  (e: 'order-placed'): void
  (e: 'on-click-action', value: string): void
  (e: 'store-payment', value: string | number): void
  (e: 'reset', cart?: Cart): void
}>()

const { t } = useI18n()
const toast = useToast()
const { getPrintMeta, printPreview, store, update } = useOrder()
const { can } = useAuth()
const sound = usePosSound()

const { hasPendingMutations, data, clear } = props.cart
const { refundPaymentMethod } = toRefs(props.form)
const { order } = toRefs(props.meta)
const canReceivePayment = computed(() => can('admin.orders.receive_payment'))
const isEditMode = computed(() => props.form.mode === 'edit')
const isDeliveryOrder = computed(() => data.value.orderType?.id === 'delivery')
const toMinorUnits = (amount: number | string, precision: number) => {
  const value = Number(amount)
  if (!Number.isFinite(value)) return 0
  const [coefficient, exponent = '0'] = value.toString().split('e')

  return Math.round(Number(`${coefficient}e${Number(exponent) + precision}`))
}
const hasRequiredDeliveryAddress = computed(
  () =>
    !isDeliveryOrder.value ||
    ((!!props.cart.draft.value?.delivery?.customer_address_id ||
      !!data.value.deliveryContext?.customerAddress?.id) &&
      data.value.deliveryContext?.pricing?.available !== false),
)

const editedBalance = computed(() => {
  const precision = data.value.total.precision ?? order.value?.due_amount?.precision ?? 3
  const factor = 10 ** precision
  const currentTotalMinor = toMinorUnits(data.value.total.amount, precision)

  if (!isEditMode.value || order.value === null || order.value === undefined)
    return { currentTotalMinor, dueMinor: 0, balanceMinor: 0, factor }

  const previousTotalMinor = toMinorUnits(order.value.total.amount, precision)
  const previousDueMinor = toMinorUnits(order.value.due_amount.amount, precision)
  const paidMinor = Math.max(previousTotalMinor - previousDueMinor, 0)

  return {
    currentTotalMinor,
    dueMinor: Math.max(currentTotalMinor - paidMinor, 0),
    balanceMinor: paidMinor - currentTotalMinor,
    factor,
  }
})

const overpaidAmount = computed(() => editedBalance.value.balanceMinor / editedBalance.value.factor)

watch(
  () => isEditMode.value && order?.value !== null && order?.value !== undefined,
  (hasOrder) => {
    if (!hasOrder) {
      refundPaymentMethod.value = null
      props.form.refundGiftCardCode = null
    }
  },
  { immediate: true },
)

const hasRefundAmount = computed(() => overpaidAmount.value > 0 && isEditMode.value)
const showDetails = ref(false)
const showDiscount = ref(false)
watch(
  () => data.value.orderType?.id,
  (type) => {
    if (type && ['delivery', 'drive_thru', 'pre_order', 'catering'].includes(type))
      showDetails.value = true
  },
)
const loadingCancelOrder = ref<boolean>(false)
const pendingOrderTypeSelection = ref<string | null>(null)
const isSubmitDisabled = computed(() => {
  return (
    data.value.items.length === 0 ||
    !data.value.orderType?.id ||
    (data.value.orderType.id === 'dine_in' && !props.form.table?.id) ||
    !hasRequiredDeliveryAddress.value ||
    !props.form.registerId ||
    (hasRefundAmount.value &&
      (!props.form.refundPaymentMethod ||
        (props.form.refundPaymentMethod === 'gift_card' && !props.form.refundGiftCardCode?.trim())))
  )
})

const submitState = ref<{
  action: null | 'send_to_kitchen' | 'hold_order' | 'pay_and_fire'
  isLoading: boolean
}>({
  action: null,
  isLoading: false,
})

async function submit(action: 'send_to_kitchen' | 'hold_order' | 'pay_and_fire') {
  if (submitState.value.isLoading || props.cart.syncFailed.value) {
    return
  }

  submitState.value = { action, isLoading: true }
  try {
    await props.cart.withConfirmedCart(async () => {
      if (!isSubmitDisabled.value && (await placeOrder(action))) emit('order-placed')
    })
  } finally {
    submitState.value = { action: null, isLoading: false }
  }
}

async function placeOrder(
  action: 'send_to_kitchen' | 'hold_order' | 'pay_and_fire',
): Promise<boolean> {
  try {
    const params = {
      submit_action: action,
      menu_id: props.form.menuId,
      branch_id: props.form.branchId,
      guest_count: props.form.meta.guestCount,
      register_id: props.form.registerId,
      waiter_id: data.value.orderType?.id === 'delivery' ? null : props.form.waiter?.id,
      session_id: props.form.sessionId,
      table_id: props.form.table?.id,
      notes: props.form.meta.notes,
      refund_payment_method:
        isEditMode.value && overpaidAmount.value > 0 ? props.form.refundPaymentMethod : null,
      gift_card_code:
        hasRefundAmount.value && props.form.refundPaymentMethod === 'gift_card'
          ? props.form.refundGiftCardCode?.trim()
          : null,
      car_plate: data.value.orderType.id === 'drive_thru' ? props.form.meta.carPlate : null,
      car_description:
        data.value.orderType.id === 'drive_thru' ? props.form.meta.carDescription : null,
      scheduled_at: ['pre_order', 'catering'].includes(data.value.orderType.id as string)
        ? props.form.meta.scheduledAt
        : null,
    }
    const previousOrder = order.value
    const wasEditMode = isEditMode.value
    const response = await submitDraft(
      props.cart,
      params,
      (payload) => quote(payload, props.cart.draft.value?.order?.id),
      (payload, id) => (id ? update(id, payload) : store(payload)),
      (total, changes) =>
        useConfirmDialog({
          message: [t('cart::local.confirm_price', { total }), changes].filter(Boolean).join('\n'),
        }),
      true,
    )
    if (!response) return false

    const orderId = response.data.body.order_id
    const isScheduledOrder = !!params.scheduled_at
    const isScheduledForTodayOrder = isScheduledForToday(params.scheduled_at)
    const isHeldOrderStatusChange =
      wasEditMode && previousOrder?.status?.id === 'pending' && action === 'send_to_kitchen'
    const isPendingAfterSubmit = !wasEditMode && action !== 'send_to_kitchen'
    const shouldPrintScheduledTodayEditOrder =
      wasEditMode &&
      action === 'send_to_kitchen' &&
      isScheduledForTodayOrder &&
      previousOrder?.status?.id !== 'pending'

    if (!wasEditMode || isHeldOrderStatusChange || shouldPrintScheduledTodayEditOrder) {
      if (props.qintrix.isSetup.value && props.form.registerId) {
        const context = {
          branchId: props.form.branchId,
          registerId: props.form.registerId,
          mode: props.form.mode,
          createJob: props.qintrix.captureJobCreator(),
        }
        void enqueueOrderPrint(
          () =>
            printCreatedOrder(
              orderId,
              action,
              isScheduledOrder,
              isScheduledForTodayOrder,
              isPendingAfterSubmit,
              context,
            ),
          () =>
            toast.error(t('pos::pos_viewer.order_print_failed', { order: orderId }), {
              timeout: false,
            }),
        )
      }
    }

    if (action === 'pay_and_fire') {
      emit('store-payment', orderId)
    } else if (response.data?.message) {
      toast.success(response.data.message)
    }

    emit('reset', response.data.body.cart)
    sound.play(PosSoundAction.OrderSubmitted)
    return true
  } catch (error: any) {
    if (error?.response?.status === 409 || !error?.response) await props.cart.getCart()
    if (error instanceof DraftConflict) {
      toast.error(
        t(error.message === 'submission_pending' ? 'cart::local.pending' : 'cart::local.conflict'),
      )
    } else if (error?.response?.status === 401) {
      toast.error(t('core::errors.unauthorized'))
    } else if (error.response?.data?.message) {
      const data = error.response.data
      const errors = data.errors
      const errorKeys = typeof data.errors === 'object' ? Object.keys(errors) : []
      const firstKey = errorKeys.length > 0 ? errorKeys[0] : null
      const firstError = firstKey && Array.isArray(errors?.[firstKey]) ? errors[firstKey][0] : null
      toast.error(firstError || data.message)
    } else {
      toast.error(t('core::errors.an_unexpected_error_occurred'))
    }

    sound.play(PosSoundAction.Error)

    return false
  }
}

async function printCreatedOrder(
  orderId: string | number,
  action: 'send_to_kitchen' | 'hold_order' | 'pay_and_fire',
  isScheduledOrder: boolean,
  isScheduledForTodayOrder: boolean,
  isPendingAfterSubmit: boolean,
  context: {
    branchId: number | null
    registerId: number
    mode: string
    createJob: ReturnType<typeof props.qintrix.captureJobCreator>
  },
) {
  const response = (await getPrintMeta(orderId, context.branchId, context.registerId)).data.body
  const availableContents = Object.values(response.contents || {}) as Record<string, any>[]

  const billContents = availableContents.filter((content) => content?.type?.id === 'bill')
  const kitchenContents = availableContents.filter((content) => content?.type?.id === 'kitchen')
  const deliveryContents = availableContents.filter((content) => content?.type?.id === 'delivery')
  let printQueue: Record<string, any>[]
  const shouldPrintKitchenForScheduledToday = isScheduledForTodayOrder && !isPendingAfterSubmit
  const includeDeliveryPrint = deliveryContents.length > 0

  if (context.mode === 'edit') {
    printQueue = kitchenContents
  } else if (
    action === 'hold_order' ||
    (isScheduledOrder && !shouldPrintKitchenForScheduledToday)
  ) {
    printQueue = billContents
  } else if (action === 'pay_and_fire') {
    printQueue = kitchenContents
  } else {
    printQueue = [...kitchenContents, ...billContents]
  }

  if (includeDeliveryPrint) {
    printQueue = [...printQueue, ...deliveryContents]
  }

  for (const content of printQueue) {
    const preview = (
      await printPreview(
        orderId,
        content.type.id,
        content.type.id === 'kitchen' ? content.id : undefined,
        context.registerId,
        'pdf',
      )
    ).data.body

    if (preview.printer?.qintrix_id && preview.printer?.paper_size) {
      const job = await context.createJob(
        preview.content,
        preview.printer.qintrix_id,
        preview.printer.paper_size,
        `${orderId}`,
        `order-${content.type.id}`,
        'pdf',
        false,
      )
      if (!job) throw new Error('Print job was not acknowledged')
    }
  }
}

async function cancelOrder() {
  if (props.cart.processing.value) return
  loadingCancelOrder.value = true
  if (await clear()) {
    emit('reset')
    sound.play(PosSoundAction.Cancelled)
  }
  loadingCancelOrder.value = false
}

const amountDue = computed(() => {
  const { currentTotalMinor, dueMinor, factor } = editedBalance.value
  return dueMinor === currentTotalMinor ? 0 : dueMinor / factor
})

function isScheduledForToday(scheduledAt?: string | null) {
  if (!scheduledAt) {
    return false
  }

  const scheduledDate = String(scheduledAt).slice(0, 10)
  const today = new Date().toLocaleDateString('en-CA')

  return scheduledDate <= today
}

async function requestCustomerForOrderType(orderTypeId: string) {
  pendingOrderTypeSelection.value = null
  await nextTick()
  pendingOrderTypeSelection.value = orderTypeId
}

function clearPendingOrderTypeSelection() {
  pendingOrderTypeSelection.value = null
}
</script>

<template>
  <div class="pos-order-panel">
    <div class="pos-ticket-body">
      <div class="pos-ticket-header">
        <OrderTypes
          :cart="cart"
          :form="form"
          :meta="meta"
          @on-click-action="(action: string) => $emit('on-click-action', action)"
          @require-customer-for-order-type="requestCustomerForOrderType"
        />
        <Header
          :cart="cart"
          :form="form"
          :load-customers="loadCustomers"
          :meta="meta"
          :pending-order-type-selection="pendingOrderTypeSelection"
          @pending-order-type-resolved="clearPendingOrderTypeSelection"
        />
        <TableInfo
          v-if="form.table !== null && form.table !== undefined"
          :cart="cart"
          :form="form"
          :meta="meta"
          @select-table="$emit('on-click-action', 'table_viewer')"
        />
      </div>
      <CartItems
        :cart="cart"
        :form="form"
      />
    </div>
    <div class="pos-ticket-footer">
      <VAlert
        v-if="
          cart.recoverableDrafts.value.length && !data.items.length && !cart.draft.value?.submission
        "
        type="info"
        density="compact"
      >
        {{ t('cart::local.recovery') }}
        <VBtn
          v-for="candidate in cart.recoverableDrafts.value"
          :key="candidate.key"
          @click="cart.recover(candidate.key)"
        >
          {{ t('cart::local.restore') }} {{ new Date(candidate.updatedAt).toLocaleString() }}
        </VBtn>
      </VAlert>
      <VAlert
        v-if="cart.draft.value?.submission && !submitState.isLoading"
        type="warning"
        density="compact"
      >
        {{ t('cart::local.pending') }}
        <VBtn
          :loading="submitState.isLoading"
          @click="submit(cart.draft.value.submission.payload.submit_action)"
        >
          {{ t('pos::pos_viewer.refresh') }}
        </VBtn>
      </VAlert>
      <VAlert
        v-if="cart.syncFailed.value"
        type="error"
        density="compact"
      >
        {{ t('cart::local.storage_failed') }}
        <VBtn
          variant="text"
          @click="cart.getCart()"
        >
          {{ t('pos::pos_viewer.refresh') }}
        </VBtn>
      </VAlert>
      <div
        v-if="pendingOrderPrints"
        role="status"
        class="text-caption px-3"
      >
        {{ t('pos::pos_viewer.printing_orders', { count: pendingOrderPrints }) }}
      </div>
      <div class="pos-ticket-tools">
        <div class="pos-ticket-detail-buttons">
          <VBtn
            class="pos-ticket-tool pos-ticket-tool--details"
            prepend-icon="tabler-list-details"
            variant="flat"
            color="default"
            @click="showDetails = true"
          >
            {{ t('pos::pos_viewer.order_details') }}
          </VBtn>
          <VBtn
            class="pos-ticket-tool pos-ticket-tool--discount"
            prepend-icon="tabler-ticket"
            variant="flat"
            color="default"
            @click="showDiscount = true"
          >
            {{ t('pos::pos_viewer.discount') }} / {{ t('order::orders.voucher') }}
          </VBtn>
        </div>
      </div>
      <PosExtensionRenderer
        mount="order-panel-notices"
        :cart="cart"
        :form="form"
        :meta="meta"
      />
      <Invoice
        :amount-due="amountDue"
        :cart="cart"
        :is-edit-mode="isEditMode"
      />
      <RefundPaymentMethod
        v-if="hasRefundAmount"
        class="refund-payment-method mb-2"
        :form="form"
        :meta="meta"
        :overpaid-amount="overpaidAmount"
        :disabled="cart.processing.value"
      />
      <div
        class="pos-checkout-actions"
        role="group"
        :aria-label="t('admin::admin.buttons.actions')"
      >
        <div v-if="!isEditMode">
          <VBtn
            block
            class="pos-submit-kitchen"
            variant="flat"
            color="default"
            :disabled="
              (isSubmitDisabled && !hasPendingMutations) ||
              cart.syncFailed.value ||
              (submitState.action !== null && submitState.action !== undefined)
            "
            :loading="submitState.isLoading && submitState.action === 'send_to_kitchen'"
            size="x-large"
            @click="submit('send_to_kitchen')"
          >
            <span class="pos-action-icon"><VIcon icon="tabler-chef-hat" /></span>
            <span class="pos-action-label">{{ t('pos::pos_viewer.send_to_kitchen') }}</span>
          </VBtn>
        </div>

        <div
          v-if="
            data.orderType?.id !== 'dine_in' &&
            (!isEditMode || (isEditMode && overpaidAmount < 0)) &&
            canReceivePayment
          "
        >
          <VBtn
            block
            class="pos-submit-payment"
            variant="flat"
            color="success"
            :disabled="
              (isSubmitDisabled && !hasPendingMutations) ||
              cart.syncFailed.value ||
              (submitState.action !== null && submitState.action !== undefined)
            "
            :loading="submitState.isLoading && submitState.action === 'pay_and_fire'"
            size="x-large"
            @click="submit('pay_and_fire')"
          >
            <span class="pos-action-icon"><VIcon icon="tabler-flame" /></span>
            <span class="pos-action-label">{{ t('pos::pos_viewer.pay_and_fire') }}</span>
          </VBtn>
        </div>

        <div>
          <VBtn
            block
            class="pos-submit-cancel"
            :class="{ 'pos-submit-cancel-edit': isEditMode }"
            variant="flat"
            color="default"
            :disabled="
              (data.items.length === 0 && cart.pendingProducts.value.length === 0) ||
              cart.processing.value
            "
            :loading="loadingCancelOrder"
            size="x-large"
            @click="cancelOrder"
          >
            <span class="pos-action-icon">
              <VIcon :icon="isEditMode ? 'tabler-x' : 'tabler-trash'" />
            </span>
            <span class="pos-action-label">
              {{ t(`pos::pos_viewer.${isEditMode ? 'cancel_edit' : 'cancel_order'}`) }}
            </span>
          </VBtn>
        </div>

        <div v-if="!isEditMode">
          <VBtn
            block
            class="pos-submit-hold"
            variant="flat"
            color="default"
            :disabled="
              (isSubmitDisabled && !hasPendingMutations) ||
              cart.syncFailed.value ||
              (submitState.action !== null && submitState.action !== undefined)
            "
            :loading="submitState.isLoading && submitState.action === 'hold_order'"
            size="x-large"
            @click="submit('hold_order')"
          >
            <span class="pos-action-icon"><VIcon icon="tabler-clock-pause" /></span>
            <span class="pos-action-label">{{ t('pos::pos_viewer.hold_order') }}</span>
          </VBtn>
        </div>

        <div v-if="isEditMode">
          <VBtn
            block
            class="pos-submit-kitchen"
            variant="flat"
            color="default"
            :disabled="
              (isSubmitDisabled && !hasPendingMutations) ||
              cart.syncFailed.value ||
              (submitState.action !== null && submitState.action !== undefined)
            "
            :loading="submitState.isLoading && submitState.action === 'send_to_kitchen'"
            size="x-large"
            @click="submit('send_to_kitchen')"
          >
            <span class="pos-action-icon"><VIcon icon="tabler-chef-hat" /></span>
            <span class="pos-action-label">{{ t('pos::pos_viewer.send_to_kitchen') }}</span>
          </VBtn>
        </div>
      </div>
    </div>
  </div>
  <VDialog
    v-model="showDetails"
    eager
    max-width="520"
    scrollable
  >
    <VCard class="pos-details-dialog pos-checkout-dialog">
      <VCardTitle class="pos-checkout-dialog__header">
        <span class="pos-checkout-dialog__icon">
          <VIcon
            icon="tabler-list-details"
            size="22"
          />
        </span>
        <div class="pos-checkout-dialog__heading">
          <h2>{{ t('pos::pos_viewer.order_details') }}</h2>
          <span>{{ data.orderType?.name }}</span>
        </div>
        <VBtn
          icon="tabler-x"
          variant="text"
          :aria-label="t('admin::admin.buttons.close')"
          @click="showDetails = false"
        />
      </VCardTitle>
      <VCardText class="pos-checkout-dialog__body">
        <AdditionalInformation
          :cart="cart"
          :form="form"
          :meta="meta"
        />
      </VCardText>
      <VCardActions class="pos-checkout-dialog__footer">
        <VBtn
          variant="tonal"
          color="primary"
          @click="showDetails = false"
        >
          {{ t('admin::admin.buttons.close') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
  <VDialog
    v-model="showDiscount"
    eager
    max-width="440"
    scrollable
  >
    <VCard class="pos-details-dialog pos-checkout-dialog">
      <VCardTitle class="pos-checkout-dialog__header">
        <span class="pos-checkout-dialog__icon">
          <VIcon
            icon="tabler-ticket"
            size="22"
          />
        </span>
        <div class="pos-checkout-dialog__heading">
          <h2>{{ t('pos::pos_viewer.discount') }} / {{ t('order::orders.voucher') }}</h2>
          <span>{{ t('pos::pos_viewer.order_summary') }} · {{ data.total.formatted }}</span>
        </div>
        <VBtn
          icon="tabler-x"
          variant="text"
          :aria-label="t('admin::admin.buttons.close')"
          @click="showDiscount = false"
        />
      </VCardTitle>
      <VCardText class="pos-checkout-dialog__body">
        <Discount
          :cart="cart"
          :meta="meta"
        />
      </VCardText>
    </VCard>
  </VDialog>
</template>
