<script lang="ts" setup>
import type { ConvertedMoney, Money as MoneyValue } from '@/modules/core/contracts/Money.ts'
import type {
  ConvertedDisplayMoney,
  DisplayMoney,
  TaxSummaryLine,
} from '@/modules/tax/contracts/TaxSummary.ts'
import { useI18n } from 'vue-i18n'
import Money from '@/modules/core/components/Money.vue'

defineProps<{
  taxes: TaxSummaryLine[]
}>()

const { t } = useI18n()

function taxDetails(tax: TaxSummaryLine): string {
  const type = typeof tax.type === 'string' ? tax.type : tax.type?.id
  const details = [
    tax.scope === 'product'
      ? t('tax::taxes.display.product_tax')
      : t('tax::taxes.display.global_tax'),
  ]

  if (type) {
    details.push(
      type === 'inclusive'
        ? t('tax::taxes.display.inclusive_tax')
        : t('tax::taxes.display.exclusive_tax'),
    )
  }

  if (typeof tax.rate === 'number') {
    details.push(`${tax.rate}%`)
  }

  return details.join(' · ')
}

function moneyValue(amount: ConvertedDisplayMoney | DisplayMoney): ConvertedMoney | MoneyValue {
  return amount as unknown as ConvertedMoney | MoneyValue
}
</script>

<template>
  <div
    v-for="tax in taxes"
    :key="tax.key"
    class="tax-summary-row"
  >
    <span class="tax-summary-title">
      <span>{{ tax.name }}</span>
      <small>{{ taxDetails(tax) }}</small>
    </span>
    <Money
      class="tax-summary-amount"
      :money="moneyValue(tax.amount)"
    />
  </div>
</template>

<style lang="scss" scoped>
.tax-summary-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.4rem 0;
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.95rem;
}

.tax-summary-title {
  display: flex;
  min-width: 0;
  flex-direction: column;
}

.tax-summary-title small {
  color: rgba(var(--v-theme-on-surface), 0.58);
  font-size: 0.72rem;
  font-weight: 400;
  line-height: 1.3;
}

.tax-summary-amount {
  flex: 0 0 auto;
  font-weight: 600;
}
</style>
