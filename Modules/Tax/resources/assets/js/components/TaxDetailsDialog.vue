<script lang="ts" setup>
import type { ConvertedMoney, Money as MoneyValue } from '@/modules/core/contracts/Money.ts'
import type { TaxDetail } from '@/modules/tax/contracts/TaxSummary.ts'
import { useI18n } from 'vue-i18n'
import Money from '@/modules/core/components/Money.vue'

defineProps<{
  taxes: TaxDetail[]
  total: ConvertedMoney | MoneyValue
}>()

const open = defineModel<boolean>({ default: false })
const { t } = useI18n()

function typeId(tax: TaxDetail): string | undefined {
  return typeof tax.type === 'string' ? tax.type : tax.type?.id
}

function taxDetails(tax: TaxDetail): string {
  const type = typeId(tax)
  const details: string[] = []

  if (type) {
    details.push(
      type === 'inclusive'
        ? t('tax::taxes.display.inclusive_tax')
        : t('tax::taxes.display.exclusive_tax'),
    )
  }

  if (typeof tax.rate === 'number') details.push(`${tax.rate}%`)

  return details.join(' · ')
}
</script>

<template>
  <VDialog
    v-model="open"
    max-width="560"
    scrollable
  >
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between border-b px-5 py-4">
        <div class="d-flex align-center ga-2">
          <VIcon
            color="primary"
            icon="tabler-receipt-tax"
            size="22"
          />
          <span>{{ t('tax::taxes.display.tax_details') }}</span>
        </div>
        <VBtn
          :aria-label="t('admin::admin.buttons.close')"
          data-test="close-tax-details"
          icon="tabler-x"
          variant="text"
          @click="open = false"
        />
      </VCardTitle>

      <VCardText class="pa-5">
        <div
          v-for="tax in taxes"
          :key="tax.key || tax.id || tax.name"
          class="tax-detail-row"
        >
          <div>
            <div class="font-weight-medium">{{ tax.name }}</div>
            <div class="text-body-2 text-medium-emphasis">
              {{ taxDetails(tax) }}
            </div>
          </div>
          <Money :money="tax.amount" />
        </div>

        <VDivider class="my-3" />

        <div class="d-flex align-center justify-space-between font-weight-bold">
          <span>{{ t('tax::taxes.display.total_tax') }}</span>
          <Money :money="total" />
        </div>
      </VCardText>

      <VCardActions class="justify-end border-t px-5 py-3">
        <VBtn
          color="primary"
          variant="tonal"
          @click="open = false"
        >
          {{ t('admin::admin.buttons.close') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.tax-detail-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.65rem 0;
}
</style>
