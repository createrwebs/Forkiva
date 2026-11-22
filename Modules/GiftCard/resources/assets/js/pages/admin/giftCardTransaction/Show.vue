<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import PageStateWrapper from '@/modules/core/components/PageStateWrapper.vue'
import TableEnum from '@/modules/core/components/Table/Partials/TableEnum.vue'
import TableMoney from '@/modules/core/components/Table/Partials/TableMoney.vue'
import { useGiftCardTransaction } from '@/modules/giftcard/composables/giftCardTransaction.ts'
import ReverseTransactionDialog from '@/modules/giftcard/pages/admin/giftCardTransaction/Partials/ReverseTransactionDialog.vue'
import {
  canReverseGiftCardTransaction,
  giftCardTransactionAuditRows,
} from '@/modules/giftcard/utils/giftCardLifecycle.ts'

const { getShowData } = useGiftCardTransaction()
const { can } = useAuth()
const route = useRoute()
const router = useRouter()
const { t } = useI18n()

const loading = ref(false)
const isNotFound = ref(false)
const item = ref<Record<string, any> | null>(null)
const reversalDialog = ref(false)
const auditRows = computed(() => giftCardTransactionAuditRows(item.value || {}))
const canReverse = computed(() => item.value && canReverseGiftCardTransaction(item.value, can))

function openGiftCard() {
  if (!item.value?.gift_card?.id) return

  router.push({
    name: 'admin.gift_cards.show',
    params: { id: item.value.gift_card.id },
  })
}

function openTransaction(transactionId?: number | string) {
  if (!transactionId || !can('admin.gift_card_transactions.show')) return

  router.push({
    name: 'admin.gift_card_transactions.show',
    params: { id: transactionId },
  })
}

async function reversalSaved() {
  if (!item.value) return

  item.value = (await getShowData(item.value.id)).data
}

onBeforeMount(async () => {
  loading.value = true
  const response = await getShowData((route.params as Record<string, any>).id)
  if (response.status === 200) {
    item.value = response.data
  } else if (response.status === 404) {
    isNotFound.value = true
  }
  loading.value = false
})
</script>

