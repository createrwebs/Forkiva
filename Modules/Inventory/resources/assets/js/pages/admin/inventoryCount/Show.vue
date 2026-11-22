<script lang="ts" setup>
import type { RouteLocationRaw } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import BlockInfo from '@/modules/core/components/BlockInfo.vue'
import PageStateWrapper from '@/modules/core/components/PageStateWrapper.vue'
import TableStatus from '@/modules/core/components/Table/Partials/TableStatus.vue'
import { useConfirmDialog } from '@/modules/core/composables/confirmDialog.ts'
import { useInventoryCount } from '@/modules/inventory/composables/inventoryCount.ts'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const toast = useToast()
const { getInventoryCount, approveInventoryCount, cancelInventoryCount } = useInventoryCount()
const loading = ref(false)
const isNotFound = ref(false)
const item = ref<Record<string, any> | null>(null)
const activeAction = ref<'approve' | 'cancel' | null>(null)

async function loadItem(showLoader = true): Promise<void> {
  if (showLoader) loading.value = true
  try {
    const response = await getInventoryCount(Number(route.params.id))
    if (response.status === 200) item.value = response.data
    else if (response.status === 404) isNotFound.value = true
  } finally {
    if (showLoader) loading.value = false
  }
}

async function runAction(
  action: 'approve' | 'cancel',
  callback: () => Promise<any>,
): Promise<void> {
  activeAction.value = action
  try {
    const response = await callback()
    if (response?.data?.message) toast.success(response.data.message)
    await loadItem(false)
  } finally {
    activeAction.value = null
  }
}

async function approve(): Promise<void> {
  if (!item.value?.allow_approve) return
  const confirmed = await useConfirmDialog({
    message: t('inventory::inventory_counts.confirm.approve'),
    confirmButtonText: t('inventory::inventory_counts.actions.approve'),
  })
  if (confirmed) await runAction('approve', () => approveInventoryCount(item.value!.id))
}

async function cancel(): Promise<void> {
  if (!item.value?.allow_cancel) return
  const confirmed = await useConfirmDialog({
    message: t('inventory::inventory_counts.confirm.cancel'),
    confirmButtonText: t('inventory::inventory_counts.actions.cancel'),
    confirmColor: 'error',
  })
  if (confirmed) await runAction('cancel', () => cancelInventoryCount(item.value!.id))
}

function edit(): void {
  if (!item.value?.allow_edit) return
  router.push({
    name: 'admin.inventory_counts.edit',
    params: { id: item.value.id },
  } as unknown as RouteLocationRaw)
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
        v-if="item.allow_edit"
        :disabled="!!activeAction"
        prepend-icon="tabler-pencil"
        variant="tonal"
        @click="edit"
      >
        {{ t('inventory::inventory_counts.actions.edit') }}
      </VBtn>

      <VBtn
        v-if="item.allow_approve"
        color="success"
        :disabled="activeAction === 'cancel'"
        :loading="activeAction === 'approve'"
        prepend-icon="tabler-check"
        @click="approve"
      >
        {{ t('inventory::inventory_counts.actions.approve') }}
      </VBtn>

      <VBtn
        v-if="item.allow_cancel"
        color="error"
        :disabled="activeAction === 'approve'"
        :loading="activeAction === 'cancel'"
        prepend-icon="tabler-ban"
        variant="tonal"
        @click="cancel"
      >
        {{ t('inventory::inventory_counts.actions.cancel') }}
      </VBtn>
    </div>

    <VRow v-if="item">
      <VCol
        cols="12"
        lg="8"
      >
        <VCard>
          <VCardTitle>{{ t('inventory::inventory_counts.show.details') }}</VCardTitle>

          <VCardText>
            <VRow>
              <VCol
                cols="12"
                md="4"
              >
                <BlockInfo
                  :title="t('inventory::inventory_counts.table.reference_number')"
                  :value="item.reference_number"
                />
              </VCol>

              <VCol
                cols="12"
                md="4"
              >
                <BlockInfo
                  :title="t('inventory::attributes.inventory_counts.branch_id')"
                  :value="item.branch?.name"
                />
              </VCol>

              <VCol
                cols="12"
                md="4"
              >
                <BlockInfo
                  :title="t('inventory::inventory_counts.table.count_date')"
                  :value="item.count_date"
                />
              </VCol>

              <VCol
                cols="12"
                md="4"
              >
                <BlockInfo :title="t('inventory::inventory_counts.table.status')">
                  <TableStatus :item="item" />
                </BlockInfo>
              </VCol>

              <VCol
                cols="12"
                md="4"
              >
                <BlockInfo
                  :title="t('inventory::inventory_counts.table.approved_by')"
                  :value="item.approved_by"
                />
              </VCol>

              <VCol
                cols="12"
                md="4"
              >
                <BlockInfo
                  :title="t('inventory::inventory_counts.table.approved_at')"
                  :value="item.approved_at"
                />
              </VCol>

              <VCol cols="12">
                <BlockInfo
                  :title="t('inventory::attributes.inventory_counts.notes')"
                  :value="item.notes"
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
          <VCardTitle>{{ t('inventory::inventory_counts.show.audit') }}</VCardTitle>

          <VCardText>
            <BlockInfo
              class="mb-4"
              :title="t('admin::admin.table.created_by')"
              :value="item.created_by"
            />

            <BlockInfo
              class="mb-4"
              :title="t('admin::admin.table.created_at')"
              :value="item.created_at"
            />

            <BlockInfo
              :title="t('admin::admin.table.updated_at')"
              :value="item.updated_at"
            />
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12">
        <VCard>
          <VCardTitle>{{ t('inventory::inventory_counts.show.items') }}</VCardTitle>

          <VTable>
            <thead>
              <tr>
                <th>{{ t('inventory::inventory_counts.show.ingredient') }}</th>
                <th>{{ t('inventory::inventory_counts.show.expected') }}</th>
                <th>{{ t('inventory::inventory_counts.show.counted') }}</th>
                <th>{{ t('inventory::inventory_counts.show.variance') }}</th>
                <th>
                  {{ t('inventory::inventory_counts.show.snapshot_movement') }}
                </th>
                <th>
                  {{ t('inventory::inventory_counts.show.adjustment_movement') }}
                </th>
              </tr>
            </thead>

            <tbody>
              <tr
                v-for="countItem in item.items"
                :key="countItem.id"
              >
                <td>{{ countItem.ingredient?.name }}</td>
                <td>
                  {{ countItem.expected_quantity }}
                  {{ countItem.unit?.symbol || '' }}
                </td>
                <td>
                  {{ countItem.counted_quantity }}
                  {{ countItem.unit?.symbol || '' }}
                </td>
                <td>
                  {{ countItem.variance_quantity }}
                  {{ countItem.unit?.symbol || '' }}
                </td>
                <td>{{ countItem.snapshot_movement_id || '-' }}</td>
                <td>{{ countItem.generated_movement?.id || '-' }}</td>
              </tr>
            </tbody>
          </VTable>
        </VCard>
      </VCol>
    </VRow>
  </PageStateWrapper>
</template>
