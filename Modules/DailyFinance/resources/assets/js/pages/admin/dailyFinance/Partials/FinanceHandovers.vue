<script lang="ts" setup>
import { computed, nextTick, ref, useId, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import TableStatus from '@/modules/core/components/Table/Partials/TableStatus.vue'
import { useCashHandovers } from '@/modules/dailyfinance/composables/useCashHandovers'
import type { CashHandover } from '@/modules/dailyfinance/contracts/cashHandover'

const props = defineProps<{ sessionId: number; sessionLabel?: string }>()
const emit = defineEmits<{ busy: [value: boolean]; editing: [value: boolean]; saved: [] }>()
const { t } = useI18n()
const formId = useId()
const { summary, loading, saving, error, errors, uncertain, load, create, receive, cancel } =
  useCashHandovers(() => props.sessionId)
const mode = ref<'create' | 'receive' | 'cancel' | null>(null)
const selected = ref<CashHandover | null>(null)
const amount = ref('')
const receiverId = ref<number | null>(null)
const notes = ref('')
const reason = ref('')
const reviewing = ref(false)
const acknowledged = ref(false)
const formHeading = ref<HTMLElement | null>(null)
let dialogActivator: HTMLElement | null = null
const busy = computed(() => saving.value || uncertain.value)
const summaryRows = computed(() => [
  { key: 'counted', value: summary.value?.counted },
  { key: 'handed_over', value: summary.value?.handed_over },
])
const statusRows = computed(() => [
  { key: 'pending', value: summary.value?.pending, icon: 'tabler-clock', color: 'primary' },
  {
    key: 'received',
    value: summary.value?.received,
    icon: 'tabler-circle-check',
    color: 'success',
  },
  { key: 'variance', value: summary.value?.variance, icon: 'tabler-scale', color: 'secondary' },
])
const receiver = computed(() =>
  summary.value?.receivers.find((user) => user.id === receiverId.value),
)
const title = computed(() =>
  t(
    `dailyfinance::cash_handovers.${mode.value === 'create' ? (reviewing.value ? 'review' : 'create') : mode.value === 'receive' ? 'receive' : 'cancel'}`,
  ),
)

function units(value: string): bigint | null {
  if (!/^\d{1,14}(?:\.\d{1,4})?$/.test(value)) return null

  const [whole = '0', fraction = ''] = value.split('.')
  const precision = summary.value?.precision ?? 2
  if (fraction.slice(precision).replace(/0/g, '')) return null

  return BigInt(whole + fraction.slice(0, precision).padEnd(precision, '0'))
}

const enteredAmount = computed(() => units(amount.value))
const amountLimit = computed(() => {
  const available = units(summary.value?.available_decimal ?? '')
  const declared = units(selected.value?.amount_decimal ?? '')

  return mode.value === 'create'
    ? available
    : declared === null
      ? null
      : declared + (available ?? 0n)
})
const amountError = computed(() => {
  if (!amount.value || mode.value === 'cancel') return ''
  if (enteredAmount.value === null)
    return t('dailyfinance::cash_handovers.invalid_amount', {
      precision: summary.value?.precision ?? 2,
    })
  if (
    amountLimit.value === null ||
    enteredAmount.value > amountLimit.value ||
    enteredAmount.value < (mode.value === 'create' ? 1n : 0n)
  )
    return t(
      `dailyfinance::cash_handovers.errors.${mode.value === 'create' ? 'available' : 'received_amount'}`,
    )

  return ''
})
const hasDifference = computed(
  () =>
    mode.value === 'receive' &&
    enteredAmount.value !== null &&
    enteredAmount.value !== units(selected.value?.amount_decimal ?? ''),
)
const canSubmit = computed(() => {
  if (busy.value || loading.value) return false
  if (mode.value === 'cancel') return reason.value.trim().length >= 3
  if (!amount.value || amountError.value) return false
  if (mode.value === 'create') return !!receiver.value && (!reviewing.value || acknowledged.value)

  return acknowledged.value && (!hasDifference.value || reason.value.trim().length >= 3)
})

watch(busy, (value) => emit('busy', value), { flush: 'sync', immediate: true })
watch(
  () => !!mode.value,
  (value) => emit('editing', value),
  { flush: 'sync', immediate: true },
)
watch(
  [amount, receiverId, notes, reason],
  () => {
    acknowledged.value = false
  },
  { flush: 'sync' },
)

watch(amount, () => {
  delete errors.value.amount
  delete errors.value.received_amount
})
watch(receiverId, () => {
  delete errors.value.receiver_id
})
watch(notes, () => {
  delete errors.value.notes
})
watch(reason, () => {
  delete errors.value.variance_reason
  delete errors.value.cancellation_reason
})

function start(action: 'create' | 'receive' | 'cancel', record: CashHandover | null = null) {
  if (busy.value || loading.value) return

  dialogActivator = document.activeElement instanceof HTMLElement ? document.activeElement : null
  mode.value = action
  selected.value = record
  amount.value = action === 'receive' ? (record?.amount_decimal ?? '') : ''
  receiverId.value = null
  notes.value = ''
  reason.value = ''
  reviewing.value = false
  acknowledged.value = false
  errors.value = {}
  error.value = ''
}

function focusForm() {
  formHeading.value?.focus({ preventScroll: true })
}

function restoreActivator() {
  if (dialogActivator?.isConnected) dialogActivator.focus({ preventScroll: true })
  dialogActivator = null
}

function editReview() {
  reviewing.value = false
  acknowledged.value = false
  void nextTick(() => formHeading.value?.focus())
}

function dismissForm() {
  if (busy.value) return

  mode.value = null
  errors.value = {}
  error.value = ''
}

async function submit() {
  if (!canSubmit.value && !uncertain.value) return
  if (mode.value === 'create' && !reviewing.value) {
    reviewing.value = true
    void nextTick(() => formHeading.value?.focus())
    return
  }
  const success =
    mode.value === 'create'
      ? await create({
          amount: amount.value,
          receiver_id: receiverId.value!,
          notes: notes.value.trim() || null,
        })
      : mode.value === 'receive'
        ? await receive(selected.value!.id, amount.value, reason.value.trim() || null)
        : await cancel(selected.value!.id, reason.value.trim())
  if (success) {
    mode.value = null
    emit('saved')
  } else if (mode.value === 'create' && !uncertain.value && Object.keys(errors.value).length) {
    editReview()
  }
}
</script>

<template>
  <section class="finance-handovers">
    <VAlert
      v-if="error && !mode"
      type="warning"
      variant="tonal"
      role="alert"
    >
      {{ error }}
      <VBtn
        v-if="!mode"
        variant="text"
        :disabled="saving || loading"
        @click="load()"
      >
        {{ t('dailyfinance::cash_handovers.retry') }}
      </VBtn>
    </VAlert>
    <div
      v-if="loading || summary"
      class="finance-handovers__overview"
      :aria-busy="loading"
    >
      <section class="finance-handovers__cash">
        <div class="finance-handovers__cash-heading">
          <div>
            <span class="finance-handovers__cash-label">
              {{ t('dailyfinance::cash_handovers.available') }}
            </span>
            <span
              v-if="loading"
              class="finance-handovers__bone finance-handovers__bone--hero"
            />
            <strong
              v-else
              class="finance-handovers__cash-value"
            >
              <bdi>
                {{ summary?.available?.formatted ?? t('pos::pos_sessions.show.not_available') }}
              </bdi>
            </strong>
          </div>
          <VBtn
            v-if="loading || summary?.can_create"
            color="primary"
            variant="flat"
            prepend-icon="tabler-cash-banknote"
            :disabled="loading || busy || !!mode || !summary?.receivers.length"
            @click="start('create')"
          >
            {{ t('dailyfinance::cash_handovers.create') }}
          </VBtn>
        </div>
        <dl class="finance-handovers__balances">
          <div
            v-for="row in summaryRows"
            :key="row.key"
          >
            <dt>{{ t(`dailyfinance::cash_handovers.${row.key}`) }}</dt>
            <dd>
              <span
                v-if="loading"
                class="finance-handovers__bone finance-handovers__bone--value"
              />
              <bdi v-else>
                {{ row.value?.formatted ?? t('pos::pos_sessions.show.not_available') }}
              </bdi>
            </dd>
          </div>
        </dl>
      </section>
      <dl class="finance-handovers__status">
        <div
          v-for="row in statusRows"
          :key="row.key"
        >
          <VAvatar
            :color="row.color"
            variant="tonal"
            size="34"
          >
            <VIcon
              :icon="row.icon"
              size="19"
              aria-hidden="true"
            />
          </VAvatar>
          <div>
            <dt>{{ t(`dailyfinance::cash_handovers.${row.key}`) }}</dt>
            <dd>
              <span
                v-if="loading"
                class="finance-handovers__bone finance-handovers__bone--value"
              />
              <bdi v-else>{{ row.value?.formatted }}</bdi>
            </dd>
          </div>
        </div>
      </dl>
    </div>
    <div
      v-if="loading"
      class="finance-handovers__history-loading"
      role="status"
      :aria-label="t('dailyfinance::cash_handovers.loading')"
    >
      <span
        class="finance-handovers__bone finance-handovers__bone--heading"
        aria-hidden="true"
      />
      <div
        v-for="row in 2"
        :key="row"
        class="finance-handovers__record"
        aria-hidden="true"
      >
        <div class="finance-handovers__record-header">
          <span class="finance-handovers__bone finance-handovers__bone--value" />
          <span class="finance-handovers__bone finance-handovers__bone--value" />
        </div>
        <div class="finance-handovers__people">
          <span class="finance-handovers__bone finance-handovers__bone--value" />
          <span class="finance-handovers__bone finance-handovers__bone--value" />
        </div>
        <span class="finance-handovers__bone finance-handovers__bone--heading" />
        <div class="finance-handovers__record-footer">
          <span class="finance-handovers__bone finance-handovers__bone--heading" />
          <span class="finance-handovers__bone finance-handovers__bone--value" />
        </div>
      </div>
    </div>
    <template v-if="summary">
      <VAlert
        v-if="summary.can_create && !summary.receivers.length && !mode"
        type="info"
        variant="tonal"
        density="compact"
      >
        {{ t('dailyfinance::cash_handovers.no_receiver') }}
      </VAlert>
      <VDialog
        :model-value="!!mode"
        :aria-labelledby="`${formId}-title`"
        :persistent="busy"
        max-width="580"
        scrollable
        @update:model-value="
          (open) => {
            if (!open) dismissForm()
          }
        "
        @after-enter="focusForm"
        @after-leave="restoreActivator"
      >
        <VCard
          v-if="mode"
          class="finance-handover-dialog"
        >
          <header class="finance-handover-dialog__header">
            <div>
              <h2
                :id="`${formId}-title`"
                ref="formHeading"
                tabindex="-1"
              >
                {{ title }}
              </h2>
              <p v-if="sessionLabel">{{ sessionLabel }}</p>
            </div>
            <ol
              v-if="mode === 'create'"
              class="finance-handovers__steps"
              :aria-label="title"
            >
              <li
                :class="{ 'finance-handovers__step--active': !reviewing }"
                :aria-current="!reviewing ? 'step' : undefined"
              >
                <span>
                  <VIcon
                    v-if="reviewing"
                    icon="tabler-check"
                    size="16"
                    aria-hidden="true"
                  />
                  <template v-else>1</template>
                </span>
                {{ t('dailyfinance::cash_handovers.details') }}
              </li>
              <li
                :class="{ 'finance-handovers__step--active': reviewing }"
                :aria-current="reviewing ? 'step' : undefined"
              >
                <span>2</span>
                {{ t('dailyfinance::cash_handovers.review_step') }}
              </li>
            </ol>
            <VBtn
              icon="tabler-x"
              variant="text"
              color="on-surface"
              :aria-label="t('dailyfinance::daily_finance.actions.dismiss')"
              :disabled="busy"
              @click="dismissForm"
            />
          </header>
          <VCardText class="finance-handover-dialog__body">
            <VForm
              class="finance-handovers__form"
              :aria-label="title"
              @submit.prevent="submit"
            >
              <VAlert
                v-if="error"
                type="warning"
                variant="tonal"
                role="alert"
              >
                {{ error }}
              </VAlert>
              <header class="finance-handovers__form-header">
                <p class="finance-handovers__form-hint">
                  {{
                    t(
                      `dailyfinance::cash_handovers.${mode === 'create' ? (reviewing ? 'review_hint' : 'details_hint') : mode === 'receive' ? 'receive_hint' : 'cancel_hint'}`,
                    )
                  }}
                </p>
                <div
                  v-if="selected"
                  class="finance-handovers__selected"
                >
                  <strong>{{ selected.reference }}</strong>
                  <span>{{ selected.receiver.name }}</span>
                  <bdi>{{ selected.amount.formatted }}</bdi>
                </div>
              </header>
              <template v-if="mode === 'create' && reviewing">
                <dl class="finance-handovers__review">
                  <div>
                    <dt>{{ t('dailyfinance::cash_handovers.amount') }}</dt>
                    <dd>
                      <bdi>{{ amount }} {{ summary.currency }}</bdi>
                    </dd>
                  </div>
                  <div>
                    <dt>{{ t('dailyfinance::cash_handovers.to') }}</dt>
                    <dd>{{ receiver?.name }}</dd>
                  </div>
                </dl>
                <p
                  v-if="notes"
                  class="finance-handovers__note"
                >
                  {{ notes }}
                </p>
                <VCheckbox
                  v-model="acknowledged"
                  :label="t('dailyfinance::cash_handovers.acknowledge')"
                  :disabled="busy"
                  hide-details
                />
              </template>
              <template v-else>
                <div
                  v-if="mode !== 'cancel'"
                  class="finance-handovers__field"
                >
                  <label :for="`${formId}-amount`">
                    {{
                      t(`dailyfinance::cash_handovers.${mode === 'create' ? 'amount' : 'actual'}`)
                    }}
                  </label>
                  <VTextField
                    :id="`${formId}-amount`"
                    class="finance-handovers__amount"
                    variant="outlined"
                    persistent-placeholder
                    hide-details="auto"
                    :placeholder="(0).toFixed(summary.precision)"
                    v-model="amount"
                    inputmode="decimal"
                    :suffix="summary.currency"
                    :disabled="busy"
                    :error-messages="
                      amountError || errors[mode === 'create' ? 'amount' : 'received_amount']
                    "
                  />
                  <div
                    v-if="mode === 'create'"
                    class="finance-handovers__available"
                  >
                    <span>
                      {{ t('dailyfinance::cash_handovers.available_to_handover') }}
                      <bdi>{{ summary.available?.formatted }}</bdi>
                    </span>
                    <VBtn
                      variant="text"
                      color="primary"
                      size="small"
                      :disabled="busy || loading"
                      @click="amount = summary.available_decimal ?? ''"
                    >
                      {{ t('dailyfinance::cash_handovers.use_full_amount') }}
                    </VBtn>
                  </div>
                </div>
                <div
                  v-if="mode === 'create'"
                  class="finance-handovers__field"
                >
                  <label :for="`${formId}-receiver`">
                    {{ t('dailyfinance::cash_handovers.receiver') }}
                  </label>
                  <VAutocomplete
                    :id="`${formId}-receiver`"
                    variant="outlined"
                    prepend-inner-icon="tabler-user"
                    hide-details="auto"
                    v-model="receiverId"
                    :items="summary.receivers"
                    item-title="name"
                    item-value="id"
                    :placeholder="t('dailyfinance::cash_handovers.choose_receiver')"
                    :disabled="busy"
                    :error-messages="errors.receiver_id"
                  />
                </div>
                <div
                  v-if="mode === 'create'"
                  class="finance-handovers__field"
                >
                  <label :for="`${formId}-notes`">
                    {{ t('dailyfinance::cash_handovers.notes') }}
                  </label>
                  <VTextarea
                    :id="`${formId}-notes`"
                    variant="outlined"
                    hide-details="auto"
                    v-model="notes"
                    :disabled="busy"
                    :error-messages="errors.notes"
                    :maxlength="1000"
                    rows="2"
                    :max-rows="4"
                    auto-grow
                  />
                </div>
                <div
                  v-if="mode === 'cancel' || hasDifference"
                  class="finance-handovers__field"
                >
                  <label :for="`${formId}-reason`">
                    {{
                      t(
                        `dailyfinance::cash_handovers.${mode === 'cancel' ? 'cancel_reason' : 'reason'}`,
                      )
                    }}
                  </label>
                  <VTextarea
                    :id="`${formId}-reason`"
                    variant="outlined"
                    hide-details="auto"
                    v-model="reason"
                    :disabled="busy"
                    :error-messages="
                      errors[mode === 'cancel' ? 'cancellation_reason' : 'variance_reason'] ||
                      (reason && reason.trim().length < 3
                        ? t('dailyfinance::cash_handovers.errors.reason')
                        : '')
                    "
                    :maxlength="1000"
                    rows="2"
                    :max-rows="4"
                    auto-grow
                  />
                </div>
                <VCheckbox
                  v-if="mode === 'receive'"
                  v-model="acknowledged"
                  :label="t('dailyfinance::cash_handovers.receipt_acknowledge')"
                  :disabled="busy"
                  hide-details
                />
              </template>
              <div class="finance-handovers__actions finance-handovers__form-actions">
                <VBtn
                  variant="text"
                  color="on-surface"
                  :disabled="busy"
                  @click="dismissForm"
                >
                  {{ t('dailyfinance::cash_handovers.dismiss') }}
                </VBtn>
                <VBtn
                  v-if="reviewing && !uncertain"
                  variant="text"
                  :disabled="saving"
                  @click="editReview"
                >
                  {{ t('dailyfinance::cash_handovers.back') }}
                </VBtn>
                <VBtn
                  type="submit"
                  :color="mode === 'cancel' ? 'error' : 'primary'"
                  :append-icon="
                    mode === 'create' && !reviewing ? 'tabler-arrow-right' : 'tabler-check'
                  "
                  :loading="saving"
                  :disabled="saving || loading || (!canSubmit && !uncertain)"
                >
                  {{
                    t(
                      `dailyfinance::cash_handovers.${uncertain ? 'retry' : mode === 'create' ? (reviewing ? 'confirm' : 'review') : mode === 'receive' ? 'receive' : 'cancel'}`,
                    )
                  }}
                </VBtn>
              </div>
            </VForm>
          </VCardText>
        </VCard>
      </VDialog>
      <div
        v-if="!summary.data.length"
        class="finance-handovers__empty"
      >
        <VIcon
          icon="tabler-cash-banknote"
          size="30"
          color="secondary"
          aria-hidden="true"
        />
        <strong>{{ t('dailyfinance::cash_handovers.empty') }}</strong>
        <p>
          {{ t(`dailyfinance::cash_handovers.${summary.can_create ? 'empty_hint' : 'no_cash'}`) }}
        </p>
      </div>
      <div
        v-else-if="summary.data.length"
        class="finance-handovers__history"
      >
        <header class="finance-handovers__history-heading">
          <h4>{{ t('dailyfinance::cash_handovers.history') }}</h4>
          <span>
            {{
              t('dailyfinance::cash_handovers.history_count', { count: summary.pagination.total })
            }}
          </span>
        </header>
        <article
          v-for="record in summary.data"
          :key="record.id"
          class="finance-handovers__record"
        >
          <header class="finance-handovers__record-header">
            <div class="finance-handovers__record-identity">
              <span class="finance-handovers__record-icon">
                <VIcon
                  icon="tabler-cash-banknote"
                  size="22"
                  aria-hidden="true"
                />
              </span>
              <div>
                <div class="finance-handovers__record-reference">
                  <strong>{{ record.reference }}</strong>
                  <TableStatus :item="record" />
                </div>
                <span class="finance-handovers__record-date">
                  <span class="finance-handovers__sr-only">
                    {{ t('dailyfinance::cash_handovers.created_at') }}
                  </span>
                  <bdi dir="ltr">{{ record.created_at }}</bdi>
                </span>
              </div>
            </div>
            <div class="finance-handovers__record-value">
              <strong>
                <bdi>{{ record.amount.formatted }}</bdi>
              </strong>
            </div>
          </header>
          <dl class="finance-handovers__people">
            <div>
              <dt>{{ t('dailyfinance::cash_handovers.cashier') }}</dt>
              <dd>{{ record.cashier.name }}</dd>
            </div>
            <div class="finance-handovers__receiver">
              <dt>{{ t('dailyfinance::cash_handovers.to') }}</dt>
              <dd>
                <VIcon
                  class="finance-handovers__direction"
                  icon="tabler-arrow-right"
                  size="18"
                  aria-hidden="true"
                />
                {{ record.receiver.name }}
              </dd>
            </div>
          </dl>
          <dl
            v-if="record.received_amount"
            class="finance-handovers__receipt"
          >
            <div>
              <dt>{{ t('dailyfinance::cash_handovers.received') }}</dt>
              <dd>
                <bdi>{{ record.received_amount.formatted }}</bdi>
              </dd>
            </div>
            <div>
              <dt>{{ t('dailyfinance::cash_handovers.variance') }}</dt>
              <dd>
                <bdi>{{ record.variance?.formatted }}</bdi>
              </dd>
            </div>
          </dl>
          <p
            v-for="(note, key) in {
              notes: record.notes,
              reason: record.variance_reason,
              cancel_reason: record.cancellation_reason,
            }"
            v-show="note"
            :key="key"
            class="finance-handovers__note"
          >
            <strong>{{ t(`dailyfinance::cash_handovers.${key}`) }}:</strong>
            {{ note }}
          </p>
          <footer class="finance-handovers__record-footer">
            <dl class="finance-handovers__audit">
              <div>
                <dt>{{ t('dailyfinance::cash_handovers.sent_by') }}</dt>
                <dd>{{ record.created_by.name }}</dd>
              </div>
              <div v-if="record.received_at">
                <dt>{{ t('dailyfinance::cash_handovers.received_at') }}</dt>
                <dd>
                  <bdi dir="ltr">{{ record.received_at }}</bdi>
                </dd>
              </div>
              <div v-if="record.cancelled_at">
                <dt>{{ t('dailyfinance::cash_handovers.cancelled_at') }}</dt>
                <dd>
                  <bdi dir="ltr">{{ record.cancelled_at }}</bdi>
                </dd>
              </div>
            </dl>
            <div
              v-if="record.can_receive || record.can_cancel"
              class="finance-handovers__actions"
            >
              <VBtn
                v-if="record.can_cancel"
                variant="outlined"
                color="error"
                prepend-icon="tabler-x"
                size="small"
                :disabled="!!mode || busy"
                @click="start('cancel', record)"
              >
                {{ t('dailyfinance::cash_handovers.cancel') }}
              </VBtn>
              <VBtn
                v-if="record.can_receive"
                color="primary"
                variant="tonal"
                size="small"
                :disabled="!!mode || busy"
                @click="start('receive', record)"
              >
                {{ t('dailyfinance::cash_handovers.receive') }}
              </VBtn>
            </div>
          </footer>
        </article>
        <VPagination
          v-if="summary.pagination.last_page > 1"
          :model-value="summary.pagination.current_page"
          :length="summary.pagination.last_page"
          :total-visible="4"
          :disabled="!!mode || busy"
          @update:model-value="load($event)"
        />
      </div>
    </template>
  </section>
