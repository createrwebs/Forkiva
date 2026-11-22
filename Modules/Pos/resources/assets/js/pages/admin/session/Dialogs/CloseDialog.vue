<script lang="ts" setup>
import { computed, ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import { useClosingReview } from '@/modules/pos/composables/useClosingReview'
import PaymentBreakdown from '../Partials/Show/PaymentBreakdown.vue'

const props = defineProps<{ modelValue: boolean; item: { id: number } }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; saved: [] }>()
const { t } = useI18n()
const titleId = useId()
const countTitleId = useId()
const countHintId = useId()
const countErrorId = useId()
const {
  preview,
  declaredCash,
  closingReason,
  varianceReason,
  reasonErrors,
  step,
  acknowledged,
  loading,
  saving,
  error,
  countError,
  unavailable,
  canReview,
  canConfirm,
  reviewCount,
  confirmClose,
  editCount,
  refresh,
} = useClosingReview(
  () => props.item.id,
  () => props.modelValue,
  () => {
    emit('saved')
    emit('update:modelValue', false)
  },
)
type ClosingAction = 'refresh' | 'retry' | 'submit'

const activeAction = ref<ClosingAction | null>(null)
const retryMessage = ref('')
const displayedError = computed(() => error.value || retryMessage.value)
const busy = computed(() => loading.value || saving.value || activeAction.value !== null)
const balanced = computed(() => preview.value?.review.cash_difference?.amount === 0)
const varianceLabel = computed(() =>
  t(
    balanced.value
      ? 'pos::pos_sessions.show.balanced'
      : (preview.value?.review.cash_difference?.amount ?? 0) < 0
        ? 'pos::pos_sessions.show.shortage'
        : 'pos::pos_sessions.show.surplus',
  ),
)
const countPlaceholder = computed(() => {
  const precision = preview.value?.opening_float?.precision ?? 2
  return precision === 0 ? '0' : `0.${'0'.repeat(precision)}`
})
const cashRows = computed(() => [
  { key: 'opening_float', value: preview.value?.opening_float },
  { key: 'cash_in', value: preview.value?.cash_reconciliation?.cash_in },
  { key: 'cash_out', value: preview.value?.cash_reconciliation?.cash_out },
])
function dismiss() {
  if (!busy.value) emit('update:modelValue', false)
}

async function runAction(action: ClosingAction, handler: () => unknown) {
  if (busy.value) return

  activeAction.value = action
  retryMessage.value = action === 'retry' ? error.value : ''
  try {
    await handler()
  } finally {
    activeAction.value = null
    retryMessage.value = ''
  }
}

