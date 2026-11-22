<script lang="ts" setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/modules/auth/composables/auth'
import { useHandoverFollowUpSessions } from '@/modules/dailyfinance/composables/useHandoverFollowUpSessions'
import type {
  CashHandoverFollowUpKind,
  CashHandoverFollowUpSession,
} from '@/modules/dailyfinance/contracts/cashHandover'
import FinanceDrawer from './FinanceDrawer.vue'
import FinanceHandovers from './FinanceHandovers.vue'
import FinanceCashFollowUpSession from './FinanceCashFollowUpSession.vue'

const props = defineProps<{
  branchId: number
  branchName: string
  kind: CashHandoverFollowUpKind
}>()
const emit = defineEmits<{ close: []; saved: [] }>()
const { t } = useI18n()
const { can } = useAuth()
const enabled = computed(() =>
  [
    'admin.daily_finance_handovers.index',
    'admin.pos_sessions.index',
    'admin.pos_sessions.show',
  ].every((permission) => can(permission)),
)
const { result, loading, error, load } = useHandoverFollowUpSessions(
  () => props.branchId,
  () => props.kind,
  () => enabled.value,
)
const selected = ref<CashHandoverFollowUpSession | null>(null)
const editing = ref(false)
const busy = ref(false)
const title = computed(() => t(`dailyfinance::cash_handovers.followup.${props.kind}`))

function back() {
  if (busy.value || editing.value) return
  selected.value = null
  void load()
}
function saved() {
  emit('saved')
}
</script>

<template>
  <FinanceDrawer
    class="cash-followup-drawer"
    :class="{ 'cash-followup-drawer--list': !selected }"
    :title="selected ? t('dailyfinance::cash_handovers.title') : title"
    :context="branchName"
    :persistent="busy || editing"
    compact
    narrow
    @close="emit('close')"
  >
    <template #context>
      <p class="cash-followup__branch">
        <VIcon
          icon="tabler-building-store"
          size="15"
        />
        <span>{{ branchName }}</span>
      </p>
    </template>
    <template #header-end>
      <VBtn
        v-if="selected"
        variant="tonal"
        color="secondary"
        prepend-icon="tabler-arrow-left"
        :disabled="busy || editing"
        @click="back"
      >
        {{ t('dailyfinance::cash_handovers.followup.sessions') }}
      </VBtn>
      <VBtn
        v-else
        variant="text"
        color="secondary"
        icon="tabler-refresh"
        :aria-label="t('dailyfinance::daily_finance.actions.refresh')"
        :disabled="loading || !enabled"
        @click="load()"
      />
    </template>
    <template v-if="selected && enabled">
      <div class="cash-followup__source">
        <strong>{{ selected.register ?? '—' }} · #{{ selected.id }}</strong>
        <small>
          {{ selected.cashier ?? '—' }} ·
          <bdi>{{ selected.closed_at_formatted ?? '—' }}</bdi>
        </small>
      </div>
      <FinanceHandovers
        :key="selected.id"
        :session-id="selected.id"
        :session-label="`${selected.register ?? '—'} · #${selected.id}`"
        @editing="editing = $event"
        @busy="busy = $event"
        @saved="saved"
      />
    </template>
    <section
      v-else
      class="cash-followup"
    >
      <div class="cash-followup__toolbar">
        <h3>
          {{ t('dailyfinance::cash_handovers.followup.sessions') }}
          <span
            v-if="loading"
            class="cash-followup__bone cash-followup__loading-count"
          />
          <span
            v-else-if="result"
            class="cash-followup__count"
          >
            {{ result.pagination.total }}
          </span>
        </h3>
        <span class="cash-followup__scope">
          <VIcon
            icon="tabler-calendar"
            size="16"
          />
          {{ t('dailyfinance::payment_settlements.followup.all_dates') }}
        </span>
      </div>
      <div
        v-if="loading || result?.data.length"
        class="cash-followup__columns"
        aria-hidden="true"
      >
        <span>{{ t('dailyfinance::cash_handovers.followup.source') }}</span>
        <span>
          {{ t(`dailyfinance::cash_handovers.${kind === 'unhanded' ? 'available' : 'pending'}`) }}
        </span>
      </div>
      <div
        v-if="loading"
        role="status"
        :aria-label="t('dailyfinance::cash_handovers.followup.loading')"
        class="cash-followup__list"
      >
        <FinanceCashFollowUpSession
          v-for="index in 4"
          :key="index"
          :kind="kind"
          loading
        />
      </div>
      <div
        v-else-if="error || !enabled"
        class="cash-followup__empty"
        role="alert"
      >
        <VAvatar
          color="secondary"
          variant="tonal"
          size="56"
        >
          <VIcon
            icon="tabler-cloud-off"
            size="28"
          />
        </VAvatar>
        <h3>{{ t('dailyfinance::cash_handovers.load_error') }}</h3>
        <VBtn
          v-if="enabled"
          color="primary"
          variant="tonal"
          @click="load()"
        >
          {{ t('dailyfinance::daily_finance.actions.retry') }}
        </VBtn>
      </div>
      <div
        v-else-if="!result?.data.length"
        class="cash-followup__empty"
      >
        <VAvatar
          color="success"
          variant="tonal"
          size="56"
        >
          <VIcon
            icon="tabler-circle-check"
            size="28"
          />
        </VAvatar>
        <h3>{{ t(`dailyfinance::cash_handovers.followup.${kind}_clear`) }}</h3>
      </div>
      <div
        v-else
        class="cash-followup__list"
      >
        <FinanceCashFollowUpSession
          v-for="row in result.data"
          :key="row.id"
          :item="row"
          :kind="kind"
          @open="selected = $event"
        />
      </div>
      <footer
        v-if="loading || result?.pagination.total"
        class="cash-followup__footer"
      >
        <template v-if="loading">
          <span class="cash-followup__bone cash-followup__loading-page" />
        </template>
        <template v-else-if="result">
          <span>
            {{
              t('dailyfinance::cash_handovers.followup.page', {
                current: result.pagination.current_page,
                total: result.pagination.last_page,
              })
            }}
          </span>
          <VPagination
            v-if="result.pagination.last_page > 1"
            :model-value="result.pagination.current_page"
            :length="result.pagination.last_page"
            :total-visible="3"
            :disabled="loading"
            density="compact"
            @update:model-value="load($event)"
          />
        </template>
      </footer>
    </section>
  </FinanceDrawer>
