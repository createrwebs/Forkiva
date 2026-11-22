<script lang="ts" setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import TableStatus from '@/modules/core/components/Table/Partials/TableStatus.vue'
import { useAuth } from '@/modules/auth/composables/auth'
import { useFinanceTabScroll } from '@/modules/dailyfinance/composables/useFinanceTabScroll'
import type {
  DailyFinanceSession,
  DailyFinanceSummary,
} from '@/modules/dailyfinance/contracts/dailyFinance'
import FinanceHint from './FinanceHint.vue'

const props = defineProps<{ sessions: NonNullable<DailyFinanceSummary['sessions']> }>()
const emit = defineEmits<{ review: [id: number]; handover: [id: number] }>()
const { t } = useI18n()
const { can } = useAuth()
const search = ref<string | null>('')
const status = ref<'all' | 'open' | 'closed'>('all')
const actionSessionId = ref<number | null>(null)
const actionsElement = ref<HTMLElement | null>(null)
const { focusTab, scrollTabs } = useFinanceTabScroll(actionsElement, status)
const pendingAction = ref<{ session: DailyFinanceSession; kind: 'review' | 'handover' } | null>(
  null,
)
const canReview = computed(() => can('admin.pos_sessions.show'))
const canHandover = computed(() => canReview.value && can('admin.daily_finance_handovers.index'))
const rows = computed(() =>
  props.sessions.rows.filter((session) => {
    const matchesStatus = status.value === 'all' || session.status === status.value
    const matchesSearch = `${session.register ?? ''} ${session.cashier ?? ''}`
      .toLocaleLowerCase()
      .includes((search.value ?? '').trim().toLocaleLowerCase())
    return matchesStatus && matchesSearch
  }),
)

function reviewSession(id: number, event: Event) {
  if (!canReview.value) return

  const row = event.currentTarget
  if (row instanceof HTMLElement) {
    row.querySelector('button')?.focus({ preventScroll: true })
  }

  emit('review', id)
}

function changeActionMenu(id: number, open: boolean) {
  if (open) actionSessionId.value = id
  else if (actionSessionId.value === id) actionSessionId.value = null
}

function selectAction(session: DailyFinanceSession, kind: 'review' | 'handover') {
  if (
    !canReview.value ||
    (kind === 'handover' && (!canHandover.value || session.status !== 'closed'))
  )
    return

  pendingAction.value = { session, kind }
  actionSessionId.value = null
}

function completeAction(id: number) {
  const action = pendingAction.value
  if (!action || action.session.id !== id) return

  pendingAction.value = null
  if (
    !canReview.value ||
    (action.kind === 'handover' && (!canHandover.value || action.session.status !== 'closed'))
  )
    return

  actionsElement.value
    ?.querySelector<HTMLButtonElement>(`button[data-session-id="${id}"]`)
    ?.focus({ preventScroll: true })
  if (action.kind === 'handover') emit('handover', id)
  else emit('review', id)
}

watch(rows, () => {
  actionSessionId.value = null
  pendingAction.value = null
})
</script>

