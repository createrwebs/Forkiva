<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import TableEnum from '@/modules/core/components/Table/Partials/TableEnum.vue'
import type { DailyFinanceEntry } from '@/modules/dailyfinance/contracts/dailyFinance'

defineProps<{ entries: DailyFinanceEntry[]; kind: 'collections' | 'refunds' }>()
const { t } = useI18n()
</script>

<template>
  <section class="finance-receipts">
    <div
      v-if="!entries.length"
      class="finance-receipts__empty"
    >
      <span class="finance-receipts__empty-icon">
        <VIcon
          :icon="kind === 'refunds' ? 'tabler-receipt-refund' : 'tabler-cash-off'"
          size="32"
        />
      </span>
      <h4>{{ t(`dailyfinance::daily_finance.receipts.empty_${kind}`) }}</h4>
      <p>{{ t(`dailyfinance::daily_finance.receipts.hint_${kind}`) }}</p>
    </div>
    <div
      v-else
      class="finance-receipts__list"
    >
      <details
        v-for="entry in entries"
        :key="entry.id"
        class="finance-receipt"
      >
        <summary class="finance-receipt__summary">
          <span
            class="finance-receipt__icon"
            aria-hidden="true"
          >
            <VIcon
              :icon="entry.label.icon || 'tabler-wallet'"
              size="22"
            />
          </span>
          <span class="finance-receipt__reference">
            <strong>
              {{
                entry.reference ||
                t('dailyfinance::daily_finance.receipts.number', { number: entry.id })
              }}
            </strong>
            <span class="finance-receipt__meta">
              <span>#{{ entry.id }}</span>
              <span aria-hidden="true">·</span>
              <time
                :datetime="entry.occurred_at ?? undefined"
                dir="ltr"
              >
                {{ entry.occurred_at_formatted ?? '—' }}
              </time>
            </span>
          </span>
          <span class="finance-receipt__method">
            <TableEnum
              :item="entry"
              column="label"
            />
          </span>
          <span class="finance-receipt__amount">
            <bdi>{{ entry.amount.formatted }}</bdi>
          </span>
          <VIcon
            icon="tabler-chevron-down"
            class="finance-receipt__chevron"
            size="18"
            aria-hidden="true"
          />
        </summary>
        <div class="finance-receipt__details">
          <dl class="finance-receipt__metadata">
            <div>
              <dt>{{ t('dailyfinance::daily_finance.receipts.receipt') }}</dt>
              <dd>
                <bdi>#{{ entry.id }}</bdi>
              </dd>
            </div>
            <div>
              <dt>{{ t('payment::payments.table.cashier') }}</dt>
              <dd>{{ entry.receipt?.cashier ?? '—' }}</dd>
            </div>
            <div>
              <dt>{{ t('payment::payments.table.order_reference_no') }}</dt>
              <dd>
                <bdi>{{ entry.reference ?? '—' }}</bdi>
              </dd>
            </div>
            <div>
              <dt>{{ t('payment::payments.show.received_at') }}</dt>
              <dd>
                <time
                  :datetime="entry.occurred_at ?? undefined"
                  dir="ltr"
                >
                  {{ entry.occurred_at_formatted ?? '—' }}
                </time>
              </dd>
            </div>
          </dl>
          <div class="finance-receipt__footer">
            <span>
              <VIcon
                :icon="kind === 'refunds' ? 'tabler-arrow-up-right' : 'tabler-arrow-down-left'"
                size="18"
              />
              {{ t(`dailyfinance::daily_finance.receipts.amount_${kind}`) }}
            </span>
            <strong>
              <bdi>{{ entry.amount.formatted }}</bdi>
            </strong>
          </div>
        </div>
      </details>
    </div>
  </section>
</template>

<style lang="scss" scoped>
.finance-receipt {
  border: 1px solid transparent;
  border-block-end-color: rgba(var(--v-theme-on-surface), 0.1);
}

