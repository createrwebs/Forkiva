<script lang="ts" setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/modules/auth/composables/auth'
import { getApiErrorStatus } from '@/modules/core/api/errors'
import TableStatus from '@/modules/core/components/Table/Partials/TableStatus.vue'
import { show } from '@/modules/pos/api/session.api'
import type { PosSessionDetail } from '@/modules/pos/contracts/sessionReconciliation'
import PaymentBreakdown from '@/modules/pos/pages/admin/session/Partials/Show/PaymentBreakdown.vue'
import ClosingAudit from '@/modules/pos/pages/admin/session/Partials/Show/ClosingAudit.vue'
import CloseDialog from '@/modules/pos/pages/admin/session/Dialogs/CloseDialog.vue'
import FinanceDrawer from './FinanceDrawer.vue'
import FinanceSessionCash from './FinanceSessionCash.vue'
import FinanceSessionLoading from './FinanceSessionLoading.vue'

const props = defineProps<{ id: number }>()
const emit = defineEmits<{ close: []; saved: []; handover: [id: number] }>()
const { t } = useI18n()
const { can } = useAuth()
const item = ref<PosSessionDetail | null>(null)
const loading = ref(false)
const error = ref<number | null>(null)
const closing = ref(false)
const activeTab = ref<'cash' | 'payments' | 'audit'>('cash')
let revision = 0
const canClose = computed(
  () =>
    item.value?.status.id === 'open' &&
    item.value.can_close !== false &&
    can('admin.pos_sessions.close'),
)
const sessionEvents = computed(() =>
  item.value?.status.id === 'closed' ? (['opened', 'closed'] as const) : (['opened'] as const),
)
const closingTotals = computed(() => [
  { key: 'total_collections', value: item.value?.total_collections ?? item.value?.total_sales },
  { key: 'total_refunds', value: item.value?.total_refunds },
])

async function load() {
  const current = ++revision
  activeTab.value = 'cash'
  loading.value = true
  item.value = null
  error.value = null
  try {
    const response = await show(props.id)
    if (current === revision) item.value = response.data.body
  } catch (cause: unknown) {
    if (current === revision) error.value = getApiErrorStatus(cause) || 500
  } finally {
    if (current === revision) loading.value = false
  }
}

async function saved() {
  closing.value = false
  emit('saved')
  await load()
}

watch(() => props.id, load, { immediate: true })
onBeforeUnmount(() => {
  revision++
})
</script>

