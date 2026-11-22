<script lang="ts" setup>
import type { RouteLocationRaw } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import BlockInfo from '@/modules/core/components/BlockInfo.vue'
import Money from '@/modules/core/components/Money.vue'
import PageStateWrapper from '@/modules/core/components/PageStateWrapper.vue'
import TableEnum from '@/modules/core/components/Table/Partials/TableEnum.vue'
import { useStockMovement } from '@/modules/inventory/composables/stockMovement.ts'
import CorrectionDialog from './Partials/CorrectionDialog.vue'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const { user, can } = useAuth()
const { getShowData } = useStockMovement()

const loading = ref(false)
const isNotFound = ref(false)
const item = ref<Record<string, any> | null>(null)
const correctionDialog = ref(false)

async function loadItem(showLoader = true): Promise<void> {
  if (showLoader) loading.value = true

  try {
    const response = await getShowData(Number(route.params.id))
    if (response.status === 200) {
      item.value = response.data
      isNotFound.value = false
    } else if (response.status === 404) {
      isNotFound.value = true
    }
  } finally {
    if (showLoader) loading.value = false
  }
}

function openMovement(id?: number): void {
  if (!id || !can('admin.stock_movements.show')) return

  router.push({
    name: 'admin.stock_movements.show',
    params: { id },
  } as unknown as RouteLocationRaw)
}

async function handleCorrected(): Promise<void> {
  correctionDialog.value = false
  await loadItem(false)
}

onBeforeMount(() => loadItem())
</script>

<template>
  <PageStateWrapper
    :loading="loading"
    :not-found="isNotFound"
  >
    <div
      v-if="item"
      class="d-flex flex-wrap justify-end gap-3 mb-4"
    >
      <VBtn
        v-if="item.original_movement"
        color="primary"
        prepend-icon="tabler-arrow-back-up"
        variant="tonal"
        @click="openMovement(item.original_movement.id)"
      >
        {{ t('inventory::stock_movements.show.original_movement') }}
        #{{ item.original_movement.id }}
      </VBtn>

      <VBtn
        v-for="movement in item.reversals"
        :key="movement.id"
        color="primary"
        prepend-icon="tabler-arrow-forward-up"
        variant="tonal"
        @click="openMovement(movement.id)"
      >
        {{ t('inventory::stock_movements.show.reversal') }} #{{ movement.id }}
      </VBtn>

      <VBtn
        v-if="can('admin.stock_movements.correct') && !item.reversals?.length"
        color="warning"
        prepend-icon="tabler-replace"
        variant="tonal"
        @click="correctionDialog = true"
      >
        {{ t('inventory::stock_movements.actions.correct') }}
      </VBtn>
    </div>

    <VRow v-if="item">
      <VCol
        cols="12"
        lg="8"
      >
        <VCard>
          <VCardTitle>{{ t('inventory::stock_movements.show.details') }}</VCardTitle>

          <VCardText>
            <VRow>
              <VCol
                v-if="!user?.assigned_to_branch"
                cols="12"
                md="4"
              >
                <BlockInfo
                  :title="t('inventory::stock_movements.show.branch')"
                  :value="item.branch?.name"
                />
              </VCol>

              <VCol
                cols="12"
                md="4"
              >
                <BlockInfo
                  :title="t('inventory::stock_movements.show.ingredient')"
                  :value="item.ingredient?.name"
                />
              </VCol>

              <VCol
                cols="12"
                md="4"
              >
                <BlockInfo :title="t('inventory::stock_movements.show.type')">
                  <TableEnum
                    column="type"
                    :item="item"
                  />
                </BlockInfo>
              </VCol>

              <VCol
                cols="12"
                md="4"
              >
                <BlockInfo
                  :title="t('inventory::stock_movements.show.quantity')"
                  :value="item.display_quantity"
                />
              </VCol>

              <VCol
                cols="12"
                md="4"
              >
                <BlockInfo :title="t('inventory::stock_movements.show.unit_cost')">
                  <Money
                    v-if="item.unit_cost"
                    :money="item.unit_cost"
                  />
                </BlockInfo>
              </VCol>

              <VCol
                cols="12"
                md="4"
              >
                <BlockInfo :title="t('inventory::stock_movements.show.total_cost')">
                  <Money
                    v-if="item.total_cost"
                    :money="item.total_cost"
                  />
                </BlockInfo>
              </VCol>

              <VCol
                cols="12"
                md="4"
              >
                <BlockInfo
                  :title="t('inventory::stock_movements.show.occurred_at')"
                  :value="item.occurred_at"
                />
              </VCol>

              <VCol
                cols="12"
                md="4"
              >
                <BlockInfo
                  :title="t('inventory::stock_movements.show.committed_at')"
                  :value="item.committed_at"
                />
              </VCol>

              <VCol cols="12">
                <BlockInfo
                  :title="t('inventory::stock_movements.show.note')"
                  :value="item.note"
                />
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        lg="4"
      >
        <VCard>
          <VCardTitle>{{ t('inventory::stock_movements.show.audit') }}</VCardTitle>

          <VCardText>
            <VRow>
              <VCol
                cols="12"
                md="6"
              >
                <BlockInfo
                  class="mb-4"
                  :title="t('inventory::stock_movements.show.source_type')"
                  :value="item.source?.type"
                />
              </VCol>

              <VCol
                cols="12"
                md="6"
              >
                <BlockInfo
                  class="mb-4"
                  :title="t('inventory::stock_movements.show.source_name')"
                  :value="item.source?.name"
                />
              </VCol>

              <VCol
                cols="12"
                md="6"
              >
                <BlockInfo
                  class="mb-4"
                  :title="t('inventory::stock_movements.show.accounting_status')"
                >
                  <TableEnum
                    v-if="item.accounting_status"
                    column="accounting_status"
                    :item="item"
                  />
                  <span v-else>-</span>
                </BlockInfo>
              </VCol>

              <VCol
                cols="12"
                md="6"
              >
                <BlockInfo
                  class="mb-4"
                  :title="t('admin::admin.table.created_by')"
                  :value="item.created_by?.name"
                />
              </VCol>

              <VCol
                cols="12"
                md="6"
              >
                <BlockInfo
                  class="mb-4"
                  :title="t('inventory::stock_movements.show.created_at')"
                  :value="item.created_at"
                />
              </VCol>

              <VCol
                cols="12"
                md="6"
              >
                <BlockInfo
                  :title="t('inventory::stock_movements.show.updated_at')"
                  :value="item.updated_at"
                />
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <CorrectionDialog
      v-if="item && can('admin.stock_movements.correct')"
      v-model="correctionDialog"
      :movement="item"
      @corrected="handleCorrected"
    />
  </PageStateWrapper>
</template>