<template>
  <section
    ref="actionsElement"
    class="finance-panel finance-sessions"
  >
    <header class="finance-sessions__header">
      <div class="finance-sessions__title">
        <h2>{{ t('dailyfinance::daily_finance.sessions.title') }}</h2>
        <FinanceHint :text="t('dailyfinance::daily_finance.sessions.hint')" />
      </div>
      <div class="finance-sessions__toolbar">
        <VTabs
          v-model="status"
          class="finance-session-tabs"
          density="compact"
          hide-slider
          :show-arrows="false"
          :scroll-to-active="false"
          @focusin.capture="focusTab"
          @click.capture="scrollTabs"
        >
          <VTab value="all">
            {{ t('dailyfinance::daily_finance.sessions.all') }}
            <span class="finance-session-tabs__count">{{ sessions.rows.length }}</span>
          </VTab>
          <VTab value="open">
            {{ t('dailyfinance::daily_finance.sessions.open') }}
            <span class="finance-session-tabs__count">{{ sessions.open_count }}</span>
          </VTab>
          <VTab value="closed">
            {{ t('dailyfinance::daily_finance.sessions.closed') }}
            <span class="finance-session-tabs__count">{{ sessions.closed_count }}</span>
          </VTab>
        </VTabs>
        <VTextField
          v-model="search"
          :placeholder="t('dailyfinance::daily_finance.sessions.search')"
          :aria-label="t('dailyfinance::daily_finance.sessions.search')"
          prepend-inner-icon="tabler-search"
          variant="outlined"
          density="compact"
          hide-details
          clearable
          class="finance-sessions__search"
          @click:clear="search = ''"
        />
      </div>
    </header>
    <div
      v-if="!rows.length"
      class="finance-empty-message"
    >
      <VIcon
        icon="tabler-clock-dollar"
        size="32"
      />
      <p>
        {{
          t(
            sessions.rows.length
              ? 'dailyfinance::daily_finance.sessions.no_match'
              : 'dailyfinance::daily_finance.sessions.empty',
          )
        }}
      </p>
    </div>
    <VTable
      v-else
      class="finance-session-table"
    >
      <thead>
        <tr>
          <th scope="col">{{ t('dailyfinance::daily_finance.sessions.register') }}</th>
          <th scope="col">{{ t('dailyfinance::daily_finance.sessions.cashier') }}</th>
          <th scope="col">{{ t('dailyfinance::daily_finance.sessions.opened') }}</th>
          <th scope="col">{{ t('dailyfinance::daily_finance.sessions.status') }}</th>
          <th
            scope="col"
            class="finance-session__amount"
          >
            {{ t('dailyfinance::daily_finance.cash.expected') }}
          </th>
          <th
            scope="col"
            class="finance-session__amount"
          >
            {{ t('dailyfinance::daily_finance.cash.counted') }}
          </th>
          <th
            scope="col"
            class="finance-session__amount"
          >
            {{ t('dailyfinance::daily_finance.cash.variance') }}
          </th>
          <th
            v-if="canReview"
            scope="col"
            class="finance-session__actions"
          >
            {{ t('dailyfinance::daily_finance.sessions.actions') }}
          </th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="session in rows"
          :key="session.id"
          :class="{ 'finance-session--interactive': canReview }"
          @click="reviewSession(session.id, $event)"
        >
          <td class="finance-session__register">
            <button
              v-if="canReview"
              type="button"
              class="finance-session__name"
              aria-haspopup="dialog"
              :aria-label="
                t('dailyfinance::daily_finance.sessions.details_for', {
                  register: session.register ?? '—',
                })
              "
            >
              {{ session.register ?? '—' }}
            </button>
            <strong
              v-else
              class="finance-session__name"
            >
              {{ session.register ?? '—' }}
            </strong>
            <span
              v-if="!session.latest_for_register"
              class="finance-session__earlier"
            >
              {{ t('dailyfinance::daily_finance.sessions.earlier') }}
            </span>
          </td>
          <td>{{ session.cashier ?? '—' }}</td>
          <td class="finance-session__opened">
            <time
              :datetime="session.opened_at ?? undefined"
              dir="ltr"
            >
              {{ session.opened_at_formatted ?? '—' }}
            </time>
          </td>
          <td>
            <TableStatus
              :item="{
                status: {
                  id: session.status,
                  name: t(`dailyfinance::daily_finance.sessions.${session.status}`),
                },
              }"
            />
          </td>
          <td class="finance-session__amount">
            <bdi>{{ session.expected?.formatted ?? '—' }}</bdi>
          </td>
          <td
            class="finance-session__amount"
            :class="{ 'finance-session__unknown': !session.counted }"
          >
            <bdi>
              {{
                session.counted?.formatted ??
                t(
                  `dailyfinance::daily_finance.cash.${session.status === 'open' ? 'not_counted' : 'unavailable'}`,
                )
              }}
            </bdi>
          </td>
          <td class="finance-session__amount">
            <bdi
              :class="{
                'finance-session__variance': session.variance && session.variance.amount !== 0,
              }"
            >
              {{ session.variance?.formatted ?? '—' }}
            </bdi>
          </td>
          <td
            v-if="canReview"
            class="finance-session__actions"
            @click.stop
          >
            <VMenu
              :model-value="actionSessionId === session.id"
              location="bottom start"
              origin="top end"
              :offset="8"
              @update:model-value="changeActionMenu(session.id, $event)"
              @after-leave="completeAction(session.id)"
            >
              <template #activator="{ props: menuProps }">
                <button
                  type="button"
                  :data-session-id="session.id"
                  v-bind="menuProps"
                  :aria-label="
                    t('dailyfinance::daily_finance.sessions.actions_for', {
                      register: session.register ?? '—',
                    })
                  "
                  class="finance-session__action-trigger"
                  :class="{
                    'finance-session__action-trigger--active': actionSessionId === session.id,
                  }"
                >
                  <VIcon
                    icon="tabler-dots-vertical"
                    size="18"
                    aria-hidden="true"
                  />
                </button>
              </template>
              <VList class="finance-session-actions-menu">
                <VListItem
                  :title="t('dailyfinance::daily_finance.sessions.view')"
                  @click="selectAction(session, 'review')"
                >
                  <template #prepend>
                    <VIcon
                      icon="tabler-eye"
                      size="20"
                      color="secondary"
                    />
                  </template>
                </VListItem>
                <VListItem
                  v-if="canHandover"
                  :title="t('dailyfinance::cash_handovers.title')"
                  :disabled="session.status !== 'closed'"
                  @click="selectAction(session, 'handover')"
                >
                  <template #prepend>
                    <VIcon
                      icon="tabler-cash-banknote"
                      size="20"
                      color="primary"
                    />
                  </template>
                </VListItem>
              </VList>
            </VMenu>
          </td>
        </tr>
      </tbody>
    </VTable>
  </section>
