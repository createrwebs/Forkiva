<script lang="ts" setup>
import type { KitchenOrder } from '@/modules/pos/contracts/kitchen'
import Empty from './Empty.vue'
import Order from './Order.vue'
defineProps<{
  hasActiveFilters: boolean
  orderEffects: Record<string, 'new' | 'updated'>
  orders: KitchenOrder[]
  now: number
  delayMinutes: number
  disabled: boolean
}>()
defineEmits<{
  'order-updated': [payload: Record<string, unknown>]
  processing: [active: boolean]
  'clear-filters': []
  'action-failed': []
}>()
</script>

<template>
  <Empty
    v-if="!orders.length"
    :has-active-filters="hasActiveFilters"
    @clear-filters="$emit('clear-filters')"
  />
  <div
    v-else
    class="kds-tickets"
  >
    <Order
      v-for="order in orders"
      :key="order.id"
      :effect="orderEffects[String(order.id)] ?? null"
      :order="order"
      :now="now"
      :delay-minutes="delayMinutes"
      :disabled="disabled"
      @order-updated="$emit('order-updated', $event)"
      @processing="$emit('processing', $event)"
      @action-failed="$emit('action-failed')"
    />
  </div>
</template>