<template>
  <FinanceDrawer
    :title="item?.pos_register.name ?? t('dailyfinance::daily_finance.sessions.title')"
    :context="item ? `${item.branch.name} · #${item.id}` : ''"
    :persistent="closing"
    compact
    @close="emit('close')"
  >
    <template
      v-if="item"
      #header-end
    >
      <TableStatus :item="item" />
      <VBtn
        v-if="item.status.id === 'closed' && can('admin.daily_finance_handovers.index')"
        color="primary"
        variant="tonal"
        prepend-icon="tabler-cash-banknote"
        @click="emit('handover', item.id)"
      >
        {{ t('dailyfinance::cash_handovers.title') }}
      </VBtn>
    </template>
    <FinanceSessionLoading v-if="loading" />
    <VAlert
      v-else-if="error"
      type="error"
      variant="tonal"
      role="alert"
    >
      {{
        t(
          error === 403
            ? 'dailyfinance::daily_finance.errors.forbidden'
            : 'dailyfinance::daily_finance.entries.error',
        )
      }}
      <VBtn
        variant="text"
        @click="load"
      >
        {{ t('dailyfinance::daily_finance.actions.retry') }}
      </VBtn>
    </VAlert>
    <div
      v-else-if="item"
      class="finance-session-detail"
    >
      <section class="finance-session-detail__information">
        <header class="finance-session-detail__information-header">
          <h3>{{ t('pos::pos_sessions.show.session_details') }}</h3>
          <div class="finance-session-detail__identity">
            <VAvatar
              color="secondary"
              variant="tonal"
              size="32"
            >
              <VIcon
                icon="tabler-user"
                size="18"
              />
            </VAvatar>
            <div>
              <strong>{{ item.responsible_cashier?.name || item.opened_by.name || '—' }}</strong>
              <span>{{ t('pos::pos_sessions.show.responsible_cashier') }}</span>
            </div>
          </div>
        </header>
        <div class="finance-session-detail__timeline">
          <div
            v-for="event in sessionEvents"
            :key="event"
          >
            <VIcon
              :icon="event === 'opened' ? 'tabler-login' : 'tabler-logout'"
              size="20"
              aria-hidden="true"
            />
            <dl>
              <dt>{{ t(`pos::pos_sessions.show.${event}_by`) }}</dt>
              <dd class="finance-session-detail__person">{{ item[`${event}_by`].name || '—' }}</dd>
              <dt class="finance-session-detail__date-label">
                {{ t(`pos::pos_sessions.show.${event}_at`) }}
              </dt>
              <dd class="finance-session-detail__date">
                <bdi dir="ltr">{{ item[`${event}_at`] ?? '—' }}</bdi>
              </dd>
            </dl>
          </div>
        </div>
      </section>
      <FinanceSessionCash
        :item="item"
        :overview="activeTab === 'cash'"
      >
        <VTabs
          v-if="item.status.id === 'closed'"
          v-model="activeTab"
          class="finance-session-detail__tabs"
          density="compact"
          color="primary"
          :show-arrows="false"
        >
          <VTab value="cash">{{ t('dailyfinance::daily_finance.sessions.cash_overview') }}</VTab>
          <VTab value="payments">{{ t('dailyfinance::daily_finance.sessions.payments') }}</VTab>
          <VTab value="audit">{{ t('pos::pos_sessions.show.closing_audit') }}</VTab>
        </VTabs>
      </FinanceSessionCash>
      <template v-if="item.status.id === 'closed'">
        <dl
          v-if="activeTab === 'cash'"
          class="finance-session-detail__totals"
          role="tabpanel"
          :aria-label="t('dailyfinance::daily_finance.sessions.cash_overview')"
        >
          <div
            v-for="row in closingTotals"
            :key="row.key"
          >
            <dt>{{ t(`pos::pos_sessions.show.${row.key}`) }}</dt>
            <dd>
              <bdi>{{ row.value?.formatted ?? t('pos::pos_sessions.show.not_available') }}</bdi>
            </dd>
          </div>
          <div>
            <dt>{{ t('pos::pos_sessions.show.orders_count') }}</dt>
            <dd>{{ item.orders_count ?? '—' }}</dd>
          </div>
        </dl>
        <div
          v-else-if="activeTab === 'payments'"
          role="tabpanel"
          :aria-label="t('dailyfinance::daily_finance.sessions.payments')"
        >
          <PaymentBreakdown
            :item="item"
            compact
          />
        </div>
        <div
          v-else
          class="finance-session-detail__audit"
          role="tabpanel"
          :aria-label="t('pos::pos_sessions.show.closing_audit')"
        >
          <ClosingAudit :item="item" />
        </div>
      </template>
      <section
        v-if="item.notes"
        class="finance-session-detail__notes"
      >
        <VIcon
          icon="tabler-notes"
          size="20"
          aria-hidden="true"
        />
        <div>
          <h3>{{ t('pos::pos_sessions.show.notes') }}</h3>
          <p>{{ item.notes }}</p>
        </div>
      </section>
      <div
        v-if="canClose"
        class="finance-session-detail__actions"
      >
        <VBtn
          color="primary"
          prepend-icon="tabler-logout"
          @click="closing = true"
        >
          {{ t('pos::pos_sessions.close_session') }}
        </VBtn>
      </div>
      <CloseDialog
        v-if="closing && canClose"
        v-model="closing"
        :item="item"
        @saved="saved"
      />
    </div>
  </FinanceDrawer>
</template>

<style lang="scss" scoped>
.finance-session-detail {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding-block: 6px 14px;
}

.finance-session-detail__identity {
  display: flex;
  align-items: center;
  gap: 12px;
}

.finance-session-detail__identity strong {
  font-size: 0.9375rem;
  font-weight: 600;
}

