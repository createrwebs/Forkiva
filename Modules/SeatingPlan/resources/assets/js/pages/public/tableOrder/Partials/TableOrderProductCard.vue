<script lang="ts" setup>
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  disabled?: boolean
  product: Record<string, any>
}>()

defineEmits<{
  (event: 'open', product: any): void
}>()

const { t } = useI18n()

function productPrice() {
  return props.product?.selling_price?.formatted || props.product?.price?.formatted || ''
}

function productOriginalPrice() {
  return props.product?.price?.formatted || props.product?.selling_price?.formatted || ''
}

function hasOffer() {
  return (
    Boolean(props.product.has_special_price) ||
    (props.product.selling_price != null &&
      props.product.price != null &&
      Number(props.product.selling_price.amount) < Number(props.product.price.amount))
  )
}

function hasOptions() {
  const options = props.product.options

  return Array.isArray(options)
    ? options.length > 0
    : Boolean(options && typeof options === 'object' && Object.keys(options).length)
}
</script>

<template>
  <button
    :aria-label="
      hasOptions()
        ? t('seatingplan::tables.table_order.choose_options_for', { product: product.name })
        : t('seatingplan::tables.table_order.add_product', { product: product.name })
    "
    class="product-card"
    type="button"
    :disabled="disabled"
    @click="$emit('open', product)"
  >
    <div class="product-card__image">
      <VImg
        v-if="product.thumbnail"
        height="100%"
        width="100%"
        :src="product.thumbnail"
        :alt="product.name"
      >
        <template #error>
          <div class="product-card__image-error">
            <VIcon
              icon="tabler-photo-off"
              size="32"
            />
          </div>
        </template>
      </VImg>

      <div
        v-else
        class="product-card__fallback"
      >
        <VIcon
          icon="tabler-bowl-spoon"
          size="34"
        />
      </div>
    </div>
    <div class="product-card__body">
      <div
        v-if="hasOffer()"
        class="product-card__badge product-card__badge--offer"
      >
        <VIcon
          icon="tabler-rosette-discount-check"
          size="14"
        />
        <span>{{ t('seatingplan::tables.table_order.offer') }}</span>
      </div>

      <div
        v-else-if="product.is_new"
        class="product-card__badge product-card__badge--new"
      >
        <span>{{ t('seatingplan::tables.table_order.new') }}</span>
      </div>
      <div class="product-card__name">{{ product.name }}</div>

      <div
        class="product-card__price"
        :class="{ 'product-card__price--offer': hasOffer() }"
      >
        <span
          v-if="hasOffer()"
          class="product-card__price-current"
        >
          {{ productPrice() }}
        </span>

        <span
          class="product-card__price-original"
          :class="{ 'is-struck': hasOffer() }"
        >
          {{ hasOffer() ? productOriginalPrice() : productPrice() }}
        </span>
      </div>

      <span
        class="product-card__add"
        aria-hidden="true"
      >
        <VIcon
          :icon="hasOptions() ? 'tabler-adjustments-horizontal' : 'tabler-shopping-bag-plus'"
          size="16"
        />
        {{
          hasOptions()
            ? t('seatingplan::tables.table_order.choose_options')
            : t('seatingplan::tables.table_order.add_to_order')
        }}
      </span>
    </div>
  </button>
</template>
