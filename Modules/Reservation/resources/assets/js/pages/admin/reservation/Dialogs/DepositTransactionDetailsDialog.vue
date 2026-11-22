<script lang="ts" setup>
import { getApiErrorMessage } from '@/modules/core/api/errors'

import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BlockInfo from '@/modules/core/components/BlockInfo.vue'
import Money from '@/modules/core/components/Money.vue'
import TableEnum from '@/modules/core/components/Table/Partials/TableEnum.vue'
import { showReservationDepositTransaction } from '@/modules/reservation/api/reservation.api.ts'

const props = defineProps<{
  transactionId: number | string
}>()
const dialogModel = defineModel<boolean>({ required: true })
const { t } = useI18n()
const loading = ref(false)
const errorMessage = ref('')
const item = ref<Record<string, any> | null>(null)
let requestSequence = 0

const financialAccount = computed(() => {
  const account = item.value?.financial_account

  if (!account) {
    return '—'
  }

  return [account.account_code, account.name].filter(Boolean).join(' - ') || account.key || '—'
})

function entityReference(
  entity: Record<string, any> | null | undefined,
  referenceKey: string,
): string {
  if (!entity?.id && !entity?.[referenceKey]) {
    return '—'
  }

  return entity?.[referenceKey] || `#${entity.id}`
}

function transactionReference(transaction: Record<string, any> | null | undefined): string {
  if (!transaction) {
    return '—'
  }

  return (
    [transaction.id ? `#${transaction.id}` : null, transaction.type?.name]
      .filter(Boolean)
      .join(' · ') || '—'
  )
}

async function loadTransaction() {
  const sequence = ++requestSequence
  loading.value = true
  errorMessage.value = ''
  item.value = null

  try {
    const response = await showReservationDepositTransaction(props.transactionId)

    if (sequence === requestSequence) {
      item.value = response.data.body
    }
  } catch (error: unknown) {
    if (sequence === requestSequence) {
      errorMessage.value =
        getApiErrorMessage(error) || t('core::errors.an_unexpected_error_occurred')
    }
  } finally {
    if (sequence === requestSequence) {
      loading.value = false
    }
  }
}

function close() {
  if (!loading.value) {
    dialogModel.value = false
  }
}

watch(
  [dialogModel, () => props.transactionId],
  ([open]) => {
    if (open) {
      void loadTransaction()
    }
  },
  { immediate: true },
)

onBeforeUnmount(() => {
  requestSequence++
})
</script>

