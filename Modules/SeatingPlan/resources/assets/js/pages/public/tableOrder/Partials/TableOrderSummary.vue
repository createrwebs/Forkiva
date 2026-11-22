<script lang="ts" setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { Cart, CartItem } from '@/modules/cart/composables/cart.ts'
import { summarizeCartTaxes } from '@/modules/cart/utils/taxSummary.ts'
import TaxSummaryRows from '@/modules/tax/components/TaxSummaryRows.vue'

type CartItemOption = NonNullable<CartItem['options']>[number]

const props = defineProps<{
  cart: Partial<Cart> | null
  tableName: string
  totalLabel?: string
  allowDiscountCode: boolean
  canSubmit: boolean
  busy: boolean
  submitting: boolean
  discountLoading: boolean
  discountError: string | null
  inlineError: string | null
  closable?: boolean
}>()
const discountCode = defineModel<string>('discountCode', { required: true })
defineEmits<{
  (event: 'change-qty', item: CartItem, delta: number): void
  (event: 'remove-item', item: CartItem): void
  (event: 'edit-options', item: CartItem): void
  (event: 'apply-discount'): void
  (event: 'remove-discount'): void
  (event: 'clear-discount-error'): void
  (event: 'review'): void
  (event: 'close'): void
}>()
const { t } = useI18n()
const expandedItemIds = ref(new Set<string>())
const cartItems = computed(() => props.cart?.items ?? [])
const cartQuantity = computed(() => props.cart?.quantity ?? 0)
const cartTotal = computed(() => props.cart?.total?.formatted ?? '0')
const cartSubTotal = computed(() => props.cart?.subTotal?.formatted)
const cartTaxes = computed(() =>
  summarizeCartTaxes({
    items: cartItems.value,
    taxes: props.cart?.taxes ?? [],
  }),
)
const cartCharges = computed(() => props.cart?.charges ?? [])
const cartDiscount = computed(() => props.cart?.discount)
const hasCartDiscount = computed(
  () =>
    Boolean(cartDiscount.value?.id) &&
    Number((cartDiscount.value?.value ?? cartDiscount.value?.amount)?.amount ?? 0) > 0,
)
const canApplyDiscountCode = computed(
  () => props.allowDiscountCode && cartItems.value.length > 0 && !hasCartDiscount.value,
)
const hasCartBreakdown = computed(() => cartItems.value.length > 0)
function moneyFormatted(value: { formatted: string } | undefined) {
  return value?.formatted ?? '—'
}
function discountFormatted(discount: Cart['discount']) {
  return (discount?.value ?? discount?.amount)?.formatted ?? '—'
}
function amountFormatted(row: { amount: { formatted: string } }) {
  return row.amount.formatted
}
function cartItemId(item: CartItem) {
  return String(item.id)
}
function optionPanelId(item: CartItem) {
  return `cart-item-options-${cartItemId(item).replace(/[^a-zA-Z0-9_-]/g, '-')}`
}
function normalizeValues<T>(value: T[] | Record<string, T> | null | undefined): T[] {
  if (Array.isArray(value)) {
    return value
  }

  return value && typeof value === 'object' ? Object.values(value) : []
}

function itemOptions(item: CartItem): CartItemOption[] {
  return normalizeValues(item.options as CartItemOption[] | Record<string, CartItemOption>)
}
function optionValueLabels(option: CartItemOption) {
  return normalizeValues(option.values).map((value) => value.label).filter(Boolean).join(', ')
}
function hasItemOptions(item: CartItem) {
  return itemOptions(item).length > 0
}
function isItemExpanded(item: CartItem) {
  return expandedItemIds.value.has(cartItemId(item))
}
function toggleItemOptions(item: CartItem) {
  const itemId = cartItemId(item)
  const next = new Set(expandedItemIds.value)

  if (next.has(itemId)) {
    next.delete(itemId)
  } else {
    next.add(itemId)
  }

  expandedItemIds.value = next
}
</script>

