<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import type { useQintrix } from '@/modules/printer/composables/qintrix.ts'
import Order from '../../../Orders/Order.vue'

const props = defineProps<{
  orders: Record<string, any>[]
  isBillingMerged: boolean
  branchId: number | null
  qintrix: ReturnType<typeof useQintrix>
}>()

defineEmits<{
  (e: 'refresh'): void
  (
    e: 'store-payment' | 'refund-order' | 'cancel-order' | 'view-order' | 'print-order',
    value: string | number,
  ): void
  (e: 'init-order', response: Record<string, any>): void
}>()

const { t } = useI18n()

const expandedOrderId = ref<string | number | null>(null)
watch(
  () => props.orders.map((order) => order.id),
  (ids) => {
    if (expandedOrderId.value !== null && !ids.includes(expandedOrderId.value)) {
      expandedOrderId.value = null
    }
  },
)
</script>

<template>
  <div class="pos-table-orders">
    <div
      v-if="orders.length > 1"
      class="pos-table-orders-heading"
    >
      <h3>
        {{ t('pos::pos_viewer.table_details.orders') }}
      </h3>
      <span class="pos-table-order-count">{{ orders.length }}</span>
      <span class="pos-table-orders-hint">
        {{ t('pos::pos_viewer.table_details.open_order_hint') }}
      </span>
    </div>
    <div
      v-if="!orders.length"
      class="pos-table-orders-empty"
    >
      <VIcon
        icon="tabler-receipt-off"
        size="30"
        aria-hidden="true"
      />
      <strong>{{ t('pos::pos_viewer.active_orders_empty_box.title') }}</strong>
    </div>
    <div
      v-else
      class="pos-table-orders-list"
    >
      <Order
        v-for="order in orders"
        :key="order.id"
        :branch-id="branchId"
        :is-billing-merged="isBillingMerged"
        is-table-viewer
        :order="order"
        :collapsible="orders.length > 1"
        :expanded="expandedOrderId === order.id"
        @toggle="expandedOrderId = expandedOrderId === order.id ? null : order.id"
        :qintrix="qintrix"
        @cancel-order="(orderId: number | string) => $emit('cancel-order', orderId)"
        @init-order="(response: Record<string, any>) => $emit('init-order', response)"
        @print-order="(orderId: number | string) => $emit('print-order', orderId)"
        @refresh="$emit('refresh')"
        @refund-order="(orderId: number | string) => $emit('refund-order', orderId)"
        @store-payment="(orderId: number | string) => $emit('store-payment', orderId)"
        @view-order="(orderId: number | string) => $emit('view-order', orderId)"
      />
    </div>
  </div>
</template>

<style lang="scss" scoped>
.pos-table-orders {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-inline-size: 0;
}
.pos-table-orders-heading {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  margin-block-end: 10px;
  color: var(--pos-text);
}
.pos-table-orders-heading h3 {
  margin: 0;
  font-size: 15px;
  line-height: 1.4;
  font-weight: 700;
}
.pos-table-order-count {
  display: grid;
  place-items: center;
  min-inline-size: 24px;
  block-size: 24px;
  padding-inline: 6px;
  border-radius: 50%;
  background: var(--pos-soft);
  color: var(--pos-muted);
  font-size: 12px;
  font-weight: 700;
}
.pos-table-orders-list {
  display: grid;
  gap: 10px;
}
.pos-table-orders-hint {
  margin-inline-start: auto;
  color: var(--pos-muted);
  font-size: 12px;
}
@media (max-width: 540px) {
  .pos-table-orders-hint {
    flex-basis: 100%;
  }
}
.pos-table-orders-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  flex: 1;
  min-block-size: 120px;
  padding: 20px 16px;
  color: var(--pos-muted);
  font-size: 14px;
}
</style>
