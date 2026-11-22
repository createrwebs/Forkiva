<script lang="ts" setup>
import type { UseCart } from '@/modules/cart/composables/cart.ts'
import type { Product } from '@/modules/pos/contracts/posViewer.ts'
import { useI18n } from 'vue-i18n'
import ProductDialog from './Dialogs/ProductDialog.vue'
import Item from './Item.vue'

const props = defineProps<{
  products: Product[]
  cart: UseCart
  loading: boolean
  loadingMore: boolean
  hasMore: boolean
}>()

const emit = defineEmits<{
  (e: 'load-more'): void
}>()

const { t } = useI18n()
const productOptionDialog = ref<Record<string, any>>({
  open: false,
  product: null,
})

function openProductOptionDialog(product: Product) {
  productOptionDialog.value = { open: true, product: product }
}

function handleScroll(event: Event) {
  if (!props.hasMore || props.loading || props.loadingMore) {
    return
  }

  const element = event.target as HTMLElement
  const remaining = element.scrollHeight - element.scrollTop - element.clientHeight

  if (remaining <= 160) {
    emit('load-more')
  }
}
</script>

<template>
  <div
    v-if="loading && products.length === 0"
    class="pos-products-loading"
  >
    <VProgressCircular
      color="primary"
      indeterminate
      size="40"
    />
  </div>

  <div
    v-else
    class="pos-products-scroll"
    @scroll.passive="handleScroll"
  >
    <div
      v-if="!loading && products.length === 0"
      class="empty-cart-wrapper"
    >
      <VIcon icon="tabler-search" />
      <p>{{ t('admin::admin.table.no_matching_records_found') }}</p>
    </div>
    <div class="pos-product-grid">
      <Item
        v-for="product in products"
        :key="product.id"
        :cart="cart"
        :product="product"
        @open-options-dialog="openProductOptionDialog"
      />
    </div>

    <div
      v-if="loadingMore"
      class="pos-products-loading pos-products-loading--append"
    >
      <VProgressCircular
        color="primary"
        indeterminate
        size="28"
      />
    </div>

    <div
      v-else-if="hasMore"
      class="d-flex justify-center py-3"
    >
      <VBtn
        color="primary"
        variant="tonal"
        @click="emit('load-more')"
      >
        {{ t('pos::pos_viewer.load_more_products') }}
      </VBtn>
    </div>
  </div>

  <ProductDialog
    v-if="productOptionDialog.open"
    v-model="productOptionDialog.open"
    :cart="cart"
    :product="productOptionDialog.product"
  />
</template>
