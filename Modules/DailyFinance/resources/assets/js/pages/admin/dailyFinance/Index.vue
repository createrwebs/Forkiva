<script lang="ts" setup>
import { computed, defineAsyncComponent, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/modules/auth/composables/auth'
import { useDailyFinance } from '@/modules/dailyfinance/composables/useDailyFinance'
import type {
  DailyFinanceEntryKind,
  DailyFinanceSession,
  DailyFinanceSummary,
} from '@/modules/dailyfinance/contracts/dailyFinance'
import FinanceMetric from './Partials/FinanceMetric.vue'
import FinanceSessions from './Partials/FinanceSessions.vue'
import FinanceFilters from './Partials/FinanceFilters.vue'
import FinancePayments from './Partials/FinancePayments.vue'
import FinanceCash from './Partials/FinanceCash.vue'
import FinanceDues from './Partials/FinanceDues.vue'
import FinanceLoading from './Partials/FinanceLoading.vue'
import FinanceFollowUp from './Partials/FinanceFollowUp.vue'
import { useHandoverFollowUp } from '@/modules/dailyfinance/composables/useHandoverFollowUp'
import type { CashHandoverFollowUpKind } from '@/modules/dailyfinance/contracts/cashHandover'
import { useSettlementSummary } from '@/modules/dailyfinance/composables/useSettlementSummary'
import { useClosingFollowUp } from '@/modules/dailyfinance/composables/useClosingFollowUp'
import type { SettlementStatusFilter } from '@/modules/dailyfinance/contracts/paymentSettlement'

const FinanceEntries = defineAsyncComponent(() => import('./Partials/FinanceEntries.vue'))
const FinanceSessionDrawer = defineAsyncComponent(
  () => import('./Partials/FinanceSessionDrawer.vue'),
)
const FinanceHandoverDrawer = defineAsyncComponent(
  () => import('./Partials/FinanceHandoverDrawer.vue'),
)
const FinanceDayClosingDrawer = defineAsyncComponent(
  () => import('./Partials/FinanceDayClosingDrawer.vue'),
)
const FinanceCashFollowUpDrawer = defineAsyncComponent(
  () => import('./Partials/FinanceCashFollowUpDrawer.vue'),
)
const cashFollowUpKind = ref<CashHandoverFollowUpKind | null>(null)
const closingOpen = ref(false)
const FinanceSettlementDrawer = defineAsyncComponent(
  () => import('./Partials/FinanceSettlementDrawer.vue'),
)
const settlementsOpen = ref(false)
const settlementStatus = ref<SettlementStatusFilter | null>(null)
const { t } = useI18n()
const { can } = useAuth()
const { summary, loading, error, load } = useDailyFinance()
const refreshing = ref(false)
const canReviewSessions = computed(
  () => can('admin.pos_sessions.index') && can('admin.pos_sessions.show'),
)
const branchId = ref<number | null>(null)
const date = ref<string | null>(null)
const branches = ref<{ id: number; name: string }[]>([])
const branchLocked = ref(false)
const today = ref('')
const entryKind = ref<DailyFinanceEntryKind | null>(null)
const entrySummary = ref<DailyFinanceSummary | null>(null)
const sessionId = ref<number | null>(null)
const handoversOpen = ref(false)
const handoverSessionId = ref<number | null>(null)
const handoverSessions = ref<DailyFinanceSession[]>([])
const handoverButton = ref<{ $el: HTMLElement } | null>(null)
const handoverActivator = ref<HTMLElement | null>(null)
const canReviewHandovers = computed(
  () => canReviewSessions.value && can('admin.daily_finance_handovers.index'),
)
const canReviewClosings = computed(() =>
  [
    'admin.daily_finance_closings.index',
    'admin.orders.index',
    'admin.payments.index',
    'admin.pos_cash_movements.index',
    'admin.pos_sessions.index',
    'admin.pos_sessions.show',
    'admin.daily_finance_handovers.index',
  ].every((permission) => can(permission)),
)
const cashFollowUp = useHandoverFollowUp(
  () => branchId.value,
  () => canReviewHandovers.value,
)
const closingFollowUp = useClosingFollowUp(
  () => branchId.value,
  () => date.value,
  () => canReviewClosings.value,
)
const sessionsElement = ref<HTMLElement | null>(null)
const canReviewSettlements = computed(
  () => can('admin.daily_finance_settlements.index') && can('admin.payments.index'),
)
const settlementFollowUp = useSettlementSummary(
  () => branchId.value,
  () => canReviewSettlements.value,
)

function reviewCashFollowUp(kind: CashHandoverFollowUpKind) {
  if (loading.value || error.value || !branchId.value || !canReviewHandovers.value) return
  cashFollowUpKind.value = kind
}

async function refreshCash() {
  await Promise.all([refresh(), cashFollowUp.load(), closingFollowUp.load()])
}

function reviewClosing() {
  if (loading.value || error.value || !branchId.value || !date.value || !canReviewClosings.value)
    return
  closingOpen.value = true
}

function reviewSettlements(status: SettlementStatusFilter | null = null) {
  if (loading.value || error.value || !branchId.value || !canReviewSettlements.value) return
  settlementStatus.value = status
  settlementsOpen.value = true
}

async function refresh() {
  await load({ branch_id: branchId.value, date: date.value })
  if (summary.value) {
    branchId.value = summary.value.meta.branch_id
    date.value = summary.value.meta.date
    today.value = summary.value.meta.today
    branches.value = summary.value.branches
    branchLocked.value = summary.value.meta.branch_locked
  }
}

async function changeBranch(value: number | null) {
  entryKind.value = null
  sessionId.value = null
  handoversOpen.value = false
  cashFollowUpKind.value = null
  closingOpen.value = false
  settlementsOpen.value = false
  branchId.value = value
  await refresh()
}

async function refreshFromButton() {
  refreshing.value = true
  try {
    await Promise.all([
      refresh(),
      settlementFollowUp.load(),
      cashFollowUp.load(),
      closingFollowUp.load(),
    ])
  } finally {
    refreshing.value = false
  }
}

async function changeDate(value: string | null) {
  entryKind.value = null
  sessionId.value = null
  handoversOpen.value = false
  cashFollowUpKind.value = null
  closingOpen.value = false
  settlementsOpen.value = false
  date.value = value || null
  await refresh()
}

function reviewEntries(kind: DailyFinanceEntryKind) {
  if (!summary.value) return

  entrySummary.value = summary.value
  entryKind.value = kind
}

function reviewClosingSessions() {
  closingOpen.value = false
  reviewSessions()
}

function reviewClosingHandovers() {
  closingOpen.value = false
  reviewHandovers()
}

function reviewSessions() {
  sessionsElement.value?.scrollIntoView({
    behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
    block: 'start',
  })
  sessionsElement.value?.focus({ preventScroll: true })
}

function reviewHandovers(id: number | null = null) {
  if (loading.value || error.value || !canReviewHandovers.value || !summary.value?.sessions) return
  if (
    id !== null &&
    !summary.value.sessions.rows.some((row) => row.id === id && row.status === 'closed')
  )
    return

  handoverSessions.value = summary.value.sessions.rows
  handoverActivator.value =
    document.activeElement instanceof HTMLElement ? document.activeElement : null
  handoverSessionId.value = id
  sessionId.value = null
  handoversOpen.value = true
}

function closeHandovers() {
  handoversOpen.value = false
  const activator = handoverActivator.value?.isConnected
    ? handoverActivator.value
    : handoverButton.value?.$el
  activator?.focus({ preventScroll: true })
  handoverActivator.value = null
}

const excludedCurrencies = computed(() =>
  [summary.value?.sales, summary.value?.payments, summary.value?.movements].reduce(
    (count, section) => count + (section?.excluded_currency_count ?? 0),
    0,
  ),
)

const metrics = computed(() => [
  {
    key: 'sales',
    value: summary.value?.sales?.total ?? null,
    allowed: summary.value?.permissions.sales,
    kind: 'sales' as const,
    icon: 'tabler-shopping-cart',
    color: 'primary',
  },
  {
    key: 'collections',
    value: summary.value?.payments?.collections ?? null,
    allowed: summary.value?.permissions.payments,
    kind: 'collections' as const,
    icon: 'tabler-coins',
    color: 'success',
  },
  {
    key: 'refunds',
    value: summary.value?.payments?.refunds ?? null,
    allowed: summary.value?.permissions.payments,
    kind: 'refunds' as const,
    icon: 'tabler-arrow-back-up',
    color: 'error',
  },
  {
    key: 'expenses',
    value: summary.value?.expenses ?? null,
    allowed: summary.value?.permissions.expenses,
    kind: null,
    icon: 'tabler-file-invoice',
    color: 'info',
  },
])

onMounted(refresh)
</script>

<template>
  <div
    class="daily-finance"
    :aria-busy="loading"
  >
    <header class="finance-header">
      <div>
        <h1>{{ t('dailyfinance::daily_finance.title') }}</h1>
        <p>{{ t('dailyfinance::daily_finance.subtitle') }}</p>
      </div>
      <div class="finance-header__actions">
        <VBtn
          :loading="refreshing"
          :disabled="loading"
          color="on-surface"
          prepend-icon="tabler-refresh"
          variant="tonal"
          @click="refreshFromButton"
        >
          {{ t('dailyfinance::daily_finance.actions.refresh') }}
        </VBtn>
        <VBtn
          v-if="canReviewSessions"
          :disabled="loading || !!error || !summary?.sessions"
          color="secondary"
          variant="tonal"
          prepend-icon="tabler-list-details"
          @click="reviewSessions"
        >
          {{ t('dailyfinance::daily_finance.actions.review_sessions') }}
        </VBtn>
        <VBtn
          v-if="canReviewHandovers"
          ref="handoverButton"
          :disabled="loading || !!error || !summary?.sessions"
          color="success"
          variant="tonal"
          prepend-icon="tabler-cash-banknote"
          @click="reviewHandovers()"
        >
          {{ t('dailyfinance::cash_handovers.title') }}
        </VBtn>
        <VBtn
          v-if="canReviewClosings"
          :disabled="loading || !!error || !summary?.meta.branch_id"
          color="primary"
          prepend-icon="tabler-calendar-check"
          @click="reviewClosing"
        >
          {{ t('dailyfinance::day_closings.title') }}
        </VBtn>
        <VBtn
          v-if="canReviewSettlements"
          :disabled="loading || !!error || !summary?.meta.branch_id"
          color="secondary"
          variant="tonal"
          prepend-icon="tabler-building-bank"
          @click="reviewSettlements()"
        >
          {{ t('dailyfinance::payment_settlements.title') }}
        </VBtn>
      </div>
    </header>
    <FinanceFilters
      :branch-id="branchId"
      :branches="branches"
      :branch-locked="branchLocked"
      :date="date"
      :today="today"
      :loading="loading"
      @branch="changeBranch"
      @date="changeDate"
    />
    <VAlert
      v-if="error"
      type="error"
      variant="tonal"
      role="alert"
    >
      {{
        t(
          error === 403
            ? 'dailyfinance::daily_finance.errors.forbidden'
            : 'dailyfinance::daily_finance.errors.load',
        )
      }}
      <VBtn
        variant="text"
        @click="refresh"
      >
        {{ t('dailyfinance::daily_finance.actions.retry') }}
      </VBtn>
    </VAlert>
    <FinanceLoading
      v-else-if="loading"
      :settlements="canReviewSettlements"
      :handovers="canReviewHandovers"
      :closings="canReviewClosings"
    />
    <VAlert
      v-else-if="summary && !summary.meta.branch_id"
      type="info"
      variant="tonal"
    >
      {{ t('dailyfinance::daily_finance.no_branch') }}
    </VAlert>
    <template v-else-if="summary">
      <VAlert
        v-if="excludedCurrencies"
        type="warning"
        variant="tonal"
      >
        {{
          t('dailyfinance::daily_finance.currency_warning', {
            count: excludedCurrencies,
            currency: summary.meta.currency,
          })
        }}
      </VAlert>
      <div
        class="finance-overview"
        :aria-label="t('dailyfinance::daily_finance.overview')"
      >
        <FinanceMetric
          v-for="metric in metrics"
          :key="metric.key"
          :label="t(`dailyfinance::daily_finance.metrics.${metric.key}`)"
          :value="metric.value"
          :hint="t(`dailyfinance::daily_finance.hints.${metric.key}`)"
          :unavailable="
            t(
              metric.allowed
                ? 'dailyfinance::daily_finance.unavailable'
                : 'dailyfinance::daily_finance.restricted',
            )
          "
          :icon="metric.icon"
          :color="metric.color"
          :interactive="!!metric.kind && !!metric.allowed"
          @review="metric.kind && reviewEntries(metric.kind)"
        />
      </div>
      <FinanceFollowUp
        v-if="branchId && (canReviewSettlements || canReviewHandovers || canReviewClosings)"
        :settlements="
          canReviewSettlements
            ? {
                summary: settlementFollowUp.summary.value,
                loading: settlementFollowUp.loading.value,
                error: settlementFollowUp.error.value,
              }
            : undefined
        "
        :cash="
          canReviewHandovers
            ? {
                summary: cashFollowUp.summary.value,
                loading: cashFollowUp.loading.value,
                error: cashFollowUp.error.value,
              }
            : undefined
        "
        :closing="
          canReviewClosings
            ? {
                summary: closingFollowUp.summary.value,
                loading: closingFollowUp.loading.value,
                error: closingFollowUp.error.value,
              }
            : undefined
        "
        @review-settlements="reviewSettlements('outstanding')"
        @retry-settlements="settlementFollowUp.load()"
        @review-cash="reviewCashFollowUp"
        @retry-cash="cashFollowUp.load()"
        @review-closing="reviewClosing"
        @retry-closing="closingFollowUp.load()"
      />
      <div class="finance-columns">
        <FinancePayments
          :payments="summary.payments"
          @review="reviewEntries('collections')"
        />
        <div class="finance-cash-column">
          <FinanceCash
            :sessions="summary.sessions"
            :movements="summary.movements"
            @movements="reviewEntries('movements')"
          />
          <FinanceDues
            v-if="summary.sales"
            :amount="summary.sales.dues"
            @review="reviewEntries('dues')"
          />
        </div>
      </div>
      <div
        v-if="summary.sessions"
        ref="sessionsElement"
        tabindex="-1"
        class="finance-sessions-anchor"
      >
        <FinanceSessions
          :sessions="summary.sessions"
          @review="sessionId = $event"
          @handover="reviewHandovers($event)"
        />
      </div>
    </template>
    <FinanceDayClosingDrawer
      v-if="closingOpen && branchId && date && canReviewClosings"
      :branch-id="branchId"
      :date="date"
      :branch-name="branches.find((branch) => branch.id === branchId)?.name ?? ''"
      @close="closingOpen = false"
      @saved="refresh"
      @reviewed="closingFollowUp.acceptReview"
      @sessions="reviewClosingSessions"
      @handovers="reviewClosingHandovers"
    />
    <FinanceSettlementDrawer
      v-if="settlementsOpen && branchId && date"
      :branch-id="branchId"
      :date="date"
      :branch-name="branches.find((branch) => branch.id === branchId)?.name ?? ''"
      :initial-status="settlementStatus"
      @close="settlementsOpen = false"
      @changed="settlementFollowUp.load()"
    />
    <FinanceEntries
      v-if="entryKind && entrySummary"
      :kind="entryKind"
      :summary="entrySummary"
      @kind="entryKind = $event"
      @close="entryKind = null"
    />
    <FinanceSessionDrawer
      v-if="sessionId"
      :id="sessionId"
      @close="sessionId = null"
      @saved="refreshCash"
      @handover="reviewHandovers($event)"
    />
    <FinanceCashFollowUpDrawer
      v-if="cashFollowUpKind && branchId && canReviewHandovers"
      :branch-id="branchId"
      :branch-name="branches.find((branch) => branch.id === branchId)?.name ?? ''"
      :kind="cashFollowUpKind"
      @close="cashFollowUpKind = null"
      @saved="refreshCash"
    />
    <FinanceHandoverDrawer
      v-if="handoversOpen"
      :sessions="handoverSessions"
      :initial-id="handoverSessionId"
      @close="closeHandovers"
      @saved="refreshCash"
    />
  </div>
</template>

<style lang="scss" scoped>
.daily-finance {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 4px 2px 24px;
}

.finance-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
}

