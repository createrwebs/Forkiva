<script lang="ts" setup>
import type { UseCart } from '@/modules/cart/composables/cart.ts'
import { useI18n } from 'vue-i18n'
import { formatPrice } from '@/modules/core/utils/formatters.ts'
import CartTaxSummaryRows from '@/modules/pos/components/CartTaxSummaryRows.vue'

const props = defineProps<{
  cart: UseCart
  isEditMode: boolean
  amountDue: number
}>()
const { data, removeDiscount } = props.cart
const { t } = useI18n()
</script>

<template>
  <div class="invoice-card pos-checkout-summary">
    <details class="pos-summary-details">
      <summary
        class="pos-summary-toggle"
        :aria-label="t('pos::pos_viewer.order_summary')"
      >
        <VIcon
          icon="tabler-receipt"
          size="18"
        />
        <span>{{ t('pos::pos_viewer.subtotal') }}</span>
        <strong>{{ data.subTotal.formatted }}</strong>
        <VIcon
          class="pos-summary-chevron"
          icon="tabler-chevron-down"
          size="17"
        />
      </summary>
      <div class="pos-invoice-breakdown">
        <div
          v-if="data.discount?.id"
          class="row-line discount-row"
        >
          <span class="discount-title">
            {{ t('pos::pos_viewer.discount') }}
            (
            <span class="discount-name">{{ data.discount?.name }}</span>
            )
            <VBtn
              variant="text"
              color="error"
              icon="tabler-x"
              size="x-small"
              :aria-label="t('admin::admin.buttons.clear') + ' ' + t('pos::pos_viewer.discount')"
              @click="removeDiscount"
            />
          </span>
          <span class="text-success">- {{ data.discount?.value?.formatted }}</span>
        </div>
        <CartTaxSummaryRows :cart="data" />
        <div
          v-for="charge in data.charges"
          :key="charge.code"
          class="row-line"
        >
          <span>{{ charge.name }}</span>
          <span>{{ charge.amount.formatted }}</span>
        </div>
      </div>
    </details>
    <div class="row-line total-row">
      <span>{{ t('pos::pos_viewer.total') }}</span>
      <span>{{ data.total.formatted }}</span>
    </div>
    <div
      v-if="isEditMode && amountDue > 0"
      class="row-line amount_due-row"
    >
      <span>{{ t('pos::pos_viewer.amount_due') }}</span>
      <span>
        {{ formatPrice(amountDue, data.total.currency || '', data.total.precision) }}
      </span>
    </div>
  </div>
</template>
