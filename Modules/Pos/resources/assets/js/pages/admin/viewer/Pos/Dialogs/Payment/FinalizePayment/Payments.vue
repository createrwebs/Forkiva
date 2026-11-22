<script lang="ts" setup>
import type { Ref } from 'vue'
import { toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import PromptPayQr from './PromptPayQr.vue'

const props = defineProps<{
  meta: Record<string, any>
  form: Record<string, any>
  orderId: string | number
}>()

defineEmits<{
  (e: 'add-payment'): void
  (e: 'set-active-input', path: string): void
}>()

const { t } = useI18n()
const payments = toRef(props.form.state as Record<string, any>, 'payments') as Ref<
  Record<string, any>[]
>

if (!payments.value) {
  payments.value = []
}

function removePayment(index: number) {
  payments.value.splice(index, 1)
}

function needsTransactionId(method: string | null) {
  return ['card', 'bank_transfer', 'mobile_wallet'].includes(method ?? '')
}

function updatePaymentMethod(payment: Record<string, any>, method: string | null) {
  if (!needsTransactionId(method)) payment.transaction_id = null
  if (method !== 'gift_card') payment.gift_card_code = null
}
</script>

<template>
  <div
    class="payments-heading"
    data-payment-field="payments"
    tabindex="-1"
  >
    <h4>{{ t('order::orders.payments') }}</h4>
    <VBtn
      color="primary"
      variant="outlined"
      size="small"
      prepend-icon="tabler-plus"
      :disabled="form.loading.value"
      @click="$emit('add-payment')"
    >
      {{ t('order::orders.add_payment') }}
    </VBtn>
  </div>
  <div class="mt-4">
    <template
      v-for="(payment, index) in payments"
      :key="index"
    >
      <VRow
        class="payment-entry"
        :class="{
          'payment-entry--simple':
            !needsTransactionId(payment.method) && payment.method !== 'gift_card',
        }"
        dense
      >
        <VCol
          cols="12"
          md="4"
        >
          <VSelect
            v-model="payment.method"
            :data-payment-field="`payments.${index}.method`"
            hide-details="auto"
            @update:model-value="updatePaymentMethod(payment, $event)"
            :error="!!form.errors.value?.[`payments.${index}.method`]"
            :error-messages="form.errors.value?.[`payments.${index}.method`]"
            item-title="name"
            item-value="id"
            :items="meta.paymentMethods"
            :label="t('order::attributes.payments.payments.*.method')"
            :readonly="form.loading.value"
          />
        </VCol>
        <VCol
          cols="12"
          md="4"
        >
          <VTextField
            v-model="payment.amount"
            :data-payment-field="`payments.${index}.amount`"
            hide-details="auto"
            v-decimal-en
            :error="!!form.errors.value?.[`payments.${index}.amount`]"
            :error-messages="form.errors.value?.[`payments.${index}.amount`]"
            :label="t('order::attributes.payments.payments.*.amount')"
            :prefix="meta.order.currency"
            :readonly="form.loading.value"
            @focus="$emit('set-active-input', `payments.${index}.amount`)"
          />
        </VCol>
        <VCol
          v-if="needsTransactionId(payment.method)"
          cols="12"
          md="3"
        >
          <VTextField
            v-model="payment.transaction_id"
            :data-payment-field="`payments.${index}.transaction_id`"
            hide-details="auto"
            :error="!!form.errors.value?.[`payments.${index}.transaction_id`]"
            :error-messages="form.errors.value?.[`payments.${index}.transaction_id`]"
            :label="t('order::attributes.payments.payments.*.transaction_id')"
            :readonly="form.loading.value"
          />
        </VCol>
        <VCol
          v-if="payment.method === 'gift_card'"
          cols="12"
          md="3"
        >
          <VTextField
            v-model="payment.gift_card_code"
            :data-payment-field="`payments.${index}.gift_card_code`"
            hide-details="auto"
            :error="!!form.errors.value?.[`payments.${index}.gift_card_code`]"
            :error-messages="form.errors.value?.[`payments.${index}.gift_card_code`]"
            :label="t('order::attributes.payments.payments.*.gift_card_code')"
            :readonly="form.loading.value"
          />
        </VCol>
        <VCol
          class="d-flex ga-2"
          cols="12"
          md="auto"
        >
          <VBtn
            :aria-label="t('admin::admin.buttons.delete')"
            color="error"
            :disabled="form.loading.value || payments.length === 1"
            icon="tabler-trash"
            rounded
            variant="tonal"
            @click="removePayment(index)"
          />
        </VCol>
      </VRow>
      <PromptPayQr
        v-if="payment.method === 'promptpay'"
        :order-id="orderId"
        :amount="payment.amount"
        :currency="meta.order?.currency ?? ''"
      />
    </template>
  </div>
</template>

<style lang="scss" scoped>
.payments-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  h4 {
    font-size: 18px;
  }
}
.payment-entry {
  display: grid;
  grid-template-columns: 1.1fr 0.9fr 1fr 40px;
  gap: 8px;
  margin: 0 0 10px;
  padding: 10px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.02);
  &.v-row > div {
    max-width: none;
    width: 100%;
    min-width: 0;
    padding: 0;
  }
  :deep(.v-field__input) {
    min-width: 0;
  }
  .v-btn {
    width: 36px;
    height: 40px;
  }
}
.payment-entry--simple {
  grid-template-columns: 1.1fr 1.9fr 40px;
}
@media (max-width: 600px) {
  .payment-entry {
    grid-template-columns: 1fr 1fr;
  }
}
</style>