</template>

<style lang="scss" scoped>
.finance-handovers {
  display: grid;
  gap: 24px;
  padding-block: 22px 8px;
}

.finance-handovers__overview {
  display: grid;
  gap: 20px;
}

.finance-handovers__cash {
  padding: 24px;
  border-radius: 14px;
  background: rgb(var(--v-theme-secondary));
  color: rgb(var(--v-theme-on-secondary));
}

.finance-handovers__cash-heading {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 20px;
}

.finance-handovers__cash-label {
  font-size: 0.875rem;
  opacity: 0.8;
}

.finance-handovers__cash-value {
  display: block;
  margin-block-start: 8px;
  font-size: clamp(1.8rem, 3vw, 2.5rem);
  line-height: 1.2;
  letter-spacing: -0.035em;
  font-variant-numeric: tabular-nums;
}

.finance-handovers__balances {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 20px;
  margin: 24px 0 0;
  padding-block-start: 18px;
  border-block-start: 1px solid rgb(var(--v-theme-on-secondary), 0.2);
}

.finance-handovers__balances > div + div {
  padding-inline-start: 20px;
  border-inline-start: 1px solid rgb(var(--v-theme-on-secondary), 0.2);
}

.finance-handovers__balances dt {
  color: rgb(var(--v-theme-on-secondary), 0.75);
}