function submit() {
  return runAction('submit', step.value === 'confirm' ? confirmClose : reviewCount)
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    :aria-labelledby="titleId"
    max-width="960"
    scrollable
    persistent
    @update:model-value="
      (value) => {
        if (!value) dismiss()
      }
    "
  >
    <VCard class="closing-card">
      <VForm
        class="closing-form"
        @submit.prevent="submit"
      >
        <header class="closing-header">
          <div class="closing-heading">
            <h2 :id="titleId">{{ t('pos::pos_sessions.close_session') }}</h2>
            <p
              v-if="preview"
              class="session-context"
              :title="`${preview.pos_register.name} · ${preview.branch.name}`"
            >
              {{ preview.pos_register.name }} · {{ preview.branch.name }}
            </p>
          </div>
          <ol
            class="closing-steps"
            aria-live="polite"
          >
            <li
              class="closing-step closing-step-active"
              :aria-current="step === 'count' ? 'step' : undefined"
            >
              <span class="step-number">
                <VIcon
                  v-if="step === 'confirm'"
                  icon="tabler-check"
                  size="14"
                />
                <template v-else>1</template>
              </span>
              <span>{{ t('pos::pos_sessions.closing.review_step') }}</span>
            </li>
            <li
              class="step-connector"
              :class="{ 'step-connector-complete': step === 'confirm' }"
              aria-hidden="true"
            />
            <li
              class="closing-step"
              :class="{ 'closing-step-active': step === 'confirm' }"
              :aria-current="step === 'confirm' ? 'step' : undefined"
            >
              <span class="step-number">2</span>
              <span>{{ t('pos::pos_sessions.closing.confirm_step') }}</span>
            </li>
          </ol>
          <VBtn
            class="closing-dismiss"
            :aria-label="t('admin::admin.buttons.cancel')"
            :disabled="busy"
            icon="tabler-x"
            variant="text"
            color="default"
            size="small"
            @click="dismiss"
          />
        </header>
        <div class="closing-body">
          <VAlert
            v-if="displayedError"
            type="warning"
            variant="tonal"
            role="alert"
            class="closing-error"
          >
            {{ displayedError }}
            <VBtn
              v-if="!unavailable"
              :disabled="busy"
              :loading="activeAction === 'retry'"
              variant="text"
              size="small"
              @click="runAction('retry', refresh)"
            >
              {{ t('pos::pos_sessions.closing.retry') }}
            </VBtn>
          </VAlert>
          <div
            v-if="loading && !preview && activeAction !== 'retry'"
            class="closing-loading"
          >
            <VProgressCircular
              indeterminate
              color="primary"
            />
          </div>
          <div
            v-if="preview"
            class="closing-workspace"
          >
            <aside
              class="drawer-summary"
              :aria-label="t('pos::pos_sessions.show.cash_reconciliation_title')"
            >
              <div class="expected-label">{{ t('pos::pos_sessions.closing.expected_drawer') }}</div>
              <div class="expected-amount">
                {{ preview.cash_reconciliation?.expected_cash.formatted }}
              </div>
              <p class="cash-formula">{{ t('pos::pos_sessions.show.cash_formula') }}</p>
              <div class="drawer-custody">
                <span>{{ t('pos::pos_sessions.closing.responsible_cashier') }}</span>
                <strong>
                  <bdi>{{ (preview.responsible_cashier ?? preview.opened_by).name }}</bdi>
                </strong>
              </div>
              <dl class="drawer-movements">
                <div
                  v-for="row in cashRows"
                  :key="row.key"
                >
                  <dt>{{ t(`pos::pos_sessions.show.${row.key}`) }}</dt>
                  <dd>
                    <span
                      v-if="row.key !== 'opening_float'"
                      class="movement-sign"
                      aria-hidden="true"
                    >
                      {{ row.key === 'cash_in' ? '+' : '−' }}
                    </span>
                    {{ row.value?.formatted }}
                  </dd>
                </div>
              </dl>
              <dl class="session-totals">
                <div>
                  <dt>{{ t('pos::pos_sessions.closing.collections') }}</dt>
                  <dd>{{ (preview.total_collections ?? preview.total_sales)?.formatted }}</dd>
                </div>
                <div>
                  <dt>{{ t('pos::pos_sessions.closing.refunds') }}</dt>
                  <dd>{{ preview.total_refunds?.formatted }}</dd>
                </div>
                <div>
                  <dt>{{ t('pos::pos_sessions.closing.orders') }}</dt>
                  <dd>{{ preview.orders_count }}</dd>
                </div>
              </dl>
            </aside>
            <main class="closing-main">
              <p
                v-if="preview.review.actor?.name"
                class="closing-actor"
              >
                <VIcon
                  icon="tabler-user-check"
                  size="18"
                />
                {{
                  t('pos::pos_sessions.closing.counting_as', { name: preview.review.actor.name })
                }}
              </p>
              <VAlert
                v-if="preview.review.requires_closing_reason"
                type="info"
                variant="tonal"
                class="closing-delegated"
              >
                {{
                  t('pos::pos_sessions.closing.closing_for_other', {
                    name: (preview.responsible_cashier ?? preview.opened_by).name,
                  })
                }}
              </VAlert>
              <section
                class="cash-count-panel"
                :aria-labelledby="countTitleId"
              >
                <h3 :id="countTitleId">
                  {{
                    t(
                      step === 'count'
                        ? 'pos::pos_sessions.closing.count_title'
                        : 'pos::pos_sessions.closing.confirm_title',
                    )
                  }}
                </h3>
                <p
                  :id="countHintId"
                  class="count-hint"
                >
                  {{
                    t(
                      step === 'count'
                        ? 'pos::pos_sessions.closing.count_subtitle'
                        : 'pos::pos_sessions.closing.confirm_subtitle',
                    )
                  }}
                </p>
                <template v-if="step === 'count'">
                  <div
                    class="cash-field"
                    :class="{
                      'cash-field-error': countError,
                      'cash-field-disabled': busy || unavailable,
                    }"
                  >
                    <input
                      v-model="declaredCash"
                      :aria-labelledby="countTitleId"
                      :aria-describedby="
                        countError ? `${countHintId} ${countErrorId}` : countHintId
                      "
                      :aria-invalid="!!countError"
                      :placeholder="countPlaceholder"
                      :disabled="busy || unavailable"
                      type="text"
                      inputmode="decimal"
                      autocomplete="off"
                    />
                    <span class="cash-currency">{{ preview.opening_float?.currency }}</span>
                  </div>
                  <p
                    v-if="countError"
                    :id="countErrorId"
                    class="count-error"
                    role="alert"
                  >
                    {{ countError }}
                  </p>
                  <div class="count-policy">
                    <span
                      v-if="!preview.review.cash_difference"
                      class="pending-difference"
                    >
                      <VIcon
                        icon="tabler-scale"
                        size="18"
                      />
                      {{ t('pos::pos_sessions.closing.pending_difference') }}
                    </span>
                    <span
                      v-else
                      class="count-difference text-warning"
                    >
                      <VIcon
                        icon="tabler-alert-circle"
                        size="18"
                      />
                      {{ t('pos::pos_sessions.closing.difference') }}
                      <strong>{{ preview.review.cash_difference.formatted }}</strong>
                    </span>
                    <span class="allowed-difference">
                      {{ t('pos::pos_sessions.closing.allowed_difference') }}
                      <strong>{{ preview.review.allowed_difference.formatted }}</strong>
                    </span>
                  </div>
                  <VAlert
                    v-if="preview.review.cash_difference && !preview.review.can_close"
                    type="warning"
                    variant="tonal"
                    class="threshold-warning"
                    role="alert"
                  >
                    {{ t('pos::pos_sessions.closing.threshold_exceeded') }}
                  </VAlert>
                </template>
                <template v-else>
                  <div
                    class="confirmed-count"
                    :class="balanced ? 'count-balanced' : 'count-variance'"
                  >
                    <div>
                      <span class="confirmed-label">
                        {{ t('pos::pos_sessions.closing.counted_cash') }}
                      </span>
                      <strong class="confirmed-amount">
                        {{ preview.declared_cash?.formatted }}
                      </strong>
                    </div>
                    <div
                      class="confirmed-difference"
                      aria-live="polite"
                    >
                      <strong>
                        {{ preview.review.cash_difference?.formatted }}
                        <span>{{ t('pos::pos_sessions.closing.difference') }}</span>
                      </strong>
                      <span class="variance-status">
                        <VIcon
                          :icon="balanced ? 'tabler-circle-check-filled' : 'tabler-alert-circle'"
                          size="18"
                        />
                        {{ varianceLabel }}
                      </span>
                    </div>
                  </div>
                  <p class="confirmation-policy">
                    {{ t('pos::pos_sessions.closing.allowed_difference') }}
                    <strong>{{ preview.review.allowed_difference.formatted }}</strong>
                  </p>
                </template>
              </section>
              <div
                v-if="
                  preview.review.requires_closing_reason || preview.review.requires_variance_reason
                "
                class="closing-reasons"
              >
                <div v-if="preview.review.requires_closing_reason">
                  <label :for="`${titleId}-closing-reason`">
                    {{ t('pos::pos_sessions.closing.closing_reason') }}
                  </label>
                  <textarea
                    :id="`${titleId}-closing-reason`"
                    v-model="closingReason"
                    :disabled="busy"
                    rows="2"
                    minlength="3"
                    maxlength="1000"
                    required
                    :aria-invalid="!!reasonErrors.closing_reason"
                    :aria-describedby="
                      reasonErrors.closing_reason
                        ? `${titleId}-closing-error`
                        : `${titleId}-reason-hint`
                    "
                  />
                  <p
                    v-if="reasonErrors.closing_reason"
                    :id="`${titleId}-closing-error`"
                    class="count-error"
                    role="alert"
                  >
                    {{ reasonErrors.closing_reason }}
                  </p>
                </div>
                <div v-if="preview.review.requires_variance_reason">
                  <label :for="`${titleId}-variance-reason`">
                    {{ t('pos::pos_sessions.closing.variance_reason') }}
                  </label>
                  <textarea
                    :id="`${titleId}-variance-reason`"
                    v-model="varianceReason"
                    :disabled="busy"
                    rows="2"
                    minlength="3"
                    maxlength="1000"
                    required
                    :aria-invalid="!!reasonErrors.variance_reason"
                    :aria-describedby="
                      reasonErrors.variance_reason
                        ? `${titleId}-variance-error`
                        : `${titleId}-reason-hint`
                    "
                  />
                  <p
                    v-if="reasonErrors.variance_reason"
                    :id="`${titleId}-variance-error`"
                    class="count-error"
                    role="alert"
                  >
                    {{ reasonErrors.variance_reason }}
                  </p>
                </div>
                <p
                  :id="`${titleId}-reason-hint`"
                  class="closing-reason-hint"
                >
                  {{ t('pos::pos_sessions.closing.reason_hint') }}
                </p>
              </div>
              <PaymentBreakdown
                :item="preview"
                compact
              />
              <div
                v-if="step === 'confirm'"
                class="closing-acknowledgement"
              >
                <VCheckbox
                  v-model="acknowledged"
                  :disabled="busy"
                  :label="t('pos::pos_sessions.closing.acknowledgement')"
                  density="compact"
                  color="primary"
                  hide-details
                />
              </div>
            </main>
          </div>
        </div>
        <footer class="closing-footer">
          <div
            v-if="preview"
            class="closing-freshness"
          >
            <span>
              {{
                t('pos::pos_sessions.closing.last_refreshed', { time: preview.review.generated_at })
              }}
            </span>
            <VBtn
              :disabled="busy || unavailable"
              :loading="activeAction === 'refresh'"
              variant="text"
              color="default"
              prepend-icon="tabler-refresh"
              size="small"
              :aria-label="t('pos::pos_sessions.closing.refresh_data')"
              @click="runAction('refresh', refresh)"
            >
              {{ t('pos::pos_sessions.closing.refresh') }}
            </VBtn>
          </div>
          <div class="closing-actions">
            <VBtn
              :disabled="busy"
              variant="text"
              color="default"
              @click="step === 'confirm' ? editCount() : dismiss()"
            >
              {{
                t(
                  step === 'confirm'
                    ? 'pos::pos_sessions.closing.edit_count'
                    : 'admin::admin.buttons.cancel',
                )
              }}
            </VBtn>
            <VBtn
              :disabled="busy || (step === 'confirm' ? !canConfirm : !canReview)"
              :loading="activeAction === 'submit'"
              color="primary"
              variant="flat"
              type="submit"
              :append-icon="step === 'confirm' ? 'tabler-lock' : 'tabler-arrow-right'"
            >
              {{
                t(
                  step === 'confirm'
                    ? 'pos::pos_sessions.closing.confirm_close'
                    : 'pos::pos_sessions.closing.review_cash_count',
                )
              }}
            </VBtn>
          </div>
        </footer>
      </VForm>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.v-card.closing-card {
  --closing-line: rgba(var(--v-theme-on-surface), 0.12);
  --closing-muted: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  --closing-sidebar: rgba(var(--v-theme-on-surface), 0.018);
  overflow: clip;
  border-radius: 20px;
  max-block-size: calc(100dvh - 48px);
}
.closing-form {
  display: flex;
  flex-direction: column;
  flex: 1 1 auto;
  min-block-size: 0;
  overflow: hidden;
}