.finance-header h1 {
  font-size: clamp(1.6rem, 2.5vw, 2rem);
  font-weight: 750;
  letter-spacing: -0.035em;
  line-height: 1.2;
}

.finance-header p {
  margin: 6px 0 0;
  color: rgba(var(--v-theme-on-surface), 0.6);
  font-size: 0.85rem;
}

.finance-header__actions {
  display: flex;
  align-items: center;
  gap: 10px;
  max-inline-size: 65%;
  flex-wrap: wrap;
  justify-content: flex-end;
}

.daily-finance :deep(.finance-panel) {
  min-inline-size: 0;
  border: 1px solid rgba(var(--v-border-color), 0.1);
  border-radius: 12px;
  background: rgb(var(--v-theme-surface));
  box-shadow: 0 2px 8px rgba(var(--v-theme-on-surface), 0.015);
  overflow: hidden;
}

.daily-finance :deep(h2) {
  font-size: 1.05rem;
  font-weight: 700;
  line-height: 1.5;
}

.daily-finance :deep(.finance-section-heading) {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 20px 22px 16px;
}

.daily-finance :deep(.finance-empty-message) {
  padding: 32px 24px;
  color: rgba(var(--v-theme-on-surface), 0.6);
  font-size: 0.85rem;
  text-align: center;
}

