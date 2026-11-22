<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import { formatPrice } from '@/modules/core/utils/formatters.ts'

const props = defineProps<{
  orders: Record<string, any>[]
  mergeId: number
}>()

defineEmits<{
  (e: 'store-payment', value: string | number): void
}>()

const { t } = useI18n()
const { can } = useAuth()

const firstOrder = computed(() => props.orders[0])
const toMinorUnits = (money: Record<string, any>) => {
  const factor = 10 ** money.precision

  return Math.round(Number(money.amount) * factor)
}
const total = computed(() => {
  if (!firstOrder.value) return 0

  const factor = 10 ** firstOrder.value.total.precision
  return props.orders.reduce((sum, order) => sum + toMinorUnits(order.total), 0) / factor
})
const totalFormatted = computed(() =>
  firstOrder.value
    ? formatPrice(total.value, firstOrder.value.total.currency, firstOrder.value.total.precision)
    : '',
)
const dueAmount = computed(() => {
  if (!firstOrder.value) return 0

  const factor = 10 ** firstOrder.value.due_amount.precision
  return props.orders.reduce((sum, order) => sum + toMinorUnits(order.due_amount), 0) / factor
})
const dueAmountFormatted = computed(() =>
  firstOrder.value
    ? formatPrice(
        dueAmount.value,
        firstOrder.value.due_amount.currency,
        firstOrder.value.due_amount.precision,
      )
    : '',
)
const allowAddPayment = computed(
  () =>
    props.orders.length > 0 &&
    props.orders.filter(
      (order) => order.next_status.id === 'completed' && order.payment_status.id !== 'paid',
    ).length === props.orders.length,
)
const isPartiallyPaid = computed(() =>
  props.orders.some((order) => order.payment_status.id === 'partially_paid'),
)
</script>

<template>
  <div class="pos-table-bulk-actions">
    <div class="pos-table-bulk-totals">
      <div>
        <span>{{ t('pos::pos_viewer.table_details.combined_total') }}</span>
        <strong class="pos-table-bulk-total">{{ totalFormatted }}</strong>
      </div>
      <div>
        <span>{{ t('pos::pos_viewer.due_amount') }}</span>
        <strong>{{ dueAmountFormatted }}</strong>
      </div>
    </div>
    <VBtn
      v-if="can('admin.orders.receive_payment')"
      color="success"
      :disabled="!allowAddPayment || orders.length === 0"
      variant="flat"
      @click="$emit('store-payment', orders[0]?.id ?? '')"
    >
      <VIcon
        icon="tabler-cash"
        start
      />
      {{ t(`pos::pos_viewer.order_actions.${isPartiallyPaid ? 'complete_payment' : 'pay_now'}`) }}
    </VBtn>
  </div>
</template>

<style lang="scss" scoped>
.pos-table-bulk-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 10px;
  background: var(--pos-soft);
  border: 1px solid var(--pos-border);
}
.pos-table-bulk-totals {
  display: flex;
  flex-wrap: wrap;
  gap: 12px 28px;
  font-variant-numeric: tabular-nums;
}
.pos-table-bulk-totals > div {
  display: grid;
  gap: 4px;
}
.pos-table-bulk-totals span {
  font-size: 11px;
  color: var(--pos-muted);
}
.pos-table-bulk-totals strong {
  font-size: 17px;
  line-height: 1.3;
  color: var(--pos-text);
}
.pos-table-bulk-totals .pos-table-bulk-total {
  color: var(--pos-accent);
}
.pos-table-bulk-actions .v-btn {
  min-block-size: 40px;
  border-radius: 8px;
  font-size: 13px;
}
</style>