</template>

<style lang="scss" scoped>
.finance-sessions__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 16px 24px;
  padding: 20px 24px;
}

.finance-sessions__title {
  display: flex;
  align-items: center;
  gap: 6px;
}

.finance-sessions__toolbar {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 24px;
  margin-inline-start: auto;
  min-inline-size: 0;
}

.finance-sessions__search {
  flex: 0 1 260px;
  inline-size: 260px;
}

.finance-sessions__search :deep(input) {
  font-size: 0.8125rem;
}

.finance-session-tabs {
  flex: 0 0 auto;
  padding: 4px;
  border: 1px solid rgba(var(--v-border-color), 0.08);
  border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.035);
  block-size: auto;
}

.finance-session-tabs :deep(.v-tab) {
  min-inline-size: 78px;
  padding-inline: 14px;
  block-size: 34px;
  border-radius: 7px !important;
  text-transform: none;
  letter-spacing: 0;
  font-size: 0.8rem;
  color: rgba(var(--v-theme-on-surface), 0.7);
  // Keep responsive padding out of the active tab's scroll calculation.
  transition-property: color, background-color, opacity, box-shadow;
}

.finance-session-tabs :deep(.v-tab--selected) {
  background: rgb(var(--v-theme-secondary));
  color: rgb(var(--v-theme-on-secondary)) !important;
}

.finance-session-tabs__count {
  margin-inline-start: 8px;
  font-size: 0.7rem;
  line-height: 20px;
  min-inline-size: 20px;
  border-radius: 6px;
  background: rgba(var(--v-theme-on-surface), 0.055);
  font-variant-numeric: tabular-nums;
}

.finance-session-tabs :deep(.v-tab--selected) .finance-session-tabs__count {
  background: rgba(255, 255, 255, 0.18);
}

