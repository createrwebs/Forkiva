<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import FinanceHint from './FinanceHint.vue'
import AnalyticsChart from '@/modules/core/components/Charts/AnalyticsChart.vue'
import type { DailyFinanceSummary } from '@/modules/dailyfinance/contracts/dailyFinance'
const props = defineProps<{ payments: DailyFinanceSummary['payments'] }>()
const { t, locale } = useI18n()
const emit = defineEmits<{ review: [] }>()
const colors = ['primary', '#477f9f', '#329ca6', '#91ced2', '#9473d6', '#91a2b8']
const hasActivity = computed(() =>
  props.payments?.methods.some((row) => row.collections.amount !== 0 || row.refunds.amount !== 0),
)
const segments = computed(
  () =>
    props.payments?.methods.flatMap((row, index) => {
      const total = props.payments?.collections.amount ?? 0
      if (total <= 0 || row.collections.amount <= 0) return []
      const share = row.collections.amount / total
      return [
        {
          id: row.method.id,
          name: row.method.name,
          share,
          amount: row.collections.amount,
          color: colors[index % colors.length] ?? 'primary',
          label: new Intl.NumberFormat(locale.value, {
            style: 'percent',
            maximumFractionDigits: 1,
          }).format(share),
        },
      ]
    }) ?? [],
)
const datasets = computed(() => [
  {
    label: t('dailyfinance::daily_finance.metrics.collections'),
    data: segments.value.map((segment) => segment.amount),
    color: segments.value.map((segment) => segment.color),
  },
])

function segmentColor(color: string) {
  return color.startsWith('#') ? color : `rgb(var(--v-theme-${color}))`
}
</script>

<template>
  <section class="finance-panel finance-payments">
    <div class="finance-section-heading">
      <div class="finance-payments__heading">
        <h2>{{ t('dailyfinance::daily_finance.payments.title') }}</h2>
        <FinanceHint :text="t('dailyfinance::daily_finance.payments.hint')" />
      </div>
      <VBtn
        v-if="payments"
        @click="emit('review')"
        variant="text"
        size="small"
        :aria-label="t('dailyfinance::daily_finance.actions.payments')"
        prepend-icon="tabler-receipt"
      >
        {{ t('dailyfinance::daily_finance.actions.receipts') }}
      </VBtn>
    </div>
    <p
      v-if="!payments"
      class="finance-empty-message"
    >
      {{ t('dailyfinance::daily_finance.restricted') }}
    </p>
    <template v-else>
      <figure
        v-if="segments.length"
        class="finance-mix"
        :aria-label="t('dailyfinance::daily_finance.payments.mix_hint')"
      >
        <div class="finance-mix__chart">
          <AnalyticsChart
            type="doughnut"
            :datasets="datasets"
            :labels="segments.map((segment) => segment.name)"
            :height="190"
            :currency="payments.collections.currency"
            :precision="payments.collections.precision"
            :rtl="locale === 'ar'"
            aria-hidden="true"
          />
          <div class="finance-mix__center">
            <strong
              :class="{ 'finance-mix__long-total': payments.collections.formatted.length > 16 }"
            >
              <bdi>{{ payments.collections.formatted }}</bdi>
            </strong>
            <span>{{ t('dailyfinance::daily_finance.metrics.collections') }}</span>
          </div>
        </div>
        <ul class="finance-mix__legend">
          <li
            v-for="segment in segments"
            :key="segment.id"
          >
            <i :style="{ background: segmentColor(segment.color) }" />
            <span>{{ segment.name }}</span>
            <bdi>{{ segment.label }}</bdi>
          </li>
        </ul>
      </figure>
      <div
        v-else
        class="finance-payments-empty"
      >
        <span
          class="finance-payments-empty__icon"
          aria-hidden="true"
        >
          <VIcon
            :icon="hasActivity ? 'tabler-receipt-refund' : 'tabler-receipt-off'"
            size="30"
          />
        </span>
        <h3>
          {{
            t(
              `dailyfinance::daily_finance.payments.${hasActivity ? 'no_collections' : 'empty_title'}`,
            )
          }}
        </h3>
        <p>
          {{ t(`dailyfinance::daily_finance.payments.${hasActivity ? 'refunds_only' : 'empty'}`) }}
        </p>
      </div>
      <table
        v-if="payments.methods.length"
        class="finance-payment-table"
      >
        <thead>
          <tr>
            <th>{{ t('dailyfinance::daily_finance.payments.method') }}</th>
            <th>{{ t('dailyfinance::daily_finance.metrics.collections') }}</th>
            <th>{{ t('dailyfinance::daily_finance.metrics.refunds') }}</th>
            <th>{{ t('dailyfinance::daily_finance.payments.net') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in payments.methods"
            :key="row.method.id"
          >
            <td>
              <span class="finance-payment-method">
                <VIcon
                  :icon="row.method.icon"
                  size="20"
                />
                <strong>{{ row.method.name }}</strong>
              </span>
            </td>
            <td>
              <span class="finance-mobile-label">
                {{ t('dailyfinance::daily_finance.metrics.collections') }}
              </span>
              <bdi>{{ row.collections.formatted }}</bdi>
            </td>
            <td>
              <span class="finance-mobile-label">
                {{ t('dailyfinance::daily_finance.metrics.refunds') }}
              </span>
              <bdi>{{ row.refunds.formatted }}</bdi>
            </td>
            <td>
              <span class="finance-mobile-label">
                {{ t('dailyfinance::daily_finance.payments.net') }}
              </span>
              <bdi>{{ row.net.formatted }}</bdi>
            </td>
          </tr>
        </tbody>
      </table>
      <div class="finance-payments__total">
        <div>
          <span>{{ t('dailyfinance::daily_finance.payments.net') }}</span>
          <strong>
            <bdi>{{ payments.net.formatted }}</bdi>
          </strong>
        </div>
      </div>
    </template>
  </section>
</template>

<style lang="scss" scoped>
.finance-payments__heading {
  display: flex;
  align-items: center;
  gap: 4px;
}

.finance-payments {
  align-self: start;
}

.finance-mix {
  display: grid;
  grid-template-columns: minmax(190px, 1fr) minmax(160px, 1fr);
  align-items: center;
  gap: 24px;
  margin: 0;
  padding: 8px 32px 24px;
}

.finance-mix__chart {
  position: relative;
  inline-size: 190px;
  justify-self: center;
}

.finance-mix__center {
  position: absolute;
  inset: 50% auto auto 50%;
  transform: translate(-50%, -50%);
  inline-size: 68%;
  text-align: center;
  pointer-events: none;
}

.finance-mix__center strong {
  display: block;
  font-size: 1rem;
  font-weight: 750;
  font-variant-numeric: tabular-nums;
  overflow-wrap: anywhere;
}

.finance-mix__center .finance-mix__long-total {
  font-size: 0.8rem;
}

.finance-mix__center span {
  display: block;
  margin-block-start: 2px;
  color: rgba(var(--v-theme-on-surface), 0.6);
  font-size: 0.72rem;
}

.finance-mix__legend {
  display: grid;
  gap: 16px;
  list-style: none;
  margin: 0;
  padding: 0;
}

.finance-mix__legend li {
  display: grid;
  grid-template-columns: 10px minmax(0, 1fr) auto;
  align-items: center;
  gap: 10px;
  font-size: 0.8rem;
}

.finance-mix__legend bdi {
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

.finance-mix__legend i {
  inline-size: 10px;
  block-size: 10px;
  border-radius: 50%;
}

.finance-payments-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
  min-block-size: 222px;
  padding: 24px;
  margin: 0 24px 20px;
  border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.025);
  text-align: center;
}

.finance-payments-empty__icon {
  display: grid;
  place-items: center;
  inline-size: 62px;
  block-size: 62px;
  border-radius: 50%;
  background: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-border-color), 0.12);
  color: rgba(var(--v-theme-on-surface), 0.5);
}

