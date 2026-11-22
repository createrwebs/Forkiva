<script lang="ts" setup>
import type { CartItem, CartItemAction, UseCart } from '@/modules/cart/composables/cart.ts'
import type { PosForm } from '@/modules/pos/contracts/posViewer.ts'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { PosSoundAction } from '@/modules/pos/enums/posSound'
import BtnProductVoid from '@/modules/pos/pages/admin/viewer/Pos/OrderPanel/CartItems/BtnProductVoid.vue'
import ProductDialog from '@/modules/pos/pages/admin/viewer/Pos/MenuPanel/Products/Dialogs/ProductDialog.vue'
import ProductThumbnail from '@/modules/pos/pages/admin/viewer/Pos/MenuPanel/Products/ProductThumbnail.vue'

const props = defineProps<{
  cartItem: CartItem
  form: PosForm
  cart: UseCart
}>()

const { updateItem, deleteItem, storeAction, removeAction, getItemQuantity, isItemMutating } =
  props.cart
const { t } = useI18n()

const open = ref<boolean>(false)
const optionDialog = ref(false)
const draftLine = computed(() =>
  props.cart.draft.value?.lines.find((line) => line.line_id === props.cartItem.id),
)

/** Open the shared product-options dialog without mutating the saved line. */
function editOptions() {
  if (!itemLocked.value) optionDialog.value = true
}

const hasLoyaltyGift = computed(() => props.cartItem?.loyaltyGift)
const enableEditOrRemoveItem = computed(
  () =>
    !props.cartItem.orderProduct?.status || props.cartItem.orderProduct?.status?.id === 'pending',
)
const allowCancelProduct = computed(
  () =>
    !hasLoyaltyGift.value &&
    props.cartItem.orderProduct?.status?.id === 'preparing' &&
    getActionNumberOfQuantity('cancel') < props.cartItem.qty,
)
const allowRefundProduct = computed(
  () =>
    !hasLoyaltyGift.value &&
    props.cartItem.orderProduct &&
    ['ready', 'served'].includes(props.cartItem.orderProduct?.status?.id || '') &&
    getActionNumberOfQuantity('refund') < props.cartItem.qty,
)
const isDisabled = computed(
  () =>
    props.cartItem.orderProduct &&
    ['cancelled', 'refunded'].includes(props.cartItem.orderProduct?.status?.id || ''),
)
const disabledRemoveProduct = computed(
  () =>
    props.form.mode === 'edit' &&
    hasLoyaltyGift.value !== null &&
    hasLoyaltyGift.value !== undefined,
)
const displayedQty = computed(() => getItemQuantity(props.cartItem.id, props.cartItem.qty))
const loadingQuantity = computed(() => isItemMutating(props.cartItem.id, 'quantity'))
const loadingDelete = computed(() => isItemMutating(props.cartItem.id, 'delete'))
const loadingDeleteAction = computed(() => isItemMutating(props.cartItem.id, 'action'))
const loadingAction = computed(() => isItemMutating(props.cartItem.id, 'action'))
const itemBusy = computed(() => isItemMutating(props.cartItem.id))
const itemLocked = computed(
  () =>
    props.cart.processing.value ||
    loadingDelete.value ||
    loadingAction.value ||
    loadingDeleteAction.value ||
    (props.cartItem.item.id !== null &&
      props.cartItem.item.id !== undefined &&
      props.cart.isProductMutating(props.cartItem.item.id)),
)

const disabledIncreaseButton = computed(
  () =>
    (hasLoyaltyGift.value !== null && hasLoyaltyGift.value !== undefined) ||
    !enableEditOrRemoveItem.value ||
    itemLocked.value,
)
const disabledDecreaseButton = computed(
  () =>
    (hasLoyaltyGift.value !== null && hasLoyaltyGift.value !== undefined) ||
    !enableEditOrRemoveItem.value ||
    displayedQty.value === 1 ||
    itemLocked.value,
)

const hasOptions = computed(() => (props.cartItem.options?.length ?? 0) > 0)

function toggle() {
  if (hasOptions.value && !isDisabled.value) open.value = !open.value
}

async function updateQty(qty: number) {
  if (qty < 1 || !enableEditOrRemoveItem.value || itemLocked.value) {
    return
  }

  await updateItem(props.cartItem.id, qty)
}

async function removeItem() {
  if (isItemMutating(props.cartItem.id)) return
  await deleteItem(props.cartItem.id)
}

function getActionNumberOfQuantity(action: string): number {
  const actionObject = props.cartItem.actions?.find(
    (actionObject: CartItemAction) => actionObject.id === action,
  )
  return actionObject?.quantity ?? 0
}

async function refundProduct(quantity: number) {
  if (itemBusy.value) return
  await storeAction(props.cartItem.id, 'refund', quantity)
}

async function cancelProduct(quantity: number) {
  if (itemBusy.value) return
  await storeAction(props.cartItem.id, 'cancel', quantity)
}

function getActionLabel(action: string) {
  switch (action) {
    case 'cancel': {
      return t('admin::resource.cancel', { resource: t('product::products.product') })
    }
    case 'refund': {
      return t('admin::resource.refund', { resource: t('product::products.product') })
    }
    default: {
      return action
    }
  }
}

async function deleteAction(id: string) {
  if (itemBusy.value) return
  await removeAction(props.cartItem.id, id)
}
</script>

