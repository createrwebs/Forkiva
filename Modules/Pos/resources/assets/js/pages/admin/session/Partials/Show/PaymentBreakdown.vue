<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { SessionPaymentSummary } from '@/modules/pos/contracts/sessionReconciliation'

const props = defineProps<{ item: SessionPaymentSummary | null; compact?: boolean }>()
const { t } = useI18n()
const legacyTotals = computed(() => [
  { key: 'cash_collections', value: props.item?.system_cash_sales },
  { key: 'card_collections', value: props.item?.system_card_sales },
  { key: 'other_collections', value: props.item?.system_other_sales },
])
</script>

<template>
  <div :class="['payment-breakdown', { 'payment-breakdown--compact': compact }]">
    <div class="payment-heading">
      <span
        v-if="!compact"
        class="payment-heading-icon"
        aria-hidden="true"
      >
        <VIcon
          icon="tabler-credit-card"
          size="24"
        />
      </span>
      <div>
        <h2>{{ t('pos::pos_sessions.show.cards.payment_breakdown.title') }}</h2>
        <p class="text-body-2 text-medium-emphasis mb-0">
          {{
            t(
              compact
                ? 'pos::pos_sessions.closing.payment_hint'
                : 'pos::pos_sessions.show.cards.payment_breakdown.description',
            )
          }}
        </p>
      </div>
    </div>
    <template v-if="item?.payment_reconciliation">
      <VAlert
        v-if="item.payment_reconciliation.unattributed_order_payments_count > 0"
        type="warning"
        variant="tonal"
        class="mt-4"
        role="alert"
      >
        {{
          t('pos::pos_sessions.show.excluded_order_payments', {
            count: item.payment_reconciliation.unattributed_order_payments_count,
          })
        }}
      </VAlert>
      <VAlert
        v-if="item.payment_reconciliation.currency_mismatch_payments_count > 0"
        type="warning"
        variant="tonal"
        class="mt-3"
        role="alert"
      >
        {{
          t('pos::pos_sessions.show.excluded_currency_payments', {
            count: item.payment_reconciliation.currency_mismatch_payments_count,
          })
        }}
      </VAlert>
      <VTable class="payment-table mt-4">
        <thead>
          <tr>
            <th scope="col">
              {{
                t(
                  compact
                    ? 'pos::pos_sessions.closing.method'
                    : 'pos::pos_sessions.show.payment_method',
                )
              }}
            </th>
            <th
              scope="col"
              class="payment-amount"
            >
              {{
                t(
                  compact
                    ? 'pos::pos_sessions.closing.collected'
                    : 'pos::pos_sessions.show.collections',
                )
              }}
            </th>
            <th
              scope="col"
              class="payment-amount"
            >
              {{
                t(compact ? 'pos::pos_sessions.closing.refunded' : 'pos::pos_sessions.show.refunds')
              }}
            </th>
            <th
              scope="col"
              class="payment-amount"
            >
              {{
                t(
                  compact
                    ? 'pos::pos_sessions.closing.net'
                    : 'pos::pos_sessions.show.net_collections',
                )
              }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in item.payment_reconciliation.methods"
            :key="row.method.id"
            :data-method="row.method.id"
          >
            <th
              scope="row"
              class="payment-method"
            >
              <div class="payment-method-label">
                <VIcon
                  v-if="row.method.icon"
                  :icon="row.method.icon"
                  size="20"
                />
                <span>{{ row.method.name }}</span>
              </div>
            </th>
            <td :class="['payment-amount', { 'payment-zero': row.collections.amount === 0 }]">
              <span class="mobile-payment-label">
                {{
                  t(
                    compact
                      ? 'pos::pos_sessions.closing.collected'
                      : 'pos::pos_sessions.show.collections',
                  )
                }}
              </span>
              <bdi class="payment-value">{{ row.collections.formatted }}</bdi>
            </td>
            <td :class="['payment-amount', { 'payment-zero': row.refunds.amount === 0 }]">
              <span class="mobile-payment-label">
                {{
                  t(
                    compact
                      ? 'pos::pos_sessions.closing.refunded'
                      : 'pos::pos_sessions.show.refunds',
                  )
                }}
              </span>
              <bdi class="payment-value">{{ row.refunds.formatted }}</bdi>
            </td>
            <td
              :class="['payment-amount', 'payment-net', { 'payment-zero': row.net.amount === 0 }]"
            >
              <span class="mobile-payment-label">
                {{
                  t(
                    compact
                      ? 'pos::pos_sessions.closing.net'
                      : 'pos::pos_sessions.show.net_collections',
                  )
                }}
              </span>
              <bdi class="payment-value">{{ row.net.formatted }}</bdi>
            </td>
          </tr>
        </tbody>
      </VTable>
      <div :class="['tender-note', compact ? 'mt-2' : 'mt-4']">
        <VIcon
          v-if="!compact"
          icon="tabler-info-circle"
          size="18"
        />
        <span>
          {{
            t(
              compact
                ? 'pos::pos_sessions.closing.tender_note'
                : 'pos::pos_sessions.show.tender_note',
            )
          }}
        </span>
      </div>
    </template>
    <template v-else-if="item">
      <p class="text-body-2 text-medium-emphasis mt-4">
        {{ t('pos::pos_sessions.show.legacy_payment_summary') }}
      </p>
      <dl class="legacy-payment-totals">
        <div
          v-for="row in legacyTotals"
          :key="row.key"
        >
          <dt class="text-body-2 text-medium-emphasis">
            {{ t(`pos::pos_sessions.show.${row.key}`) }}
          </dt>
          <dd>{{ row.value?.formatted ?? t('pos::pos_sessions.show.not_available') }}</dd>
        </div>
      </dl>
    </template>
  </div>
</template>

<style lang="scss" scoped>
.payment-breakdown {
  min-inline-size: 0;
}
.payment-heading {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}
.payment-heading-icon {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  inline-size: 50px;
  block-size: 50px;
  border-radius: 10px;
  color: rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.1);
}
.payment-heading h2 {
  font-size: 1.25rem;
  font-weight: 700;
  line-height: 1.4;
  margin-block-end: 3px;
  color: rgb(var(--v-theme-on-surface));
}
.payment-heading p {
  font-size: 0.875rem !important;
  line-height: 1.5;
}
.payment-table {
  --v-table-row-height: 47px;
  --v-table-header-height: 43px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  font-size: 0.875rem;
}
.payment-table :deep(thead) {
  background: rgba(var(--v-theme-on-surface), 0.025);
}
.payment-table :deep(th) {
  text-transform: none;
  letter-spacing: 0;
}

