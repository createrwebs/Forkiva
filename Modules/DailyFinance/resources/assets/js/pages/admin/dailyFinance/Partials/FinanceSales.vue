<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import TableEnum from '@/modules/core/components/Table/Partials/TableEnum.vue'
import TableStatus from '@/modules/core/components/Table/Partials/TableStatus.vue'
import type { DailyFinanceEntry } from '@/modules/dailyfinance/contracts/dailyFinance'

defineProps<{ entries: DailyFinanceEntry[]; outstanding?: boolean }>()
const { t } = useI18n()
const breakdown = ['subtotal', 'discount', 'tax', 'charges', 'total', 'due'] as const
</script>

<template>
  <section
    class="finance-sales"
    :class="{ 'finance-sales--outstanding': outstanding }"
  >
    <div
      v-if="!entries.length"
      class="finance-sales__empty"
    >
      <span class="finance-sales__empty-icon">
        <VIcon
          :icon="outstanding ? 'tabler-circle-check' : 'tabler-receipt-off'"
          size="32"
        />
      </span>
      <h4>{{ t(`dailyfinance::daily_finance.${outstanding ? 'dues' : 'sales'}.empty_title`) }}</h4>
      <p>{{ t(`dailyfinance::daily_finance.${outstanding ? 'dues' : 'sales'}.empty_hint`) }}</p>
    </div>
    <div
      v-else
      class="finance-sales__list"
    >
      <details
        v-for="entry in entries"
        :key="entry.id"
        class="finance-sale"
      >
        <summary class="finance-sale__summary">
          <span
            class="finance-sale__icon"
            aria-hidden="true"
          >
            <VIcon
              :icon="outstanding ? 'tabler-receipt-2' : 'tabler-receipt'"
              size="22"
            />
          </span>
          <span class="finance-sale__reference">
            <strong>{{ entry.reference || `#${entry.id}` }}</strong>
            <time
              :datetime="entry.occurred_at ?? undefined"
              dir="ltr"
            >
              {{ entry.occurred_at_formatted ?? '—' }}
            </time>
          </span>
          <span class="finance-sale__amount">
            <span
              v-if="outstanding"
              class="finance-sale__amount-label"
            >
              {{ t('dailyfinance::daily_finance.sales.due') }}
            </span>
            <bdi>{{ entry.amount.formatted }}</bdi>
          </span>
          <VIcon
            icon="tabler-chevron-down"
            class="finance-sale__chevron"
            size="18"
            aria-hidden="true"
          />
          <span class="finance-sale__labels">
            <TableEnum
              v-if="entry.order"
              :item="entry.order"
              column="type"
            />
            <TableStatus
              v-if="entry.status"
              :item="entry"
            />
            <TableEnum
              :item="entry"
              column="label"
            />
          </span>
        </summary>
        <dl
          v-if="entry.order"
          class="finance-sale__breakdown"
        >
          <div
            v-for="key in breakdown"
            :key="key"
            :class="{ 'finance-sale__total': key === 'total', 'finance-sale__due': key === 'due' }"
          >
            <dt>
              {{ t(`dailyfinance::daily_finance.sales.${key === 'total' ? 'order_total' : key}`) }}
            </dt>
            <dd>
              <bdi>
                {{ key === 'discount' && entry.order.discount.amount > 0 ? '−' : ''
                }}{{ entry.order[key].formatted }}
              </bdi>
            </dd>
          </div>
        </dl>
        <p
          v-else
          class="finance-sale__unavailable"
        >
          {{ t('dailyfinance::daily_finance.unavailable') }}
        </p>
      </details>
    </div>
  </section>
</template>

<style lang="scss" scoped>
.finance-sales__empty p {
  margin: 6px 0 0;
  font-size: 0.8rem;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

.finance-sale {
  border: 1px solid transparent;
  border-block-end-color: rgba(var(--v-theme-on-surface), 0.1);
}

.finance-sale[open] {
  border-color: rgba(var(--v-theme-on-surface), 0.14);
  border-radius: 12px;
  margin-block: 8px;
}

.finance-sale__summary {
  display: grid;
  grid-template-columns: 36px minmax(150px, 1fr) auto minmax(80px, auto) 18px;
  align-items: center;
  gap: 8px 12px;
  padding: 8px 12px;
  cursor: pointer;
  list-style: none;
  border-radius: 10px;
}

.finance-sale__summary::-webkit-details-marker {
  display: none;
}

.finance-sale__summary:hover {
  background: rgba(var(--v-theme-primary), 0.025);
}

.finance-sale__summary:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: -2px;
}

