<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import DatePicker from '@/modules/core/components/Form/DatePicker.vue'
import { useGiftCardTransaction } from '@/modules/giftcard/composables/giftCardTransaction.ts'
import {
  financialActionFieldVisibility,
  giftCardActionGeneralError,
  type GiftCardFinancialAction,
  useRetainedIdempotencyKey,
} from '@/modules/giftcard/utils/giftCardLifecycle.ts'

const props = defineProps<{
  modelValue: boolean
  card: Record<string, any>
  action: string
  meta: Record<string, any>
}>()
const emit = defineEmits(['update:modelValue', 'saved'])
const { t } = useI18n()
const { getByGiftCard, submitFinancialAction } = useGiftCardTransaction()
const loading = ref(false)
const issues = ref<any[]>([])
const errors = ref<Record<string, string[]>>({})
const generalError = ref('')
const idempotency = useRetainedIdempotencyKey()
const form = reactive({
  amount: null as string | null,
  funding_type: 'promotional',
  tender_method: 'cash',
  financial_account_key: null as string | null,
  original_transaction_id: null as number | null,
  economic_date: new Date().toISOString().slice(0, 10),
  notes: '',
})

const isRecharge = computed(() => props.action === 'recharge')
const isCashOut = computed(() => props.action === 'cash_out')
const isIssueVoid = computed(() => props.action === 'issue_void')
const fields = computed(() =>
  financialActionFieldVisibility(
    props.action as GiftCardFinancialAction,
    form.funding_type,
    Boolean(props.meta.accounting_available),
  ),
)

function resetForm() {
  form.amount = null
  form.funding_type = 'promotional'
  form.tender_method = 'cash'
  form.financial_account_key = null
  form.original_transaction_id = null
  form.economic_date = new Date().toISOString().slice(0, 10)
  form.notes = ''
  issues.value = []
}

watch(
  () => props.modelValue,
  async (open) => {
    if (open) {
      resetForm()
      errors.value = {}
      generalError.value = ''
    }
    if (!open || !isIssueVoid.value) return
    issues.value = (await getByGiftCard(props.card.id)).filter(
      (transaction: any) => transaction.type?.id === 'purchase' && !transaction.reversed_at,
    )
  },
)

function close() {
  // Vue's v-model contract requires the camel-cased model prop in the emitted event.

  if (!loading.value) emit('update:modelValue', false)
}

async function submit() {
  loading.value = true
  errors.value = {}
  generalError.value = ''
  const payload: Record<string, any> = {
    type: props.action,
    economic_date: form.economic_date,
    notes: form.notes,
    idempotency_key: idempotency.key.value,
  }
  if (!isIssueVoid.value) payload.amount = form.amount
  if (isRecharge.value) payload.funding_type = form.funding_type
  if (isRecharge.value || isCashOut.value) payload.tender_method = form.tender_method
  if (fields.value.financialAccount) payload.financial_account_key = form.financial_account_key
  if (isIssueVoid.value) payload.original_transaction_id = form.original_transaction_id
  const response = await submitFinancialAction(props.card.id, payload)
  loading.value = false
  if (response.status >= 200 && response.status < 300) {
    idempotency.regenerateAfterSuccess()
    emit('saved')
    // Vue's v-model contract requires the camel-cased model prop in the emitted event.

    emit('update:modelValue', false)
  } else {
    errors.value = response.errors || {}
    generalError.value = giftCardActionGeneralError(
      errors.value,
      response.message,
      t('core::errors.an_unexpected_error_occurred'),
    )
  }
}
</script>

<template>
  <VDialog
    max-width="680"
    :model-value="modelValue"
    persistent
    @update:model-value="close"
  >
    <VCard>
      <VCardTitle class="d-flex align-center gap-2 border-b">
        <VIcon
          color="primary"
          icon="tabler-gift-card"
        />
        {{ t(`giftcard::gift_card_transactions.actions.${action}`) }}
      </VCardTitle>

      <VCardText class="pt-6">
        <VAlert
          v-if="generalError"
          class="mb-4"
          closable
          type="error"
          variant="tonal"
          @click:close="generalError = ''"
        >
          {{ generalError }}
        </VAlert>

        <VRow>
          <VCol
            v-if="fields.amount"
            cols="12"
            md="6"
          >
            <VTextField
              v-model="form.amount"
              v-decimal-en
              :error-messages="errors.amount"
              :label="t('giftcard::gift_card_transactions.table.amount')"
              :prefix="card.currency"
            />
          </VCol>

          <VCol
            v-if="fields.fundingType"
            cols="12"
            md="6"
          >
            <VSelect
              v-model="form.funding_type"
              :error-messages="errors.funding_type"
              item-title="name"
              item-value="id"
              :items="meta.funding_types || []"
              :label="t('giftcard::attributes.gift_cards.funding_type')"
            />
          </VCol>

          <VCol
            v-if="fields.tenderMethod"
            cols="12"
            md="6"
          >
            <VSelect
              v-model="form.tender_method"
              :error-messages="errors.tender_method"
              item-title="name"
              item-value="id"
              :items="meta.tender_methods || []"
              :label="t('giftcard::attributes.gift_cards.tender_method')"
            />
          </VCol>

          <VCol
            v-if="fields.financialAccount"
            cols="12"
            md="6"
          >
            <VSelect
              v-model="form.financial_account_key"
              :error-messages="errors.financial_account_key"
              item-title="label"
              item-value="key"
              :items="meta.financial_accounts || []"
              :label="t('giftcard::attributes.gift_cards.financial_account_key')"
            />
          </VCol>

          <VCol
            v-if="fields.originalIssue"
            cols="12"
          >
            <VSelect
              v-model="form.original_transaction_id"
              :error-messages="errors.original_transaction_id"
              item-title="uuid"
              item-value="id"
              :items="issues"
              :label="t('giftcard::gift_card_transactions.actions.original_issue')"
            />
          </VCol>

          <VCol
            cols="12"
            :md="isIssueVoid ? 12 : 6"
          >
            <DatePicker
              v-model="form.economic_date"
              :error-messages="errors.economic_date"
              :label="t('giftcard::gift_card_transactions.actions.economic_date')"
            />
          </VCol>

          <VCol cols="12">
            <VTextarea
              v-model="form.notes"
              :error-messages="errors.notes"
              :label="t('giftcard::attributes.gift_card_transactions.notes')"
              rows="3"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VCardActions class="justify-end border-t pa-4">
        <VBtn
          :disabled="loading"
          variant="text"
          @click="close"
        >
          {{ t('admin::admin.buttons.cancel') }}
        </VBtn>

        <VBtn
          color="primary"
          :loading="loading"
          @click="submit"
        >
          {{ t('admin::admin.buttons.save') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
