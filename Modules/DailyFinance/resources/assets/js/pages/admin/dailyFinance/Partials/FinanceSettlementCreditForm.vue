<script lang="ts" setup>
import { computed, ref, useId, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import DatePicker from '@/modules/core/components/Form/DatePicker.vue'
import {
  cancelSettlement,
  receiveSettlement,
} from '@/modules/dailyfinance/api/paymentSettlement.api'
import { usePaymentSettlementMutation } from '@/modules/dailyfinance/composables/usePaymentSettlementMutation'
import type {
  CancelSettlement,
  PaymentSettlement,
  ReceiveSettlement,
} from '@/modules/dailyfinance/contracts/paymentSettlement'
import {
  settlementDecimal,
  settlementFormatted,
  settlementUnits,
} from '@/modules/dailyfinance/utils/settlementMoney'

const props = defineProps<{ record: PaymentSettlement; mode: 'receive' | 'cancel' }>()
const emit = defineEmits<{ close: []; saved: [record: PaymentSettlement] }>()
const { t, locale } = useI18n()
const titleId = useId()
const open = ref(true)
const activator = document.activeElement
const amount = ref(
  settlementDecimal(
    settlementUnits(props.record.remaining_decimal, props.record.expected.precision) ?? 0n,
    props.record.expected.precision,
  ),
)
const receivedDate = ref(props.record.today)
const reference = ref('')
const final = ref(false)
const reason = ref('')
const acknowledged = ref(false)
const requestKey = crypto.randomUUID()
let accepted: PaymentSettlement | null = null
const { saving, uncertain, error, errors, save } = usePaymentSettlementMutation<
  ReceiveSettlement | CancelSettlement
>((payload) =>
  'amount' in payload
    ? receiveSettlement(props.record.id, payload)
    : cancelSettlement(props.record.id, payload),
)
const busy = computed(() => saving.value || uncertain.value)
const precision = props.record.expected.precision
const units = computed(() => settlementUnits(amount.value, precision))
const expected = settlementUnits(props.record.expected_decimal, precision) ?? 0n
const actual = settlementUnits(props.record.actual_decimal, precision) ?? 0n
const total = computed(() => actual + (units.value ?? 0n))
const isFinal = computed(() => final.value || total.value >= expected)
const difference = computed(() => total.value - expected)
const needsReason = computed(
  () => props.mode === 'cancel' || (isFinal.value && difference.value !== 0n),
)
const valid = computed(
  () =>
    acknowledged.value &&
    (!needsReason.value || reason.value.trim().length >= 3) &&
    (props.mode === 'cancel' ||
      (units.value !== null &&
        units.value > 0n &&
        reference.value.trim().length > 0 &&
        receivedDate.value &&
        receivedDate.value <= props.record.today)),
)
const amountError = computed(() =>
  amount.value && units.value === null
    ? t('dailyfinance::payment_settlements.errors.precision')
    : '',
)

function money(value: bigint) {
  return settlementFormatted(value, props.record.currency, precision, locale.value)
}

async function submit() {
  if (!uncertain.value && !valid.value) return
  const payload: ReceiveSettlement | CancelSettlement =
    props.mode === 'cancel'
      ? { branch_id: props.record.branch_id, cancellation_reason: reason.value.trim() }
      : {
          branch_id: props.record.branch_id,
          amount: settlementDecimal(units.value ?? 0n, precision),
          received_date: receivedDate.value,
          reference: reference.value.trim(),
          is_final: final.value,
          variance_reason: reason.value.trim() || null,
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

watch([amount, receivedDate, reference, final, reason], () => {
  acknowledged.value = false
})
</script>

<template>
  <VDialog
    v-model="open"
    :aria-labelledby="titleId"
    :persistent="busy"
    max-width="640"
    scrollable
    @after-leave="afterLeave"
  >
    <VCard class="settlement-dialog">
      <header class="settlement-dialog__header">
        <div>
          <h2 :id="titleId">
            {{ t(`dailyfinance::payment_settlements.${mode === 'cancel' ? 'cancel' : 'receive'}`) }}
          </h2>
          <span class="settlement-dialog__context">
            {{ record.reference }} · {{ record.destination_name }}
          </span>
        </div>
        <VBtn
          icon="tabler-x"
          variant="text"
          color="on-surface"
          :disabled="busy"
          :aria-label="t('dailyfinance::payment_settlements.dismiss')"
          @click="open = false"
        />
      </header>
      <VCardText class="settlement-dialog__body">
        <VAlert
          v-if="error || uncertain"
          type="warning"
          variant="tonal"
          role="alert"
        >
          {{ uncertain ? t('dailyfinance::payment_settlements.uncertain') : error }}
        </VAlert>
        <p class="settlement-dialog__note">
          {{
            t(
              `dailyfinance::payment_settlements.${mode === 'cancel' ? 'cancel_hint' : 'receive_hint'}`,
            )
          }}
        </p>
        <template v-if="mode === 'receive'">
          <div class="settlement-credit__balance">
            <VIcon
              icon="tabler-building-bank"
              size="24"
            />
            <span>
              {{ t('dailyfinance::payment_settlements.remaining') }}
              <strong>
                <bdi>{{ record.remaining.formatted }}</bdi>
              </strong>
            </span>
            <small>
              {{ t('dailyfinance::payment_settlements.actual') }}
              <bdi>{{ record.actual.formatted }}</bdi>
            </small>
          </div>
          <div class="settlement-dialog__fields">
            <VTextField
              v-model="amount"
              :label="t('dailyfinance::payment_settlements.amount')"
              :suffix="record.currency"
              inputmode="decimal"
              :error-messages="errors.amount || amountError"
              :disabled="busy"
              hide-details="auto"
            />
            <DatePicker
              v-model="receivedDate"
              :max="record.today"
              :label="t('dailyfinance::payment_settlements.received_date')"
              :error-messages="errors.received_date"
              :disabled="busy"
              hide-details="auto"
            />
            <VTextField
              v-model="reference"
              class="settlement-dialog__fields--full"
              :label="t('dailyfinance::payment_settlements.bank_reference')"
              :error-messages="errors.reference"
              maxlength="120"
              :disabled="busy"
              hide-details="auto"
            />
          </div>
          <VCheckbox
            :model-value="isFinal"
            :label="t('dailyfinance::payment_settlements.is_final')"
            :disabled="busy || total >= expected"
            hide-details
            @update:model-value="final = !!$event"
          />
          <p
            v-if="total >= expected"
            class="settlement-dialog__note"
          >
            {{ t('dailyfinance::payment_settlements.auto_final') }}
          </p>
          <dl class="settlement-dialog__review">
            <div>
              <dt>{{ t('dailyfinance::payment_settlements.expected') }}</dt>
              <dd>
                <bdi>{{ record.expected.formatted }}</bdi>
              </dd>
            </div>
            <div>
              <dt>{{ t('dailyfinance::payment_settlements.after_credit') }}</dt>
              <dd>
                <bdi>{{ money(total) }}</bdi>
              </dd>
            </div>
            <div class="settlement-dialog__review-total">
              <dt>
                {{ t(`dailyfinance::payment_settlements.${isFinal ? 'variance' : 'remaining'}`) }}
              </dt>
              <dd>
                <bdi>{{ money(isFinal ? difference : expected - total) }}</bdi>
              </dd>
            </div>
          </dl>
        </template>
        <VTextarea
          v-if="needsReason"
          v-model="reason"
          :label="
            t(
              `dailyfinance::payment_settlements.${mode === 'cancel' ? 'cancellation_reason' : 'variance_reason'}`,
            )
          "
          :error-messages="errors.cancellation_reason || errors.variance_reason"
          rows="2"
          auto-grow
          max-rows="4"
          maxlength="1000"
          :disabled="busy"
          hide-details="auto"
        />
        <VCheckbox
          v-model="acknowledged"
          class="settlement-dialog__confirmation"
          :label="
            t(
              `dailyfinance::payment_settlements.${mode === 'cancel' ? 'cancel_acknowledge' : 'receipt_acknowledge'}`,
            )
          "
          :disabled="busy"
          hide-details
        />
      </VCardText>
      <footer class="settlement-dialog__footer">
        <span>
          {{
            t(
              mode === 'receive'
                ? 'dailyfinance::payment_settlements.amount'
                : 'dailyfinance::payment_settlements.expected',
            )
          }}
          <strong>
            <bdi>{{ mode === 'receive' ? money(units ?? 0n) : record.expected.formatted }}</bdi>
          </strong>
        </span>
        <div class="settlement-dialog__actions">
          <VBtn
            :disabled="busy"
            color="secondary"
            variant="text"
            @click="open = false"
          >
            {{ t('dailyfinance::payment_settlements.dismiss') }}
          </VBtn>
          <VBtn
            :loading="saving"
            :disabled="uncertain ? saving : !valid || saving"
            :color="mode === 'cancel' ? 'error' : 'primary'"
            :prepend-icon="mode === 'cancel' ? 'tabler-circle-x' : 'tabler-check'"
            @click="submit"
          >
            {{
              t(
                `dailyfinance::payment_settlements.${uncertain ? 'retry' : mode === 'cancel' ? 'confirm_cancel' : 'confirm_receipt'}`,
              )
            }}
          </VBtn>
        </div>
      </footer>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
@use './settlement-dialog';
.settlement-credit__balance {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px;
  border-radius: 10px;
  background: rgba(var(--v-theme-secondary), 0.05);
  color: rgb(var(--v-theme-secondary));
}
.settlement-credit__balance > span {
  display: grid;
  gap: 4px;
  font-size: 0.8125rem;
}
.settlement-credit__balance strong {
  font-size: 1.375rem;
}
.settlement-credit__balance small {
  margin-inline-start: auto;
  text-align: end;
  display: grid;
  gap: 6px;
  font-size: 0.75rem;
  color: rgba(var(--v-theme-on-surface), 0.65);
}
</style>
