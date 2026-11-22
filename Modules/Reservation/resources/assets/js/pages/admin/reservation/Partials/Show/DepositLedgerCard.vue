<script lang="ts" setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import TableEnum from '@/modules/core/components/Table/Partials/TableEnum.vue'
import { formatPrice } from '@/modules/core/utils/formatters.ts'
import DepositTransactionDetailsDialog from '@/modules/reservation/pages/admin/reservation/Dialogs/DepositTransactionDetailsDialog.vue'
import {
  canApplyReservationDeposit,
  canCollectReservationDeposit,
  canForfeitReservationDeposit,
  canRefundReservationDeposit,
  reservationDepositBalance,
} from '@/modules/reservation/utils/depositActions.ts'

type DepositAction = 'collection' | 'application' | 'refund' | 'forfeiture'

const props = defineProps<{
  canCreate: boolean
  canEdit: boolean
  canShow: boolean
  item: Record<string, any>
}>()
const emit = defineEmits<{
  action: [action: DepositAction]
  reverse: [transaction: Record<string, any>]
}>()
const { t } = useI18n()
const showDetailsDialog = ref(false)
const selectedTransactionId = ref<number | string | null>(null)

const transactions = computed(() => props.item.deposit_transactions || [])
const canCollect = computed(() => props.canCreate && canCollectReservationDeposit(props.item))
const canApply = computed(() => props.canCreate && canApplyReservationDeposit(props.item))
const canRefund = computed(() => props.canCreate && canRefundReservationDeposit(props.item))
const canForfeit = computed(() => props.canCreate && canForfeitReservationDeposit(props.item))

function amountValue(value: any): number {
  return reservationDepositBalance({ deposit_paid_amount: value })
}

function formatAmount(value: any, currency?: string): string {
  return formatPrice(amountValue(value), currency || props.item.deposit_currency, 3)
}

function openDetails(transaction: Record<string, any>) {
  if (!props.canShow) {
    return
  }

  selectedTransactionId.value = transaction.id
  showDetailsDialog.value = true
}
</script>

<template>
  <VCard>
    <VCardTitle class="d-flex flex-wrap align-center justify-space-between gap-3 border-b">
      <span class="d-flex align-center gap-2">
        <VIcon
          color="primary"
          icon="tabler-receipt-2"
        />
        {{ t('reservation::frontend.deposit_ledger') }}
      </span>

      <div class="d-flex flex-wrap gap-2">
        <VBtn
          v-if="canCollect"
          color="primary"
          prepend-icon="tabler-plus"
          size="small"
          variant="tonal"
          @click="emit('action', 'collection')"
        >
          {{ t('reservation::frontend.deposit_actions.collection') }}
        </VBtn>

        <VBtn
          v-if="canApply"
          color="info"
          prepend-icon="tabler-file-invoice"
          size="small"
          variant="tonal"
          @click="emit('action', 'application')"
        >
          {{ t('reservation::frontend.deposit_actions.application') }}
        </VBtn>

        <VBtn
          v-if="canRefund"
          color="success"
          prepend-icon="tabler-cash-banknote"
          size="small"
          variant="tonal"
          @click="emit('action', 'refund')"
        >
          {{ t('reservation::frontend.deposit_actions.refund') }}
        </VBtn>

        <VBtn
          v-if="canForfeit"
          color="warning"
          prepend-icon="tabler-lock"
          size="small"
          variant="tonal"
          @click="emit('action', 'forfeiture')"
        >
          {{ t('reservation::frontend.deposit_actions.forfeiture') }}
        </VBtn>
      </div>
    </VCardTitle>

    <VCardText class="pa-0">
      <VAlert
        v-if="transactions.length === 0"
        class="ma-4"
        type="info"
        variant="tonal"
      >
        {{ t('reservation::frontend.no_deposit_transactions') }}
      </VAlert>

      <VTable v-else>
        <thead>
          <tr>
            <th>
              {{ t('reservation::attributes.reservation_deposit_transactions.type') }}
            </th>
            <th>
              {{ t('reservation::attributes.reservation_deposit_transactions.economic_date') }}
            </th>
            <th>
              {{ t('reservation::attributes.reservation_deposit_transactions.amount') }}
            </th>
            <th>
              {{ t('reservation::attributes.reservation_deposit_transactions.balance_after') }}
            </th>
            <th>
              {{ t('reservation::attributes.reservation_deposit_transactions.note') }}
            </th>
            <th class="text-end">{{ t('admin::admin.table.actions') }}</th>
          </tr>
        </thead>

        <tbody>
          <tr
            v-for="transaction in transactions"
            :key="transaction.id"
            :aria-label="
              canShow
                ? t('reservation::frontend.deposit_view_transaction', {
                    id: transaction.id,
                  })
                : undefined
            "
            :class="{ 'deposit-ledger-row--clickable': canShow }"
            :tabindex="canShow ? 0 : undefined"
            @click="openDetails(transaction)"
            @keydown.enter.prevent="openDetails(transaction)"
            @keydown.space.prevent="openDetails(transaction)"
          >
            <td>
              <TableEnum
                column="type"
                :item="transaction"
              />
            </td>

            <td>{{ transaction.economic_date }}</td>
            <td>{{ formatAmount(transaction.amount, transaction.currency) }}</td>
            <td>{{ formatAmount(transaction.balance_after, transaction.currency) }}</td>
            <td class="text-medium-emphasis">{{ transaction.note || '—' }}</td>

            <td class="text-end">
              <VBtn
                v-if="canEdit && transaction.can_reverse"
                :aria-label="`${t('reservation::frontend.deposit_actions.reverse')} #${transaction.id}`"
                color="warning"
                icon="tabler-arrow-back-up"
                size="small"
                :title="`${t('reservation::frontend.deposit_actions.reverse')} #${transaction.id}`"
                variant="text"
                @click.stop="emit('reverse', transaction)"
              />
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCardText>

    <DepositTransactionDetailsDialog
      v-if="selectedTransactionId !== null"
      v-model="showDetailsDialog"
      :transaction-id="selectedTransactionId"
    />
  </VCard>
</template>

<style lang="scss" scoped>
.deposit-ledger-row--clickable {
  cursor: pointer;
  transition: background-color 120ms ease;
}

.deposit-ledger-row--clickable:hover,
.deposit-ledger-row--clickable:focus-visible {
  background: rgba(var(--v-theme-primary), 0.06);
}

.deposit-ledger-row--clickable:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: -2px;
}
</style>
