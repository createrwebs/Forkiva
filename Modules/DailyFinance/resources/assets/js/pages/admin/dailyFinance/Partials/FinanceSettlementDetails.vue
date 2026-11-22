<script lang="ts" setup>
import { computed, defineAsyncComponent, onBeforeUnmount, onMounted, ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import { getApiErrorMessage } from '@/modules/core/api/errors'
import FinanceSettlementActivity from './FinanceSettlementActivity.vue'
import TableStatus from '@/modules/core/components/Table/Partials/TableStatus.vue'
import { settlementDetails } from '@/modules/dailyfinance/api/paymentSettlement.api'
import type { PaymentSettlement } from '@/modules/dailyfinance/contracts/paymentSettlement'

const FinanceSettlementCreditForm = defineAsyncComponent(
  () => import('./FinanceSettlementCreditForm.vue'),
)
const props = defineProps<{ id: number; branchId: number; branchName: string }>()
const emit = defineEmits<{ close: []; changed: [] }>()
const { t } = useI18n()
const titleId = useId()
const open = ref(true)
const activator = document.activeElement
const record = ref<PaymentSettlement | null>(null)
const loading = ref(true)
const error = ref('')
const action = ref<'receive' | 'cancel' | null>(null)
const tab = ref<'receipts' | 'sources'>('receipts')
const isCancelled = computed(() => record.value?.status.id === 'cancelled')
const positionKeys = computed(() =>
  isCancelled.value
    ? (['expected', 'actual'] as const)
    : (['expected', 'actual', 'remaining'] as const),
)
let controller: AbortController | null = null
let revision = 0

async function load() {
  const current = ++revision
  controller?.abort()
  controller = new AbortController()
  const signal = controller.signal
  loading.value = true
  error.value = ''
  record.value = null
  try {
    const response = await settlementDetails(props.branchId, props.id, signal)
    if (current === revision) {
      record.value = response.data.body
      if (record.value.status.id === 'cancelled') tab.value = 'sources'
    }
  } catch (failure: unknown) {
    if (current === revision && !signal.aborted)
      error.value = getApiErrorMessage(failure) || t('dailyfinance::payment_settlements.load_error')
  } finally {
    if (current === revision) loading.value = false
  }
}

function saved(value: PaymentSettlement) {
  record.value = value
  action.value = null
  tab.value = value.status.id === 'cancelled' ? 'sources' : 'receipts'
  emit('changed')
}
function afterLeave() {
  if (activator instanceof HTMLElement && activator.isConnected)
    activator.focus({ preventScroll: true })
  emit('close')
}
onMounted(load)
onBeforeUnmount(() => {
  revision++
  controller?.abort()
})
</script>

<template>
  <VDialog
    v-model="open"
    :aria-labelledby="titleId"
    :persistent="action !== null"
    max-width="820"
    scrollable
    @after-leave="afterLeave"
  >
    <VCard class="settlement-dialog settlement-detail">
      <header class="settlement-dialog__header">
        <div>
          <div class="settlement-detail__title">
            <h2 :id="titleId">
              {{ record?.reference ?? t('dailyfinance::payment_settlements.details') }}
            </h2>
            <TableStatus
              v-if="record"
              :item="record"
            />
          </div>
          <span class="settlement-dialog__context">
            <VIcon
              icon="tabler-building-store"
              size="15"
            />
            {{ branchName }}
            <template v-if="record">· {{ record.method.name }}</template>
          </span>
        </div>
        <VBtn
          icon="tabler-x"
          variant="text"
          color="on-surface"
          size="small"
          :disabled="action !== null"
          :aria-label="t('dailyfinance::payment_settlements.dismiss')"
          @click="open = false"
        />
      </header>
      <VCardText class="settlement-dialog__body settlement-detail__body">
        <VAlert
          v-if="error"
          type="warning"
          variant="tonal"
          role="alert"
        >
          {{ error }}
          <VBtn
            variant="text"
            @click="load"
          >
            {{ t('dailyfinance::payment_settlements.retry') }}
          </VBtn>
        </VAlert>
        <div
          v-else-if="loading"
          class="settlement-detail__loading"
          role="status"
          :aria-label="t('dailyfinance::payment_settlements.loading')"
        >
          <div
            class="settlement-detail__identity"
            aria-hidden="true"
          >
            <span class="settlement-detail__skeleton settlement-detail__bank-icon" />
            <div class="settlement-detail__loading-lines">
              <span class="settlement-detail__skeleton" />
              <span class="settlement-detail__skeleton" />
            </div>
          </div>
          <div
            class="settlement-detail__position"
            aria-hidden="true"
          >
            <div
              v-for="n in 3"
              :key="n"
            >
              <span class="settlement-detail__skeleton" />
              <span class="settlement-detail__skeleton settlement-detail__loading-amount" />
            </div>
          </div>
          <div
            class="settlement-detail__loading-reconciliation"
            aria-hidden="true"
          >
            <span
              v-for="n in 2"
              :key="n"
              class="settlement-detail__skeleton"
            />
          </div>
          <div
            class="settlement-detail__loading-tabs"
            aria-hidden="true"
          >
            <span
              v-for="n in 2"
              :key="n"
              class="settlement-detail__skeleton"
            />
          </div>
          <div
            class="settlement-detail__loading-list"
            aria-hidden="true"
          >
            <div
              v-for="n in 2"
              :key="n"
              class="settlement-detail__loading-row"
            >
              <span class="settlement-detail__skeleton settlement-detail__loading-icon" />
              <div class="settlement-detail__loading-lines">
                <span class="settlement-detail__skeleton" />
                <span class="settlement-detail__skeleton" />
              </div>
              <span class="settlement-detail__skeleton settlement-detail__loading-value" />
            </div>
          </div>
        </div>
        <template v-else-if="record">
          <div class="settlement-detail__identity">
            <span class="settlement-detail__bank-icon">
              <VIcon
                icon="tabler-building-bank"
                size="25"
              />
            </span>
            <div>
              <strong>{{ record.destination_name }}</strong>
              <small>
                {{ t('dailyfinance::payment_settlements.batch_reference') }}:
                <bdi>{{ record.batch_reference }}</bdi>
              </small>
            </div>
          </div>
          <section
            v-if="isCancelled"
            class="settlement-detail__cancellation"
            :aria-label="t('dailyfinance::payment_settlements.cancellation_details')"
          >
            <div class="settlement-detail__cancellation-heading">
              <VIcon
                icon="tabler-circle-x"
                size="20"
                color="error"
              />
              <strong>{{ t('dailyfinance::payment_settlements.cancellation_details') }}</strong>
              <span>
                <VIcon
                  icon="tabler-calendar-time"
                  size="15"
                />
                <bdi>{{ record.cancelled_at }}</bdi>
              </span>
            </div>
            <div class="settlement-detail__cancellation-reason">
              <span>{{ t('dailyfinance::payment_settlements.cancellation_reason') }}:</span>
              <p>{{ record.cancellation_reason }}</p>
            </div>
            <div class="settlement-detail__cancellation-meta">
              <div class="settlement-detail__cancellation-actor">
                <VIcon
                  icon="tabler-user"
                  size="15"
                />
                {{ t('dailyfinance::payment_settlements.cancelled_by') }}
                <strong>{{ record.cancelled_by?.name ?? '—' }}</strong>
              </div>
              <p class="settlement-detail__cancellation-hint">
                {{ t('dailyfinance::payment_settlements.cancelled_hint') }}
              </p>
            </div>
          </section>
          <div class="settlement-detail__summary">
            <dl
              class="settlement-detail__position"
              :class="{ 'settlement-detail__position--cancelled': isCancelled }"
            >
              <div
                v-for="key in positionKeys"
                :key="key"
              >
                <dt>
                  {{
                    t(
                      `dailyfinance::payment_settlements.${isCancelled && key === 'expected' ? 'original_expected' : key}`,
                    )
                  }}
                </dt>
                <dd>
                  <bdi>{{ record[key].formatted }}</bdi>
                </dd>
              </div>
            </dl>
            <div class="settlement-detail__reconciliation">
              <span>
                {{ t('dailyfinance::payment_settlements.gross') }}
                <strong>
                  <bdi>{{ record.gross.formatted }}</bdi>
                </strong>
              </span>
              <VIcon
                icon="tabler-minus"
                size="14"
              />
              <span>
                {{ t('dailyfinance::payment_settlements.fees') }}
                <strong>
                  <bdi>{{ record.fees.formatted }}</bdi>
                </strong>
              </span>
              <span
                v-if="record.variance"
                class="settlement-detail__variance"
              >
                <VIcon
                  :icon="record.variance.amount === 0 ? 'tabler-circle-check' : 'tabler-scale'"
                  :color="record.variance.amount === 0 ? 'success' : 'warning'"
                  size="16"
                />
                {{ t('dailyfinance::payment_settlements.variance') }}
                <strong>
                  <bdi>{{ record.variance.formatted }}</bdi>
                </strong>
              </span>
            </div>
          </div>
          <div
            v-if="record.notes"
            class="settlement-detail__notes"
          >
            <VIcon
              icon="tabler-notes"
              size="18"
            />
            <strong>{{ t('dailyfinance::payment_settlements.notes') }}</strong>
            <p>{{ record.notes }}</p>
          </div>
          <FinanceSettlementActivity
            v-model="tab"
            :record="record"
          />
        </template>
      </VCardText>
      <footer
        v-if="record"
        class="settlement-detail__footer"
      >
        <div class="settlement-detail__audit">
          <span>
            <VIcon
              icon="tabler-user"
              size="16"
            />
            {{ t('dailyfinance::payment_settlements.created_by') }}:
            <strong>{{ record.created_by.name ?? '—' }}</strong>
          </span>
          <span>
            <VIcon
              icon="tabler-calendar-time"
              size="16"
            />
            <bdi>{{ record.created_at }}</bdi>
          </span>
        </div>
        <div
          v-if="record.abilities.cancel || record.abilities.receive"
          class="settlement-detail__actions"
        >
          <VBtn
            v-if="record.abilities.cancel"
            color="error"
            variant="text"
            prepend-icon="tabler-circle-x"
            :disabled="action !== null"
            @click="action = 'cancel'"
          >
            {{ t('dailyfinance::payment_settlements.cancel') }}
          </VBtn>
          <VBtn
            v-if="record.abilities.receive"
            class="ms-auto"
            color="primary"
            prepend-icon="tabler-plus"
            :disabled="action !== null"
            @click="action = 'receive'"
          >
            {{ t('dailyfinance::payment_settlements.receive') }}
          </VBtn>
        </div>
      </footer>
      <div
        v-else-if="loading"
        class="settlement-detail__audit"
        aria-hidden="true"
      >
        <span class="settlement-detail__skeleton settlement-detail__loading-value" />
        <span class="settlement-detail__skeleton settlement-detail__loading-value" />
      </div>
    </VCard>
    <FinanceSettlementCreditForm
      v-if="action && record"
      :mode="action"
      :record="record"
      @close="action = null"
      @saved="saved"
    />
  </VDialog>
</template>

<style lang="scss" scoped>
@use './settlement-dialog';

.settlement-detail__title {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px 12px;
}

.settlement-detail__title h2 {
  color: rgb(var(--v-theme-secondary));
}

.settlement-detail__body {
  gap: 18px;
  overscroll-behavior: contain;
  scrollbar-width: thin;
}

.settlement-detail__identity {
  display: flex;
  align-items: center;
  gap: 14px;
  min-inline-size: 0;
}

.settlement-detail__identity > div {
  min-inline-size: 0;
  display: grid;
  gap: 4px;
}

.settlement-detail__identity strong {
  font-size: 1rem;
  color: rgb(var(--v-theme-secondary));
  overflow-wrap: anywhere;
}

.settlement-detail__identity small {
  color: rgba(var(--v-theme-on-surface), 0.65);
  font-size: 0.8125rem;
  overflow-wrap: anywhere;
}

.settlement-detail__cancellation {
  display: grid;
  gap: 10px;
  padding: 12px 16px;
  border-inline-start: 3px solid rgba(var(--v-theme-error), 0.5);
  border-radius: 6px;
  background: rgba(var(--v-theme-error), 0.035);
  font-size: 0.8125rem;
}

.settlement-detail__cancellation-heading,
.settlement-detail__cancellation-heading > span,
.settlement-detail__cancellation-actor {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
}

.settlement-detail__cancellation-heading strong,
.settlement-detail__cancellation-actor strong {
  color: rgb(var(--v-theme-secondary));
}

.settlement-detail__cancellation-heading > span {
  margin-inline-start: auto;
  font-size: 0.75rem;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

.settlement-detail__cancellation-reason {
  display: flex;
  align-items: baseline;
  gap: 8px;
}

.settlement-detail__cancellation-reason > span {
  flex-shrink: 0;
}

.settlement-detail__cancellation-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 6px 16px;
}

.settlement-detail__cancellation-reason > span,
.settlement-detail__cancellation-actor,
.settlement-detail__cancellation-hint {
  color: rgba(var(--v-theme-on-surface), 0.65);
  font-size: 0.75rem;
}

.settlement-detail__cancellation-reason p {
  margin: 0;
  color: rgb(var(--v-theme-on-surface));
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}

.settlement-detail__cancellation-hint {
  margin: 0;
}

.settlement-detail__bank-icon {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  inline-size: 44px;
  block-size: 44px;
  border-radius: 10px;
  background: rgba(var(--v-theme-secondary), 0.055);
  color: rgb(var(--v-theme-secondary));
}

.settlement-detail__position {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  margin: 0;
  padding: 18px 0;
  border-radius: 8px;
  background: rgba(var(--v-theme-secondary), 0.045);
  color: rgb(var(--v-theme-secondary));
}

.settlement-detail__position > div {
  display: grid;
  align-content: start;
  gap: 6px;
  padding-inline: 16px;
  text-align: center;
}

.settlement-detail__position--cancelled {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.settlement-detail__position > div + div {
  border-inline-start: 1px solid rgba(var(--v-theme-secondary), 0.14);
}

.settlement-detail__position dt {
  font-size: 0.8125rem;
  color: rgba(var(--v-theme-secondary), 0.8);
}

.settlement-detail__position dd {
  margin: 0;
  font-size: 1.75rem;
  line-height: 1.25;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  overflow-wrap: anywhere;
}

.settlement-detail__reconciliation {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-wrap: wrap;
  gap: 10px 20px;
  padding-block: 12px;
  border-block-end: 1px solid rgba(var(--v-theme-secondary), 0.1);
  font-size: 0.75rem;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

.settlement-detail__reconciliation > span {
  display: inline-flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
}

.settlement-detail__reconciliation strong {
  font-size: 0.875rem;
  color: rgb(var(--v-theme-secondary));
  font-weight: 600;
}

.settlement-detail__notes {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  font-size: 0.8125rem;
  color: rgb(var(--v-theme-secondary));
}

.settlement-detail__notes > .v-icon,
.settlement-detail__notes strong {
  flex-shrink: 0;
}

.settlement-detail__notes p {
  margin: 0;
  color: rgba(var(--v-theme-on-surface), 0.7);
  overflow-wrap: anywhere;
  white-space: pre-wrap;
}

.settlement-detail__footer {
  flex-shrink: 0;
}

.settlement-detail__audit {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 8px 14px;
  padding: 12px 22px;
  border-block-start: 1px solid rgba(var(--v-theme-secondary), 0.1);
  color: rgba(var(--v-theme-on-surface), 0.65);
  font-size: 0.75rem;
}

.settlement-detail__audit > span {
  display: inline-flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
}

.settlement-detail__audit strong {
  color: rgb(var(--v-theme-secondary));
  font-weight: 500;
}

.settlement-detail__actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
  padding: 12px 22px;
  border-block-start: 1px solid rgba(var(--v-theme-secondary), 0.1);
}

.settlement-detail__loading {
  display: grid;
  gap: 18px;
}

.settlement-detail__skeleton {
  display: block;
  block-size: 12px;
  border-radius: 5px;
  background: rgba(var(--v-theme-secondary), 0.07);
}

.settlement-detail__loading .settlement-detail__bank-icon {
  block-size: 44px;
}

.settlement-detail__loading-row {
  display: flex;
  align-items: center;
  gap: 12px;
  min-block-size: 76px;
  border-block-end: 1px solid rgba(var(--v-theme-secondary), 0.09);
}

.settlement-detail__loading-icon {
  inline-size: 36px;
  block-size: 36px;
  border-radius: 50%;
}

.settlement-detail__loading-lines {
  display: grid;
  gap: 8px;
  flex: 1;
}

.settlement-detail__loading-lines > span:first-child {
  inline-size: 40%;
  min-inline-size: 100px;
}

.settlement-detail__loading-lines > span:last-child {
  inline-size: 65%;
}

.settlement-detail__loading .settlement-detail__position > div {
  justify-items: center;
}

.settlement-detail__loading .settlement-detail__position span {
  inline-size: 60%;
}

.settlement-detail__loading .settlement-detail__loading-amount {
  block-size: 30px;
  inline-size: 80%;
}

.settlement-detail__loading-reconciliation,
.settlement-detail__loading-tabs {
  display: flex;
  justify-content: center;
  gap: 24px;
  padding-block: 12px;
  border-block-end: 1px solid rgba(var(--v-theme-secondary), 0.1);
}

.settlement-detail__loading-reconciliation span {
  inline-size: 30%;
}

.settlement-detail__loading-tabs {
  justify-content: flex-start;
}

.settlement-detail__loading-tabs span,
.settlement-detail__loading-value {
  inline-size: 120px;
}

@media (max-width: 600px) {
  .settlement-detail__cancellation-heading > span {
    inline-size: 100%;
    margin-inline-start: 26px;
  }

  .settlement-detail__title {
    gap: 6px;
  }

  .settlement-detail__title :deep(.v-chip) {
    font-size: 0.6875rem;
  }

  .settlement-detail__body {
    gap: 14px;
  }

  .settlement-detail__position {
    padding-block: 14px;
  }

  .settlement-detail__position > div {
    padding-inline: 6px;
    gap: 8px;
  }

  .settlement-detail__position dt {
    font-size: 0.6875rem;
    min-block-size: 32px;
  }

  .settlement-detail__position dd {
    font-size: 1.125rem;
  }

  .settlement-detail__reconciliation {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 8px;
  }

  .settlement-detail__reconciliation > .v-icon {
    display: none;
  }

  .settlement-detail__reconciliation strong {
    margin-inline-start: auto;
  }

  .settlement-detail__notes {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 5px 8px;
  }

  .settlement-detail__notes p {
    grid-column: 2;
  }

  .settlement-detail__audit,
  .settlement-detail__actions {
    padding-inline: 16px;
  }

  .settlement-detail__actions > .v-btn {
    font-size: 0.75rem;
    padding-inline: 10px;
  }
}
</style>