.drawer-custody {
  display: grid;
  gap: 4px;
  margin-block: 16px;
  padding: 12px;
  border: 1px solid var(--closing-line);
  border-radius: 10px;
  overflow-wrap: anywhere;
}

.drawer-custody span,
.closing-reason-hint {
  color: var(--closing-muted);
  font-size: 0.75rem;
}

.drawer-custody strong {
  font-size: 0.875rem;
}

.closing-actor {
  display: flex;
  align-items: flex-start;
  gap: 6px;
  margin: 0 0 12px;
  color: var(--closing-muted);
  font-size: 0.75rem;
  overflow-wrap: anywhere;
}

.closing-delegated {
  margin-block-end: 16px;
  font-size: 0.8125rem;
}

.closing-reasons {
  display: grid;
  gap: 12px;
  margin-block: 16px;
}

.closing-reasons label {
  display: block;
  margin-block-end: 6px;
  font-size: 0.8125rem;
  font-weight: 600;
}

.closing-reasons textarea {
  display: block;
  inline-size: 100%;
  padding: 10px 12px;
  border: 1px solid var(--closing-line);
  border-radius: 8px;
  background: rgb(var(--v-theme-surface));
  color: rgb(var(--v-theme-on-surface));
  font: inherit;
  font-size: 0.8125rem;
  resize: vertical;
}

