<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { CustomerDisplayCart } from '@/modules/pos/contracts/customerDisplay'

defineProps<{ customer: NonNullable<CustomerDisplayCart['customer']> }>()
const { t } = useI18n()
</script>

<template>
  <div class="display-customer">
    <span class="customer-icon">
      <VIcon
        icon="tabler-user"
        size="24"
        aria-hidden="true"
      />
    </span>
    <div class="customer-identity">
      <strong>
        {{ t('pos::pos_viewer.customer_display.customer_welcome', { name: customer.name }) }}
      </strong>
      <div class="customer-details">
        <span
          v-if="customer.tier"
          class="customer-tier"
        >
          {{ customer.tier }}
        </span>
        <span v-if="customer.points !== null">
          {{ t('pos::pos_viewer.customer_display.points', { count: customer.points }) }}
        </span>
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
.display-customer {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 12px;
  border: 1px solid var(--display-border);
  border-radius: 12px;
  background: var(--display-neutral);
  text-align: start;
  color: var(--display-text);
}
.customer-icon {
  display: grid;
  place-items: center;
  flex: 0 0 34px;
  block-size: 34px;
  border-radius: 50%;
  background: var(--display-surface);
  color: var(--display-muted);
}
.customer-identity {
  min-inline-size: 0;
  overflow-wrap: anywhere;
}
.customer-identity strong {
  font-size: var(--display-body, 14px);
  line-height: 1.5;
}
.customer-details {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px 14px;
  margin-block-start: 3px;
  font-size: var(--display-caption, 12px);
  color: var(--display-muted);
}
.customer-tier {
  color: var(--display-warning);
}
</style>