.finance-session-table {
  --v-table-row-height: 76px;
  --v-table-header-height: 44px;

  font-size: 0.8125rem;
}

.finance-session-table :deep(table) {
  min-inline-size: 1040px;
}

.finance-session-table .finance-session__actions {
  position: sticky;
  inset-inline-end: 0;
  z-index: 1;
  inline-size: 80px;
  text-align: end;
  background: rgb(var(--v-theme-surface));
  border-inline-start: 1px solid rgba(var(--v-border-color), 0.08);
}

.finance-session__action-trigger {
  display: inline-grid;
  place-items: center;
  inline-size: 36px;
  block-size: 36px;
  border: 1px solid rgba(var(--v-border-color), 0.14);
  border-radius: 9px;
  color: rgba(var(--v-theme-on-surface), 0.7);
  background: rgb(var(--v-theme-surface));
  cursor: pointer;
  transition:
    background 150ms,
    border-color 150ms;
}

.finance-session__action-trigger:hover,
.finance-session__action-trigger--active {
  border-color: rgba(var(--v-theme-primary), 0.35);
  color: rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.06);
}

.finance-session__action-trigger:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}

.finance-session-actions-menu {
  min-inline-size: 200px;
  padding: 6px;
  border-radius: 12px !important;
}

.finance-session-actions-menu :deep(.v-list-item) {
  min-block-size: 44px;
  padding-inline: 12px;
  border-radius: 8px;
}

.finance-session-actions-menu :deep(.v-list-item-title) {
  font-size: 0.8125rem;
}

.finance-session-table :deep(thead) {
  background: rgba(var(--v-theme-on-surface), 0.025);
}

.finance-session-table th {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.1);
  font-weight: 600;
  white-space: nowrap;
}

.finance-session-table th:first-child,
.finance-session-table td:first-child {
  padding-inline-start: 24px;
}

.finance-session-table th:last-child,
.finance-session-table td:last-child {
  padding-inline-end: 24px;
}

.finance-session-table td {
  padding-block: 16px;
  font-variant-numeric: tabular-nums;
}

.finance-session--interactive {
  cursor: pointer;
}

.finance-session--interactive:hover,
.finance-session--interactive:focus-within {
  background: rgba(var(--v-theme-primary), 0.035);
}

.finance-session__register {
  min-inline-size: 190px;
  max-inline-size: 260px;
}

.finance-session__name {
  display: block;
  color: rgb(var(--v-theme-on-surface));
  text-align: start;
  font-weight: 600;
}

button.finance-session__name {
  cursor: pointer;
  border-radius: 2px;
}

button.finance-session__name:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 4px;
}

.finance-session__earlier {
  display: block;
  margin-block-start: 4px;
  color: rgba(var(--v-theme-on-surface), 0.6);
  font-size: 0.6875rem;
}

.finance-session__opened {
  white-space: nowrap;
  color: rgba(var(--v-theme-on-surface), 0.7);
}

.finance-session-table .finance-session__amount {
  text-align: end;
  white-space: nowrap;
}

.finance-session__amount bdi {
  font-weight: 600;
}

.finance-session__unknown bdi {
  color: rgba(var(--v-theme-on-surface), 0.5);
  font-size: 0.75rem;
  font-weight: 400;
}

.finance-session__variance {
  color: rgb(var(--v-theme-error));
}

@media (max-width: 700px) {
  .finance-sessions__header {
    padding: 18px;
  }

  .finance-sessions__toolbar {
    inline-size: 100%;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 14px;
  }

  .finance-sessions__search {
    flex: 1 1 200px;
    inline-size: auto;
  }

  .finance-session-tabs {
    max-inline-size: 100%;
  }

  .finance-session-tabs :deep(.v-tab) {
    min-inline-size: 0;
    padding-inline: 8px;
  }

  .finance-session-tabs__count {
    margin-inline-start: 6px;
    min-inline-size: 18px;
  }
}
</style>