</template>

<style lang="scss" scoped>
:global(.cash-followup-drawer--list.finance-drawer .finance-drawer__body) {
  display: flex;
  flex-direction: column;
  overflow: hidden;
  min-block-size: 0;
  padding-block: 0 !important;
}

.cash-followup {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-block-size: 0;
  min-inline-size: 0;
}

.cash-followup__branch {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 3px 0 0;
  color: rgba(var(--v-theme-on-surface), 0.65);
  font-size: 0.75rem;
}

.cash-followup__toolbar,
.cash-followup__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  flex-shrink: 0;
  gap: 12px;
  padding-block: 20px;
}

.cash-followup__toolbar h3 {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0;
  font-size: 1rem;
  font-weight: 650;
  color: rgb(var(--v-theme-secondary));
}

.cash-followup__count {
  display: inline-grid;
  place-items: center;
  min-inline-size: 24px;
  block-size: 22px;
  padding-inline: 6px;
  border-radius: 12px;
  background: rgba(var(--v-theme-secondary), 0.07);
  font-size: 0.8125rem;
}

.cash-followup__scope {
  display: flex;
  align-items: center;
  gap: 6px;
}

.cash-followup__scope,
.cash-followup__columns,
.cash-followup__footer,
.cash-followup__source small {
  color: rgba(var(--v-theme-on-surface), 0.6);
  font-size: 0.8125rem;
  line-height: 1.6;
}

.cash-followup__columns {
  display: flex;
  justify-content: space-between;
  flex-shrink: 0;
  gap: 12px;
  padding-block: 8px 12px;
  padding-inline-end: 44px;
  border-block-end: 1px solid rgba(var(--v-theme-secondary), 0.1);
}

.cash-followup__list {
  flex: 1;
  overflow-y: auto;
  min-block-size: 0;
  scrollbar-gutter: stable;
}

.cash-followup__footer {
  border-block-start: 1px solid rgba(var(--v-theme-secondary), 0.1);
  min-block-size: 64px;
  padding-block: 12px;
}

.cash-followup__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  flex: 1;
  gap: 16px;
  padding: 48px 16px;
  text-align: center;
}

.cash-followup__empty h3 {
  max-inline-size: 320px;
  margin: 0;
  font-size: 1rem;
  line-height: 1.6;
}

.cash-followup__source {
  display: grid;
  gap: 6px;
  padding-block: 20px;
}

.cash-followup__source strong {
  color: rgb(var(--v-theme-secondary));
  font-size: 0.9375rem;
}

.cash-followup__bone {
  border-radius: 4px;
  background: rgba(var(--v-theme-on-surface), 0.07);
}

.cash-followup__loading-count {
  inline-size: 24px;
  block-size: 22px;
  border-radius: 12px;
}
.cash-followup__loading-page {
  inline-size: 100px;
  block-size: 12px;
}

@media (max-width: 600px) {
  :global(.cash-followup-drawer.finance-drawer .finance-drawer__header) {
    grid-template-columns: minmax(0, 1fr) auto auto;
  }
  :global(.cash-followup-drawer.finance-drawer .finance-drawer__header-end) {
    grid-column: 2;
    grid-row: 1;
  }
  :global(.cash-followup-drawer.finance-drawer .finance-drawer__close) {
    grid-column: 3;
  }
  .cash-followup__columns {
    padding-inline-end: 34px;
    font-size: 0.75rem;
  }
  .cash-followup__toolbar {
    padding-block: 16px;
  }
}
</style>