<template>
  <VCard
    class="cart-item"
    :disabled="isDisabled"
    :ripple="false"
    :link="hasOptions && !isDisabled"
    :aria-expanded="hasOptions ? open : undefined"
    v-bind="hasOptions ? { onClick: toggle } : {}"
  >
    <ProductDialog
      v-if="draftLine"
      v-model="optionDialog"
      :product="draftLine.product"
      :cart="cart"
      :line-id="cartItem.id"
      :initial-options="draftLine.options"
      @click.stop
    />
    <VCardText class="pos-cart-item-body">
      <p
        v-for="message in cartItem.validationErrors"
        :key="message"
        role="alert"
        class="text-error"
      >
        {{ message }}
      </p>
      <div class="cart-item-content">
        <ProductThumbnail
          :src="(cartItem.item as { thumbnail?: string }).thumbnail"
          class="pos-cart-thumbnail"
        />
        <div class="pos-cart-copy">
          <span class="cart-item-name ga-1">
            <VIcon
              v-if="cartItem.loyaltyGift"
              color="error"
              icon="tabler-gift"
            />
            {{ cartItem.item.name }}
          </span>

          <div
            v-if="!open && cartItem.options?.length"
            class="pos-cart-options"
          >
            {{
              cartItem.options
                .flatMap((opt) => opt.values.map((value) => value.label))
                .filter(Boolean)
                .join(' · ')
            }}
          </div>

          <VBtn
            v-if="draftLine?.product.options?.length && enableEditOrRemoveItem && !hasLoyaltyGift"
            size="x-small"
            class="pos-cart-edit-options"
            prepend-icon="tabler-adjustments-horizontal"
            variant="tonal"
            color="primary"
            :disabled="itemLocked"
            @click.stop="editOptions"
          >
            {{ t('cart::local.edit_options') }}
          </VBtn>

          <span class="cart-item-price cart-item-unit">
            {{ cartItem.unitPrice.formatted }} × {{ displayedQty }}
          </span>
        </div>
        <div class="pos-cart-pricing">
          <span class="cart-item-price cart-item-total">
            {{ cartItem.total.formatted }}
          </span>

          <div
            class="qty-box"
            :aria-busy="loadingQuantity"
          >
            <v-btn
              :disabled="disabledDecreaseButton"
              :aria-label="`${t('pos::pos_viewer.quantity')} − ${cartItem.item.name}`"
              icon="tabler-minus"
              size="small"
              variant="text"
              :data-pos-sound-action="PosSoundAction.ItemUpdated"
              @click.stop.prevent="updateQty(displayedQty - 1)"
            />

            <span class="pos-cart-quantity">{{ displayedQty }}</span>

            <VProgressCircular
              v-if="loadingQuantity"
              class="me-1"
              color="primary"
              indeterminate
              size="16"
              width="1.8"
            />

            <v-btn
              :disabled="disabledIncreaseButton"
              :aria-label="`${t('pos::pos_viewer.quantity')} + ${cartItem.item.name}`"
              icon="tabler-plus"
              size="small"
              variant="text"
              :data-pos-sound-action="PosSoundAction.ItemUpdated"
              @click.stop.prevent="updateQty(displayedQty + 1)"
            />
          </div>
        </div>

        <VTooltip
          v-if="enableEditOrRemoveItem"
          :text="t('pos::pos_viewer.remove_item')"
        >
          <template #activator="{ props: tooltipProps }">
            <VBtn
              class="pos-cart-remove"
              :aria-label="t('pos::pos_viewer.remove_item')"
              color="error"
              :disabled="disabledRemoveProduct || !enableEditOrRemoveItem || itemBusy"
              icon="tabler-trash"
              :loading="loadingDelete"
              v-bind="tooltipProps"
              variant="text"
              :data-pos-sound-action="PosSoundAction.ItemRemoved"
              @click.stop.prevent="removeItem"
            />
          </template>
        </VTooltip>

        <BtnProductVoid
          v-if="allowCancelProduct"
          action="cancel"
          :cart-item="cartItem"
          color="error"
          :disabled="itemBusy"
          icon="tabler-x"
          :label="t('admin::resource.cancel', { resource: t('product::products.product') })"
          :loading="loadingAction"
          @on-submit="cancelProduct"
        />

        <BtnProductVoid
          v-if="allowRefundProduct"
          action="refund"
          :cart-item="cartItem"
          color="error"
          :disabled="itemBusy"
          icon="tabler-arrow-back-up"
          :label="t('admin::resource.refund', { resource: t('product::products.product') })"
          :loading="loadingAction"
          @on-submit="refundProduct"
        />
      </div>

      <div
        v-if="cartItem.actions.length > 0"
        class="item-actions d-flex flex-wrap"
      >
        <div
          v-for="(act, idx) in cartItem.actions"
          :key="idx"
          class="action-tag d-inline-flex align-center pl-2"
        >
          <span class="text-caption me-">{{ getActionLabel(act.id) }} {{ act.quantity }}</span>

          <v-btn
            color="error"
            :disabled="itemBusy"
            icon="tabler-trash"
            :loading="loadingDeleteAction"
            size="small"
            variant="text"
            @click.stop.prevent="deleteAction(act.id)"
          />
        </div>
      </div>

      <div
        v-if="open && hasOptions"
        class="cart-item-details"
      >
        <div
          v-if="cartItem.taxTotal.amount > 0"
          class="detail-line"
        >
          {{ t('pos::pos_viewer.total_taxes') }}: {{ cartItem.taxTotal.formatted }}
        </div>
        <div
          v-for="opt in cartItem.options"
          :key="opt.id"
          class="detail-line"
        >
          <strong>{{ opt.name }}:</strong>

          <span class="item-option-value">
            {{ opt.values.map((v) => v.label).join(', ') }}
          </span>
        </div>
      </div>
    </VCardText>
  </VCard>
</template>
