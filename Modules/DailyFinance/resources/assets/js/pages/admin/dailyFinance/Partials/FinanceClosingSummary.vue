<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import type { DailyFinanceSummary } from '@/modules/dailyfinance/contracts/dailyFinance'

defineProps<{ summary: DailyFinanceSummary }>()
const { t } = useI18n()
const cashIcons = {
  expected: 'tabler-cash-banknote',
  counted: 'tabler-calculator',
  variance: 'tabler-scale',
}
</script>

<template>
  <section class="closing-summary">
    <div class="closing-summary__section">
      <h3>{{ t('dailyfinance::day_closings.daily_summary') }}</h3>
      <dl class="closing-summary__sales">
        <div>
          <VIcon
            icon="tabler-shopping-cart"
            size="24"
          />
          <div>
            <dt>{{ t('dailyfinance::daily_finance.metrics.sales') }}</dt>
            <dd>
              <bdi>{{ summary.sales?.total.formatted }}</bdi>
            </dd>
          </div>
        </div>
        <div>
          <VIcon
            icon="tabler-coins"
            size="24"
          />
          <div>
            <dt>{{ t('dailyfinance::daily_finance.metrics.collections') }}</dt>
            <dd>
              <bdi>{{ summary.payments?.collections.formatted }}</bdi>
            </dd>
          </div>
        </div>
      </dl>
      <dl class="closing-summary__totals">
        <div>
          <dt>
            <VIcon
              icon="tabler-arrow-back-up"
              size="19"
            />
            {{ t('dailyfinance::daily_finance.metrics.refunds') }}
          </dt>
          <dd>
            <bdi>{{ summary.payments?.refunds.formatted }}</bdi>
          </dd>
        </div>
        <div>
          <dt>
            <VIcon
              icon="tabler-file-invoice"
              size="19"
            />
            {{ t('dailyfinance::daily_finance.metrics.expenses') }}
          </dt>
          <dd>
            <bdi>
              {{ summary.expenses?.formatted ?? t('dailyfinance::day_closings.available') }}
            </bdi>
          </dd>
        </div>
        <div>
          <dt>
            <VIcon
              icon="tabler-receipt"
              size="19"
            />
            {{ t('dailyfinance::daily_finance.entries.dues') }}
          </dt>
          <dd>
            <bdi>{{ summary.sales?.dues.formatted }}</bdi>
          </dd>
        </div>
      </dl>
    </div>
    <div class="closing-summary__section">
      <h3 class="closing-summary__heading">
        <VIcon
          icon="tabler-wallet"
          color="secondary"
          size="22"
        />
        {{ t('dailyfinance::daily_finance.cash.title') }}
      </h3>
      <dl class="closing-summary__cash">
        <div
          v-for="key in ['expected', 'counted', 'variance'] as const"
          :key="key"
        >
          <dt>
            <VIcon
              :icon="cashIcons[key]"
              size="18"
              aria-hidden="true"
            />
            {{ t(`dailyfinance::day_closings.${key}`) }}
          </dt>
          <dd>
            <bdi>
              {{ summary.sessions?.[key]?.formatted ?? t('dailyfinance::day_closings.available') }}
            </bdi>
          </dd>
        </div>
      </dl>
      <dl class="closing-summary__movements">
        <div
          v-for="key in ['cash_in', 'cash_out'] as const"
          :key="key"
        >
          <VIcon
            :icon="key === 'cash_in' ? 'tabler-arrow-down-left' : 'tabler-arrow-up-right'"
            size="20"
          />
          <dt>{{ t(`dailyfinance::day_closings.${key}`) }}</dt>
          <dd>
            <bdi>{{ summary.movements?.[key].formatted }}</bdi>
          </dd>
        </div>
      </dl>
    </div>
    <div class="closing-summary__section">
      <h3>{{ t('dailyfinance::day_closings.payments') }}</h3>
      <VTable
        class="closing-summary__table"
        density="compact"
      >
        <thead>
          <tr>
            <th>{{ t('dailyfinance::daily_finance.payments.method') }}</th>
            <th>{{ t('dailyfinance::daily_finance.metrics.collections') }}</th>
            <th>{{ t('dailyfinance::daily_finance.metrics.refunds') }}</th>
            <th>{{ t('dailyfinance::day_closings.net') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in summary.payments?.methods"
            :key="row.method.id"
          >
            <td>
              <span class="closing-summary__method">
                <VIcon
                  :icon="row.method.icon"
                  size="20"
                  color="secondary"
                />
                {{ row.method.name }}
              </span>
            </td>
            <td
              v-for="key in ['collections', 'refunds', 'net'] as const"
              :key="key"
            >
              <span class="closing-summary__cell-label">
                {{
                  t(
                    key === 'net'
                      ? 'dailyfinance::day_closings.net'
                      : `dailyfinance::daily_finance.metrics.${key}`,
                  )
                }}
              </span>
              <bdi>{{ row[key].formatted }}</bdi>
            </td>
          </tr>
        </tbody>
      </VTable>
    </div>
  </section>
</template>

<style lang="scss" scoped>
.closing-summary {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  min-inline-size: 0;
  gap: 28px;
}
.closing-summary__section {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 12px;
}
.closing-summary h3 {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 650;
}

.closing-summary__heading {
  display: flex;
  align-items: center;
  gap: 10px;
}

.closing-summary dl {
  margin: 0;
}
.closing-summary dd {
  margin: 0;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  overflow-wrap: anywhere;
}
.closing-summary__sales {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  padding: 20px 24px;
  border-block: 1px solid rgba(var(--v-theme-secondary), 0.12);
}
.closing-summary__sales > div {
  display: flex;
  align-items: center;
  gap: 14px;
  color: rgb(var(--v-theme-secondary));
}
.closing-summary__sales > div + div {
  padding-inline-start: 24px;
  border-inline-start: 1px solid rgba(var(--v-theme-secondary), 0.12);
}
.closing-summary__sales dt {
  font-size: 1rem;
  color: rgb(var(--v-theme-on-surface));
}
.closing-summary__sales dd {
  margin-block-start: 6px;
  font-size: clamp(1.35rem, 2.5vw, 1.75rem);
  letter-spacing: -0.025em;
  line-height: 1.25;
}
.closing-summary__totals {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
}
.closing-summary__totals > div {
  padding: 14px 16px;
  border-radius: 8px;
  background: rgba(var(--v-theme-secondary), 0.045);
}
.closing-summary__totals dt {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.9375rem;
}
.closing-summary__totals .v-icon {
  color: rgb(var(--v-theme-secondary));
  flex-shrink: 0;
}
.closing-summary__totals dd {
  margin-block-start: 8px;
  font-size: 1.125rem;
}
.closing-summary__cash {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  padding: 20px;
  border-radius: 10px;
  background: rgb(var(--v-theme-secondary));
  color: rgb(var(--v-theme-on-secondary));
}
.closing-summary__cash > div {
  text-align: center;
  padding-inline: 12px;
}
.closing-summary__cash > div + div {
  border-inline-start: 1px solid rgb(var(--v-theme-on-secondary), 0.22);
}
.closing-summary__cash dt {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  font-size: 0.8125rem;
  color: rgb(var(--v-theme-on-secondary), 0.85);
}
.closing-summary__cash dd {
  margin-block-start: 8px;
  font-size: 1.375rem;
  line-height: 1.3;
}
.closing-summary__movements {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  padding: 14px 20px;
  border-radius: 8px;
  background: rgba(var(--v-theme-secondary), 0.045);
}
.closing-summary__movements > div {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px 12px;
  font-size: 0.875rem;
}
.closing-summary__movements > div + div {
  padding-inline-start: 20px;
  border-inline-start: 1px solid rgba(var(--v-theme-secondary), 0.12);
}
.closing-summary__movements > div:first-child {
  padding-inline-end: 20px;
}
.closing-summary__movements .v-icon {
  color: rgb(var(--v-theme-secondary));
}
.closing-summary__movements dd {
  margin-inline-start: auto;
}
.closing-summary__table {
  border: 1px solid rgba(var(--v-theme-secondary), 0.12);
  border-radius: 8px;
  font-size: 1rem;
}
.closing-summary__table :deep(th) {
  background: rgba(var(--v-theme-secondary), 0.045);
  font-size: 0.9375rem;
  font-weight: 600;
  color: rgb(var(--v-theme-on-surface));
}
.closing-summary__table :deep(th:not(:first-child)),
.closing-summary__table :deep(td:not(:first-child)) {
  text-align: end;
  white-space: nowrap;
}
.closing-summary__table :deep(td:last-child) {
  font-weight: 600;
}
.closing-summary__method {
  display: flex;
  align-items: center;
  gap: 10px;
}
.closing-summary__cell-label {
  display: none;
}
@media (max-width: 600px) {
  .closing-summary__sales {
    padding: 18px 0;
  }
  .closing-summary__sales > div {
    gap: 10px;
  }
  .closing-summary__sales > div + div {
    padding-inline-start: 16px;
  }
  .closing-summary__sales > div > .v-icon {
    display: none;
  }
  .closing-summary__totals {
    grid-template-columns: minmax(0, 1fr);
    gap: 0;
    padding: 4px 14px;
    border-radius: 8px;
    background: rgba(var(--v-theme-secondary), 0.045);
  }
  .closing-summary__totals > div {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 0;
    border-radius: 0;
    background: none;
  }
  .closing-summary__totals > div + div {
    border-block-start: 1px solid rgba(var(--v-theme-secondary), 0.1);
  }
  .closing-summary__totals dd {
    margin: 0;
    font-size: 1rem;
  }
  .closing-summary__cash {
    grid-template-columns: minmax(0, 1fr);
    padding: 6px 18px;
  }
  .closing-summary__cash > div {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 14px 0;
    text-align: start;
  }
  .closing-summary__cash > div + div {
    border-inline-start: 0;
    border-block-start: 1px solid rgb(var(--v-theme-on-secondary), 0.22);
  }
  .closing-summary__cash dd {
    margin: 0;
    font-size: 1.25rem;
  }
  .closing-summary__cash dt {
    justify-content: flex-start;
  }
  .closing-summary__movements {
    padding: 14px;
    gap: 16px;
  }
  .closing-summary__movements > div {
    gap: 6px 8px;
  }
  .closing-summary__movements > div:first-child {
    padding-inline-end: 0;
  }
  .closing-summary__movements > div + div {
    padding-inline-start: 14px;
  }
  .closing-summary__movements dd {
    inline-size: 100%;
    margin-inline-start: 0;
    font-size: 1rem;
  }
  .closing-summary__table :deep(table),
  .closing-summary__table :deep(tbody) {
    display: block;
  }
  .closing-summary__table :deep(thead) {
    position: absolute;
    inline-size: 1px;
    block-size: 1px;
    clip-path: inset(50%);
    overflow: hidden;
  }
  .closing-summary__table :deep(tr) {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    padding: 14px;
    gap: 12px;
  }
  .closing-summary__table :deep(tr + tr) {
    border-block-start: 1px solid rgba(var(--v-theme-secondary), 0.1);
  }
  .closing-summary__table :deep(td),
  .closing-summary__table :deep(td:not(:first-child)) {
    block-size: auto !important;
    padding: 0 !important;
    border: 0 !important;
    text-align: start;
    white-space: normal;
  }
  .closing-summary__table :deep(td:first-child) {
    grid-column: 1 / -1;
    font-weight: 600;
  }
  .closing-summary__cell-label {
    display: block;
    margin-block-end: 5px;
    color: rgba(var(--v-theme-on-surface), 0.7);
    font-size: 0.75rem;
  }
  .closing-summary__table bdi {
    font-size: 0.8125rem;
    font-variant-numeric: tabular-nums;
    overflow-wrap: anywhere;
  }
}
</style>
