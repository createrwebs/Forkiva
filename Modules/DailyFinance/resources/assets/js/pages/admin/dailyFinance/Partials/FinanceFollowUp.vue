<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { Money } from '@/modules/core/contracts/Money'
import TableStatus from '@/modules/core/components/Table/Partials/TableStatus.vue'
import type { DayClosingReview } from '@/modules/dailyfinance/contracts/dayClosing'
import type { PaymentSettlementSummary } from '@/modules/dailyfinance/contracts/paymentSettlement'
import type {
  CashHandoverFollowUp,
  CashHandoverFollowUpKind,
} from '@/modules/dailyfinance/contracts/cashHandover'

interface SummaryState<T> {
  summary: T | null
  loading: boolean
  error: boolean
}
interface FollowUpRow {
  key: 'settlements' | 'closing' | CashHandoverFollowUpKind
  title: string
  icon: string
  loading: boolean
  error: boolean
  clear: boolean
  context: string
  scope?: string
  status?: { id: string; name: string; color: string; icon: string }
  statusHint?: string
  statuses?: { label: string; count: number }[]
  balanceLabel: string
  balances: { currency: string; amount: Money }[]
  clearMessage: string
}

const props = defineProps<{
  settlements?: SummaryState<PaymentSettlementSummary>
  cash?: SummaryState<CashHandoverFollowUp>
  closing?: SummaryState<DayClosingReview>
}>()
const emit = defineEmits<{
  reviewSettlements: []
  reviewCash: [kind: CashHandoverFollowUpKind]
  retrySettlements: []
  retryCash: []
  reviewClosing: []
  retryClosing: []
}>()
const { t } = useI18n()
const rows = computed<FollowUpRow[]>(() => {
  const result: FollowUpRow[] = []
  if (props.settlements) {
    const state = props.settlements
    result.push({
      key: 'settlements',
      title: t('dailyfinance::payment_settlements.followup.pending'),
      scope: props.closing ? t('dailyfinance::payment_settlements.followup.all_dates') : undefined,
      icon: 'tabler-building-bank',
      loading: state.loading || (!state.summary && !state.error),
      error: state.error,
      clear: state.summary?.open_count === 0,
      context: t('dailyfinance::payment_settlements.followup.clear'),
      statuses: [
        {
          label: t('dailyfinance::payment_settlements.followup.awaiting'),
          count: state.summary?.pending_count ?? 0,
        },
        {
          label: t('dailyfinance::payment_settlements.tabs.partial'),
          count: state.summary?.partial_count ?? 0,
        },
      ].filter((status) => status.count > 0),
      balanceLabel: t('dailyfinance::payment_settlements.followup.balance'),
      balances:
        state.summary?.balances.map((balance) => ({
          currency: balance.currency,
          amount: balance.remaining,
        })) ?? [],
      clearMessage: t('dailyfinance::payment_settlements.followup.no_balance'),
    })
  }
  if (props.cash) {
    const state = props.cash
    for (const kind of ['unhanded', 'pending'] as const) {
      const count =
        kind === 'unhanded'
          ? state.summary?.unhanded_sessions_count
          : state.summary?.pending_handovers_count
      result.push({
        key: kind,
        title: t(`dailyfinance::cash_handovers.followup.${kind}`),
        scope: props.closing
          ? t('dailyfinance::payment_settlements.followup.all_dates')
          : undefined,
        icon: kind === 'unhanded' ? 'tabler-cash' : 'tabler-clock-check',
        loading: state.loading || (!state.summary && !state.error),
        error: state.error,
        clear: count === 0,
        context: t(`dailyfinance::cash_handovers.followup.${kind}_context`, {
          count: count ?? 0,
          sessions: state.summary?.pending_sessions_count ?? 0,
        }),
        balanceLabel: t(
          `dailyfinance::cash_handovers.${kind === 'unhanded' ? 'available' : 'handed_over'}`,
        ),
        balances:
          state.summary?.balances
            .filter((balance) => /[1-9]/.test(balance[`${kind}_decimal`]))
            .map((balance) => ({ currency: balance.currency, amount: balance[kind] })) ?? [],
        clearMessage: t(`dailyfinance::cash_handovers.followup.${kind}_clear`),
      })
    }
  }
  if (props.closing) {
    const state = props.closing
    const review = state.summary
    const closing = review?.closing
    const blockers = review?.blockers ?? []
    const warnings = review?.warnings ?? []
    const reasons = [...blockers, ...warnings]
    const needsAttention = reasons.length > 0
    const status = closing ? 'closed' : needsAttention ? 'attention' : 'ready'
    const reason = reasons[0]
    let context = t('dailyfinance::day_closings.followup.ready_hint')
    if (closing) {
      context = t('dailyfinance::day_closings.followup.closed_context', {
        reference: closing.reference,
        name: closing.created_by.name,
        time: closing.created_at,
      })
    } else if (reason) {
      context = t(`dailyfinance::day_closings.checks.${reason}`)
      if (reasons.length > 1)
        context += ` ${t('dailyfinance::day_closings.followup.more', { count: reasons.length - 1 })}`
    }
    result.push({
      key: 'closing',
      title: t('dailyfinance::day_closings.title'),
      scope: review?.date_formatted,
      icon: 'tabler-calendar-check',
      loading: state.loading || (!review && !state.error),
      error: state.error,
      clear: !!closing,
      context,
      status: {
        id: status,
        name: t(
          `dailyfinance::day_closings.${status === 'attention' ? 'followup.attention' : status}`,
        ),
        color: status === 'attention' ? 'warning' : 'success',
        icon: status === 'attention' ? 'tabler-alert-circle' : 'tabler-circle-check',
      },
      statusHint: closing
        ? undefined
        : blockers.length
          ? t('dailyfinance::day_closings.followup.blockers', { count: blockers.length })
          : warnings.length
            ? t('dailyfinance::day_closings.followup.explanation')
            : undefined,
      balanceLabel: '',
      balances: [],
      clearMessage: '',
    })
  }
  return result
})
const isClear = computed(
  () => rows.value.length > 0 && rows.value.every((row) => !row.loading && !row.error && row.clear),
)
const heading = computed(() =>
  t(`dailyfinance::payment_settlements.followup.${isClear.value ? 'all_clear' : 'title'}`),
)
const groups = computed(() => {
  const attention: FollowUpRow[] = []
  const quiet: FollowUpRow[] = []
  for (const row of rows.value) {
    const summary =
      row.key === 'settlements'
        ? props.settlements?.summary
        : row.key === 'closing'
          ? props.closing?.summary
          : props.cash?.summary
    const isQuiet = row.clear || row.status?.id === 'ready'
    // Loading slots describe the layout only; readiness stays hidden until the read completes.
    const quietPlaceholder =
      row.loading && (summary ? isQuiet : row.key === 'pending' || row.key === 'closing')
    const resolved = !row.loading && !row.error && isQuiet
    if (resolved || quietPlaceholder) quiet.push(row)
    else attention.push(row)
  }

  return [
    { key: 'attention', rows: attention },
    { key: 'quiet', rows: quiet },
  ].filter((group) => group.rows.length > 0)
})