<template>
  <PageStateWrapper
    :loading="loading"
    :not-found="isNotFound"
  >
    <div
      v-if="item && canReverse"
      class="d-flex justify-end mb-4"
    >
      <VBtn
        color="warning"
        prepend-icon="tabler-arrow-back-up"
        variant="tonal"
        @click="reversalDialog = true"
      >
        {{ t('giftcard::gift_card_transactions.actions.reverse') }}
      </VBtn>
    </div>

    <VRow v-if="item">
      <VCol
        cols="12"
        md="8"
      >
        <VRow>
          <VCol cols="12">
            <VCard>
              <VCardTitle class="border-b d-flex align-center gap-2">
                <VIcon icon="tabler-arrows-exchange" />
                {{ t('giftcard::gift_card_transactions.show.details') }}
              </VCardTitle>

              <VCardText class="pt-6">
                <VRow>
                  <VCol
                    cols="12"
                    md="4"
                  >
                    <div class="text-caption text-medium-emphasis mb-1">
                      {{ t('giftcard::gift_card_transactions.show.uuid') }}
                    </div>

                    <div class="font-weight-medium text-break">
                      {{ item.uuid }}
                    </div>
                  </VCol>

                  <VCol
                    cols="12"
                    md="4"
                  >
                    <div class="text-caption text-medium-emphasis mb-1">
                      {{ t('giftcard::gift_card_transactions.table.gift_card') }}
                    </div>

                    <VBtn
                      v-if="item.gift_card?.code"
                      class="px-0 text-none"
                      color="primary"
                      variant="text"
                      @click="openGiftCard"
                    >
                      {{ item.gift_card.code }}
                    </VBtn>

                    <div
                      v-else
                      class="font-weight-medium"
                    >
                      -
                    </div>
                  </VCol>

                  <VCol
                    cols="12"
                    md="4"
                  >
                    <div class="text-caption text-medium-emphasis mb-1">
                      {{ t('giftcard::gift_card_transactions.table.type') }}
                    </div>

                    <TableEnum
                      column="type"
                      :item="item"
                    />
                  </VCol>

                  <VCol
                    cols="12"
                    md="4"
                  >
                    <div class="text-caption text-medium-emphasis mb-1">
                      {{ t('giftcard::gift_card_transactions.table.amount') }}
                    </div>

                    <TableMoney
                      column="amount"
                      :item="item"
                    />
                  </VCol>

                  <VCol
                    cols="12"
                    md="4"
                  >
                    <div class="text-caption text-medium-emphasis mb-1">
                      {{ t('giftcard::gift_card_transactions.table.balance_before') }}
                    </div>

                    <TableMoney
                      column="balance_before"
                      :item="item"
                    />
                  </VCol>

                  <VCol
                    cols="12"
                    md="4"
                  >
                    <div class="text-caption text-medium-emphasis mb-1">
                      {{ t('giftcard::gift_card_transactions.table.balance_after') }}
                    </div>

                    <TableMoney
                      column="balance_after"
                      :item="item"
                    />
                  </VCol>

                  <VCol
                    cols="12"
                    md="6"
                  >
                    <div class="text-caption text-medium-emphasis mb-1">
                      {{ t('giftcard::gift_card_transactions.table.order') }}
                    </div>

                    <div class="font-weight-medium">
                      {{ item.order?.reference_no || '-' }}
                    </div>
                  </VCol>

                  <VCol
                    cols="12"
                    md="6"
                  >
                    <div class="text-caption text-medium-emphasis mb-1">
                      {{ t('giftcard::attributes.gift_card_transactions.notes') }}
                    </div>

                    <div class="font-weight-medium text-break">
                      {{ item.notes || '-' }}
                    </div>
                  </VCol>
                </VRow>
              </VCardText>
            </VCard>
          </VCol>

          <VCol cols="12">
            <VCard>
              <VCardTitle class="border-b d-flex align-center gap-2">
                <VIcon icon="tabler-shield-check" />
                {{ t('giftcard::gift_card_transactions.show.audit_trail') }}
              </VCardTitle>

              <VCardText class="pt-6">
                <VRow>
                  <VCol
                    cols="12"
                    md="4"
                  >
                    <div class="text-caption text-medium-emphasis mb-1">
                      {{ t('giftcard::gift_card_transactions.table.user') }}
                    </div>

                    <div class="font-weight-medium">
                      {{ item.createdBy?.name || '-' }}
                    </div>
                  </VCol>

                  <VCol
                    cols="12"
                    md="4"
                  >
                    <div class="text-caption text-medium-emphasis mb-1">
                      {{ t('giftcard::gift_card_transactions.table.transaction_at') }}
                    </div>

                    <div class="font-weight-medium">
                      {{ item.transaction_at }}
                    </div>
                  </VCol>

                  <VCol
                    cols="12"
                    md="4"
                  >
                    <div class="text-caption text-medium-emphasis mb-1">
                      {{ t('admin::admin.table.created_at') }}
                    </div>

                    <div class="font-weight-medium">{{ item.created_at }}</div>
                  </VCol>

                  <VCol
                    v-for="audit in auditRows"
                    :key="audit.key"
                    cols="12"
                    md="4"
                  >
                    <div class="text-caption text-medium-emphasis mb-1">
                      {{ t(`giftcard::gift_card_transactions.audit.${audit.key}`) }}
                    </div>

                    <VBtn
                      v-if="
                        audit.key === 'reversed_by_transaction' &&
                        item.reversed_by_transaction?.id &&
                        can('admin.gift_card_transactions.show')
                      "
                      class="px-0 text-none"
                      color="primary"
                      variant="text"
                      @click="openTransaction(item.reversed_by_transaction.id)"
                    >
                      {{ audit.value }}
                    </VBtn>

                    <div
                      v-else
                      class="font-weight-medium text-break"
                    >
                      {{ audit.value }}
                    </div>
                  </VCol>
                </VRow>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </VCol>

      <VCol
        cols="12"
        lg="4"
      >
        <VCard class="mb-4">
          <VCardTitle class="border-b d-flex align-center gap-2">
            <VIcon icon="tabler-building-bank" />
            {{ t('giftcard::gift_card_transactions.show.financial_context') }}
          </VCardTitle>

          <VCardText class="pt-6">
            <VRow>
              <VCol
                cols="12"
                lg="12"
                sm="6"
              >
                <div class="text-caption text-medium-emphasis mb-1">
                  {{ t('giftcard::gift_card_transactions.audit.liability_branch') }}
                </div>

                <div class="font-weight-medium">
                  {{ item.liability_branch?.name || '-' }}
                </div>
              </VCol>

              <VCol
                cols="12"
                lg="12"
                sm="6"
              >
                <div class="text-caption text-medium-emphasis mb-1">
                  {{ t('giftcard::gift_card_transactions.table.branch') }}
                </div>

                <div class="font-weight-medium">{{ item.branch?.name || '-' }}</div>
              </VCol>

              <VCol
                cols="12"
                lg="12"
                sm="6"
              >
                <div class="text-caption text-medium-emphasis mb-1">
                  {{ t('giftcard::gift_card_transactions.table.currency') }}
                </div>

                <div class="font-weight-medium">{{ item.currency || '-' }}</div>
              </VCol>

              <VCol
                cols="12"
                lg="12"
                sm="6"
              >
                <div class="text-caption text-medium-emphasis mb-1">
                  {{ t('giftcard::gift_card_transactions.audit.financial_account') }}
                </div>

                <div class="font-weight-medium text-break">
                  {{ item.financial_account_snapshot?.label || item.financial_account_key || '-' }}
                </div>
              </VCol>

              <VCol
                cols="12"
                lg="12"
                sm="6"
              >
                <div class="text-caption text-medium-emphasis mb-1">
                  {{ t('giftcard::gift_card_transactions.actions.economic_date') }}
                </div>

                <div class="font-weight-medium">
                  {{ item.economic_date || '-' }}
                </div>
              </VCol>

              <VCol
                cols="12"
                lg="12"
                sm="6"
              >
                <div class="text-caption text-medium-emphasis mb-1">
                  {{ t('giftcard::attributes.gift_card_transactions.amount_in_order_currency') }}
                </div>

                <div class="font-weight-medium">
                  {{
                    item.amount_in_order_currency?.formatted || item.amount_in_order_currency || '-'
                  }}
                </div>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <ReverseTransactionDialog
      v-model="reversalDialog"
      :transaction="item"
      @saved="reversalSaved"
    />
  </PageStateWrapper>
</template>