.finance-session-detail__identity span {
  display: block;
  margin-block-start: 3px;
  font-size: 0.75rem;
  color: rgba(var(--v-theme-on-surface), 0.6);
}

.finance-session-detail__tabs {
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.1);
  margin-block: 20px 0;
}

.finance-session-detail__tabs :deep(.v-tab) {
  min-inline-size: 0;
  padding-inline: 16px;
  text-transform: none;
  letter-spacing: 0;
  font-size: 0.8125rem;
}

.finance-session-detail__totals {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 20px;
  padding: 20px;
  border-radius: 8px;
  background: rgba(var(--v-theme-on-surface), 0.035);
  margin-block-start: -12px;
}

.finance-session-detail__totals > div + div {
  padding-inline-start: 20px;
  border-inline-start: 1px solid rgba(var(--v-theme-on-surface), 0.1);
}

.finance-session-detail__totals dt {
  margin-block-end: 6px;
  color: rgba(var(--v-theme-on-surface), 0.65);
  font-size: 0.75rem;
}

.finance-session-detail__totals dd {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  overflow-wrap: anywhere;
}

.finance-session-detail__information-header {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 16px;
  padding-block: 4px 18px;
}

.finance-session-detail__information h3 {
  font-size: 0.9375rem;
  font-weight: 600;
}

.finance-session-detail__timeline > div {
  display: flex;
  align-items: center;
  gap: 16px;
  padding-block: 14px;
  border-block-start: 1px solid rgba(var(--v-theme-on-surface), 0.1);
}

.finance-session-detail__timeline > div:last-child {
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.1);
}

.finance-session-detail__timeline dl {
  display: grid;
  grid-template-columns: minmax(0, 0.8fr) minmax(0, 1fr) auto;
  align-items: center;
  gap: 12px;
  flex: 1;
  min-inline-size: 0;
}

.finance-session-detail__timeline :deep(.v-icon) {
  flex-shrink: 0;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

.finance-session-detail__timeline dt {
  font-size: 0.8125rem;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

.finance-session-detail__timeline dd {
  margin: 0;
  font-size: 0.8125rem;
  overflow-wrap: anywhere;
}

.finance-session-detail__person {
  font-weight: 500;
}

.finance-session-detail__date {
  text-align: end;
  font-variant-numeric: tabular-nums;
}

.finance-session-detail__date-label {
  position: absolute;
  inline-size: 1px;
  block-size: 1px;
  overflow: hidden;
  clip-path: inset(50%);
  white-space: nowrap;
}

.finance-session-detail__audit :deep(.closing-audit) {
  border: 0;
  padding: 0;
  border-radius: 0;
}

.finance-session-detail__audit :deep(.closing-audit-people) {
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 20px;
  padding: 16px;
  border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.035);
}

.finance-session-detail__notes {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.finance-session-detail__notes h3 {
  margin-block-end: 6px;
  font-size: 0.875rem;
  font-weight: 600;
}

.finance-session-detail__notes p {
  margin: 0;
  font-size: 0.8125rem;
  line-height: 1.6;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
  color: rgba(var(--v-theme-on-surface), 0.7);
}

.finance-session-detail__actions {
  display: flex;
  justify-content: flex-end;
  padding-block-start: 16px;
  border-block-start: 1px solid rgba(var(--v-theme-on-surface), 0.1);
}

@media (max-width: 600px) {
  .finance-session-detail__tabs :deep(.v-tab) {
    padding-inline: 10px;
    font-size: 0.75rem;
  }

  .finance-session-detail__totals {
    gap: 10px;
    padding: 16px 12px;
  }

  .finance-session-detail__totals > div + div {
    padding-inline-start: 10px;
  }

  .finance-session-detail__totals dd {
    font-size: 1rem;
  }

  .finance-session-detail__timeline > div {
    align-items: flex-start;
    gap: 12px;
  }

  .finance-session-detail__timeline dl {
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 4px 12px;
  }

  .finance-session-detail__date {
    grid-column: 1 / -1;
    text-align: start;
    color: rgba(var(--v-theme-on-surface), 0.65);
  }
}
</style>
