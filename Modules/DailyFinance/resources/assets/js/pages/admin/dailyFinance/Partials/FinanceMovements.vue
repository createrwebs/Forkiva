<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import TableEnum from '@/modules/core/components/Table/Partials/TableEnum.vue'
import type { DailyFinanceEntry } from '@/modules/dailyfinance/contracts/dailyFinance'

defineProps<{ entries: DailyFinanceEntry[] }>()
const { t } = useI18n()

function sign(entry: DailyFinanceEntry) {
  return entry.direction?.id === 'in' ? '+' : entry.direction?.id === 'out' ? '−' : ''
}
</script>

<template>
  <section class="finance-movements">
    <div
      v-if="!entries.length"
      class="finance-movements__empty"
    >
      <span class="finance-movements__empty-icon">
        <VIcon
          icon="tabler-cash-off"
          size="32"
        />
      </span>
      <h4>{{ t('dailyfinance::daily_finance.movements.empty_title') }}</h4>
      <p>{{ t('dailyfinance::daily_finance.movements.empty_hint') }}</p>
    </div>
    <div v-else>
      <details
        v-for="entry in entries"
        :key="entry.id"
        class="finance-movement"
        :class="{ 'finance-movement--out': entry.direction?.id === 'out' }"
      >
        <summary class="finance-movement__summary">
          <span
            class="finance-movement__icon"
            aria-hidden="true"
          >
            <VIcon
              :icon="entry.label.icon || 'tabler-cash'"
              size="22"
            />
          </span>
          <span class="finance-movement__reference">
            <strong>{{ entry.label.name }}</strong>
            <span class="finance-movement__meta">
              <span>{{ entry.movement?.register || '—' }}</span>
              <span aria-hidden="true">·</span>
              <time
                :datetime="entry.occurred_at ?? undefined"
                dir="ltr"
              >
                {{ entry.occurred_at_formatted ?? '—' }}
              </time>
            </span>
          </span>
          <span class="finance-movement__direction">
            <TableEnum
              v-if="entry.direction"
              :item="entry"
              column="direction"
            />
          </span>
          <span class="finance-movement__amount">
            <bdi>{{ sign(entry) }}{{ entry.amount.formatted }}</bdi>
          </span>
          <VIcon
            icon="tabler-chevron-down"
            size="18"
            class="finance-movement__chevron"
            aria-hidden="true"
          />
        </summary>
        <div class="finance-movement__details">
          <dl class="finance-movement__metadata">
            <div>
              <dt>{{ t('dailyfinance::daily_finance.movements.number') }}</dt>
              <dd>
                <bdi>#{{ entry.id }}</bdi>
              </dd>
            </div>
            <div>
              <dt>{{ t('pos::pos_cash_movements.table.reference') }}</dt>
              <dd>
                <bdi>{{ entry.reference || '—' }}</bdi>
              </dd>
            </div>
          </dl>
          <dl
            v-if="entry.movement"
            class="finance-movement__balances"
          >
            <div>
              <dt>{{ t('pos::pos_cash_movements.table.balance_before') }}</dt>
              <dd>
                <bdi>{{ entry.movement.balance_before.formatted }}</bdi>
              </dd>
            </div>
            <div class="finance-movement__change">
              <dt>{{ t('pos::pos_cash_movements.table.amount') }}</dt>
              <dd>
                <bdi>{{ sign(entry) }}{{ entry.amount.formatted }}</bdi>
              </dd>
            </div>
            <div class="finance-movement__after">
              <dt>{{ t('pos::pos_cash_movements.table.balance_after') }}</dt>
              <dd>
                <bdi>{{ entry.movement.balance_after.formatted }}</bdi>
              </dd>
            </div>
          </dl>
          <p
            v-else
            class="finance-movement__unavailable"
          >
            {{ t('dailyfinance::daily_finance.unavailable') }}
          </p>
        </div>
      </details>
    </div>
  </section>
</template>

<style lang="scss" scoped>
.finance-movement {
  --movement-tone: var(--v-theme-success);
  border: 1px solid transparent;
  border-block-end-color: rgba(var(--v-theme-on-surface), 0.1);
}