.finance-handovers__balances dd {
  font-size: 1.25rem;
  font-weight: 600;
}

.finance-handovers__status {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  margin: 0;
  padding-block: 4px;
}

.finance-handovers__status > div {
  display: flex;
  align-items: center;
  gap: 10px;
  min-inline-size: 0;
}

.finance-handovers__status > div + div {
  padding-inline-start: 12px;
  border-inline-start: 1px solid rgba(var(--v-theme-on-surface), 0.1);
}

.finance-handovers__status dd {
  font-size: 1rem;
  font-weight: 600;
}

.finance-handovers dt,
.finance-handover-dialog dt {
  font-size: 0.75rem;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

.finance-handovers dd,
.finance-handover-dialog dd {
  margin: 6px 0 0;
  overflow-wrap: anywhere;
  font-variant-numeric: tabular-nums;
}

.finance-handovers__cash dt {
  color: rgb(var(--v-theme-on-secondary), 0.75);
}

.finance-handovers h4 {
  font-size: 0.9375rem;
  font-weight: 600;
}

.finance-handovers__history-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.finance-handovers__history-heading > span {
  font-size: 0.75rem;
  color: rgba(var(--v-theme-on-surface), 0.6);
}

.finance-handover-dialog {
  border-radius: 16px !important;
}

.finance-handover-dialog__header {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 14px 20px;
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.1);
}

