<script lang="ts" setup>
import type { UseCart } from '@/modules/cart/composables/cart.ts'
import { computed, ref, toRaw, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { PosSoundAction } from '@/modules/pos/enums/posSound'
import Options from '@/modules/pos/pages/admin/viewer/Pos/MenuPanel/Products/Dialogs/Options.vue'
import ProductThumbnail from '@/modules/pos/pages/admin/viewer/Pos/MenuPanel/Products/ProductThumbnail.vue'

const props = defineProps<{
  modelValue: boolean
  product: any
  cart: UseCart
  lineId?: string
  initialOptions?: Record<string, any>
}>()
const emit = defineEmits(['update:modelValue', 'saved'])

const { t } = useI18n()

const { storeItem, showError, isProductMutating } = props.cart
const formOptions = ref<Record<string, any>>({})
const selectedOptions = ref<Record<string, any>>({})
const errors = ref<Record<string, string>>({})
const loading = ref(false)
const isEditing = computed(() => props.lineId !== undefined)
const productLoading = computed(
  () => loading.value || props.cart.processing.value || isProductMutating(props.product.id),
)

/** Start each opening from the saved selections; cancelling never changes the draft. */
watch(
  () => props.modelValue,
  (visible) => {
    if (!visible) return
    const options = structuredClone(props.initialOptions ? toRaw(props.initialOptions) : {})
    formOptions.value = Object.fromEntries(
      Object.entries(options).map(([id, value]) => [id, { [id]: value }]),
    )
    selectedOptions.value = options
    errors.value = {}
  },
  { immediate: true },
)

watch(
  () => formOptions.value,
  (newValue) => {
    selectedOptions.value = {}
    if (newValue) {
      for (const key of Object.keys(newValue)) {
        const optionData = props.product.options.find(
          (option: Record<string, any>) => option.id === Number.parseInt(key),
        )
        selectedOptions.value[optionData.id] = newValue[key][key]
      }
    }
  },
  { deep: true },
)

function close() {
  emit('update:modelValue', false)
}

/** Apply locally validated selections to the existing line or add a new product. */
async function saveProductOptions() {
  if (productLoading.value) {
    return
  }
  try {
    loading.value = true
    const response = props.lineId
      ? await props.cart.updateItemOptions(props.lineId, selectedOptions.value)
      : await storeItem(
          {
            product_id: props.product.id,
            options: selectedOptions.value,
            qty: 1,
          },
          props.product,
        )
    if (response) {
      close()
    }
  } catch (error: any) {
    if (error?.response?.status === 422 && error.response?.data?.errors) {
      const apiErrors = error.response.data.errors
      for (const field in apiErrors) {
        errors.value[field] = apiErrors[field][0]
      }
    } else {
      showError(error)
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <VDialog
    max-width="480"
    class="pos-product-dialog-overlay"
    :model-value="modelValue"
    :persistent="productLoading"
    :aria-label="product.name"
    scrollable
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard class="pos-product-dialog">
      <div class="pos-product-dialog-header">
        <ProductThumbnail :src="product.thumbnail" />
        <div class="pos-product-dialog-heading">
          <h2>{{ product.name }}</h2>
          <div
            class="pos-product-prices"
            :class="{
              'pos-product-prices--discounted': product.selling_price.amount < product.price.amount,
            }"
          >
            <strong>
              {{
                product.selling_price.amount < product.price.amount
                  ? product.selling_price.formatted
                  : product.price.formatted
              }}
            </strong>
            <del v-if="product.selling_price.amount < product.price.amount">
              {{ product.price.formatted }}
            </del>
          </div>
        </div>
        <VBtn
          class="pos-product-dialog-close"
          icon="tabler-x"
          variant="text"
          :aria-label="t('admin::admin.buttons.close')"
          :disabled="productLoading"
          @click="close"
        />
      </div>
      <VCardText class="pos-product-dialog-body">
        <Options
          v-for="option in product.options"
          :key="`${option.id}:${modelValue}`"
          v-model="formOptions[option.id]"
          :errors="errors"
          :option="option"
        />
      </VCardText>
      <VCardActions class="pos-product-dialog-footer">
        <VBtn
          class="pos-product-dialog-cancel"
          variant="outlined"
          :disabled="productLoading"
          @click="close"
        >
          {{ t('admin::admin.buttons.cancel') }}
        </VBtn>
        <VBtn
          class="pos-product-dialog-add"
          color="primary"
          variant="flat"
          :disabled="productLoading"
          :loading="productLoading"
          :data-pos-sound-action="isEditing ? PosSoundAction.ItemUpdated : PosSoundAction.ItemAdded"
          @click="saveProductOptions"
        >
          <VIcon
            :icon="isEditing ? 'tabler-check' : 'tabler-shopping-cart-plus'"
            start
          />
          {{ t(isEditing ? 'cart::local.save_options' : 'pos::pos_viewer.add_to_cart') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