function review(key: FollowUpRow['key']) {
  if (key === 'settlements') emit('reviewSettlements')
  else if (key === 'closing') emit('reviewClosing')
  else emit('reviewCash', key)
}
function retry(key: FollowUpRow['key']) {
  if (key === 'settlements') emit('retrySettlements')
  else if (key === 'closing') emit('retryClosing')
  else emit('retryCash')
}
</script>

<template>
  <section
    class="finance-followup"
    :class="{ 'finance-followup--clear': isClear }"
    :aria-label="heading"
  >
    <header class="finance-followup__header">
      <h2>
        <VIcon
          :icon="isClear ? 'tabler-circle-check' : 'tabler-alert-circle'"
          size="20"
        />
        {{ heading }}
      </h2>
      <span
        v-if="!closing"
        class="finance-followup__scope"
      >
        {{ t('dailyfinance::payment_settlements.followup.all_dates') }}
      </span>
    </header>
    <div
      v-for="group in groups"
      :key="group.key"
      class="finance-followup__list"
      :class="{ 'finance-followup__list--quiet': group.key === 'quiet' }"
      :style="{ '--followup-columns': Math.min(group.rows.length, 2) }"
    >
      <template
        v-for="row in group.rows"
        :key="row.key"
      >
        <div
          v-if="row.loading"
          class="finance-followup__item finance-followup__item--loading"
          role="status"
          :aria-label="t('dailyfinance::daily_finance.loading')"
          aria-busy="true"
        >
          <span class="finance-followup__skeleton finance-followup__loading-icon" />
          <span class="finance-followup__identity">
            <span class="finance-followup__title">
              <span class="finance-followup__skeleton finance-followup__loading-title" />
              <span
                v-if="row.key === 'closing'"
                class="finance-followup__skeleton finance-followup__loading-status"
              />
            </span>
            <span class="finance-followup__context">
              <span class="finance-followup__skeleton finance-followup__loading-caption" />
              <span
                v-if="group.key !== 'quiet'"
                class="finance-followup__skeleton finance-followup__loading-scope"
              />
            </span>
          </span>
          <span
            v-if="group.key !== 'quiet'"
            class="finance-followup__outcome finance-followup__loading-outcome"
          >
            <span class="finance-followup__skeleton finance-followup__loading-caption" />
            <span class="finance-followup__balances">
              <span class="finance-followup__skeleton finance-followup__loading-value" />
              <span class="finance-followup__skeleton finance-followup__loading-currency" />
            </span>
          </span>
          <span
            class="finance-followup__arrow finance-followup__skeleton finance-followup__loading-arrow"
          />
        </div>
        <div
          v-else-if="row.error"
          class="finance-followup__item"
          role="alert"
        >
          <span class="finance-followup__icon">
            <VIcon
              icon="tabler-cloud-off"
              size="20"
            />
          </span>
          <span class="finance-followup__identity">
            <strong>{{ row.title }}</strong>
            <small>{{ t('dailyfinance::daily_finance.entries.error') }}</small>
          </span>
          <VBtn
            class="finance-followup__outcome"
            color="primary"
            variant="tonal"
            size="small"
            @click="retry(row.key)"
          >
            {{ t('dailyfinance::daily_finance.actions.retry') }}
          </VBtn>
        </div>
        <button
          v-else
          type="button"
          class="finance-followup__item finance-followup__button"
          :class="{ 'finance-followup__item--clear': row.clear }"
          :aria-label="
            row.key === 'settlements'
              ? t('dailyfinance::payment_settlements.followup.review')
              : row.title
          "
          aria-haspopup="dialog"
          @click="review(row.key)"
        >
          <span class="finance-followup__icon">
            <VIcon
              :icon="row.clear ? 'tabler-check' : row.icon"
              size="20"
            />
          </span>
          <span class="finance-followup__identity">
            <span class="finance-followup__title">
              <strong>{{ row.title }}</strong>
              <TableStatus
                v-if="row.status"
                :item="{ status: row.status }"
              />
            </span>
            <span class="finance-followup__context">
              <template v-if="row.statuses && !row.clear">
                <span
                  v-for="status in row.statuses"
                  :key="status.label"
                >
                  {{ status.label }}: {{ status.count }}
                </span>
              </template>
              <span v-else-if="row.status?.id !== 'ready'">
                {{ row.clear && !row.status ? row.clearMessage : row.context }}
              </span>
              <span
                v-if="row.scope"
                class="finance-followup__scope"
              >
                {{ row.scope }}
              </span>
            </span>
          </span>
          <span
            v-if="!row.clear && row.balances.length"
            class="finance-followup__outcome"
          >
            <span class="finance-followup__balance-label">{{ row.balanceLabel }}</span>
            <span class="finance-followup__balances">
              <span
                v-for="balance in row.balances"
                :key="balance.currency"
                class="finance-followup__balance"
              >
                <bdi>{{ balance.amount.formatted }}</bdi>
                <small>{{ balance.currency }}</small>
              </span>
            </span>
          </span>
          <small
            v-else-if="row.statusHint"
            class="finance-followup__outcome finance-followup__hint"
          >
            {{ row.statusHint }}
          </small>
          <VIcon
            icon="tabler-chevron-right"
            size="18"
            class="finance-followup__arrow flip-in-rtl"
          />
        </button>
      </template>
    </div>
  </section>