.finance-handover-dialog__header > div:not(.v-avatar) {
  flex: 1;
  min-inline-size: 0;
}

.finance-handover-dialog__header h2 {
  font-size: 1rem;
  font-weight: 600;
}

.finance-handover-dialog__header p {
  margin: 4px 0 0;
  font-size: 0.8125rem;
  color: rgba(var(--v-theme-on-surface), 0.6);
}

.finance-handover-dialog__body {
  padding: 20px !important;
}

.finance-handovers__form {
  display: grid;
  gap: 18px;
}

.finance-handovers__form-header {
  display: grid;
  align-items: center;
  gap: 8px 16px;
}

.finance-handovers__form-hint {
  grid-column: 1 / -1;
  margin: 0;
  font-size: 0.8125rem;
  line-height: 1.6;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

.finance-handovers__steps {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.finance-handovers__steps li {
  display: flex;
  align-items: center;
  gap: 5px;
  color: rgba(var(--v-theme-on-surface), 0.55);
  font-size: 0.75rem;
  white-space: nowrap;
}

.finance-handovers__steps li > span {
  display: grid;
  place-items: center;
  inline-size: 22px;
  block-size: 22px;
  border-radius: 50%;
  background: rgba(var(--v-theme-on-surface), 0.07);
}

.finance-handovers__steps .finance-handovers__step--active > span {
  background: rgb(var(--v-theme-secondary));
  color: rgb(var(--v-theme-on-secondary));
}

.finance-handovers__steps li + li::before {
  content: '';
  inline-size: 12px;
  margin-inline-end: 3px;
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.2);
}

.finance-handovers__steps .finance-handovers__step--active {
  font-weight: 600;
  color: rgb(var(--v-theme-secondary));
}

.finance-handovers__field {
  display: grid;
  gap: 8px;
}

.finance-handovers__field > label {
  font-size: 0.8125rem;
  font-weight: 600;
}

.finance-handovers__amount :deep(input) {
  font-size: 1.125rem;
  font-weight: 500;
  font-variant-numeric: tabular-nums;
}

.finance-handovers__available {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 4px 8px;
  font-size: 0.75rem;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

.finance-handovers__available :deep(.v-btn) {
  padding-inline: 4px;
}

.finance-handovers__review {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  align-items: center;
  gap: 20px;
  margin: 0;
  padding: 20px;
  border-radius: 10px;
  border: 1px solid rgba(var(--v-theme-secondary), 0.12);
  background: rgba(var(--v-theme-secondary), 0.035);
}

.finance-handovers__review dd {
  font-size: 1rem;
  font-weight: 600;
}

.finance-handovers__review > div:first-child dd {
  font-size: 1.5rem;
  color: rgb(var(--v-theme-secondary));
}

.finance-handovers__selected {
  grid-column: 1 / -1;
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
  margin-block-start: 14px;
  font-size: 0.8125rem;
}

.finance-handovers__selected bdi {
  margin-inline-start: auto;
  font-weight: 600;
}

.finance-handovers__empty {
  display: grid;
  justify-items: center;
  text-align: center;
  gap: 8px;
  padding: 24px 16px;
}

.finance-handovers__empty p {
  margin: 0;
  font-size: 0.8125rem;
  line-height: 1.6;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

.finance-handovers__empty strong {
  font-size: 0.875rem;
}

.finance-handovers__history {
  display: grid;
  gap: 16px;
  padding-block-start: 20px;
  border-block-start: 1px solid rgba(var(--v-theme-on-surface), 0.1);
}

.finance-handovers__record {
  position: relative;
  display: grid;
  gap: 12px;
  margin-inline-start: 15px;
  padding-block: 0 22px;
  padding-inline: 28px 0;
  border-inline-start: 1px solid rgba(var(--v-theme-secondary), 0.14);
}

.finance-handovers__record-header,
.finance-handovers__record-identity,
.finance-handovers__record-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.finance-handovers__record-header {
  align-items: flex-start;
}

.finance-handovers__record-identity {
  justify-content: flex-start;
  min-inline-size: 0;
}

.finance-handovers__record-identity strong {
  font-size: 0.875rem;
}

.finance-handovers__record-icon {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  position: absolute;
  inset-inline-start: -16px;
  inset-block-start: 0;
  inline-size: 32px;
  block-size: 32px;
  border: 4px solid rgb(var(--v-theme-surface));
  border-radius: 50%;
  background: rgb(var(--v-theme-surface));
  color: rgb(var(--v-theme-secondary));
}

.finance-handovers__record-reference {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}

.finance-handovers__record-date {
  display: block;
  margin-block-start: 4px;
  font-size: 0.6875rem;
  color: rgba(var(--v-theme-on-surface), 0.6);
}

.finance-handovers__record-value {
  display: grid;
  justify-items: end;
  gap: 5px;
  flex-shrink: 0;
}

.finance-handovers__record-value > strong {
  font-size: 1.125rem;
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.025em;
  color: rgb(var(--v-theme-secondary));
}

.finance-handovers__people,
.finance-handovers__receipt {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 20px;
  margin: 0;
}

.finance-handovers__people {
  padding: 12px 14px;
  border-radius: 8px;
  background: rgba(var(--v-theme-secondary), 0.035);
}

.finance-handovers__people dd {
  margin-block-start: 3px;
  font-size: 0.8125rem;
  font-weight: 600;
}

.finance-handovers__receiver {
  position: relative;
  padding-inline-start: 36px;
}

.finance-handovers__direction {
  position: absolute;
  inset-inline-start: 0;
  inset-block-start: 50%;
  margin-block-start: -9px;
  color: rgba(var(--v-theme-on-surface), 0.4);
}

.finance-handovers__direction:dir(rtl) {
  transform: rotate(180deg);
}

.finance-handovers__receipt {
  padding: 12px 14px;
  border-radius: 8px;
  background: rgba(var(--v-theme-secondary), 0.04);
}

.finance-handovers__receipt dd {
  font-weight: 600;
}

.finance-handovers__record-footer {
  flex-wrap: wrap;
  gap: 8px;
}

.finance-handovers__audit {
  display: grid;
  gap: 5px;
  margin: 0;
}

.finance-handovers__audit > div {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 4px 8px;
}

.finance-handovers__audit dd {
  margin: 0;
  font-size: 0.75rem;
}

.finance-handovers__record-footer .finance-handovers__actions {
  margin-inline-start: auto;
}

.finance-handovers__actions {
  display: flex;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: 10px;
}

.finance-handovers__form-actions {
  padding-block: 18px 0;
  border-block-start: 1px solid rgba(var(--v-theme-on-surface), 0.1);
}

.finance-handovers__form-actions > :first-child {
  margin-inline-end: auto;
}

.finance-handovers__note {
  margin: 0;
  padding-inline-start: 12px;
  border-inline-start: 2px solid rgba(var(--v-theme-primary), 0.3);
  color: rgba(var(--v-theme-on-surface), 0.75);
  font-size: 0.75rem;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}

.finance-handovers__sr-only {
  position: absolute;
  inline-size: 1px;
  block-size: 1px;
  overflow: hidden;
  clip-path: inset(50%);
  white-space: nowrap;
}

.finance-handovers__history-loading {
  display: grid;
  gap: 16px;
  padding-block-start: 20px;
  border-block-start: 1px solid rgba(var(--v-theme-on-surface), 0.1);
}

.finance-handovers__bone {
  display: block;
  max-inline-size: 100%;
  block-size: 14px;
  border-radius: 5px;
  background: rgba(var(--v-theme-on-surface), 0.08);
  animation: handover-loading 1.6s ease-in-out infinite alternate;
}

.finance-handovers__cash .finance-handovers__bone {
  background: rgb(var(--v-theme-on-secondary), 0.2);
}

.finance-handovers__bone--hero {
  inline-size: 185px;
  block-size: 48px;
  margin-block-start: 8px;
}

.finance-handovers__bone--value {
  inline-size: 80px;
  block-size: 24px;
}

.finance-handovers__bone--heading {
  inline-size: 150px;
}

.finance-handovers__bone--line {
  inline-size: 70%;
}

@keyframes handover-loading {
  to {
    opacity: 0.45;
  }
}

@media (prefers-reduced-motion: reduce) {
  .finance-handovers__bone {
    animation: none;
  }
}

@media (max-width: 600px) {
  .finance-handovers__cash {
    padding: 20px;
  }

  .finance-handovers__cash-heading > .v-btn {
    inline-size: 100%;
  }

  .finance-handovers__status > div {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }

  .finance-handover-dialog__header {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 12px;
    padding: 14px 16px;
  }

  .finance-handover-dialog__header > .v-btn {
    grid-column: 2;
    grid-row: 1;
  }

  .finance-handover-dialog__body {
    padding: 16px !important;
  }

  .finance-handovers__form-header {
    grid-template-columns: minmax(0, 1fr);
  }

  .finance-handovers__steps {
    grid-column: 1 / -1;
    justify-content: flex-start;
  }

  .finance-handovers__form-actions {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
  }

  .finance-handovers__form-actions > :last-child {
    grid-column: 1 / -1;
    inline-size: 100%;
  }

  .finance-handovers__review {
    grid-template-columns: minmax(0, 1fr);
    padding: 18px;
    gap: 14px;
  }

  .finance-handovers__record {
    padding-inline-start: 22px;
  }

  .finance-handovers__record-header {
    flex-wrap: wrap;
  }

  .finance-handovers__record-value {
    margin-inline-start: auto;
  }

  .finance-handovers__receiver {
    padding-inline-start: 28px;
  }
}
</style>