.finance-payments-empty h3 {
  font-size: 0.95rem;
  font-weight: 650;
}

.finance-payments-empty p {
  margin: 0;
  max-inline-size: 300px;
  font-size: 0.78rem;
  line-height: 1.65;
  color: rgba(var(--v-theme-on-surface), 0.6);
}

.finance-payment-table {
  border-collapse: collapse;
  inline-size: calc(100% - 48px);
  margin-inline: 24px;
  font-size: 0.78rem;
}

.finance-payment-table th {
  color: rgba(var(--v-theme-on-surface), 0.65);
  background: rgba(var(--v-theme-on-surface), 0.035);
  font-size: 0.7rem;
  font-weight: 600;
}

.finance-payment-table th,
.finance-payment-table td {
  text-align: end;
  padding: 11px 10px;
  font-variant-numeric: tabular-nums;
}

.finance-payment-table th:first-child,
.finance-payment-table td:first-child {
  text-align: start;
}

.finance-payment-table td {
  border-block-end: 1px solid rgba(var(--v-border-color), 0.09);
}

.finance-payment-method {
  display: flex;
  align-items: center;
  gap: 10px;
}

.finance-payment-method strong {
  font-weight: 600;
}

.finance-payments__total {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
  padding: 18px 24px;
}

.finance-payments__total > div {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-inline-start: auto;
}

.finance-payments__total span {
  font-size: 0.75rem;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

.finance-payments__total strong {
  font-size: 1rem;
}

.finance-mobile-label {
  display: none;
}

@media (max-width: 600px) {
  .finance-payments__heading {
    display: flex;
    align-items: center;
    gap: 4px;
  }

  .finance-payments {
    align-self: start;
  }

  .finance-mix {
    grid-template-columns: minmax(0, 1fr);
    gap: 20px;
    padding-inline: 24px;
  }

  .finance-mix__legend {
    gap: 12px;
  }

  .finance-payments-empty {
    margin-inline: 18px;
  }

  .finance-payment-table {
    display: block;
    inline-size: auto;
    margin-inline: 18px;
  }

  .finance-payment-table thead {
    display: none;
  }

  .finance-payment-table tbody {
    display: block;
  }

  .finance-payment-table tr {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px 6px;
    padding-block: 14px;
    border-block-end: 1px solid rgba(var(--v-border-color), 0.1);
  }

  .finance-payment-table td {
    border: 0;
    text-align: start;
    padding: 0;
    font-size: 0.73rem;
    overflow-wrap: anywhere;
  }

  .finance-payment-table td:first-child {
    grid-column: 1 / -1;
    font-size: 0.85rem;
  }

  .finance-mobile-label {
    display: block;
    font-size: 0.63rem;
    color: rgba(var(--v-theme-on-surface), 0.6);
    margin-block-end: 4px;
  }

  .finance-payments__total {
    padding-inline: 18px;
  }
}
</style>
