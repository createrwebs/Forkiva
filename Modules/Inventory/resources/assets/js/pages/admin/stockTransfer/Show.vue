<script lang="ts" setup>
import type { RouteLocationRaw } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import BlockInfo from '@/modules/core/components/BlockInfo.vue'
import PageStateWrapper from '@/modules/core/components/PageStateWrapper.vue'
import TableStatus from '@/modules/core/components/Table/Partials/TableStatus.vue'
import { useConfirmDialog } from '@/modules/core/composables/confirmDialog.ts'
import { useStockTransfer } from '@/modules/inventory/composables/stockTransfer.ts'
import ReasonDialog from './Partials/ReasonDialog.vue'
import ReceiveDialog from './Partials/ReceiveDialog.vue'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const { can } = useAuth()
const toast = useToast()
const { getStockTransfer, approveStockTransfer, dispatchStockTransfer, showActionError } =
  useStockTransfer()
const loading = ref(false)
const isNotFound = ref(false)
const item = ref<Record<string, any> | null>(null)
const activeAction = ref<string | null>(null)
const receiveDialog = ref(false)
const reasonAction = ref<'close' | 'cancel' | 'void' | null>(null)
const decimalFormatter = new Intl.NumberFormat('en-US', {
  maximumFractionDigits: 3,
})

const lifecycleMeta: Record<string, { color: string; icon: string }> = {
  created: { color: 'primary', icon: 'tabler-plus' },
  approved: { color: 'success', icon: 'tabler-check' },
  dispatched: { color: 'info', icon: 'tabler-truck-delivery' },
  completed: { color: 'success', icon: 'tabler-circle-check' },
  cancelled: { color: 'error', icon: 'tabler-ban' },
  voided: { color: 'warning', icon: 'tabler-arrow-back-up' },
}

const lifecycle = computed(() => {
  if (!item.value) return []
  return [
    { key: 'created', actor: item.value.audit?.created_by, at: item.value.created_at },
    {
      key: 'approved',
      actor: item.value.audit?.approved_by,
      at: item.value.audit?.approved_at,
    },
    {
      key: 'dispatched',
      actor: item.value.audit?.dispatched_by,
      at: item.value.audit?.dispatched_at,
    },
    {
      key: 'completed',
      actor: item.value.audit?.completed_by,
      at: item.value.audit?.completed_at,
    },
    {
      key: 'cancelled',
      actor: item.value.audit?.cancelled_by,
      at: item.value.audit?.cancelled_at,
    },
    { key: 'voided', actor: item.value.audit?.voided_by, at: item.value.audit?.voided_at },
  ]
    .filter((entry) => entry.at)
    .map((entry) => ({
      ...entry,
      ...lifecycleMeta[entry.key],
    }))
})

async function loadItem(showLoader = true): Promise<void> {
  if (showLoader) loading.value = true
  try {
    const response = await getStockTransfer(Number(route.params.id))
    if (response.status === 200) item.value = response.data
    else if (response.status === 404) isNotFound.value = true
  } finally {
    if (showLoader) loading.value = false
  }
}

async function confirmedAction(
  action: 'approve' | 'dispatch',
  callback: () => Promise<any>,
): Promise<void> {
  const confirmed = await useConfirmDialog({
    message: t(`inventory::stock_transfers.confirm.${action}`),
    confirmButtonText: t(`inventory::stock_transfers.actions.${action}`),
  })
  if (!confirmed) return

  activeAction.value = action
  try {
    const response = await callback()
    if (response?.data?.message) toast.success(response.data.message)
    await loadItem(false)
  } catch (error: unknown) {
    showActionError(error)
  } finally {
    activeAction.value = null
  }
}

function edit(): void {
  if (!item.value?.allow_edit) return
  router.push({
    name: 'admin.stock_transfers.edit',
    params: { id: item.value.id },
  } as unknown as RouteLocationRaw)
}

function approve(): void {
  if (!item.value?.id) return
  void confirmedAction('approve', () => approveStockTransfer(item.value!.id))
}

function dispatch(): void {
  if (!item.value?.id) return
  void confirmedAction('dispatch', () => dispatchStockTransfer(item.value!.id))
}

function accountingStatus(status: Record<string, any> | null | undefined): string {
  return status?.name || '-'
}

function formatDecimal(value: unknown): string {
  if (value === null || value === undefined || value === '') return '-'
  const numeric = Number(value)

  return Number.isFinite(numeric) ? decimalFormatter.format(numeric) : String(value)
}

function formatQuantity(value: unknown, symbol?: string | null): string {
  return [formatDecimal(value), symbol].filter(Boolean).join(' ')
}