.finance-sale__icon {
  display: grid;
  place-items: center;
  inline-size: 36px;
  block-size: 36px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.06);
  border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.035);
  color: rgba(var(--v-theme-on-surface), 0.6);
}

.finance-sale__reference {
  min-inline-size: 0;
  overflow-wrap: anywhere;
}

.finance-sale__reference strong {
  display: block;
  font-size: 0.95rem;
  line-height: 1.4;
}

.finance-sale__reference time {
  display: inline-block;
  margin-block-start: 3px;
  font-size: 0.75rem;
  line-height: 1.4;
  color: rgba(var(--v-theme-on-surface), 0.6);
}

.finance-sale__labels {
  grid-column: 3;
  grid-row: 1;
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.finance-sale__amount {
  grid-column: 4;
  grid-row: 1;
  font-size: 1.05rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  overflow-wrap: anywhere;
  text-align: end;
}

.finance-sale__amount-label {
  display: block;
  font-size: 0.7rem;
  font-weight: 400;
  line-height: 1.4;
  color: rgba(var(--v-theme-on-surface), 0.6);
}

.finance-sales--outstanding .finance-sale__amount {
  color: rgb(var(--v-theme-primary));
}

.finance-sales--outstanding .finance-sale__icon {
  background: rgba(var(--v-theme-primary), 0.07);
  color: rgb(var(--v-theme-primary));
  border-color: transparent;
}

.finance-sales--outstanding .finance-sale__breakdown .finance-sale__due {
  margin-block-start: 10px;
  padding: 10px 12px;
  border-radius: 8px;
  background: rgba(var(--v-theme-primary), 0.07);
  color: rgb(var(--v-theme-primary));
  font-weight: 700;
}

.finance-sale__chevron {
  grid-column: 5;
  grid-row: 1;
  color: rgba(var(--v-theme-on-surface), 0.5);
}

.finance-sale[open] .finance-sale__chevron {
  transform: rotate(180deg);
}

.finance-sale__breakdown {
  margin: 0 12px 12px;
  padding: 10px 20px;
  border-radius: 8px;
  background: rgba(var(--v-theme-on-surface), 0.035);
  font-size: 0.85rem;
}

.finance-sale__breakdown > div {
  display: flex;
  justify-content: space-between;
  gap: 20px;
  padding-block: 5px;
  color: rgba(var(--v-theme-on-surface), 0.75);
}

.finance-sale__breakdown dd {
  margin: 0;
  text-align: end;
  font-variant-numeric: tabular-nums;
  overflow-wrap: anywhere;
}

.finance-sale__breakdown .finance-sale__total {
  margin-block-start: 8px;
  padding-block: 10px;
  border-block: 1px solid rgba(var(--v-theme-on-surface), 0.1);
  color: rgb(var(--v-theme-on-surface));
  font-weight: 700;
}

.finance-sale__breakdown .finance-sale__due {
  padding-block-start: 12px;
}

.finance-sales__empty {
  padding: 40px 16px;
  text-align: center;
}

.finance-sales__empty-icon {
  display: inline-grid;
  place-items: center;
  inline-size: 64px;
  block-size: 64px;
  border-radius: 18px;
  background: rgba(var(--v-theme-primary), 0.07);
  color: rgb(var(--v-theme-primary));
  margin-block-end: 16px;
}

.finance-sales__empty h4 {
  font-size: 1rem;
}

.finance-sale__unavailable {
  padding: 12px;
}

@media (max-width: 760px) {
  .finance-sale__summary {
    grid-template-columns: 36px minmax(0, 1fr) auto 18px;
  }

  .finance-sale__labels {
    grid-column: 2 / -1;
    grid-row: 2;
  }

  .finance-sale__amount {
    grid-column: 3;
  }

  .finance-sale__chevron {
    grid-column: 4;
  }
}

@media (max-width: 480px) {
  .finance-sale__summary {
    grid-template-columns: minmax(0, 1fr) minmax(0, auto) 18px;
    gap: 8px;
    padding: 10px;
  }

  .finance-sale__icon {
    display: none;
  }

  .finance-sale__labels {
    grid-column: 1 / -1;
  }

  .finance-sale__amount {
    grid-column: 2;
  }

  .finance-sale__chevron {
    grid-column: 3;
  }

  .finance-sale__breakdown {
    padding-inline: 12px;
    margin-inline: 10px;
  }
}
</style>
