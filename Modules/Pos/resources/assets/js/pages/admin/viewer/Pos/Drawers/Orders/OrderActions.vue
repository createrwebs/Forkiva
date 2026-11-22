<script lang="ts" setup>
import type { useQintrix } from '@/modules/printer/composables/qintrix.ts'
import type { AxiosError } from 'axios'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import PosExtensionRenderer from '@/modules/pos/components/PosExtensionRenderer.vue'
import { useOrder } from '@/modules/order/composables/order.ts'
import { inject } from 'vue'
import { DraftConflict } from '@/modules/cart/services/draftStorage'
import { posCartKey } from '@/modules/pos/composables/posCartContext'

const props = defineProps<{
  order: Record<string, any>
  branchId: number | null
  isBillingMerged: boolean
  isTableViewer: boolean
  qintrix: ReturnType<typeof useQintrix>
}>()

const emit = defineEmits<{
  (e: 'refresh'): void
  (
    e: 'store-payment' | 'refund-order' | 'cancel-order' | 'view-order' | 'print-order',
    value: string | number,
  ): void
  (e: 'init-order', response: Record<string, any>): void
}>()

const { t } = useI18n()
const { can } = useAuth()
const toast = useToast()
const cart = inject(posCartKey, null)
const { getPrintMeta, moveToNextStatus, edit, printPreview } = useOrder()

const isPaid = computed(() => props.order.payment_status.id === 'paid')
const showPaymentButton = computed(
  () =>
    props.order.type.id !== 'dine_in' ||
    (props.order.type.id === 'dine_in' && props.order.next_status?.id === 'completed'),
)

const loadings = ref({
  updateStatus: false,
  edit: false,
})

const actionDisabled = computed(() => loadings.value.updateStatus || loadings.value.edit)

async function updateStatus() {
  try {
    loadings.value.updateStatus = true
    const shouldPrintKitchen =
      props.order.status?.id === 'pending' &&
      props.order.next_status?.id === 'confirmed' &&
      (!props.order.is_scheduled_order || props.order.is_scheduled_for_today)

    const response = await moveToNextStatus(props.order.id)

    if (shouldPrintKitchen) {
      await printKitchenTickets()
    }

    emit('refresh')
    toast.success(response.data.message)
  } catch (error) {
    toast.error(
      (
        error as AxiosError<{
          message?: string
        }>
      ).response?.data?.message || t('core::errors.an_unexpected_error_occurred'),
    )
  } finally {
    loadings.value.updateStatus = false
  }
}

async function editOrder() {
  if (loadings.value.edit || cart?.processing.value) return
  try {
    loadings.value.edit = true
    const activeKey = cart?.draft.value?.key
    const revision = cart?.draft.value?.revision
    const load = async () => {
      const response = (await edit(props.order.id)).data.body
      if (cart && cart.draft.value?.key !== activeKey) return
      if (cart && cart.draft.value?.revision !== revision)
        throw new DraftConflict(t('cart::local.conflict'))
      emit('init-order', response)
    }
    if (cart) await cart.withConfirmedCart(load)
    else await load()
  } catch (error) {
    toast.error(
      error instanceof DraftConflict
        ? error.message
        : (
            error as AxiosError<{
              message?: string
            }>
          ).response?.data?.message || t('core::errors.an_unexpected_error_occurred'),
    )
  } finally {
    loadings.value.edit = false
  }
}

function storePayment() {
  if (can('admin.orders.receive_payment')) {
    emit('store-payment', props.order.reference_no)
  }
}

function viewOrder() {
  if (can('admin.orders.show')) {
    emit('view-order', props.order.reference_no)
  }
}

function cancelOrder() {
  if (can('admin.orders.cancel')) {
    emit('cancel-order', props.order.reference_no)
  }
}

function refundOrder() {
  if (can('admin.orders.refund')) {
    emit('refund-order', props.order.reference_no)
  }
}

function print() {
  if (can('admin.orders.print')) {
    emit('print-order', props.order.reference_no)
  }
}