</template>

<style lang="scss" scoped>
.finance-followup {
  container-type: inline-size;
  overflow: hidden;
  border: 1px solid rgba(var(--v-theme-secondary), 0.1);
  border-radius: 12px;
  background: rgb(var(--v-theme-surface));
  color: rgb(var(--v-theme-secondary));
}

.finance-followup__header,
.finance-followup h2 {
  display: flex;
  align-items: center;
  gap: 8px;
}

.finance-followup__header {
  justify-content: space-between;
  padding: 10px 20px;
  border-block-end: 1px solid rgba(var(--v-theme-secondary), 0.08);
}

.finance-followup h2 {
  margin: 0;
  font-size: 0.9375rem;
  font-weight: 600;
  line-height: 1.5;
}

.finance-followup h2 .v-icon {
  color: rgb(var(--v-theme-primary));
}

.finance-followup__list {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
}

.finance-followup__item {
  display: grid;
  grid-template-columns: 36px minmax(0, 1fr) auto 18px;
  align-items: center;
  gap: 14px;
  min-inline-size: 0;
  padding: 10px 20px;
  color: inherit;
  text-align: start;
}

.finance-followup__item + .finance-followup__item {
  border-block-start: 1px solid rgba(var(--v-theme-secondary), 0.08);
}

.finance-followup__button {
  cursor: pointer;
  transition: background-color 0.15s;
}

