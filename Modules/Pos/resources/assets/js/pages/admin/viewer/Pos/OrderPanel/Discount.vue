<script lang="ts" setup>
import type { AxiosError } from 'axios'
import type { UseCart } from '@/modules/cart/composables/cart.ts'
import type { PosMeta } from '@/modules/pos/contracts/posViewer.ts'
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  meta: PosMeta
  cart: UseCart
}>()

const { t } = useI18n()

const { applyDiscount, applyVoucher } = props.cart
const loading = ref(false)
const errorMessage = ref('')
const discount = ref<number | string | null>(null)
const discountType = ref<'discount' | 'voucher'>('discount')
const discountTypes = [
  {
    id: 'discount',
    name: t('order::orders.discount'),
    icon: 'tabler-shopping-bag-discount',
  },
  {
    id: 'voucher',
    name: t('order::orders.voucher'),
    icon: 'tabler-ticket',
  },
]

const submit = async () => {
  if (!discount.value || loading.value || props.cart.processing.value) return
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await (discountType.value === 'voucher'
      ? applyVoucher(discount.value as string)
      : applyDiscount(discount.value as number))
    if (response) discount.value = null
  } catch (error) {
    const data = (error as AxiosError<{ message?: string; errors?: Record<string, string[]> }>)
      .response?.data
    errorMessage.value =
      Object.values(data?.errors || {}).flat()[0] ||
      data?.message ||
      t('core::errors.an_unexpected_error_occurred')
  } finally {
    loading.value = false
  }
}

const updateDiscountType = (type: Record<string, any>) => {
  if (loading.value || discountType.value === type.id) return
  discountType.value = type.id
  discount.value = null
  errorMessage.value = ''
}

watch(discount, () => (errorMessage.value = ''))
</script>

<template>
  <div class="pos-discount-picker">
    <div
      class="pos-discount-tabs"
      :aria-label="t('pos::pos_viewer.discount')"
    >
      <button
        v-for="type in discountTypes"
        :key="type.id"
        type="button"
        :aria-pressed="type.id === discountType"
        :class="{ active: type.id === discountType }"
        :disabled="loading"
        @click="updateDiscountType(type)"
      >
        <VIcon
          :icon="type.icon"
          size="20"
        />
        <span>{{ type.name }}</span>
        <VIcon
          v-if="type.id === discountType"
          icon="tabler-circle-check-filled"
          size="17"
        />
      </button>
    </div>
    <div
      v-if="cart.data.value.discount?.id"
      class="pos-discount-applied"
      role="status"
    >
      <VIcon
        icon="tabler-circle-check"
        size="20"
      />
      <span>{{ cart.data.value.discount.name }}</span>
      <strong>− {{ cart.data.value.discount.value?.formatted }}</strong>
    </div>
    <div class="pos-discount-input">
      <VTextField
        v-if="discountType === 'voucher'"
        v-model="discount"
        clearable
        density="compact"
        prepend-inner-icon="tabler-ticket"
        :label="t('pos::pos.enter_voucher_code')"
        :error-messages="errorMessage"
        :readonly="loading"
        hide-details="auto"
      />
      <VSelect
        v-else
        v-model="discount"
        density="compact"
        prepend-inner-icon="tabler-shopping-bag-discount"
        item-title="name"
        item-value="id"
        :items="meta.discounts"
        :label="t('pos::pos.discount')"
        :error-messages="errorMessage"
        :readonly="loading"
        hide-details="auto"
      />
    </div>
    <VBtn
      class="pos-discount-apply"
      color="primary"
      block
      height="44"
      :disabled="!discount || loading || cart.processing.value"
      :loading="loading"
      prepend-icon="tabler-check"
      @click="submit"
    >
      {{ t('pos::pos_viewer.apply') }}
    </VBtn>
  </div>
</template>
