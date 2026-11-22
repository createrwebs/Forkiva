<script lang="ts" setup>
import { toRefs } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatPrice } from '@/modules/core/utils/formatters.ts'
import RowLine from './RowLine.vue'

const props = defineProps<{
  order: Record<string, any>
  customerGivenAmount: number | string
  changeReturn: number | string
  currentTotalPaid: number
  finalDueAmount: number
  hasCashPayment: boolean
  form: Record<string, any>
}>()

defineEmits<{
  (e: 'close'): void
  (e: 'submit', value?: boolean): void
}>()

const { t } = useI18n()
const { with_print } = toRefs(props.form.state)
</script>

<template>
  <div class="order-summary-wrapper">
    <VCardText class="order-summary-content">
      <h3 class="title">{{ t('order::orders.order_summary') }}</h3>
      <div class="amount-due-banner">
        <span>{{ t('order::orders.amount_due') }}</span>
        <strong>{{ order.due_amount.formatted }}</strong>
      </div>
      <div class="mt-4">
        <RowLine
          :title="t('order::orders.total_products')"
          :value="order.total_products"
        />
        <RowLine
          :title="t('order::orders.sub_total')"
          :value="order.sub_total.formatted"
        />
        <RowLine
          v-if="order.discount"
          :title="t('order::orders.discount')"
          :value="order.discount?.formatted"
        />
        <RowLine
          :title="t('order::orders.total_tax')"
          :value="order.total_tax.formatted"
        />
        <RowLine
          v-if="order.charge_total && order.charge_total.amount > 0"
          :title="t('order::orders.charge_total')"
          :value="order.charge_total.formatted"
        />
        <RowLine
          :title="t('order::orders.grand_total')"
          :value="order.grand_total.formatted"
          grand-total
        />
        <RowLine
          :title="t('order::orders.previously_paid')"
          :value="order.total_paid.formatted"
        />
        <RowLine
          :title="t('order::orders.paying_now')"
          :value="formatPrice(currentTotalPaid, order.currency, order.precision)"
        />
        <RowLine
          :title="t('order::orders.remaining_balance')"
          :value="formatPrice(finalDueAmount, order.currency, order.precision)"
        />
        <template v-if="hasCashPayment">
          <RowLine
            :title="t('order::orders.cash_received')"
            :value="formatPrice(customerGivenAmount || '0', order.currency, order.precision)"
          />
          <RowLine
            :title="t('order::orders.change_return')"
            :value="formatPrice(changeReturn || '0', order.currency, order.precision)"
            change-return
          />
        </template>
      </div>
    </VCardText>
    <div class="card-footer">
      <div class="d-flex justify-space-between ga-2 w-100">
        <VBtn
          :disabled="form.loading.value"
          class="payment-secondary-action"
          color="default"
          size="large"
          variant="outlined"
          @click="$emit('close')"
        >
          <VIcon
            icon="tabler-x"
            start
          />
          {{ t('admin::admin.buttons.cancel') }}
        </VBtn>
        <VBtn
          :disabled="form.loading.value"
          :loading="form.loading.value && !with_print"
          class="payment-secondary-action"
          color="default"
          size="large"
          variant="outlined"
          @click="$emit('submit')"
        >
          <VIcon
            icon="tabler-check"
            start
          />
          {{ t('order::orders.pay') }}
        </VBtn>
      </div>
      <VBtn
        :disabled="form.loading.value"
        :loading="form.loading.value && with_print"
        block
        class="payment-print-action mt-3"
        color="primary"
        size="large"
        @click="$emit('submit', true)"
      >
        <VIcon
          icon="tabler-printer"
          start
        />
        {{ t('order::orders.pay_and_print') }}
      </VBtn>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.amount-due-banner {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 14px 16px;
  margin-top: 16px;
  border-radius: 10px;
  background: rgba(var(--v-theme-primary), 0.09);
  border: 1px solid rgba(var(--v-theme-primary), 0.22);
  font-size: 13px;
  font-weight: 600;

  strong {
    font-size: 32px;
    font-weight: 750;
    line-height: 1.3;
    font-variant-numeric: tabular-nums;
  }
}

.order-summary-wrapper {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 0;
  background-color: transparent;
}

.order-summary-content {
  flex: 1;
  overflow-y: auto;
  padding: 24px;
}

.card-footer {
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  align-items: center;
  border-top: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  background-color: transparent;
}

.title {
  font-weight: bold;
  font-size: 20px;
}

.payment-secondary-action {
  flex: 1;
  min-width: 0;
}
</style>
