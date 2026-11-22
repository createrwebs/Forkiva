<script lang="ts" setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { type RouteLocationRaw, useRoute } from 'vue-router'
import { useAuth } from '@/modules/auth/composables/auth'
import PageStateWrapper from '@/modules/core/components/PageStateWrapper.vue'
import { usePosSession } from '@/modules/pos/composables/posSession'
import type { PosSessionDetail } from '@/modules/pos/contracts/sessionReconciliation'
import CloseDialog from './Dialogs/CloseDialog.vue'
import CashSummary from './Partials/Show/CashSummary.vue'
import PaymentBreakdown from './Partials/Show/PaymentBreakdown.vue'
import SessionHeader from './Partials/Show/SessionHeader.vue'
import SessionInformation from './Partials/Show/SessionInformation.vue'
import ClosingAudit from './Partials/Show/ClosingAudit.vue'

const { t } = useI18n()
const { can } = useAuth()
const route = useRoute()
const { getShowData } = usePosSession()
const item = ref<PosSessionDetail | null>(null)
const loading = ref(true)
const errorStatus = ref<number | null>(null)
const closeDialog = ref(false)
let requestVersion = 0

const closed = computed(() => item.value?.status.id === 'closed')
const canCloseSession = computed(
  () => can('admin.pos_sessions.close') && item.value?.can_close !== false,
)
const varianceColor = computed(() => {
  if (!item.value?.cash_reconciliation || !item.value.cash_over_short || !item.value.declared_cash)
    return 'default'
  return item.value.cash_over_short.amount === 0 ? 'success' : 'warning'
})
const varianceLabel = computed(() => {
  const amount = item.value?.cash_over_short?.amount ?? 0
  return t(
    `pos::pos_sessions.show.${amount === 0 ? 'balanced' : amount < 0 ? 'shortage' : 'surplus'}`,
  )
})
const metrics = computed(() => [
  {
    key: 'total_collections',
    value: item.value?.total_collections ?? item.value?.total_sales,
    icon: 'tabler-coins',
    tone: 'positive',
  },
  { key: 'refunds', value: item.value?.total_refunds, icon: 'tabler-refresh', tone: 'refund' },
  {
    key: 'cash_variance',
    value: item.value?.cash_over_short,
    icon: 'tabler-chart-bar',
    tone: varianceColor.value === 'success' ? 'positive' : 'neutral',
  },
])
const cashMovementsLink = computed<RouteLocationRaw>(() => ({
  name: 'admin.pos_sessions.pos_cash_movements.index',
  params: { id: item.value?.id },
}))

async function loadSession() {
  const version = ++requestVersion
  item.value = null
  closeDialog.value = false
  errorStatus.value = null
  loading.value = true
  const rawId = route.params.id
  const id = typeof rawId === 'string' && /^\d+$/.test(rawId) ? Number(rawId) : NaN
  if (!Number.isSafeInteger(id) || id < 1) {
    errorStatus.value = 404
    loading.value = false
    return
  }

  try {
    const response = await getShowData(id)
    if (version !== requestVersion) return
    if (response.status === 200 && response.data) item.value = response.data
    else errorStatus.value = response.status || 500
  } catch {
    if (version === requestVersion) errorStatus.value = 500
  } finally {
    if (version === requestVersion) loading.value = false
  }
}

watch(() => route.params.id, loadSession, { immediate: true })
onBeforeUnmount(() => {
  requestVersion++
})
</script>

<template>
  <PageStateWrapper
    :loading="loading"
    :not-found="errorStatus === 404"
    class="session-detail-page"
  >
    <VAlert
      v-if="errorStatus && errorStatus !== 404"
      role="alert"
      type="error"
      variant="tonal"
    >
      {{
        t(
          errorStatus === 403
            ? 'pos::pos_sessions.show.access_denied'
            : 'pos::pos_sessions.show.load_error',
        )
      }}
      <VBtn
        v-if="errorStatus !== 403"
        class="ms-2"
        variant="text"
        @click="loadSession"
      >
        {{ t('pos::pos_sessions.show.retry') }}
      </VBtn>
    </VAlert>
    <template v-else-if="item">
      <SessionHeader :item="item">
        <template #actions>
          <VBtn
            v-if="can('admin.pos_cash_movements.index')"
            :to="cashMovementsLink"
            color="primary"
            prepend-icon="tabler-arrows-exchange"
            variant="outlined"
          >
            {{ t('pos::pos_cash_movements.pos_cash_movements') }}
          </VBtn>
          <VBtn
            v-if="!closed && canCloseSession"
            prepend-icon="tabler-logout"
            @click="closeDialog = true"
          >
            {{ t('pos::pos_sessions.close_session') }}
          </VBtn>
        </template>
      </SessionHeader>
      <div
        v-if="closed"
        class="session-metrics"
      >
        <VCard
          v-for="metric in metrics"
          :key="metric.key"
          class="session-metric"
        >
          <span
            :class="['metric-icon', `metric-icon--${metric.tone}`]"
            aria-hidden="true"
          >
            <VIcon
              :icon="metric.icon"
              size="30"
            />
          </span>
          <div class="metric-content">
            <div class="metric-label">{{ t(`pos::pos_sessions.show.${metric.key}`) }}</div>
            <div class="metric-value-row">
              <strong class="metric-value">
                <bdi>
                  {{ metric.value?.formatted ?? t('pos::pos_sessions.show.not_available') }}
                </bdi>
              </strong>
              <span
                v-if="
                  metric.key === 'cash_variance' &&
                  item.cash_reconciliation &&
                  item.cash_over_short &&
                  item.declared_cash
                "
                :class="[
                  'variance-status',
                  { 'variance-status--balanced': varianceColor === 'success' },
                ]"
              >
                <VIcon
                  :icon="
                    item.cash_over_short.amount === 0
                      ? 'tabler-circle-check'
                      : 'tabler-alert-circle'
                  "
                  size="20"
                />
                {{ varianceLabel }}
              </span>
            </div>
          </div>
        </VCard>
      </div>
      <SessionInformation
        :item="item"
        class="session-metadata"
      />
      <ClosingAudit
        v-if="closed"
        :item="item"
        class="session-metadata"
      />
      <VAlert
        v-if="!closed && can('admin.pos_sessions.close') && item.can_close === false"
        class="session-open-notice"
        type="info"
        variant="tonal"
      >
        {{ t('pos::pos_sessions.closing.not_responsible') }}
      </VAlert>
      <VAlert
        v-if="!closed"
        class="session-open-notice"
        role="status"
        type="info"
        variant="tonal"
      >
        {{ t('pos::pos_sessions.show.open_summary') }}
      </VAlert>
      <div
        :class="{ 'session-financial-panels--open': !closed }"
        class="session-financial-panels"
      >
        <VCard
          v-if="closed"
          class="session-payment-panel"
        >
          <PaymentBreakdown :item="item" />
        </VCard>
        <CashSummary
          :item="item"
          class="session-cash-panel"
        />
      </div>
      <CloseDialog
        v-if="closeDialog && !closed && canCloseSession"
        v-model="closeDialog"
        :item="item"
        @saved="loadSession"
      />
    </template>
  </PageStateWrapper>