.finance-followup__button:hover {
  background: rgba(var(--v-theme-primary), 0.04);
}

.finance-followup__button:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: -3px;
}

.finance-followup__icon {
  display: grid;
  place-items: center;
  inline-size: 36px;
  block-size: 36px;
  border-radius: 10px;
  background: rgba(var(--v-theme-primary), 0.1);
  color: rgb(var(--v-theme-primary));
}

.finance-followup__identity {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-inline-size: 0;
}

.finance-followup__title {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px 12px;
}

.finance-followup__identity strong {
  font-size: 0.875rem;
  font-weight: 600;
  line-height: 1.5;
  overflow-wrap: anywhere;
}

.finance-followup__context {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 2px 10px;
  overflow-wrap: anywhere;
}

.finance-followup small,
.finance-followup__context,
.finance-followup__scope,
.finance-followup__balance-label {
  color: rgba(var(--v-theme-on-surface), 0.65);
  font-size: 0.75rem;
  line-height: 1.5;
}

.finance-followup__context .finance-followup__scope:not(:first-child) {
  padding-inline-start: 10px;
  border-inline-start: 1px solid rgba(var(--v-theme-secondary), 0.16);
}

.finance-followup__outcome {
  grid-column: 3;
  justify-self: end;
  text-align: end;
}

.finance-followup__balances {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 4px 16px;
}

.finance-followup__balance {
  display: flex;
  align-items: baseline;
  gap: 6px;
}

.finance-followup__balance bdi {
  font-size: 1.125rem;
  font-weight: 700;
  line-height: 1.4;
  font-variant-numeric: tabular-nums;
  overflow-wrap: anywhere;
}

.finance-followup__arrow {
  grid-column: 4;
  color: rgba(var(--v-theme-secondary), 0.5);
}

.finance-followup__button:hover .finance-followup__arrow {
  color: rgb(var(--v-theme-primary));
}

.finance-followup__list--quiet {
  grid-template-columns: repeat(var(--followup-columns), minmax(0, 1fr));
  border-block-start: 1px solid rgba(var(--v-theme-secondary), 0.08);
  background: rgba(var(--v-theme-secondary), 0.025);
}

.finance-followup__list--quiet:first-of-type {
  border-block-start: 0;
}

.finance-followup__list--quiet .finance-followup__item {
  grid-template-columns: 32px minmax(0, 1fr) 18px;
  gap: 12px;
}

.finance-followup__list--quiet .finance-followup__item + .finance-followup__item {
  border-block-start: 1px solid rgba(var(--v-theme-secondary), 0.08);
}

.finance-followup__list--quiet .finance-followup__item:nth-child(2) {
  border-block-start: 0;
}

.finance-followup__list--quiet .finance-followup__item:nth-child(even) {
  border-inline-start: 1px solid rgba(var(--v-theme-secondary), 0.08);
}