.finance-receipt[open] {
  border-color: rgba(var(--v-theme-on-surface), 0.12);
  border-radius: 12px;
  margin-block: 8px;
}

.finance-receipt__summary {
  display: grid;
  grid-template-columns: 38px minmax(0, 1fr) auto minmax(80px, auto) 18px;
  align-items: center;
  gap: 8px 12px;
  padding: 12px 10px;
  border-radius: 10px;
  list-style: none;
  cursor: pointer;
}

.finance-receipt__summary::-webkit-details-marker {
  display: none;
}

.finance-receipt__summary:hover {
  background: rgba(var(--v-theme-primary), 0.025);
}

.finance-receipt__summary:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: -2px;
}

.finance-receipt__icon {
  display: grid;
  place-items: center;
  inline-size: 38px;
  block-size: 38px;
  border-radius: 11px;
  background: rgba(var(--v-theme-primary), 0.07);
  color: rgb(var(--v-theme-primary));
}

.finance-receipt__reference {
  min-inline-size: 0;
  overflow-wrap: anywhere;
}

.finance-receipt__reference strong {
  display: block;
  font-size: 0.9rem;
  line-height: 1.4;
}

.finance-receipt__meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 5px;
  margin-block-start: 4px;
  font-size: 0.7rem;
  color: rgba(var(--v-theme-on-surface), 0.6);
}

.finance-receipt__amount {
  font-size: 1rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  text-align: end;
  overflow-wrap: anywhere;
}

.finance-receipt__chevron {
  color: rgba(var(--v-theme-on-surface), 0.5);
}

.finance-receipt[open] .finance-receipt__chevron {
  transform: rotate(180deg);
}

.finance-receipt__details {
  margin: 0 10px 10px;
  padding: 16px;
  border-radius: 8px;
  background: rgba(var(--v-theme-on-surface), 0.035);
}

.finance-receipt__metadata {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px 24px;
  margin: 0;
}

.finance-receipt__metadata dt {
  font-size: 0.7rem;
  color: rgba(var(--v-theme-on-surface), 0.6);
}

.finance-receipt__metadata dd {
  margin: 4px 0 0;
  font-size: 0.85rem;
  font-weight: 500;
  overflow-wrap: anywhere;
}

.finance-receipt__footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  margin-block-start: 18px;
  padding-block-start: 12px;
  border-block-start: 1px dashed rgba(var(--v-theme-on-surface), 0.18);
  font-size: 0.85rem;
}

.finance-receipt__footer > span {
  display: flex;
  align-items: center;
  gap: 6px;
}

.finance-receipt__footer strong {
  font-size: 1.05rem;
  font-variant-numeric: tabular-nums;
  text-align: end;
  overflow-wrap: anywhere;
}

.finance-receipts__empty {
  padding: 52px 20px;
  text-align: center;
}

.finance-receipts__empty-icon {
  display: inline-grid;
  place-items: center;
  inline-size: 64px;
  block-size: 64px;
  margin-block-end: 16px;
  border-radius: 18px;
  background: rgba(var(--v-theme-primary), 0.07);
  color: rgb(var(--v-theme-primary));
}

.finance-receipts__empty h4 {
  font-size: 1rem;
}

.finance-receipts__empty p {
  max-inline-size: 280px;
  margin: 6px auto 0;
  font-size: 0.8rem;
  color: rgba(var(--v-theme-on-surface), 0.6);
}

@media (max-width: 600px) {
  .finance-receipt__summary {
    grid-template-columns: 38px minmax(0, 1fr) minmax(0, auto) 18px;
    gap: 8px;
  }

  .finance-receipt__method {
    grid-column: 2 / -1;
    grid-row: 2;
  }

  .finance-receipt__amount {
    grid-column: 3;
    grid-row: 1;
  }

  .finance-receipt__chevron {
    grid-column: 4;
    grid-row: 1;
  }

  .finance-receipt__metadata {
    gap: 14px;
  }
}
</style>
