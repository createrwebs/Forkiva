<script lang="ts" setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatPrice } from '@/modules/core/utils/formatters.ts'
import CalculatorDialog from './CalculatorDialog.vue'

defineProps<{
  form: Record<string, any>
  meta: Record<string, any>
  hasCashPayment: boolean
  quickPayAmounts: number[]
  activeInputPath: string | null
}>()

defineEmits<{
  (e: 'set-active-input', path: string): void
  (e: 'keypad-input', key: string): void
  (e: 'quick-pay', amount: number): void
  (e: 'calculator-apply', amount: number): void
}>()

const { t } = useI18n()
const keypadButtons = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '.', '0', 'C']
const openCalculator = ref(false)
</script>

<template>
  <div class="payment-entry-tools">
    <section class="keypad-section">
      <div class="section-heading">
        <h4>{{ t('order::orders.numeric_keypad') }}</h4>
        <VBtn
          class="calculator-open-btn"
          :disabled="form.loading.value"
          variant="outlined"
          size="small"
          prepend-icon="tabler-calculator"
          @click="openCalculator = true"
        >
          {{ t('order::orders.calculator') }}
        </VBtn>
      </div>
      <div class="keypad-grid">
        <VBtn
          v-for="key in keypadButtons"
          :key="key"
          class="pad-btn"
          :class="{ 'pad-btn-clear': key === 'C' }"
          :disabled="form.loading.value"
          :color="key === 'C' ? 'error' : 'default'"
          variant="flat"
          @click="$emit('keypad-input', key)"
        >
          {{ key }}
        </VBtn>
      </div>
    </section>
    <section class="cash-section">
      <div class="section-heading">
        <h4>{{ t('order::orders.quick_pay') }}</h4>
      </div>
      <div class="quick-pay-grid">
        <VBtn
          v-for="amount in quickPayAmounts"
          :key="amount"
          class="quick-pay-btn"
          color="default"
          :disabled="form.loading.value"
          variant="flat"
          @click="$emit('quick-pay', amount)"
        >
          +{{ formatPrice(amount, meta.order?.currency, meta.order?.precision) }}
        </VBtn>
      </div>
      <template v-if="hasCashPayment">
        <VTextField
          v-model="form.state.customer_given_amount"
          v-decimal-en
          class="cash-received"
          data-payment-field="customer_given_amount"
          :error="!!form.errors.value?.customer_given_amount"
          :error-messages="form.errors.value?.customer_given_amount"
          hide-details="auto"
          :label="t('order::orders.cash_received')"
          :prefix="meta.order?.currency"
          :readonly="form.loading.value"
          @focus="$emit('set-active-input', 'customer_given_amount')"
        />
        <div
          class="change-return"
          data-payment-field="change_return"
          tabindex="-1"
          aria-live="polite"
        >
          <span>{{ t('order::orders.change_return') }}</span>
          <strong>
            {{
              formatPrice(
                form.state.change_return || '0',
                meta.order?.currency,
                meta.order?.precision,
              )
            }}
          </strong>
          <p
            v-if="form.errors.value?.change_return"
            class="text-error"
          >
            {{ form.errors.value.change_return }}
          </p>
        </div>
      </template>
    </section>
  </div>
  <CalculatorDialog
    v-model="openCalculator"
    @apply="$emit('calculator-apply', $event)"
  />
</template>

<style lang="scss" scoped>
.payment-entry-tools {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  margin-top: 24px;
}
.section-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  min-height: 36px;
  margin-bottom: 12px;
  h4 {
    font-size: 16px;
    font-weight: 700;
  }
}
.calculator-open-btn {
  font-size: 12px;
  border-color: rgba(var(--v-theme-on-surface), 0.2);
}
.keypad-grid,
.quick-pay-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
}
.keypad-grid {
  padding: 8px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.015);
}
.pad-btn {
  height: 52px;
  min-width: 0;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  border-radius: 8px;
  font-size: 23px;
  font-weight: 600;
  background: rgba(var(--v-theme-on-surface), 0.045);
  color: rgb(var(--v-theme-on-surface));
}
.pad-btn-clear {
  background: rgba(var(--v-theme-error), 0.1) !important;
  color: rgb(var(--v-theme-error)) !important;
  border-color: rgba(var(--v-theme-error), 0.2);
}
.cash-section {
  padding-inline-start: 20px;
  border-inline-start: 1px solid rgba(var(--v-theme-on-surface), 0.12);
}
.quick-pay-btn {
  min-width: 0;
  padding-inline: 4px;
  height: 44px;
  border: 1px solid rgba(var(--v-theme-primary), 0.25);
  border-radius: 8px;
  font-size: 13px;
  font-weight: 700;
  background: rgba(var(--v-theme-primary), 0.12);
  color: rgb(var(--v-theme-on-surface));
}
.cash-received {
  margin-top: 24px;
}
.change-return {
  display: flex;
  flex-direction: column;
  gap: 2px;
  margin-top: 14px;
  padding: 10px 14px;
  border: 1px solid rgba(var(--v-theme-success), 0.25);
  border-radius: 9px;
  background: rgba(var(--v-theme-success), 0.1);
  font-size: 13px;
  strong {
    font-size: 26px;
    font-weight: 750;
  }
}
.quick-pay-btn.v-theme--dark {
  background: rgb(var(--v-theme-primary));
  color: var(--payment-primary-text) !important;
}
@media (max-width: 600px) {
  .payment-entry-tools {
    grid-template-columns: 1fr;
  }
  .cash-section {
    padding-inline-start: 0;
    border-inline-start: 0;
  }
}
</style>