async function printKitchenTickets() {
  try {
    if (!props.qintrix.isSetup.value) {
      return
    }

    const response = (await getPrintMeta(props.order.reference_no, props.branchId)).data.body
    const kitchenContents = (
      Object.values(response.contents || []) as Record<string, any>[]
    ).filter((content) => content?.type?.id === 'kitchen')

    for (const content of kitchenContents) {
      const preview = (
        await printPreview(props.order.reference_no, content.type.id, content.id, undefined, 'pdf')
      ).data.body

      if (preview.printer?.qintrix_id && preview.printer?.paper_size) {
        await props.qintrix.createJob(
          preview.content,
          preview.printer.qintrix_id,
          preview.printer.paper_size,
          `${props.order.reference_no}`,
          `order-${content.type.id}`,
          'pdf',
        )
      }
    }
  } catch (error) {
    toast.error(
      (
        error as AxiosError<{
          message?: string
        }>
      ).response?.data?.message || t('core::errors.an_unexpected_error_occurred'),
    )
  }
}
</script>

<template>
  <div
    class="actions d-flex justify-space-between"
    :class="{ 'mt-2': !isTableViewer, 'table-order-actions': isTableViewer }"
  >
    <div class="d-flex ga-2">
      <VTooltip
        v-if="can('admin.orders.show')"
        :text="t('pos::pos_viewer.order_actions.view')"
      >
        <template #activator="{ props: tooltipProps }">
          <VBtn
            :color="isTableViewer ? 'default' : 'grey'"
            :icon="isTableViewer ? false : 'tabler-eye'"
            :prepend-icon="isTableViewer ? 'tabler-eye' : undefined"
            :text="isTableViewer ? t('pos::pos_viewer.order_actions.view') : undefined"
            :aria-label="t('pos::pos_viewer.order_actions.view')"
            :rounded="!isTableViewer"
            v-bind="tooltipProps"
            :variant="isTableViewer ? 'outlined' : 'tonal'"
            @click="viewOrder"
          />
        </template>
      </VTooltip>

      <PosExtensionRenderer
        mount="order-actions"
        :order="order"
      />

      <VTooltip
        v-if="order.allow_edit && can('admin.orders.edit')"
        :text="t('pos::pos_viewer.order_actions.edit')"
      >
        <template #activator="{ props: tooltipProps }">
          <VBtn
            :color="isTableViewer ? 'default' : 'grey'"
            :disabled="actionDisabled"
            :icon="isTableViewer ? false : 'tabler-edit'"
            :prepend-icon="isTableViewer ? 'tabler-edit' : undefined"
            :text="isTableViewer ? t('pos::pos_viewer.order_actions.edit') : undefined"
            :aria-label="t('pos::pos_viewer.order_actions.edit')"
            :loading="loadings.edit"
            :rounded="!isTableViewer"
            v-bind="tooltipProps"
            :variant="isTableViewer ? 'outlined' : 'tonal'"
            @click="editOrder"
          />
        </template>
      </VTooltip>

      <VTooltip
        v-if="order.allow_cancel && can('admin.orders.cancel')"
        :text="t('pos::pos_viewer.order_actions.cancel')"
      >
        <template #activator="{ props: tooltipProps }">
          <VBtn
            :class="{ 'table-order-cancel': isTableViewer }"
            :color="isTableViewer ? 'error' : 'grey'"
            :disabled="actionDisabled"
            :icon="isTableViewer ? false : 'tabler-x'"
            :prepend-icon="isTableViewer ? 'tabler-trash' : undefined"
            :text="isTableViewer ? t('pos::pos_viewer.order_actions.cancel') : undefined"
            :aria-label="t('pos::pos_viewer.order_actions.cancel')"
            :rounded="!isTableViewer"
            v-bind="tooltipProps"
            :variant="isTableViewer ? 'text' : 'tonal'"
            @click="cancelOrder"
          />
        </template>
      </VTooltip>

      <VTooltip
        v-if="order.allow_refund && can('admin.orders.refund')"
        :text="t('pos::pos_viewer.order_actions.refund')"
      >
        <template #activator="{ props: tooltipProps }">
          <VBtn
            :class="{ 'table-order-refund': isTableViewer }"
            :color="isTableViewer ? 'error' : 'grey'"
            :disabled="actionDisabled"
            :icon="isTableViewer ? false : 'tabler-arrow-back-up'"
            :prepend-icon="isTableViewer ? 'tabler-arrow-back-up' : undefined"
            :text="isTableViewer ? t('pos::pos_viewer.order_actions.refund') : undefined"
            :aria-label="t('pos::pos_viewer.order_actions.refund')"
            :rounded="!isTableViewer"
            v-bind="tooltipProps"
            :variant="isTableViewer ? 'text' : 'tonal'"
            @click="refundOrder"
          />
        </template>
      </VTooltip>

      <VBtn
        v-if="can('admin.orders.print')"
        :color="isTableViewer ? 'default' : 'grey'"
        :disabled="actionDisabled"
        :variant="isTableViewer ? 'outlined' : 'tonal'"
        @click="print"
      >
        <VIcon
          icon="tabler-printer"
          start
        />
        {{ t('pos::pos_viewer.order_actions.print') }}
      </VBtn>
    </div>

    <div class="primary-action d-flex ga-1">
      <VBtn
        v-if="
          can('admin.orders.receive_payment') && !isBillingMerged && !isPaid && showPaymentButton
        "
        color="success"
        :disabled="actionDisabled"
        :variant="isTableViewer ? 'flat' : 'tonal'"
        @click="storePayment"
      >
        <VIcon
          icon="tabler-cash"
          start
        />
        {{
          t(
            `pos::pos_viewer.order_actions.${order.payment_status.id === 'partially_paid' ? 'complete_payment' : 'pay_now'}`,
          )
        }}
      </VBtn>

      <VBtn
        v-if="order.allow_update_status"
        :color="order.next_status.color"
        :disabled="actionDisabled"
        :loading="loadings.updateStatus"
        :variant="isTableViewer ? 'flat' : 'tonal'"
        @click="updateStatus"
      >
        <VIcon
          :icon="
            isTableViewer && order.status.id === 'preparing'
              ? 'tabler-circle-check'
              : order.next_status.icon
          "
          start
        />
        {{
          isTableViewer && order.status.id === 'preparing'
            ? t('pos::pos_viewer.table_details.mark_as_ready')
            : t(`pos::pos_viewer.order_actions.update_status.${order.status.id}`)
        }}
      </VBtn>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.table-order-actions {
  flex-wrap: wrap;
  gap: 12px;
  padding-block-start: 12px;
  border-block-start: 1px solid var(--pos-border);
  align-items: center;
}
.table-order-actions > div {
  flex-wrap: wrap;
  align-items: center;
}
.table-order-actions > .primary-action {
  margin-inline-start: auto;
}
.table-order-actions .v-btn {
  min-block-size: 40px;
  block-size: auto;
  padding-inline: 12px !important;
  min-inline-size: 0;
  font-size: 13px;
  border-radius: 8px !important;
  letter-spacing: 0;
}
.table-order-actions .v-btn--variant-outlined {
  border-color: var(--pos-border);
  color: var(--pos-text) !important;
}
.table-order-actions .table-order-cancel {
  order: 1;
}
.table-order-actions .table-order-refund {
  order: 2;
}
.table-order-actions :deep(.v-icon) {
  color: inherit;
  font-size: 18px;
}
.table-order-actions :deep(.v-btn__prepend) {
  margin-inline-end: 8px;
}
@media (max-width: 540px) {
  .table-order-actions {
    gap: 10px;
    padding-block-start: 12px;
  }
  .table-order-actions > div {
    flex: 1 1 100%;
  }
  .table-order-actions > div > .v-btn {
    flex: 1;
  }
  .table-order-actions .v-btn {
    font-size: 13px;
    padding-inline: 10px !important;
  }
}
</style>
