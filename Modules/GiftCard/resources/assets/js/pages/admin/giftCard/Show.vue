<script lang="ts" setup>
import type { TableHeader } from '@/modules/core/contracts/Table.ts'
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import PageStateWrapper from '@/modules/core/components/PageStateWrapper.vue'
import TableEnum from '@/modules/core/components/Table/Partials/TableEnum.vue'
import TableMoney from '@/modules/core/components/Table/Partials/TableMoney.vue'
import TableStatus from '@/modules/core/components/Table/Partials/TableStatus.vue'
import SmartDataTable from '@/modules/core/components/Table/SmartDataTable.vue'
import { useGiftCard } from '@/modules/giftcard/composables/giftCard.ts'
import FinancialActionDialog from '@/modules/giftcard/pages/admin/giftCard/Partials/FinancialActionDialog.vue'
import {
  availableGiftCardFinancialActions,
  type GiftCardFinancialAction,
  giftCardFinancialActionDefinitions,
} from '@/modules/giftcard/utils/giftCardLifecycle.ts'

const { getShowData, getFormMeta } = useGiftCard()
const { can } = useAuth()
const route = useRoute()
const { t, locale } = useI18n()

const loading = ref(false)
const isNotFound = ref(false)
const item = ref<Record<string, any> | null>(null)
const actionDialog = ref(false)
const selectedAction = ref('recharge')
const activeTab = ref('information')
const tableKey = ref(0)
const actionMeta = ref<Record<string, any>>({})

const actions = computed(() => {
  if (!item.value) return []
  const available = availableGiftCardFinancialActions(item.value, can)

  return giftCardFinancialActionDefinitions.filter((action) => available.includes(action.key))
})

function openAction(action: GiftCardFinancialAction) {
  if (!item.value || !availableGiftCardFinancialActions(item.value, can).includes(action)) {
    return
  }

  selectedAction.value = action
  actionDialog.value = true
}

async function load() {
  const response = await getShowData((route.params as Record<string, any>).id)
  if (response.status === 200) item.value = response.data
  else if (response.status === 404) isNotFound.value = true
}

async function actionSaved() {
  await load()
  tableKey.value++
}

const transactionsHeaders: TableHeader[] = [
  { title: t('giftcard::gift_card_transactions.table.type'), value: 'type', sortable: true },
  { title: t('giftcard::gift_card_transactions.table.amount'), value: 'amount', sortable: true },
  {
    title: t('giftcard::gift_card_transactions.table.balance_before'),
    value: 'balance_before',
    sortable: true,
  },
  {
    title: t('giftcard::gift_card_transactions.table.balance_after'),
    value: 'balance_after',
    sortable: true,
  },
  {
    title: t('giftcard::gift_card_transactions.table.order'),
    value: 'order.reference_no',
    sortable: false,
  },
  {
    title: t('giftcard::gift_card_transactions.table.user'),
    value: 'createdBy.name',
    sortable_key: 'created_by',
    sortable: true,
  },
  {
    title: t('giftcard::gift_card_transactions.table.transaction_at'),
    value: 'transaction_at',
    sortable: true,
  },
]

const transactionCellComponents: Record<string, any> = {
  type: {
    component: TableEnum,
    props: { column: 'type' },
  },
  amount: {
    component: TableMoney,
    props: { column: 'amount' },
  },
  balance_before: {
    component: TableMoney,
    props: { column: 'balance_before' },
  },
  balance_after: {
    component: TableMoney,
    props: { column: 'balance_after' },
  },
}

const translatedBatchName = computed(() => {
  const name = item.value?.batch?.name
  if (!name) return null
  if (typeof name === 'string') return name
  return name[locale.value] || name.en || Object.values(name)[0]
})

onBeforeMount(async () => {
  loading.value = true
  await load()
  const liabilityBranchId = item.value?.liability_branch?.id || item.value?.branch?.id
  if (liabilityBranchId) actionMeta.value = (await getFormMeta(liabilityBranchId)).data.body
  loading.value = false
})
</script>