<template>
  <VDialog
    v-model="dialogModel"
    max-width="760"
    persistent
    scrollable
  >
    <VCard class="deposit-transaction-details">
      <VCardTitle class="d-flex align-center justify-space-between gap-3 border-b">
        <span class="d-flex align-center gap-2">
          <VIcon
            color="primary"
            icon="tabler-receipt-2"
          />
          {{ t('reservation::frontend.deposit_transaction_details') }}
        </span>

        <VBtn
          :aria-label="t('admin::admin.buttons.close')"
          :disabled="loading"
          icon="tabler-x"
          size="small"
          variant="text"
          @click="close"
        />
      </VCardTitle>

      <VCardText class="pt-6">
        <div
          v-if="loading"
          class="deposit-transaction-details__loader d-flex align-center justify-center"
        >
          <VProgressCircular
            color="primary"
            indeterminate
            size="48"
            width="4"
          />
        </div>

        <VAlert
          v-else-if="errorMessage"
          type="error"
          variant="tonal"
        >
          {{ errorMessage }}
        </VAlert>

        <VRow v-else-if="item">
          <VCol
            cols="12"
            md="4"
            sm="6"
          >
            <BlockInfo :title="t('reservation::attributes.reservation_deposit_transactions.type')">
              <TableEnum
                column="type"
                :item="item"
              />
            </BlockInfo>
          </VCol>

          <VCol
            cols="12"
            md="4"
            sm="6"
          >
            <BlockInfo
              :title="t('reservation::attributes.reservation_deposit_transactions.economic_date')"
            >
              {{ item.economic_date || '—' }}
            </BlockInfo>
          </VCol>

          <VCol
            cols="12"
            md="4"
            sm="6"
          >
            <BlockInfo
              :title="t('reservation::attributes.reservation_deposit_transactions.amount')"
            >
              <Money :money="item.amount" />
            </BlockInfo>
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <BlockInfo
              :title="t('reservation::attributes.reservation_deposit_transactions.balance_before')"
            >
              <Money :money="item.balance_before" />
            </BlockInfo>
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <BlockInfo
              :title="t('reservation::attributes.reservation_deposit_transactions.balance_after')"
            >
              <Money :money="item.balance_after" />
            </BlockInfo>
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <BlockInfo
              :title="t('reservation::attributes.reservation_deposit_transactions.tender_method')"
            >
              <TableEnum
                v-if="item.tender_method"
                column="tender_method"
                :item="item"
              />
              <template v-else>—</template>
            </BlockInfo>
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <BlockInfo
              :title="
                t('reservation::attributes.reservation_deposit_transactions.financial_account_key')
              "
            >
              {{ financialAccount }}
            </BlockInfo>
          </VCol>

          <VCol
            cols="12"
            md="4"
            sm="6"
          >
            <BlockInfo :title="t('reservation::frontend.deposit_order')">
              {{ entityReference(item.order, 'reference_no') }}
            </BlockInfo>
          </VCol>

          <VCol
            cols="12"
            md="4"
            sm="6"
          >
            <BlockInfo
              :title="t('reservation::attributes.reservation_deposit_transactions.invoice_id')"
            >
              {{ entityReference(item.invoice, 'invoice_number') }}
            </BlockInfo>
          </VCol>

          <VCol
            cols="12"
            md="4"
            sm="6"
          >
            <BlockInfo :title="t('reservation::frontend.deposit_payment')">
              {{ entityReference(item.payment, 'transaction_id') }}
            </BlockInfo>
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <BlockInfo
              :title="t('reservation::attributes.reservation_deposit_transactions.currency')"
            >
              {{ item.currency || '—' }}
            </BlockInfo>
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <BlockInfo
              :title="t('reservation::attributes.reservation_deposit_transactions.currency_rate')"
            >
              {{ item.currency_rate || '—' }}
            </BlockInfo>
          </VCol>

          <VCol
            v-if="item.original_transaction"
            cols="12"
            sm="6"
          >
            <BlockInfo :title="t('reservation::frontend.deposit_original_transaction')">
              {{ transactionReference(item.original_transaction) }}
            </BlockInfo>
          </VCol>

          <VCol
            v-if="item.reversed_by_transaction"
            cols="12"
            sm="6"
          >
            <BlockInfo :title="t('reservation::frontend.deposit_reversed_by_transaction')">
              {{ transactionReference(item.reversed_by_transaction) }}
            </BlockInfo>
          </VCol>

          <VCol
            v-if="item.reversed_at"
            cols="12"
            sm="6"
          >
            <BlockInfo :title="t('reservation::frontend.deposit_reversed_at')">
              {{ item.reversed_at }}
            </BlockInfo>
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <BlockInfo :title="t('admin::admin.table.created_by')">
              {{ item.created_by?.name || '—' }}
            </BlockInfo>
          </VCol>

          <VCol
            cols="12"
            sm="6"
          >
            <BlockInfo :title="t('admin::admin.table.created_at')">
              {{ item.created_at || '—' }}
            </BlockInfo>
          </VCol>

          <VCol cols="12">
            <BlockInfo :title="t('reservation::attributes.reservation_deposit_transactions.note')">
              <span class="text-pre-wrap">{{ item.note || '—' }}</span>
            </BlockInfo>
          </VCol>
        </VRow>
      </VCardText>

      <VCardActions class="justify-end border-t pa-4">
        <VBtn
          :disabled="loading"
          variant="text"
          @click="close"
        >
          {{ t('admin::admin.buttons.close') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.deposit-transaction-details {
  max-block-size: calc(100dvh - 48px);
}

.deposit-transaction-details__loader {
  min-block-size: 280px;
}
</style>
