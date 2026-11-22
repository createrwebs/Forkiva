<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAppStore } from '@/modules/core/stores/appStore.ts'
import { formatPrice } from '@/modules/core/utils/formatters.ts'
import BaseInfoCard, { type InfoCardField } from './BaseInfoCard.vue'

const props = defineProps<{
  item: Record<string, any>
}>()

const { t } = useI18n()
const appStore = useAppStore()
const depositAmount = computed(() => moneyAmount(props.item.deposit_amount))
const depositPaidAmount = computed(() => moneyAmount(props.item.deposit_paid_amount))
const depositCurrency = computed(
  () =>
    props.item.deposit_currency ||
    props.item.deposit_amount?.original?.currency ||
    props.item.deposit_amount?.currency ||
    appStore.currency,
)
const depositRemainingAmount = computed(() =>
  Math.max(depositAmount.value - depositPaidAmount.value, 0),
)
const depositOverpaidAmount = computed(() =>
  Math.max(depositPaidAmount.value - depositAmount.value, 0),
)
const depositBalanceLabel = computed(() =>
  depositOverpaidAmount.value > 0
    ? t('reservation::frontend.deposit_overpaid_amount')
    : t('reservation::frontend.deposit_remaining_amount'),
)
const depositBalanceValue = computed(() =>
  formatPrice(
    depositOverpaidAmount.value > 0 ? depositOverpaidAmount.value : depositRemainingAmount.value,
    depositCurrency.value,
    props.item.deposit_amount?.original?.precision || props.item.deposit_amount?.precision || 3,
  ),
)

const fields = computed<InfoCardField[]>(() => [
  {
    column: 'payment_status',
    label: t('reservation::attributes.reservations.payment_status'),
    type: 'tableStatus',
  },
  {
    label: t('reservation::attributes.reservations.deposit_required'),
    type: 'boolean',
    value: props.item.deposit_required,
  },
  {
    label: t('reservation::attributes.reservations.deposit_amount'),
    type: 'money',
    value: props.item.deposit_amount,
  },
  {
    label: t('reservation::attributes.reservations.deposit_paid_amount'),
    type: 'money',
    value: props.item.deposit_paid_amount,
  },
  {
    label: depositBalanceLabel.value,
    value: depositBalanceValue.value,
  },
  {
    label: t('reservation::attributes.reservations.deposit_currency'),
    value: props.item.deposit_currency,
  },
  {
    label: t('reservation::attributes.reservations.deposit_currency_rate'),
    value: props.item.deposit_currency_rate,
  },
])

function moneyAmount(value: any): number {
  const amount = value?.original?.amount ?? value?.amount ?? value ?? 0
  const parsed = Number.parseFloat(String(amount))

  return Number.isFinite(parsed) ? parsed : 0
}
</script>

<template>
  <BaseInfoCard
    :columns="4"
    :fields="fields"
    icon="tabler-cash"
    :item="item"
    :title="t('reservation::frontend.payment_details')"
  />
</template>