<template>
  <PageStateWrapper
    :loading="loading"
    :not-found="isNotFound"
  >
    <div
      v-if="item && actions.length > 0"
      class="d-flex justify-end flex-wrap gap-2 mb-4"
    >
      <VBtn
        v-for="action in actions"
        :key="action.key"
        :color="action.color"
        :prepend-icon="action.icon"
        variant="tonal"
        @click="openAction(action.key)"
      >
        {{ t(`giftcard::gift_card_transactions.actions.${action.key}`) }}
      </VBtn>
    </div>

    <VRow v-if="item">
      <VCol
        cols="12"
        lg="3"
      >
        <VCard>
          <VList
            class="pa-3"
            density="comfortable"
            nav
          >
            <VListItem
              :active="activeTab === 'information'"
              color="primary"
              prepend-icon="tabler-info-circle"
              :title="t('giftcard::gift_cards.gift_card_information')"
              @click="activeTab = 'information'"
            />

            <VListItem
              :active="activeTab === 'transactions'"
              color="primary"
              prepend-icon="tabler-receipt-2"
              :title="t('giftcard::gift_card_transactions.gift_card_transactions')"
              @click="activeTab = 'transactions'"
            />
          </VList>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        lg="9"
      >
        <VWindow v-model="activeTab">
          <VWindowItem value="information">
            <VCard>
              <VCardTitle
                class="border-b pb-2 mb-4 d-flex align-center gap-2 font-weight-bold text-h6"
              >
                <VIcon icon="tabler-gift-card" />
                {{ t('giftcard::gift_cards.gift_card') }}
              </VCardTitle>

              <VCardText>
                <VRow>
                  <VCol
                    cols="12"
                    md="4"
                  >
                    <div class="text-caption text-medium-emphasis mb-1">
                      {{ t('giftcard::attributes.gift_cards.code') }}
                    </div>

                    <div class="font-weight-medium">{{ item.code }}</div>
                  </VCol>

                  <VCol
                    cols="12"
                    md="4"
                  >
                    <div class="text-caption text-medium-emphasis mb-1">
                      {{ t('giftcard::attributes.gift_cards.branch_id') }}
                    </div>

                    <div class="font-weight-medium">
                      {{ item.liability_branch?.name || item.branch?.name || '-' }}
                    </div>
                  </VCol>

                  <VCol
                    cols="12"
                    md="4"
                  >
                    <div class="text-caption text-medium-emphasis mb-1">
                      {{ t('giftcard::attributes.gift_cards.customer_id') }}
                    </div>

                    <div class="font-weight-medium">
                      {{ item.customer?.name || '-' }}
                    </div>
                  </VCol>

                  <VCol
                    cols="12"
                    md="4"
                  >
                    <div class="text-caption text-medium-emphasis mb-1">
                      {{ t('giftcard::attributes.gift_cards.gift_card_batch_id') }}
                    </div>

                    <div class="font-weight-medium">
                      {{ translatedBatchName || '-' }}
                    </div>
                  </VCol>

                  <VCol
                    cols="12"
                    md="4"
                  >
                    <div class="text-caption text-medium-emphasis mb-1">
                      {{ t('giftcard::attributes.gift_cards.initial_balance') }}
                    </div>

                    <div class="font-weight-medium">
                      {{ item.initial_balance?.formatted || '-' }}
                    </div>
                  </VCol>

                  <VCol
                    cols="12"
                    md="4"
                  >
                    <div class="text-caption text-medium-emphasis mb-1">
                      {{ t('giftcard::attributes.gift_cards.current_balance') }}
                    </div>

                    <div class="font-weight-medium">
                      {{ item.current_balance?.formatted || '-' }}
                    </div>
                  </VCol>

                  <VCol
                    cols="12"
                    md="4"
                  >
                    <div class="text-caption text-medium-emphasis mb-1">
                      {{ t('giftcard::attributes.gift_cards.expiry_date') }}
                    </div>

                    <div class="font-weight-medium">
                      {{ item.expiry_date || '-' }}
                    </div>
                  </VCol>

                  <VCol
                    cols="12"
                    md="4"
                  >
                    <div class="text-caption text-medium-emphasis mb-1">
                      {{ t('giftcard::attributes.gift_cards.currency') }}
                    </div>

                    <div class="font-weight-medium">
                      {{ item.currency || '-' }}
                    </div>
                  </VCol>

                  <VCol
                    cols="12"
                    md="4"
                  >
                    <div class="text-caption text-medium-emphasis mb-1">
                      {{ t('giftcard::attributes.gift_cards.scope') }}
                    </div>

                    <TableEnum
                      column="scope"
                      :item="item"
                    />
                  </VCol>

                  <VCol
                    cols="12"
                    md="4"
                  >
                    <div class="text-caption text-medium-emphasis mb-1">
                      {{ t('giftcard::attributes.gift_cards.status') }}
                    </div>

                    <TableStatus :item="item" />
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
                    cols="12"
                    md="4"
                  >
                    <div class="text-caption text-medium-emphasis mb-1">
                      {{ t('admin::admin.table.updated_at') }}
                    </div>

                    <div class="font-weight-medium">{{ item.updated_at }}</div>
                  </VCol>

                  <VCol cols="12">
                    <div class="text-caption text-medium-emphasis mb-1">
                      {{ t('giftcard::attributes.gift_cards.notes') }}
                    </div>

                    <div class="font-weight-medium">
                      {{ item.notes || '-' }}
                    </div>
                  </VCol>
                </VRow>
              </VCardText>
            </VCard>
          </VWindowItem>

          <VWindowItem value="transactions">
            <SmartDataTable
              :key="tableKey"
              api-uri="/v1/gift-card-transactions"
              :cell-components="transactionCellComponents"
              :default-filters="{ gift_card_id: item.id }"
              :except-filters="['branch_id']"
              :headers="transactionsHeaders"
              module="giftcard"
              name="gift_card_transaction"
              resource="gift_card_transactions"
              :title="t('giftcard::gift_card_transactions.gift_card_transactions')"
            />
          </VWindowItem>
        </VWindow>
      </VCol>
    </VRow>

    <FinancialActionDialog
      v-if="item"
      v-model="actionDialog"
      :action="selectedAction"
      :card="item"
      :meta="actionMeta"
      @saved="actionSaved"
    />
  </PageStateWrapper>
</template>