.finance-followup__list--quiet .finance-followup__arrow {
  grid-column: 3;
}

.finance-followup__list--quiet .finance-followup__icon {
  inline-size: 32px;
  block-size: 32px;
  border-radius: 50%;
  background: rgba(var(--v-theme-secondary), 0.06);
  color: rgb(var(--v-theme-secondary));
}

.finance-followup--clear h2 .v-icon {
  color: rgb(var(--v-theme-success));
}

.finance-followup__item--clear .finance-followup__icon {
  background: rgba(var(--v-theme-success), 0.1);
  color: rgb(var(--v-theme-success));
}

.finance-followup__skeleton {
  position: relative;
  display: block;
  overflow: hidden;
  border-radius: 4px;
  background: rgba(var(--v-theme-on-surface), 0.07);
}

.finance-followup__skeleton::after {
  position: absolute;
  inset: 0;
  content: '';
  background: linear-gradient(90deg, transparent, rgba(var(--v-theme-surface), 0.7), transparent);
  animation: finance-followup-shimmer 1.8s ease-in-out infinite;
}

.finance-followup__loading-icon {
  inline-size: 36px;
  block-size: 36px;
  border-radius: 10px;
}

.finance-followup__loading-title {
  inline-size: 150px;
  max-inline-size: 100%;
  block-size: 16px;
}

.finance-followup__loading-caption {
  inline-size: 110px;
  max-inline-size: 80%;
  block-size: 12px;
}

.finance-followup__loading-status {
  inline-size: 90px;
  block-size: 22px;
  border-radius: 6px;
}

.finance-followup__loading-scope {
  inline-size: 45px;
  block-size: 12px;
}

.finance-followup__loading-outcome {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 5px;
}

.finance-followup__loading-outcome .finance-followup__balances {
  align-items: baseline;
  gap: 6px;
}

.finance-followup__loading-value {
  inline-size: 100px;
  block-size: 22px;
}

.finance-followup__loading-currency {
  inline-size: 24px;
  block-size: 12px;
}

.finance-followup__loading-arrow {
  inline-size: 10px;
  block-size: 16px;
  justify-self: center;
}

.finance-followup__item--loading {
  min-block-size: 68px;
}

.finance-followup__list--quiet .finance-followup__loading-icon {
  inline-size: 32px;
  block-size: 32px;
  border-radius: 50%;
}

.finance-followup__list--quiet .finance-followup__item--loading {
  min-block-size: 66px;
}

.finance-followup__list--quiet .finance-followup__loading-title {
  inline-size: 120px;
}

@container (max-width: 700px) {
  .finance-followup__list--quiet {
    grid-template-columns: minmax(0, 1fr);
  }

  .finance-followup__list--quiet .finance-followup__item + .finance-followup__item {
    border-block-start: 1px solid rgba(var(--v-theme-secondary), 0.08);
    border-inline-start: 0;
  }
}

@container (max-width: 440px) {
  .finance-followup__header {
    padding: 12px 14px;
  }

  .finance-followup__item {
    grid-template-columns: 32px minmax(0, 1fr) 18px;
    gap: 8px 10px;
    padding: 12px 14px;
  }

  .finance-followup__icon,
  .finance-followup__loading-icon {
    inline-size: 32px;
    block-size: 32px;
  }

  .finance-followup__outcome {
    grid-column: 2;
    grid-row: 2;
    justify-self: start;
    text-align: start;
  }

  .finance-followup__loading-outcome {
    align-items: flex-start;
  }

  .finance-followup__item--loading {
    min-block-size: 123px;
  }

  .finance-followup__list--quiet .finance-followup__item--loading {
    min-block-size: 70px;
  }

  .finance-followup__balances {
    justify-content: flex-start;
  }

  .finance-followup__arrow {
    grid-column: 3;
    grid-row: 1;
  }
}

@keyframes finance-followup-shimmer {
  from {
    transform: translateX(-100%);
  }

  to {
    transform: translateX(100%);
  }
}

@media (prefers-reduced-motion: reduce) {
  .finance-followup__skeleton::after {
    display: none;
    animation: none;
  }
}
</style>
