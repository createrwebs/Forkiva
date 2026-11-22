<script lang="ts" setup>
import type { UseCart } from '@/modules/cart/composables/cart.ts'
import type { Product } from '@/modules/pos/contracts/posViewer.ts'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { PosSoundAction } from '@/modules/pos/enums/posSound'
import ProductThumbnail from './ProductThumbnail.vue'

const emit = defineEmits(['open-options-dialog'])

const props = defineProps<{
  product: Product
  cart: UseCart
}>()

const { t } = useI18n()
const { storeItem, showError, isProductMutating } = props.cart

const hasOptions = computed(() => (props.product.options?.length ?? 0) > 0)
const hasDiscount = computed(
  () => (props.product.selling_price?.amount ?? 0) < (props.product.price?.amount ?? 0),
)
const loading = computed(() => isProductMutating(props.product.id))

async function addProductToCart() {
  if (props.cart.processing.value) {
    return
  }
  if (hasOptions.value) {
    emit('open-options-dialog', props.product)
  } else {
    try {
      await storeItem(
        {
          product_id: props.product.id,
          options: {},
          qty: 1,
        },
        props.product,
      )
    } catch (error: unknown) {
      showError(error)
    }
  }
}
</script>

<template>
  <button
    type="button"
    class="pos-product"
    :class="{ 'pos-product--loading': loading }"
    :disabled="cart.processing.value"
    :aria-label="`${product.name} — ${product.selling_price?.formatted || product.price?.formatted}`"
    :aria-haspopup="hasOptions ? 'dialog' : undefined"
    :data-pos-sound-action="hasOptions ? PosSoundAction.Interaction : PosSoundAction.ItemAdded"
    @click="addProductToCart"
  >
    <div class="pos-product-top">
      <ProductThumbnail
        v-if="product.thumbnail"
        :src="product.thumbnail"
      />
      <span
        v-else
        class="pos-product-symbol"
      >
        <VIcon
          icon="tabler-tools-kitchen-2"
          size="23"
        />
      </span>
      <span
        v-if="product.is_new"
        class="pos-product-badge"
      >
        {{ t('pos::pos_viewer.new') }}
      </span>
      <span
        v-if="hasOptions"
        class="pos-product-option-hint"
      >
        {{ t('option::options.options') }}
        <VIcon
          icon="tabler-adjustments-horizontal"
          size="14"
        />
      </span>
    </div>
    <span class="pos-product-name">{{ product.name }}</span>
    <div class="pos-product-bottom">
      <div
        class="pos-product-prices"
        :class="{ 'pos-product-prices--discounted': hasDiscount }"
      >
        <strong>
          {{ hasDiscount ? product.selling_price?.formatted : product.price?.formatted }}
        </strong>
        <del v-if="hasDiscount">{{ product.price?.formatted }}</del>
      </div>
      <span
        class="pos-product-add"
        aria-hidden="true"
      >
        <VIcon
          icon="tabler-plus"
          size="21"
        />
      </span>
    </div>
    <div
      v-if="loading"
      class="pos-product-sync"
    >
      <VProgressCircular
        indeterminate
        color="primary"
        size="28"
      />
    </div>
  </button>
</template>
