<script lang="ts" setup>
import FinanceSettlementCollections from './FinanceSettlementCollections.vue'
import { computed, nextTick, onMounted, ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import { createSettlement } from '@/modules/dailyfinance/api/paymentSettlement.api'
import { useSettlementCollections } from '@/modules/dailyfinance/composables/useSettlementCollections'
import { usePaymentSettlementMutation } from '@/modules/dailyfinance/composables/usePaymentSettlementMutation'
import type {
  CreateSettlement,
  PaymentSettlement,
  SettlementMeta,
} from '@/modules/dailyfinance/contracts/paymentSettlement'
import {
  settlementDecimal,
  settlementFormatted,
  settlementUnits,
} from '@/modules/dailyfinance/utils/settlementMoney'

const props = defineProps<{
  branchId: number
  branchName: string
  date: string
  meta: SettlementMeta
}>()
const emit = defineEmits<{ close: []; saved: [record: PaymentSettlement] }>()
const { t, locale } = useI18n()
const { saving, uncertain, error, errors, save } =
  usePaymentSettlementMutation<CreateSettlement>(createSettlement)
const open = ref(true)
const titleId = useId()
const heading = ref<HTMLElement | null>(null)
const body = ref<{ $el: HTMLElement } | null>(null)
const activator = document.activeElement
const step = ref(0)
const accountKey = ref('manual')
const destination = ref('')
const reference = ref('')
const fees = ref('0')
const notes = ref('')
const acknowledged = ref(false)
const requestKey = crypto.randomUUID()
let accepted: PaymentSettlement | null = null

const busy = computed(() => saving.value || uncertain.value)
const collections = useSettlementCollections(props.branchId, props.date, () => busy.value)
const { method, selected, loading, loadSources } = collections
const gross = computed(() =>
  [...selected.value.values()].reduce(
    (sum, row) => sum + (settlementUnits(row.amount_decimal, props.meta.precision) ?? 0n),
    0n,
  ),
)
const charge = computed(() => settlementUnits(fees.value, props.meta.precision))
const expected = computed(() => gross.value - (charge.value ?? 0n))
const accounts = computed(() => [
  { key: 'manual', name: t('dailyfinance::payment_settlements.manual_account') },
  ...props.meta.accounts,
])
const destinationLabel = computed(() =>
  accountKey.value === 'manual'
    ? destination.value
    : (props.meta.accounts.find((row) => row.key === accountKey.value)?.name ?? ''),
)
const feesError = computed(() =>
  !fees.value
    ? ''
    : charge.value === null
      ? t('dailyfinance::payment_settlements.errors.precision')
      : charge.value >= gross.value
        ? t('dailyfinance::payment_settlements.errors.fees')
        : '',
)
const detailsValid = computed(
  () =>
    reference.value.trim() &&
    destinationLabel.value.trim() &&
    charge.value !== null &&
    expected.value > 0n &&
    gross.value <= 99999999999999n * 10n ** BigInt(props.meta.precision),
)
const canContinue = computed(
  () =>
    !busy.value &&
    !loading.value &&
    (step.value === 0 ? selected.value.size > 0 : !!detailsValid.value),
)

function money(value: bigint) {
  return settlementFormatted(value, props.meta.currency, props.meta.precision, locale.value)
}

function changeStep(value: number) {
  if (busy.value || value < 0 || value > 2 || (value > step.value && !canContinue.value)) return
  step.value = value
  acknowledged.value = false
  void nextTick(() => {
    if (body.value) body.value.$el.scrollTop = 0
    heading.value?.focus({ preventScroll: true })
  })
}

async function submit() {
  if (!uncertain.value && (!detailsValid.value || !acknowledged.value || step.value !== 2)) return
  const payload: CreateSettlement = {
    branch_id: props.branchId,
    method: method.value,
    payment_ids: [...selected.value.keys()],
    source_hashes: Object.fromEntries(
      [...selected.value.values()].map((row) => [row.id, row.source_hash]),
    ),
    fees: settlementDecimal(charge.value ?? 0n, props.meta.precision),
    account_key: accountKey.value === 'manual' ? null : accountKey.value,
    destination_name: accountKey.value === 'manual' ? destination.value.trim() : null,
    reference: reference.value.trim(),
    notes: notes.value.trim() || null,
    request_key: requestKey,
  }
  const record = await save(payload)
  if (record) {
    accepted = record
    open.value = false
  }
}

function afterLeave() {
  if (activator instanceof HTMLElement && activator.isConnected)
    activator.focus({ preventScroll: true })
  if (accepted) emit('saved', accepted)
  else emit('close')
}

onMounted(() => {
  if (props.meta.accounts.length) accountKey.value = props.meta.accounts[0]!.key
  void loadSources()
})
</script>

<template>
  <VDialog
    v-model="open"
    :aria-labelledby="titleId"
    :persistent="busy"
    max-width="920"
    height="760"
    scrollable
    @after-leave="afterLeave"
  >
    <VCard class="settlement-dialog settlement-create">
      <header class="settlement-dialog__header settlement-create__header">
        <div>
          <h2
            :id="titleId"
            ref="heading"
            tabindex="-1"
          >
            {{ t('dailyfinance::payment_settlements.create_title') }}
          </h2>
          <span class="settlement-dialog__context">
            <VIcon
              icon="tabler-building-store"
              size="16"
            />
            {{ branchName }}
          </span>
        </div>
        <ol class="settlement-create__steps">
          <li
            v-for="(key, index) in ['collections', 'details', 'review']"
            :key="key"
            :class="{ 'settlement-create__step--active': step === index }"
            :aria-current="step === index ? 'step' : undefined"
          >
            <button
              type="button"
              :disabled="index >= step || busy"
              @click="changeStep(index)"
            >
              <span>
                <VIcon
                  v-if="index < step"
                  icon="tabler-check"
                  size="14"
                />
                <template v-else>{{ index + 1 }}</template>
              </span>
              {{
                t(`dailyfinance::payment_settlements.${key === 'details' ? 'step_details' : key}`)
              }}
            </button>
          </li>
        </ol>
        <VBtn
          :aria-label="t('dailyfinance::daily_finance.actions.dismiss')"
          :disabled="busy"
          icon="tabler-x"
          variant="text"
          color="on-surface"
          @click="open = false"
        />
      </header>
      <VCardText
        ref="body"
        class="settlement-dialog__body"
        :class="{ 'settlement-create__collection-body': step === 0 }"
      >
        <VAlert
          v-if="error || uncertain"
          type="warning"
          variant="tonal"
          role="alert"
        >
          {{ uncertain ? t('dailyfinance::payment_settlements.uncertain') : error }}
        </VAlert>
        <FinanceSettlementCollections
          v-if="step === 0"
          :state="collections"
          :meta="meta"
          :busy="busy"
        />
        <div
          v-else-if="step === 1"
          class="settlement-create__details"
        >
          <section class="settlement-create__fields">
            <h3>{{ t('dailyfinance::payment_settlements.bank_destination') }}</h3>
            <div class="settlement-dialog__fields">
              <VSelect
                v-model="accountKey"
                :items="accounts"
                item-title="name"
                item-value="key"
                :label="t('dailyfinance::payment_settlements.bank_account')"
                :error-messages="errors.account_key"
                :disabled="busy"
                hide-details="auto"
              />
              <VTextField
                v-if="accountKey === 'manual'"
                v-model="destination"
                :label="t('dailyfinance::payment_settlements.destination')"
                :error-messages="errors.destination_name"
                :disabled="busy"
                maxlength="160"
                hide-details="auto"
              />
              <VTextField
                v-model="reference"
                :label="t('dailyfinance::payment_settlements.batch_reference')"
                :error-messages="errors.reference"
                :disabled="busy"
                maxlength="120"
                hide-details="auto"
              />
              <VTextField
                v-model="fees"
                :label="t('dailyfinance::payment_settlements.fees')"
                :suffix="meta.currency"
                inputmode="decimal"
                :error-messages="errors.fees || feesError"
                :disabled="busy"
                hide-details="auto"
              />
              <VTextarea
                v-model="notes"
                class="settlement-dialog__fields--full"
                :label="t('dailyfinance::payment_settlements.notes')"
                :error-messages="errors.notes"
                :disabled="busy"
                rows="2"
                auto-grow
                max-rows="4"
                maxlength="1000"
                hide-details="auto"
              />
            </div>
          </section>
          <aside class="settlement-create__summary">
            <h3>{{ t('dailyfinance::payment_settlements.summary') }}</h3>
            <p>
              {{ meta.methods.find((row) => row.id === method)?.name }}
              <small>
                {{ t('dailyfinance::payment_settlements.selected', { count: selected.size }) }}
              </small>
            </p>
            <dl class="settlement-dialog__review">
              <div>
                <dt>{{ t('dailyfinance::payment_settlements.gross') }}</dt>
                <dd>{{ money(gross) }}</dd>
              </div>
              <div>
                <dt>{{ t('dailyfinance::payment_settlements.fees') }}</dt>
                <dd>{{ money(charge ?? 0n) }}</dd>
              </div>
              <div class="settlement-dialog__review-total">
                <dt>{{ t('dailyfinance::payment_settlements.expected') }}</dt>
                <dd>{{ money(expected) }}</dd>
              </div>
            </dl>
          </aside>
        </div>
        <template v-else>
          <div class="settlement-create__review-heading">
            <VAvatar
              color="secondary"
              variant="tonal"
              rounded="lg"
              size="48"
            >
              <VIcon
                icon="tabler-building-bank"
                size="26"
              />
            </VAvatar>
            <div class="settlement-create__review-destination">
              <strong>{{ destinationLabel }}</strong>
              <span>
                {{ meta.methods.find((row) => row.id === method)?.name }} ·
                {{ t('dailyfinance::payment_settlements.selected', { count: selected.size }) }}
              </span>
              <small>
                {{ t('dailyfinance::payment_settlements.batch_reference') }}: {{ reference }}
              </small>
            </div>
            <VBtn
              variant="text"
              color="secondary"
              prepend-icon="tabler-pencil"
              :disabled="busy"
              @click="changeStep(1)"
            >
              {{ t('dailyfinance::payment_settlements.edit_details') }}
            </VBtn>
          </div>
          <div class="settlement-create__review-total">
            <span>{{ t('dailyfinance::payment_settlements.expected') }}</span>
            <strong>
              <bdi>{{ money(expected) }}</bdi>
            </strong>
          </div>
          <dl class="settlement-create__review-breakdown">
            <div>
              <dt>{{ t('dailyfinance::payment_settlements.gross') }}</dt>
              <dd>
                <bdi>{{ money(gross) }}</bdi>
              </dd>
            </div>
            <div>
              <dt>{{ t('dailyfinance::payment_settlements.fees') }}</dt>
              <dd>
                <bdi>{{ money(charge ?? 0n) }}</bdi>
              </dd>
            </div>
          </dl>
          <div
            v-if="notes"
            class="settlement-create__review-notes"
          >
            <span>{{ t('dailyfinance::payment_settlements.notes') }}</span>
            <p class="settlement-dialog__note">{{ notes }}</p>
          </div>
          <VCheckbox
            v-model="acknowledged"
            class="settlement-dialog__confirmation"
            :label="t('dailyfinance::payment_settlements.acknowledge')"
            :disabled="busy"
            hide-details
          />
          <p class="settlement-dialog__note">
            {{ t('dailyfinance::payment_settlements.operational_hint') }}
          </p>
        </template>
      </VCardText>
      <footer class="settlement-dialog__footer">
        <span v-if="step === 0">
          {{ t('dailyfinance::payment_settlements.selected', { count: selected.size }) }}
          <strong>{{ money(gross) }}</strong>
        </span>
        <div
          class="settlement-dialog__actions"
          :class="{ 'settlement-create__navigation': step > 0 }"
        >
          <VBtn
            :disabled="busy"
            color="secondary"
            variant="text"
            @click="step ? changeStep(step - 1) : (open = false)"
          >
            {{ t(`dailyfinance::payment_settlements.${step ? 'back' : 'dismiss'}`) }}
          </VBtn>
          <VBtn
            v-if="step < 2"
            :disabled="!canContinue"
            color="primary"
            append-icon="tabler-arrow-right"
            @click="changeStep(step + 1)"
          >
            {{ t('dailyfinance::payment_settlements.continue') }}
          </VBtn>
          <VBtn
            v-else
            :disabled="uncertain ? saving : !acknowledged || !detailsValid || saving"
            :loading="saving"
            color="primary"
            prepend-icon="tabler-check"
            @click="submit"
          >
            {{ t(`dailyfinance::payment_settlements.${uncertain ? 'retry' : 'confirm'}`) }}
          </VBtn>
        </div>
      </footer>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
@use './settlement-dialog';

.settlement-create__steps {
  display: flex;
  align-items: center;
  gap: 14px;
  margin: 6px 0 0 auto;
  padding: 0;
  list-style: none;
  color: rgba(var(--v-theme-on-surface), 0.55);
  font-size: 0.75rem;
}

.settlement-create__steps button {
  color: inherit;
  font: inherit;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 6px;
}

.settlement-create__steps button > span {
  display: grid;
  place-items: center;
  inline-size: 24px;
  block-size: 24px;
  border-radius: 50%;
  background: rgba(var(--v-theme-secondary), 0.06);
}

.settlement-create__steps .settlement-create__step--active {
  color: rgb(var(--v-theme-secondary));
}

.settlement-create__step--active button > span {
  background: rgb(var(--v-theme-secondary)) !important;
  color: rgb(var(--v-theme-on-secondary));
}

.settlement-create__details {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 260px;
  gap: 24px;
}
.settlement-create__fields {
  display: grid;
  align-content: start;
  gap: 20px;
}
.settlement-create__fields .settlement-dialog__fields {
  grid-template-columns: minmax(0, 1fr);
}
.settlement-create__details h3 {
  margin: 0;
  font-size: 0.9375rem;
  color: rgb(var(--v-theme-secondary));
}
.settlement-create__summary {
  align-self: stretch;
  padding: 20px;
  background: rgba(var(--v-theme-secondary), 0.045);
  border-radius: 10px;
}
.settlement-create__summary p {
  margin-block: 18px 24px;
  color: rgb(var(--v-theme-secondary));
  font-size: 0.875rem;
}
.settlement-create__summary small {
  display: block;
  margin-block-start: 4px;
  color: rgba(var(--v-theme-on-surface), 0.65);
}
.settlement-create__summary .settlement-dialog__review {
  border: 0;
  border-radius: 0;
}
.settlement-create__summary .settlement-dialog__review > div {
  padding: 12px 0;
}
.settlement-create__summary .settlement-dialog__review dt {
  font-size: 0.8125rem;
}
.settlement-create__summary .settlement-dialog__review-total {
  display: grid;
  justify-content: start;
  gap: 6px;
  background: none;
}
.settlement-create__summary .settlement-dialog__review-total dd {
  font-weight: 700;
  font-size: 1.75rem;
  text-align: start;
}
.settlement-create__navigation {
  inline-size: 100%;
  justify-content: space-between;
}

.settlement-create .settlement-create__collection-body {
  display: flex;
  flex-direction: column;
  min-block-size: 0;
  overflow: hidden !important;
  padding: 0 !important;
  gap: 0;
}
.settlement-create__collection-body > .v-alert {
  flex: 0 0 auto;
  margin: 12px 22px 0;
}
.settlement-create__review-heading {
  display: grid;
  grid-template-columns: 48px minmax(0, 1fr) auto;
  align-items: center;
  gap: 14px;
}
.settlement-create__review-destination {
  display: grid;
  gap: 5px;
  min-inline-size: 0;
  overflow-wrap: anywhere;
}
.settlement-create__review-heading strong {
  color: rgb(var(--v-theme-secondary));
  font-size: 1rem;
}
.settlement-create__review-heading span,
.settlement-create__review-heading small {
  font-size: 0.8125rem;
  color: rgba(var(--v-theme-on-surface), 0.65);
}
.settlement-create__review-heading > .v-btn {
  justify-self: end;
}
.settlement-create__review-total {
  display: grid;
  gap: 8px;
  padding: 20px;
  border-radius: 10px;
  background: rgba(var(--v-theme-secondary), 0.05);
  color: rgb(var(--v-theme-secondary));
  font-size: 0.875rem;
}
.settlement-create__review-total strong {
  font-size: 2rem;
  font-variant-numeric: tabular-nums;
}
.settlement-create__review-breakdown {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  margin: 0;
}
.settlement-create__review-breakdown > div {
  display: grid;
  gap: 6px;
  padding-inline: 20px;
}
.settlement-create__review-breakdown > div + div {
  border-inline-start: 1px solid rgba(var(--v-theme-secondary), 0.12);
}
.settlement-create__review-breakdown dt {
  font-size: 0.8125rem;
  color: rgba(var(--v-theme-on-surface), 0.65);
}
.settlement-create__review-breakdown dd {
  margin: 0;
  color: rgb(var(--v-theme-secondary));
  font-size: 1.125rem;
  font-weight: 600;
}
.settlement-create__review-notes {
  border-block-start: 1px solid rgba(var(--v-theme-secondary), 0.1);
  padding-block-start: 16px;
  display: grid;
  gap: 6px;
}
.settlement-create__review-notes > span {
  font-size: 0.8125rem;
  font-weight: 600;
}

@media (max-width: 600px) {
  .settlement-create__review-heading {
    grid-template-columns: 48px minmax(0, 1fr);
  }
  .settlement-create__review-heading > .v-btn {
    grid-column: 2;
    justify-self: start;
  }
  .settlement-create__details {
    grid-template-columns: minmax(0, 1fr);
  }
  .settlement-create__header {
    flex-wrap: wrap;
  }

  .settlement-create__steps {
    order: 3;
    inline-size: 100%;
    margin: 0;
    justify-content: flex-end;
  }
}
</style>