.finance-movement--out {
  --movement-tone: var(--v-theme-error);
}

.finance-movement[open] {
  border-color: rgba(var(--v-theme-on-surface), 0.12);
  border-radius: 12px;
  margin-block: 8px;
}

.finance-movement__summary {
  display: grid;
  grid-template-columns: 38px minmax(0, 1fr) auto minmax(80px, auto) 18px;
  align-items: center;
  gap: 8px 12px;
  padding: 12px 10px;
  border-radius: 10px;
  list-style: none;
  cursor: pointer;
}

.finance-movement__summary::-webkit-details-marker {
  display: none;
}

.finance-movement__summary:hover {
  background: rgba(var(--v-theme-on-surface), 0.025);
}

.finance-movement__summary:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: -2px;
}

.finance-movement__icon {
  display: grid;
  place-items: center;
  inline-size: 38px;
  block-size: 38px;
  border-radius: 11px;
  background: rgba(var(--movement-tone), 0.08);
  color: rgb(var(--movement-tone));
}

.finance-movement__reference {
  min-inline-size: 0;
  overflow-wrap: anywhere;
}

.finance-movement__reference strong {
  display: block;
  font-size: 0.9rem;
  line-height: 1.4;
}

.finance-movement__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
  margin-block-start: 4px;
  font-size: 0.7rem;
  color: rgba(var(--v-theme-on-surface), 0.6);
}

.finance-movement__amount {
  font-size: 1rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  color: rgb(var(--movement-tone));
  text-align: end;
  overflow-wrap: anywhere;
}

.finance-movement__chevron {
  color: rgba(var(--v-theme-on-surface), 0.5);
}

.finance-movement[open] .finance-movement__chevron {
  transform: rotate(180deg);
}

.finance-movement__details {
  padding: 16px;
  margin: 0 10px 10px;
  border-radius: 8px;
  background: rgba(var(--v-theme-on-surface), 0.035);
}

.finance-movement__metadata {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
  margin: 0;
}

.finance-movement__details dt {
  font-size: 0.7rem;
  color: rgba(var(--v-theme-on-surface), 0.6);
}

.finance-movement__details dd {
  margin: 4px 0 0;
  font-size: 0.85rem;
  font-weight: 500;
  font-variant-numeric: tabular-nums;
  overflow-wrap: anywhere;
}

.finance-movement__balances {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
  padding-block-start: 14px;
  margin: 16px 0 0;
  border-block-start: 1px dashed rgba(var(--v-theme-on-surface), 0.18);
}

.finance-movement__balances .finance-movement__change dd {
  color: rgb(var(--movement-tone));
  font-weight: 700;
}

.finance-movement__balances .finance-movement__after dd {
  font-weight: 700;
}

.finance-movement__unavailable {
  margin-block-start: 16px;
  font-size: 0.8rem;
}

.finance-movements__empty {
  padding: 44px 20px;
  text-align: center;
}

.finance-movements__empty-icon {
  display: inline-grid;
  place-items: center;
  inline-size: 64px;
  block-size: 64px;
  margin-block-end: 16px;
  border-radius: 18px;
  color: rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.07);
}

.finance-movements__empty h4 {
  font-size: 1rem;
}

.finance-movements__empty p {
  margin: 6px 0 0;
  font-size: 0.8rem;
  color: rgba(var(--v-theme-on-surface), 0.6);
}

@media (max-width: 600px) {
  .finance-movement__summary {
    grid-template-columns: 38px minmax(0, 1fr) minmax(0, auto) 18px;
    gap: 8px;
  }

  .finance-movement__direction {
    grid-column: 2 / -1;
    grid-row: 2;
  }

  .finance-movement__amount {
    grid-column: 3;
    grid-row: 1;
  }

  .finance-movement__chevron {
    grid-column: 4;
    grid-row: 1;
  }

  .finance-movement__balances {
    grid-template-columns: minmax(0, 1fr);
    gap: 10px;
  }

  .finance-movement__balances > div {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 12px;
  }

  .finance-movement__balances dd {
    margin: 0;
    text-align: end;
  }
}
</style>