</template>

<style lang="scss" scoped>
.session-detail-page {
  --session-line: rgba(var(--v-theme-on-surface), 0.065);
  --session-shadow: 0 3px 16px rgba(var(--v-theme-on-surface), 0.025);
  --session-positive: 0, 156, 68;
  --session-refund: 241, 37, 94;
}

:global(.v-theme--dark) .session-detail-page {
  --session-positive: 109, 220, 150;
  --session-refund: 255, 119, 155;
  --session-line: rgba(var(--v-theme-on-surface), 0.12);
}

.session-metrics {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 18px;
  margin-block-start: 18px;
}

.session-metric {
  display: flex;
  align-items: center;
  gap: 22px;
  min-inline-size: 0;
  padding: 22px;
  border: 1px solid var(--session-line);
  border-radius: 12px;
  box-shadow: var(--session-shadow);
}

.metric-icon {
  display: grid;
  place-items: center;
  inline-size: 72px;
  block-size: 72px;
  flex-shrink: 0;
  border-radius: 12px;
}

.metric-icon--positive {
  color: rgb(var(--session-positive));
  background: rgba(var(--session-positive), 0.08);
}

.metric-icon--refund {
  color: rgb(var(--session-refund));
  background: rgba(var(--session-refund), 0.065);
}

.metric-icon--neutral {
  color: rgba(var(--v-theme-on-surface), 0.7);
  background: rgba(var(--v-theme-on-surface), 0.045);
}

.metric-content {
  min-inline-size: 0;
}

.metric-label {
  font-size: 0.9375rem;
  color: rgba(var(--v-theme-on-surface), 0.65);
  margin-block-end: 5px;
}

.metric-value-row {
  display: flex;
  align-items: center;
  gap: 22px;
  flex-wrap: wrap;
}

.metric-value {
  font-size: 2rem;
  font-weight: 700;
  line-height: 1.25;
  color: rgb(var(--v-theme-on-surface));
  overflow-wrap: anywhere;
  font-variant-numeric: tabular-nums;
}

.variance-status {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 0.875rem;
  font-weight: 600;
  color: rgb(var(--v-theme-on-surface));
}

.variance-status :deep(.v-icon) {
  color: rgb(var(--v-theme-warning));
}

.variance-status--balanced {
  color: rgb(var(--session-positive));
}

.variance-status--balanced :deep(.v-icon) {
  color: inherit;
}

.session-metadata,
.session-open-notice {
  margin-block-start: 18px;
}

.session-financial-panels {
  display: grid;
  grid-template-columns: minmax(0, 1.8fr) minmax(0, 1fr);
  align-items: stretch;
  gap: 18px;
  margin-block-start: 20px;
}

.session-financial-panels--open {
  grid-template-columns: minmax(0, 1fr);
}

.session-payment-panel {
  min-inline-size: 0;
  padding: 20px;
  border: 1px solid var(--session-line);
  border-radius: 12px;
  box-shadow: var(--session-shadow);
}

.session-cash-panel {
  min-inline-size: 0;
}

@media (max-width: 1200px) {
  .session-metric {
    gap: 14px;
    padding: 18px;
  }

  .metric-icon {
    inline-size: 54px;
    block-size: 54px;
  }

  .metric-value {
    font-size: 1.75rem;
  }
}

@media (max-width: 1100px) {
  .session-financial-panels {
    grid-template-columns: minmax(0, 1fr);
  }
}

@media (max-width: 700px) {
  .session-metrics {
    grid-template-columns: minmax(0, 1fr);
    gap: 12px;
  }

  .metric-content {
    flex: 1;
  }

  .metric-value-row {
    justify-content: space-between;
    gap: 12px;
  }

  .session-payment-panel {
    padding: 18px;
  }
}
</style>
