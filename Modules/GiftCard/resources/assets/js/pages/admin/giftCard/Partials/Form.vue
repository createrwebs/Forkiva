<script lang="ts" setup>
import { useRouter, type RouteLocationRaw } from 'vue-router'
import { onBeforeMount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import BaseForm from '@/modules/core/components/Form/BaseForm.vue'
import DateTimePicker from '@/modules/core/components/Form/DateTimePicker.vue'
import { useForm } from '@/modules/core/composables/form.ts'
import { generateUuid } from '@/modules/core/utils/uuid.ts'
import { useGiftCard } from '@/modules/giftcard/composables/giftCard.ts'

const props = defineProps<{
  item?: Record<string, any> | null
  action: 'update' | 'create'
}>()

const { t } = useI18n()
const { user } = useAuth()
const { getFormMeta, store, update } = useGiftCard()
const router = useRouter()

const form = useForm({
  code: props.item?.code || null,
  branch_id: user?.assigned_to_branch ? user.branch_id : props.item?.branch?.id || null,
  customer_id: props.item?.customer?.id || null,
  status: props.item?.status?.id || 'active',
  initial_balance: props.item?.initial_balance?.amount || null,
  currency: props.item?.currency || null,
  expiry_date: props.item?.expiry_date || null,
  notes: props.item?.notes || null,
  funding_type: 'promotional',
  tender_method: 'cash',
  financial_account_key: null as string | null,
  idempotency_key: generateUuid(),
})

type GiftCardFormMeta = {
  branches: Array<{ id: number; name: string; currency?: string | null }>
  customers: Array<{ id: number; name: string }>
  statuses: Array<{ id: string; name: string }>
  default_currency: string | null
  funding_types: Array<{ id: string; name: string }>
  tender_methods: Array<{ id: string; name: string }>
  accounting_available: boolean
  financial_accounts: Array<{ key: string; label: string; currency: string }>
}

const meta = ref<GiftCardFormMeta>({
  branches: [],
  customers: [],
  statuses: [],
  default_currency: null,
  funding_types: [],
  tender_methods: [],
  accounting_available: false,
  financial_accounts: [],
})

function syncCurrencyFromBranch() {
  if (props.action === 'update') return
  const selectedBranch = meta.value.branches.find(
    (branch: Record<string, any>) => branch.id === form.state.branch_id,
  )
  form.state.currency =
    selectedBranch?.currency || meta.value.default_currency || props.item?.currency || null
}

/** Update card details without resubmitting issuance amounts or funding instructions. */
async function submit() {
  const { code, branch_id, customer_id, status, expiry_date, notes } = form.state
  const details = { code, branch_id, customer_id, status, expiry_date, notes }
  if (
    !form.loading.value &&
    (await form.submit(() =>
      props.action === 'create' ? store(form.state) : update(props.item?.id, details),
    ))
  ) {
    await router.push({ name: 'admin.gift_cards.index' } as unknown as RouteLocationRaw)
  }
}

async function loadMeta(branchId?: number | null) {
  try {
    const response = (await getFormMeta(branchId)).data.body
    meta.value = {
      branches: response.branches || [],
      customers: response.customers || [],
      statuses: response.statuses || [],
      default_currency: response.default_currency || null,
      funding_types: response.funding_types || [],
      tender_methods: (response.tender_methods || []).filter(
        (method: Record<string, any>) => method.id !== 'gift_card',
      ),
      accounting_available: Boolean(response.accounting_available),
      financial_accounts: response.financial_accounts || [],
    }
    syncCurrencyFromBranch()
  } catch {
    /* empty */
  }
}

onBeforeMount(async () => {
  await loadMeta(form.state.branch_id)
})

watch(
  () => form.state.branch_id,
  async (branchId) => {
    form.state.financial_account_key = null
    await loadMeta(branchId)
  },
)
</script>

<template>
  <BaseForm
    :action="action"
    :loading="form.loading.value"
    resource="gift_cards"
    @submit="submit"
  >
    <VRow>
      <VCol
        cols="12"
        lg="7"
      >
        <VCard class="mb-6">
          <VCardTitle class="border-b pb-2 mb-4 d-flex align-center gap-2 font-weight-bold text-h6">
            <VIcon icon="tabler-gift-card" />
            {{ t('giftcard::gift_cards.gift_card') }}
          </VCardTitle>

          <VCardText>
            <VRow>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="form.state.code"
                  :error="!!form.errors.value?.code"
                  :error-messages="form.errors.value?.code"
                  :hint="
                    action === 'create'
                      ? `${t('admin::admin.optional')} • Auto generated if left empty`
                      : t('admin::admin.optional')
                  "
                  :label="t('giftcard::attributes.gift_cards.code')"
                  persistent-hint
                  :placeholder="
                    action === 'create' ? t('giftcard::attributes.gift_cards.code') : undefined
                  "
                />
              </VCol>

              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="form.state.initial_balance"
                  v-decimal-en
                  :readonly="action === 'update'"
                  :error="!!form.errors.value?.initial_balance"
                  :error-messages="form.errors.value?.initial_balance"
                  :label="t('giftcard::attributes.gift_cards.initial_balance')"
                  :prefix="form.state.currency || ''"
                />
              </VCol>

              <VCol
                cols="12"
                md="6"
              >
                <DateTimePicker
                  v-model="form.state.expiry_date"
                  :error="!!form.errors.value?.expiry_date"
                  :error-messages="form.errors.value?.expiry_date"
                  :label="t('giftcard::attributes.gift_cards.expiry_date')"
                />
              </VCol>
            </VRow>
          </VCardText>
        </VCard>

        <VCard>
          <VCardTitle class="border-b pb-2 mb-4 d-flex align-center gap-2 font-weight-bold text-h6">
            <VIcon icon="tabler-notes" />
            {{ t('giftcard::attributes.gift_cards.notes') }}
          </VCardTitle>

          <VCardText>
            <VRow>
              <VCol cols="12">
                <VTextarea
                  v-model="form.state.notes"
                  :error="!!form.errors.value?.notes"
                  :error-messages="form.errors.value?.notes"
                  :label="t('giftcard::attributes.gift_cards.notes')"
                  rows="4"
                />
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        lg="5"
      >
        <VCard class="mb-6">
          <VCardTitle class="border-b pb-2 mb-4 d-flex align-center gap-2 font-weight-bold text-h6">
            <VIcon icon="tabler-adjustments" />
            {{ t('admin::admin.settings') }}
          </VCardTitle>

          <VCardText>
            <VRow>
              <VCol
                v-if="!user?.assigned_to_branch"
                cols="12"
                md="6"
              >
                <VSelect
                  v-model="form.state.branch_id"
                  clearable
                  :error="!!form.errors.value?.branch_id"
                  :error-messages="form.errors.value?.branch_id"
                  item-title="name"
                  item-value="id"
                  :items="meta.branches"
                  :label="t('giftcard::attributes.gift_cards.branch_id')"
                />
              </VCol>

              <VCol
                v-if="action === 'create'"
                cols="12"
                md="6"
              >
                <VSelect
                  v-model="form.state.funding_type"
                  :error="!!form.errors.value?.funding_type"
                  :error-messages="form.errors.value?.funding_type"
                  item-title="name"
                  item-value="id"
                  :items="meta.funding_types"
                  :label="t('giftcard::attributes.gift_cards.funding_type')"
                />
              </VCol>

              <VCol
                v-if="action === 'create' && form.state.funding_type === 'paid'"
                cols="12"
                md="6"
              >
                <VSelect
                  v-model="form.state.tender_method"
                  :error="!!form.errors.value?.tender_method"
                  :error-messages="form.errors.value?.tender_method"
                  item-title="name"
                  item-value="id"
                  :items="meta.tender_methods"
                  :label="t('giftcard::attributes.gift_cards.tender_method')"
                />
              </VCol>

              <VCol
                v-if="
                  action === 'create' &&
                  form.state.funding_type === 'paid' &&
                  meta.accounting_available
                "
                cols="12"
                md="6"
              >
                <VSelect
                  v-model="form.state.financial_account_key"
                  :disabled="!form.state.branch_id"
                  :error="!!form.errors.value?.financial_account_key"
                  :error-messages="form.errors.value?.financial_account_key"
                  item-title="label"
                  item-value="key"
                  :items="meta.financial_accounts"
                  :label="t('giftcard::attributes.gift_cards.financial_account_key')"
                />
              </VCol>

              <VCol
                cols="12"
                md="6"
              >
                <VSelect
                  v-model="form.state.customer_id"
                  clearable
                  :error="!!form.errors.value?.customer_id"
                  :error-messages="form.errors.value?.customer_id"
                  item-title="name"
                  item-value="id"
                  :items="meta.customers"
                  :label="t('giftcard::attributes.gift_cards.customer_id')"
                />
              </VCol>

              <VCol
                cols="12"
                md="6"
              >
                <VSelect
                  v-model="form.state.status"
                  :error="!!form.errors.value?.status"
                  :error-messages="form.errors.value?.status"
                  item-title="name"
                  item-value="id"
                  :items="meta.statuses"
                  :label="t('giftcard::attributes.gift_cards.status')"
                />
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </BaseForm>
</template>