.payment-table :deep(thead th) {
  font-weight: 600;
}
.payment-table :deep(tbody th) {
  font-weight: 400;
  border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
.payment-table :deep(tbody tr:last-child th) {
  border-block-end: 0;
}
.payment-method {
  min-inline-size: 170px;
}
.payment-method-label {
  display: flex;
  align-items: center;
  gap: 12px;
}
.payment-breakdown:not(.payment-breakdown--compact) {
  [data-method='cash'] .payment-method-label {
    color: rgb(var(--session-positive, var(--v-theme-success)));
  }
  [data-method='card'] .payment-method-label {
    color: #198aff;
  }
  [data-method='bank_transfer'] .payment-method-label {
    color: #a03cf7;
  }
  [data-method='mobile_wallet'] .payment-method-label {
    color: rgb(var(--v-theme-primary));
  }
  [data-method='gift_card'] .payment-method-label {
    color: rgb(var(--session-refund, var(--v-theme-error)));
  }
  [data-method='reservation_deposit'] .payment-method-label {
    color: rgba(var(--v-theme-on-surface), 0.7);
  }
  .payment-method-label span {
    color: rgb(var(--v-theme-on-surface));
  }
}
.payment-amount {
  text-align: end !important;
  white-space: nowrap;
  font-variant-numeric: tabular-nums;
}
.mobile-payment-label {
  display: none;
}

.payment-net {
  font-weight: 600;
}
.payment-zero {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-weight: 400;
}
.tender-note {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px;
  border-radius: 8px;
  background: rgba(var(--v-theme-info), 0.06);
  font-size: 0.8125rem;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
}
.tender-note :deep(.v-icon) {
  flex-shrink: 0;
  margin-block-start: 1px;
  color: rgb(var(--v-theme-info));
}
.legacy-payment-totals {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
}
.legacy-payment-totals dd {
  margin: 4px 0 0;
  font-size: 1.125rem;
  font-weight: 600;
}

.payment-breakdown--compact {
  .payment-heading h2 {
    font-size: 1rem;
    font-weight: 600;
    margin-block-end: 0;
  }
  .payment-heading p {
    font-size: 0.8125rem !important;
    line-height: 1.4;
  }
  .payment-table {
    --v-table-row-height: 36px;
    --v-table-header-height: 32px;
    border: 0;
    border-radius: 0;
    margin-block-start: 12px !important;
    font-size: 0.875rem;
  }
  .payment-table :deep(thead) {
    background: none;
  }
  .payment-table :deep(th),
  .payment-table :deep(td) {
    padding-inline: 8px;
  }
  .payment-table :deep(tr > :first-child) {
    padding-inline-start: 0;
  }
  .payment-table :deep(tr > :last-child) {
    padding-inline-end: 0;
  }
  .payment-table :deep(thead th) {
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  }
  .payment-method {
    min-inline-size: 150px;
  }
  .payment-method-label {
    gap: 8px;
  }
  .payment-method-label :deep(.v-icon) {
    font-size: 18px !important;
  }
  .tender-note {
    padding: 0;
    background: none;
    border-radius: 0;
    font-size: 0.75rem;
  }
  :deep(.v-alert) {
    padding: 10px 12px;
    font-size: 0.8125rem;
    margin-block-start: 12px !important;
  }
}
@media (max-width: 600px) {
  .payment-table :deep(table),
  .payment-table :deep(tbody) {
    display: block;
    inline-size: 100%;
  }

  .payment-table :deep(thead) {
    position: absolute;
    inline-size: 1px;
    block-size: 1px;
    overflow: hidden;
    clip-path: inset(50%);
  }

  .payment-table :deep(tbody tr) {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    padding: 12px;
    gap: 10px;
    border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  }

  .payment-table :deep(tbody tr:last-child) {
    border-block-end: 0;
  }

  .payment-breakdown .payment-table :deep(table tbody tr th),
  .payment-breakdown .payment-table :deep(table tbody tr td) {
    padding: 0 !important;
    height: auto !important;
    border: 0 !important;
  }

  .payment-method {
    grid-column: 1 / -1;
    min-inline-size: 0;
  }

  .payment-amount {
    text-align: start !important;
    white-space: normal;
    overflow-wrap: anywhere;
    font-size: 0.8125rem;
  }

  .mobile-payment-label {
    display: block;
    margin-block-end: 4px;
    font-size: 0.75rem;
    font-weight: 400;
    overflow-wrap: normal;
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  }

  .legacy-payment-totals {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
