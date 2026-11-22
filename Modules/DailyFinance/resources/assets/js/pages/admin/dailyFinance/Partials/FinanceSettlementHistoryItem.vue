<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import TableStatus from '@/modules/core/components/Table/Partials/TableStatus.vue'
import type { PaymentSettlement } from '@/modules/dailyfinance/contracts/paymentSettlement'

defineProps<{ item?: PaymentSettlement }>()
const emit = defineEmits<{ open: [id: number] }>()
const { t } = useI18n()
</script>

<template>
  <button
    type="button"
    class="settlement-row"
    :class="{ 'settlement-row--loading': !item }"
    :disabled="!item"
    :aria-hidden="!item || undefined"
    @click="item && emit('open', item.id)"
  >
    <span class="settlement-row__identity">
      <template v-if="item">
        <strong>{{ item.reference }}</strong>
        <TableStatus :item="item" />
        <bdi class="settlement-row__date">{{ item.created_at }}</bdi>
      </template>
      <template v-else>
        <span class="settlement-row__skeleton settlement-row__skeleton--reference" />
        <span class="settlement-row__skeleton settlement-row__skeleton--badge" />
        <span class="settlement-row__skeleton settlement-row__skeleton--date" />
      </template>
    </span>
    <span class="settlement-row__destination">
      <template v-if="item">
        <strong>{{ item.destination_name }}</strong>
        <span class="settlement-row__method">
          <VIcon
            :icon="item.method.icon"
            size="16"
          />
          {{ item.method.name }}
        </span>
        <small>{{ item.batch_reference }}</small>
      </template>
      <template v-else>
        <span class="settlement-row__skeleton" />
        <span class="settlement-row__skeleton settlement-row__skeleton--method" />
        <span class="settlement-row__skeleton settlement-row__skeleton--reference" />
      </template>
    </span>
    <span class="settlement-row__amounts">
      <span
        v-for="key in ['expected', 'actual', 'remaining'] as const"
        :key="key"
        class="settlement-row__amount"
      >
        <small class="settlement-row__amount-label">
          {{ t('dailyfinance::payment_settlements.columns.' + key) }}
        </small>
        <strong v-if="item">
          <bdi>{{ item[key].formatted }}</bdi>
        </strong>
        <span
          v-else
          class="settlement-row__skeleton settlement-row__skeleton--amount"
        />
      </span>
    </span>
    <VIcon
      class="settlement-row__chevron"
      icon="tabler-chevron-right"
      size="17"
    />
  </button>
</template>

<style lang="scss" scoped>
.settlement-row {
  display: grid;
  grid-template-columns: var(--settlement-columns);
  align-items: center;
  gap: 18px;
  inline-size: 100%;
  min-block-size: 108px;
  padding: 16px 24px;
  border-block-end: 1px solid rgba(var(--v-theme-secondary), 0.09);
  background: rgb(var(--v-theme-surface));
  color: rgb(var(--v-theme-secondary));
  text-align: start;
  cursor: pointer;
  transition: background-color 150ms ease;
}

.settlement-row:hover:not(:disabled) {
  background: rgba(var(--v-theme-primary), 0.035);
}

.settlement-row:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: -2px;
}

.settlement-row__identity,
.settlement-row__destination {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 5px;
  min-inline-size: 0;
  font-size: 0.875rem;
  line-height: 1.4;
  overflow-wrap: anywhere;
}

.settlement-row__identity > strong {
  font-size: 0.9375rem;
  font-weight: 600;
}

.settlement-row__identity :deep(.v-chip) {
  block-size: 21px;
  padding-inline: 7px;
  font-size: 0.6875rem;
}

.settlement-row__destination > strong {
  font-weight: 500;
}

.settlement-row__destination small,
.settlement-row__method,
.settlement-row__date {
  color: rgba(var(--v-theme-on-surface), 0.65);
  font-size: 0.75rem;
}

.settlement-row__date {
  font-size: 0.6875rem;
}

.settlement-row__method {
  display: flex;
  align-items: center;
  gap: 6px;
}

.settlement-row__chevron {
  color: rgba(var(--v-theme-secondary), 0.5);
}

:global(.v-locale--is-rtl) .settlement-row__chevron {
  transform: rotate(180deg);
}

.settlement-row__amounts {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
}

.settlement-row__amount {
  display: grid;
  gap: 5px;
  min-inline-size: 0;
  text-align: end;
}

.settlement-row__amount strong {
  font-size: 0.9375rem;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  overflow-wrap: anywhere;
}

.settlement-row__amount-label {
  position: absolute;
  inline-size: 1px;
  block-size: 1px;
  overflow: hidden;
  clip-path: inset(50%);
  white-space: nowrap;
}

.settlement-row--loading {
  cursor: default;
}

.settlement-row--loading .settlement-row__chevron {
  visibility: hidden;
}

.settlement-row__skeleton {
  display: block;
  inline-size: 80%;
  block-size: 15px;
  border-radius: 4px;
  background: rgba(var(--v-theme-secondary), 0.07);
  animation: settlement-row-pulse 1.4s ease-in-out infinite alternate;
}

.settlement-row__skeleton--reference {
  inline-size: 72px;
  block-size: 19px;
}

.settlement-row__skeleton--badge {
  inline-size: 66px;
  block-size: 21px;
  border-radius: 4px;
}

.settlement-row__skeleton--date {
  inline-size: 130px;
  block-size: 15px;
}

.settlement-row__skeleton--method {
  inline-size: 96px;
  block-size: 17px;
}

.settlement-row__skeleton--amount {
  justify-self: end;
  inline-size: 65px;
  max-inline-size: 100%;
  block-size: 21px;
}

@keyframes settlement-row-pulse {
  to {
    opacity: 0.45;
  }
}

@media (prefers-reduced-motion: reduce) {
  .settlement-row {
    transition: none;
  }

  .settlement-row__skeleton {
    animation: none;
  }
}

@media (max-width: 700px) {
  .settlement-row {
    grid-template-columns: minmax(0, 1fr) 17px;
    gap: 12px;
    padding: 18px;
  }

  .settlement-row__identity {
    flex-direction: row;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px 8px;
  }

  .settlement-row__date,
  .settlement-row__skeleton--date {
    flex-basis: 100%;
  }

  .settlement-row__chevron {
    grid-column: 2;
    grid-row: 1;
    align-self: start;
  }

  .settlement-row__destination {
    grid-column: 1 / -1;
    flex-flow: row wrap;
    align-items: center;
    gap: 4px 10px;
  }

  .settlement-row__destination > strong {
    flex-basis: 100%;
  }

  .settlement-row__amounts {
    grid-column: 1 / -1;
    padding-block-start: 10px;
  }

  .settlement-row__amount {
    text-align: start;
  }

  .settlement-row__amount-label {
    position: static;
    inline-size: auto;
    block-size: auto;
    overflow: visible;
    clip-path: none;
    color: rgba(var(--v-theme-on-surface), 0.65);
    font-size: 0.75rem;
    white-space: normal;
  }

  .settlement-row__skeleton--amount {
    justify-self: start;
  }
}
</style>