.closing-reasons textarea:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}

.closing-reasons textarea:disabled {
  opacity: 0.6;
}

.closing-reason-hint {
  margin: 0;
}
.closing-header {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto auto;
  align-items: center;
  gap: 16px;
  flex-shrink: 0;
  padding: 16px 24px;
  border-block-end: 1px solid var(--closing-line);
}
.closing-heading {
  min-inline-size: 0;
}
.closing-header h2 {
  font-size: 1.25rem;
  font-weight: 650;
  line-height: 1.25;
  letter-spacing: -0.025em;
}
.session-context {
  color: var(--closing-muted);
  font-size: 0.75rem;
  line-height: 1.5;
  margin: 3px 0 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.closing-steps {
  display: flex;
  align-items: center;
  gap: 10px;
  list-style: none;
  padding: 0;
  margin: 0;
}
.closing-step {
  display: flex;
  align-items: center;
  gap: 6px;
  color: var(--closing-muted);
  font-size: 0.75rem;
  white-space: nowrap;
}
.closing-step-active {
  color: rgb(var(--v-theme-on-surface));
  font-weight: 600;
}
.step-number {
  display: grid;
  place-items: center;
  inline-size: 22px;
  block-size: 22px;
  flex-shrink: 0;
  border-radius: 50%;
  background: rgba(var(--v-theme-on-surface), 0.08);
  color: var(--closing-muted);
  font-weight: 600;
}
.closing-step-active .step-number {
  background: rgb(var(--v-theme-primary));
  color: rgb(var(--v-theme-on-primary));
}
.step-connector {
  block-size: 1px;
  inline-size: 24px;
  background: linear-gradient(to right, rgb(var(--v-theme-primary)) 50%, var(--closing-line) 50%);
}
.step-connector-complete {
  background: rgb(var(--v-theme-primary));
}
.closing-card:dir(rtl) .step-connector {
  transform: rotate(180deg);
}
.closing-body {
  min-block-size: 0;
  overflow-y: auto;
  flex: 1 1 auto;
  overscroll-behavior: contain;
}
.closing-error {
  margin: 16px 24px;
  font-size: 0.875rem;
}
.closing-loading {
  display: grid;
  place-items: center;
  padding: 80px;
}
.closing-workspace {
  display: grid;
  grid-template-columns: minmax(0, 0.34fr) minmax(0, 0.66fr);
}
.drawer-summary {
  padding: 26px 28px;
  border-inline-end: 1px solid var(--closing-line);
  background: var(--closing-sidebar);
}
.expected-label {
  color: var(--closing-muted);
  font-size: 0.875rem;
}
.expected-amount {
  font-size: clamp(1.75rem, 3vw, 2.5rem);
  font-weight: 700;
  line-height: 1.25;
  letter-spacing: -0.035em;
  margin-block-start: 8px;
  font-variant-numeric: tabular-nums;
  overflow-wrap: anywhere;
}
.cash-formula {
  color: var(--closing-muted);
  font-size: 0.75rem;
  line-height: 1.55;
  margin: 8px 0 24px;
}
.drawer-movements,
.session-totals {
  margin: 0;
  display: grid;
  gap: 14px;
  font-size: 0.875rem;
}
.drawer-movements > div,
.session-totals > div {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 16px;
}
.drawer-summary dt {
  color: var(--closing-muted);
}
.drawer-summary dd {
  margin: 0;
  text-align: end;
  font-weight: 500;
  font-variant-numeric: tabular-nums;
  overflow-wrap: anywhere;
}
.movement-sign {
  margin-inline-end: 1px;
}
.session-totals {
  border-block-start: 1px solid var(--closing-line);
  padding-block-start: 24px;
  margin-block-start: 28px;
}
.closing-main {
  padding: 22px 28px 18px;
  min-inline-size: 0;
}
.cash-count-panel {
  margin-block-end: 24px;
}
.cash-count-panel h3 {
  font-size: 1.125rem;
  line-height: 1.4;
  font-weight: 650;
  letter-spacing: -0.02em;
}
.count-hint {
  margin: 4px 0 14px;
  font-size: 0.8125rem;
  color: var(--closing-muted);
  line-height: 1.5;
}
.cash-field {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 14px 10px 18px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.22);
  border-radius: 9px;
  transition:
    border-color 150ms,
    box-shadow 150ms;
}
.cash-field:focus-within {
  border-color: rgb(var(--v-theme-primary));
  box-shadow: 0 0 0 3px rgba(var(--v-theme-primary), 0.1);
}
.cash-field input {
  inline-size: 100%;
  min-inline-size: 0;
  border: 0;
  outline: 0;
  background: none;
  color: rgb(var(--v-theme-on-surface));
  font: inherit;
  font-size: 1.875rem;
  line-height: 1.4;
  font-variant-numeric: tabular-nums;
}
.cash-field input::placeholder {
  color: var(--closing-muted);
  opacity: 0.8;
}
.cash-field-disabled {
  opacity: 0.6;
}
.cash-field-error {
  border-color: rgb(var(--v-theme-error));
}
.cash-currency {
  padding: 5px 9px;
  border-radius: 6px;
  background: rgba(var(--v-theme-on-surface), 0.05);
  font-size: 0.75rem;
  font-weight: 600;
  flex-shrink: 0;
}
.count-error {
  color: rgb(var(--v-theme-error));
  font-size: 0.8125rem;
  margin: 6px 0 0;
}
.count-policy {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-block-start: 10px;
  color: var(--closing-muted);
  font-size: 0.75rem;
  line-height: 1.5;
  flex-wrap: wrap;
}
.pending-difference,
.count-difference {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.allowed-difference strong,
.confirmation-policy strong {
  color: rgb(var(--v-theme-on-surface));
  font-weight: 500;
  margin-inline-start: 4px;
}
.threshold-warning {
  margin-block-start: 12px;
  font-size: 0.8125rem;
}
.confirmed-count {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  border: 1px solid;
  border-radius: 8px;
  padding: 14px 18px;
}
.count-balanced {
  background: rgba(var(--v-theme-success), 0.07);
  border-color: rgba(var(--v-theme-success), 0.25);
}
.count-variance {
  background: rgba(var(--v-theme-warning), 0.07);
  border-color: rgba(var(--v-theme-warning), 0.3);
}
.confirmed-count > div {
  min-inline-size: 0;
  overflow-wrap: anywhere;
}
.confirmed-label {
  display: block;
  font-size: 0.8125rem;
  color: var(--closing-muted);
}
.confirmed-amount {
  display: block;
  font-size: 1.875rem;
  font-weight: 700;
  line-height: 1.3;
  letter-spacing: -0.025em;
  font-variant-numeric: tabular-nums;
  overflow-wrap: anywhere;
}
.confirmed-difference {
  display: grid;
  gap: 5px;
  font-size: 0.875rem;
  color: rgb(var(--v-theme-warning));
  text-align: end;
}
.count-balanced .confirmed-difference {
  color: rgb(var(--v-theme-success));
}
.confirmed-difference strong {
  font-weight: 600;
}
.confirmed-difference strong span {
  font-weight: 400;
}
.variance-status {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 6px;
  font-size: 0.8125rem;
}
.confirmation-policy {
  margin: 6px 0 0;
  font-size: 0.75rem;
  color: var(--closing-muted);
}
.closing-acknowledgement {
  margin-block-start: 14px;
}
.closing-acknowledgement :deep(.v-label) {
  opacity: 1;
  font-size: 0.8125rem;
  line-height: 1.5;
}
.closing-acknowledgement :deep(.v-selection-control) {
  align-items: flex-start;
}
.closing-acknowledgement :deep(.v-selection-control__input) {
  margin-inline-start: -8px;
}
.closing-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex: 0 0 auto;
  padding: 16px 24px;
  border-block-start: 1px solid var(--closing-line);
}
.closing-freshness {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--closing-muted);
  font-size: 0.75rem;
}
.closing-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-inline-start: auto;
}
.closing-footer :deep(.v-btn) {
  text-transform: none;
  letter-spacing: normal;
  font-weight: 500;
}
.closing-actions :deep(.v-btn[type='submit']) {
  min-block-size: 40px;
  padding-inline: 18px;
  font-size: 0.875rem;
}
.closing-card:dir(rtl) :deep(.tabler-arrow-right) {
  transform: rotate(180deg);
}
@media (max-width: 700px) {
  .closing-header {
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 10px 12px;
    padding: 14px 16px;
  }
  .closing-header h2 {
    font-size: 1.125rem;
  }
  .closing-steps {
    grid-column: 1 / -1;
    grid-row: 2;
    gap: 8px;
  }
  .closing-dismiss {
    grid-column: 2;
    grid-row: 1;
  }
  .step-connector {
    inline-size: 16px;
  }
  .closing-workspace {
    display: flex;
    flex-direction: column;
  }
  .drawer-summary {
    padding: 20px;
    border-inline-end: 0;
    border-block-end: 1px solid var(--closing-line);
  }
  .expected-amount {
    font-size: 1.75rem;
  }
  .cash-formula {
    margin-block-end: 16px;
  }
  .drawer-movements,
  .session-totals {
    gap: 10px;
  }
  .session-totals {
    margin-block-start: 16px;
    padding-block-start: 16px;
  }
  .closing-main {
    padding: 20px;
  }
  .confirmed-count {
    padding: 12px;
    flex-wrap: wrap;
  }
  .confirmed-amount {
    font-size: 1.5rem;
  }
  .closing-footer {
    padding: 12px 16px;
    flex-wrap: wrap;
    gap: 8px;
  }
  .closing-freshness {
    justify-content: space-between;
    inline-size: 100%;
  }
  .closing-actions {
    inline-size: 100%;
  }
  .closing-actions :deep(.v-btn[type='submit']) {
    flex: 1;
    min-inline-size: 0;
    padding-inline: 12px;
    font-size: 0.8125rem;
  }
}
</style>