<template>
  <VCard
    class="sheet-card order-sheet"
    :aria-label="t('seatingplan::tables.table_order.your_order')"
  >
    <header class="sheet-header">
      <div class="sheet-header__copy">
        <h2>{{ t('seatingplan::tables.table_order.your_order') }}</h2>
        <p class="order-sheet__context">
          {{ tableName }} · {{ t('seatingplan::tables.table_order.items_count', cartQuantity) }}
        </p>
      </div>
      <VBtn
        v-if="closable"
        :aria-label="t('seatingplan::tables.table_order.close')"
        icon="tabler-x"
        size="small"
        variant="text"
        @click="$emit('close')"
      />
    </header>

    <VCardText class="sheet-body">
      <VAlert
        v-if="inlineError"
        class="mb-4"
        type="error"
        variant="tonal"
      >
        {{ inlineError }}
      </VAlert>

      <div
        v-if="cartItems.length === 0"
        class="empty-cart"
      >
        <div class="empty-cart__mark">
          <VIcon
            icon="tabler-shopping-bag"
            size="28"
          />
        </div>

        <h3>{{ t('seatingplan::tables.table_order.cart_empty_title') }}</h3>
        <p>{{ t('seatingplan::tables.table_order.cart_empty_message') }}</p>
      </div>

      <div
        v-else
        class="order-sheet__items"
      >
        <article
          v-for="item in cartItems"
          :key="item.id"
          class="cart-item"
        >
          <div class="cart-item__content">
            <div class="cart-item__headline">
              <div class="cart-item__name">{{ item.item?.name }}</div>
              <strong>{{ moneyFormatted(item.total) }}</strong>
            </div>
          </div>

          <p v-for="message in item.validationErrors" :key="message" role="alert" class="text-error">{{message}}</p>
          <VBtn v-if="hasItemOptions(item)" size="small" variant="text" :disabled="busy" @click="$emit('edit-options',item)">{{t('cart::local.edit_options')}}</VBtn>
          <div class="cart-item__controls">
            <button
              v-if="hasItemOptions(item)"
              class="cart-item__options-toggle"
              type="button"
              :aria-controls="optionPanelId(item)"
              :aria-expanded="isItemExpanded(item)"
              @click="toggleItemOptions(item)"
            >
              <span>
                {{ t('seatingplan::tables.table_order.options') }}
                <small>{{ itemOptions(item).length }}</small>
              </span>
              <VIcon
                icon="tabler-chevron-down"
                size="16"
              />
            </button>

            <div class="cart-item__actions">
              <div class="qty-stepper qty-stepper--compact">
                <VBtn
                  class="qty-stepper__button"
                  color="primary"
                  icon
                  size="small"
                  variant="text"
                  :aria-label="
                    t('seatingplan::tables.table_order.decrease_quantity', {
                      product: item.item?.name,
                    })
                  "
                  :disabled="busy"
                  @click="$emit('change-qty', item, -1)"
                >
                  <VIcon icon="tabler-minus" />
                </VBtn>

                <strong>{{ item.qty }}</strong>

                <VBtn
                  class="qty-stepper__button"
                  color="primary"
                  icon
                  size="small"
                  variant="text"
                  :aria-label="
                    t('seatingplan::tables.table_order.increase_quantity', {
                      product: item.item?.name,
                    })
                  "
                  :disabled="busy"
                  @click="$emit('change-qty', item, 1)"
                >
                  <VIcon icon="tabler-plus" />
                </VBtn>
              </div>
              <VBtn
                class="cart-item__remove"
                icon
                color="secondary"
                size="small"
                variant="outlined"
                :disabled="busy"
                :aria-label="
                  t('seatingplan::tables.table_order.remove_product', { product: item.item?.name })
                "
                @click="$emit('remove-item', item)"
              >
                <VIcon
                  icon="tabler-trash"
                  size="16"
                />
              </VBtn>
            </div>
          </div>

          <div
            v-if="hasItemOptions(item)"
            v-show="isItemExpanded(item)"
            :id="optionPanelId(item)"
            class="cart-item__options"
          >
            <div
              v-for="option in itemOptions(item)"
              :key="option.id"
              class="cart-item__option"
            >
              <span>{{ option.name }}</span>
              <strong>{{ optionValueLabels(option) }}</strong>
            </div>
          </div>
        </article>
      </div>
    </VCardText>

    <div
      v-if="canApplyDiscountCode || hasCartBreakdown"
      class="order-sheet__breakdown"
    >
      <form
        v-if="canApplyDiscountCode"
        class="discount-code-form"
        @submit.prevent="$emit('apply-discount')"
      >
        <VTextField
          v-model="discountCode"
          :aria-label="t('seatingplan::tables.table_order.discount_code')"
          class="discount-code-form__field"
          :disabled="busy"
          :error="!!discountError"
          :error-messages="discountError"
          hide-details="auto"
          :placeholder="t('seatingplan::tables.table_order.discount_code')"
          @update:model-value="$emit('clear-discount-error')"
        />

        <VBtn
          class="discount-code-form__button"
          color="primary"
          variant="outlined"
          :disabled="busy || !discountCode.trim()"
          :loading="discountLoading"
          type="submit"
        >
          {{ t('seatingplan::tables.table_order.apply') }}
        </VBtn>
      </form>

      <div
        v-if="hasCartBreakdown"
        class="sheet-summary"
      >
        <div
          v-if="cartSubTotal"
          class="sheet-summary__row"
        >
          <span>{{ t('seatingplan::tables.table_order.subtotal') }}</span>
          <strong>{{ cartSubTotal }}</strong>
        </div>

        <div
          v-if="hasCartDiscount"
          class="sheet-summary__row sheet-summary__row--discount"
        >
          <span>
            {{ t('seatingplan::tables.table_order.discount') }}
            <em v-if="cartDiscount?.name">({{ cartDiscount.name }})</em>
          </span>

          <div class="sheet-summary__value-action">
            <strong>- {{ discountFormatted(cartDiscount) }}</strong>

            <VBtn
              color="error"
              :disabled="busy"
              icon
              :loading="discountLoading"
              size="x-small"
              variant="text"
              :aria-label="t('seatingplan::tables.table_order.remove_discount')"
              @click="$emit('remove-discount')"
            >
              <VIcon icon="tabler-x" />
            </VBtn>
          </div>
        </div>

        <TaxSummaryRows :taxes="cartTaxes" />

        <div
          v-for="charge in cartCharges"
          :key="charge.code || charge.name"
          class="sheet-summary__row"
        >
          <span>{{ charge.name }}</span>
          <strong>{{ amountFormatted(charge) }}</strong>
        </div>
      </div>
    </div>

    <footer class="sheet-footer">
      <div
        class="sheet-total"
        aria-live="polite"
      >
        <span>{{ totalLabel || t('seatingplan::tables.table_order.total') }}</span>
        <strong>{{ cartTotal }}</strong>
      </div>

      <VBtn
        block
        class="dialog-primary-action"
        color="primary"
        :disabled="!canSubmit || busy"
        :loading="submitting"
        size="large"
        variant="flat"
        @click="$emit('review')"
      >
        {{ t('seatingplan::tables.table_order.review_order') }}
        <VIcon
          icon="tabler-arrow-right"
          end
          class="order-sheet__next"
        />
      </VBtn>
    </footer>
  </VCard>
</template>
