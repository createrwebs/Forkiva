<script lang="ts" setup>
import type { ConvertedMoney, Money as MoneyValue } from '@/modules/core/contracts/Money.ts'
import type { TaxDetail } from '@/modules/tax/contracts/TaxSummary.ts'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import Money from '@/modules/core/components/Money.vue'
import TaxDetailsDialog from './TaxDetailsDialog.vue'

defineProps<{
  amount: ConvertedMoney | MoneyValue
  taxes?: TaxDetail[]
}>()

const open = ref(false)
const { t } = useI18n()
</script>

<template>
  <button
    v-if="taxes?.length"
    :aria-label="t('tax::taxes.display.view_tax_details')"
    class="tax-details-trigger"
    data-test="open-tax-details"
    type="button"
    @click.stop="open = true"
  >
    <Money :money="amount" />
    <VIcon
      icon="tabler-info-circle"
      size="16"
    />
  </button>
  <Money
    v-else
    :money="amount"
  />

  <TaxDetailsDialog
    v-if="open"
    v-model="open"
    :taxes="taxes || []"
    :total="amount"
  />
</template>

<style lang="scss" scoped>
.tax-details-trigger {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.2rem 0.35rem;
  border: 0;
  border-radius: 0.35rem;
  background: transparent;
  color: inherit;
  cursor: pointer;
  font: inherit;
  font-variant-numeric: tabular-nums;

  &:hover,
  &:focus-visible {
    background: rgba(var(--v-theme-primary), 0.08);
    color: rgb(var(--v-theme-primary));
    outline: none;
  }
}
</style>