function movementIds(...ids: Array<number | string | null | undefined>): number[] {
  return ids.filter(Boolean).map(Number).filter(Number.isFinite)
}

function transferLine(id: number | string): Record<string, any> | undefined {
  return item.value?.items?.find((line: Record<string, any>) => Number(line.id) === Number(id))
}

function openMovement(id: number): void {
  if (!can('admin.stock_movements.show')) return
  router.push({
    name: 'admin.stock_movements.show',
    params: { id },
  } as unknown as RouteLocationRaw)
}

onBeforeMount(loadItem)
</script>

<template>
  <PageStateWrapper
    :loading="loading"
    :not-found="isNotFound"
  >
    <template v-if="item">
      <div class="d-flex flex-wrap justify-end gap-3 mb-4">
        <VBtn
          v-if="item.allow_edit"
          prepend-icon="tabler-pencil"
          variant="tonal"
          @click="edit"
        >
          {{ t('admin::resource.edit', { resource: '' }) }}
        </VBtn>

        <VBtn
          v-if="item.allow_approve"
          color="success"
          :loading="activeAction === 'approve'"
          prepend-icon="tabler-check"
          @click="approve"
        >
          {{ t('inventory::stock_transfers.actions.approve') }}
        </VBtn>

        <VBtn
          v-if="item.allow_dispatch"
          color="primary"
          :loading="activeAction === 'dispatch'"
          prepend-icon="tabler-truck-delivery"
          @click="dispatch"
        >
          {{ t('inventory::stock_transfers.actions.dispatch') }}
        </VBtn>

        <VBtn
          v-if="item.allow_receive"
          color="primary"
          prepend-icon="tabler-package-import"
          variant="tonal"
          @click="receiveDialog = true"
        >
          {{ t('inventory::stock_transfers.actions.receive') }}
        </VBtn>

        <VBtn
          v-if="item.allow_close"
          color="warning"
          prepend-icon="tabler-alert-triangle"
          variant="tonal"
          @click="reasonAction = 'close'"
        >
          {{ t('inventory::stock_transfers.actions.close') }}
        </VBtn>

        <VBtn
          v-if="item.allow_cancel"
          color="error"
          prepend-icon="tabler-ban"
          variant="tonal"
          @click="reasonAction = 'cancel'"
        >
          {{ t('inventory::stock_transfers.actions.cancel') }}
        </VBtn>

        <VBtn
          v-if="item.allow_void"
          color="error"
          prepend-icon="tabler-arrow-back-up"
          @click="reasonAction = 'void'"
        >
          {{ t('inventory::stock_transfers.actions.void') }}
        </VBtn>
      </div>

      <VRow>
        <VCol
          cols="12"
          lg="6"
        >
          <VCard>
            <VCardTitle>{{ t('inventory::stock_transfers.show.details') }}</VCardTitle>

            <VCardText>
              <VRow>
                <VCol
                  cols="12"
                  md="4"
                >
                  <BlockInfo
                    :title="t('inventory::stock_transfers.table.reference_number')"
                    :value="item.reference_number"
                  />
                </VCol>

                <VCol
                  cols="12"
                  md="4"
                >
                  <BlockInfo
                    :title="t('inventory::stock_transfers.table.source_branch')"
                    :value="item.source_branch?.name"
                  />
                </VCol>

                <VCol
                  cols="12"
                  md="4"
                >
                  <BlockInfo
                    :title="t('inventory::stock_transfers.table.destination_branch')"
                    :value="item.destination_branch?.name"
                  />
                </VCol>

                <VCol
                  cols="12"
                  md="4"
                >
                  <BlockInfo :title="t('inventory::stock_transfers.table.status')">
                    <TableStatus :item="item" />
                  </BlockInfo>
                </VCol>

                <VCol
                  cols="12"
                  md="4"
                >
                  <BlockInfo
                    :title="t('inventory::stock_transfers.table.expected_at')"
                    :value="item.expected_at"
                  />
                </VCol>

                <VCol
                  cols="12"
                  md="4"
                >
                  <BlockInfo
                    :title="t('inventory::attributes.stock_transfers.currency')"
                    :value="item.currency || '-'"
                  />
                </VCol>

                <VCol cols="12">
                  <BlockInfo
                    :title="t('inventory::attributes.stock_transfers.notes')"
                    :value="item.notes || '-'"
                  />
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
        </VCol>

        <VCol
          cols="12"
          lg="6"
        >
          <VCard class="h-100">
            <VCardTitle>
              {{ t('inventory::stock_transfers.show.accounting') }}
            </VCardTitle>

            <VCardText>
              <VRow>
                <VCol
                  cols="12"
                  md="6"
                >
                  <BlockInfo :title="t('inventory::stock_transfers.actions.dispatch')">
                    <VChip
                      v-if="item.accounting?.dispatched"
                      :color="item.accounting.dispatched.color"
                      size="small"
                    >
                      {{ accountingStatus(item.accounting.dispatched) }}
                    </VChip>

                    <span v-else>-</span>
                  </BlockInfo>
                </VCol>

                <VCol
                  cols="12"
                  md="6"
                >
                  <BlockInfo :title="t('inventory::stock_transfers.actions.close')">
                    <VChip
                      v-if="item.accounting?.closed"
                      :color="item.accounting.closed.color"
                      size="small"
                    >
                      {{ accountingStatus(item.accounting.closed) }}
                    </VChip>

                    <span v-else>-</span>
                  </BlockInfo>
                </VCol>

                <VCol
                  cols="12"
                  md="6"
                >
                  <BlockInfo :title="t('inventory::stock_transfers.actions.void')">
                    <VChip
                      v-if="item.accounting?.voided"
                      :color="item.accounting.voided.color"
                      size="small"
                    >
                      {{ accountingStatus(item.accounting.voided) }}
                    </VChip>

                    <span v-else>-</span>
                  </BlockInfo>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
        </VCol>

        <VCol cols="12">
          <VCard>
            <VCardTitle>{{ t('inventory::stock_transfers.show.items') }}</VCardTitle>

            <div class="table-responsive">
              <VTable>
                <thead>
                  <tr>
                    <th>
                      {{ t('inventory::stock_transfers.show.source_ingredient') }}
                    </th>
                    <th>
                      {{ t('inventory::stock_transfers.show.destination_ingredient') }}
                    </th>
                    <th>
                      {{ t('inventory::stock_transfers.show.requested') }}
                    </th>
                    <th>
                      {{ t('inventory::stock_transfers.show.dispatched') }}
                    </th>
                    <th>{{ t('inventory::stock_transfers.show.received') }}</th>
                    <th>
                      {{ t('inventory::stock_transfers.show.remaining') }}
                    </th>
                    <th>
                      {{ t('inventory::stock_transfers.show.discrepancy') }}
                    </th>
                    <th>
                      {{ t('inventory::stock_transfers.show.unit_cost') }}
                    </th>
                    <th>
                      {{ t('inventory::stock_transfers.show.movements') }}
                    </th>
                  </tr>
                </thead>

                <tbody>
                  <tr
                    v-for="line in item.items"
                    :key="line.id"
                  >
                    <td>{{ line.source_ingredient?.name }}</td>
                    <td>{{ line.destination_ingredient?.name }}</td>
                    <td>
                      {{ formatQuantity(line.requested_quantity, line.unit?.symbol) }}
                    </td>
                    <td>
                      {{ formatQuantity(line.dispatched_quantity, line.unit?.symbol) }}
                    </td>
                    <td>
                      {{ formatQuantity(line.received_quantity, line.unit?.symbol) }}
                    </td>
                    <td>
                      {{ formatQuantity(line.remaining_quantity, line.unit?.symbol) }}
                    </td>

                    <td>
                      {{ formatQuantity(line.discrepancy_quantity, line.unit?.symbol) }}
                      <div
                        v-if="line.discrepancy_reason"
                        class="text-caption text-medium-emphasis"
                      >
                        {{ line.discrepancy_reason }}
                      </div>
                    </td>

                    <td>{{ line.unit_cost_formatted || '-' }}</td>

                    <td>
                      <div
                        v-if="
                          movementIds(line.outbound_movement_id, line.outbound_reversal_movement_id)
                            .length > 0
                        "
                        class="d-flex flex-wrap gap-1"
                      >
                        <VBtn
                          v-for="movementId in movementIds(
                            line.outbound_movement_id,
                            line.outbound_reversal_movement_id,
                          )"
                          :key="movementId"
                          :color="can('admin.stock_movements.show') ? 'primary' : undefined"
                          density="comfortable"
                          :disabled="!can('admin.stock_movements.show')"
                          size="small"
                          variant="tonal"
                          @click="openMovement(movementId)"
                        >
                          #{{ movementId }}
                        </VBtn>
                      </div>

                      <span v-else>-</span>
                    </td>
                  </tr>
                </tbody>
              </VTable>
            </div>
          </VCard>
        </VCol>

        <VCol
          cols="12"
          lg="8"
        >
          <VCard>
            <VCardTitle>{{ t('inventory::stock_transfers.show.receipts') }}</VCardTitle>

            <VCardText
              v-if="!item.receipts?.length"
              class="text-medium-emphasis"
            >
              {{ t('inventory::stock_transfers.show.no_receipts') }}
            </VCardText>

            <VExpansionPanels
              v-else
              variant="accordion"
            >
              <VExpansionPanel
                v-for="receipt in item.receipts"
                :key="receipt.id"
                :title="`${receipt.reference_number} · ${receipt.received_at}`"
              >
                <VExpansionPanelText>
                  <div class="d-flex flex-wrap gap-4 mb-3">
                    <span>{{ receipt.received_by }}</span>

                    <VChip
                      v-if="receipt.accounting_status"
                      :color="receipt.accounting_status.color"
                      size="small"
                    >
                      {{ receipt.accounting_status.name }}
                    </VChip>
                  </div>

                  <VTable density="compact">
                    <thead>
                      <tr>
                        <th>#</th>
                        <th>
                          {{ t('inventory::stock_transfers.show.received') }}
                        </th>
                        <th>
                          {{ t('inventory::stock_transfers.show.unit_cost') }}
                        </th>
                        <th>
                          {{ t('inventory::stock_transfers.show.total_cost') }}
                        </th>
                        <th>
                          {{ t('inventory::stock_transfers.show.movements') }}
                        </th>
                      </tr>
                    </thead>

                    <tbody>
                      <tr
                        v-for="receiptLine in receipt.items"
                        :key="receiptLine.id"
                      >
                        <td>{{ receiptLine.stock_transfer_item_id }}</td>

                        <td>
                          {{
                            formatQuantity(
                              receiptLine.quantity,
                              transferLine(receiptLine.stock_transfer_item_id)?.unit?.symbol,
                            )
                          }}
                        </td>

                        <td>
                          {{ receiptLine.unit_cost_formatted || '-' }}
                        </td>
                        <td>
                          {{ receiptLine.total_cost_formatted || '-' }}
                        </td>

                        <td>
                          <VBtn
                            v-if="receiptLine.inbound_movement_id"
                            :color="can('admin.stock_movements.show') ? 'primary' : undefined"
                            density="comfortable"
                            :disabled="!can('admin.stock_movements.show')"
                            size="small"
                            variant="tonal"
                            @click="openMovement(Number(receiptLine.inbound_movement_id))"
                          >
                            #{{ receiptLine.inbound_movement_id }}
                          </VBtn>

                          <span v-else>-</span>
                        </td>
                      </tr>
                    </tbody>
                  </VTable>
                </VExpansionPanelText>
              </VExpansionPanel>
            </VExpansionPanels>
          </VCard>
        </VCol>

        <VCol
          cols="12"
          lg="4"
        >
          <VCard>
            <VCardTitle>
              {{ t('inventory::stock_transfers.show.lifecycle') }}
            </VCardTitle>

            <VCardText>
              <VTimeline
                align="start"
                density="compact"
                side="end"
              >
                <VTimelineItem
                  v-for="entry in lifecycle"
                  :key="entry.key"
                  :dot-color="entry.color"
                  fill-dot
                  size="small"
                >
                  <template #icon>
                    <VIcon
                      color="white"
                      :icon="entry.icon"
                      size="14"
                    />
                  </template>

                  <div class="stock-transfer-lifecycle">
                    <VChip
                      :color="entry.color"
                      density="comfortable"
                      size="small"
                      variant="tonal"
                    >
                      {{ t(`inventory::stock_transfers.lifecycle.${entry.key}`) }}
                    </VChip>

                    <div class="mt-1 text-body-2">{{ entry.actor || '-' }}</div>
                    <div class="text-caption text-medium-emphasis">
                      {{ entry.at }}
                    </div>
                  </div>
                </VTimelineItem>
              </VTimeline>

              <VAlert
                v-if="item.audit?.cancellation_reason || item.audit?.void_reason"
                class="mt-4"
                color="warning"
                variant="tonal"
              >
                {{ item.audit.cancellation_reason || item.audit.void_reason }}
              </VAlert>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <ReceiveDialog
        v-if="receiveDialog"
        v-model="receiveDialog"
        :transfer="item"
        @saved="loadItem(false)"
      />

      <ReasonDialog
        v-if="reasonAction"
        :action="reasonAction"
        :model-value="true"
        :transfer-id="item.id"
        @saved="loadItem(false)"
        @update:model-value="
          (value) => {
            if (!value) reasonAction = null
          }
        "
      />
    </template>
  </PageStateWrapper>
</template>

<style lang="scss" scoped>
.table-responsive {
  overflow-x: auto;
}

.table-responsive :deep(table) {
  min-width: 1100px;
}
</style>