.daily-finance :deep(.finance-overview) {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 16px;
}

.daily-finance :deep(.finance-columns) {
  display: grid;
  grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr);
  align-items: start;
  gap: 20px;
}

.daily-finance :deep(.finance-cash-column) {
  display: grid;
  gap: 20px;
  min-inline-size: 0;
}

.finance-sessions-anchor {
  scroll-margin-block-start: 90px;
  border-radius: 12px;
}

.finance-sessions-anchor:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 3px;
}

@media (max-width: 1200px) {
  .daily-finance :deep(.finance-overview) {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 900px) {
  .daily-finance :deep(.finance-columns) {
    grid-template-columns: minmax(0, 1fr);
  }

  .finance-header {
    align-items: flex-start;
    flex-direction: column;
  }

  .finance-header__actions {
    inline-size: 100%;
    max-inline-size: 100%;
    flex-wrap: wrap;
    justify-content: flex-start;
  }
}

@media (max-width: 600px) {
  .daily-finance {
    gap: 14px;
  }

  .finance-header {
    flex-direction: column;
    gap: 14px;
  }

  .finance-header__actions {
    inline-size: 100%;
  }

  .finance-header__actions .v-btn {
    flex: 1 1 calc(50% - 5px);
    min-inline-size: 0;
  }

  .finance-header__actions .v-btn:last-child:nth-child(3) {
    flex-basis: 100%;
  }

  .daily-finance :deep(.finance-overview) {
    gap: 12px;
  }

  .daily-finance :deep(.finance-section-heading) {
    padding: 18px;
    flex-wrap: wrap;
  }
}
</style>
