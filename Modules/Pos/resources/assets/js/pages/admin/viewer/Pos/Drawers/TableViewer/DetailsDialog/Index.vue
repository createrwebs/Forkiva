<script lang="ts" setup>
import type { UseCart } from '@/modules/cart/composables/cart.ts'
import type { PosForm } from '@/modules/pos/contracts/posViewer.ts'
import type { useQintrix } from '@/modules/printer/composables/qintrix.ts'
import { computed, onBeforeMount, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import { useForm } from '@/modules/core/composables/form.ts'
import { useTable } from '@/modules/seatingPlan/composables/table.ts'
import MergeDetails from './MergeDetails.vue'
import MergeDialog from './MergeDialog.vue'
import OrdersDetails from './OrdersDetails/Index.vue'
import OrderBulkActions from './OrdersDetails/OrderBulkActions.vue'

const props = defineProps<{
  modelValue: boolean
  tableId: number
  form: PosForm
  cart: UseCart
  qintrix: ReturnType<typeof useQintrix>
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (e: 'close-parent' | 'refresh'): void
  (
    e: 'store-payment' | 'refund-order' | 'cancel-order' | 'view-order' | 'print-order',
    value: string | number,
  ): void
  (e: 'init-order', response: Record<string, any>): void
}>()

const currentTableId = ref(props.tableId)
const { t } = useI18n()
const toast = useToast()
const { can } = useAuth()
const { getTableViewerDetails, assignWaiter, updateStatusToAvailable, loadings, splitTable } =
  useTable()
const { storeOrderType } = props.cart
const { form } = toRefs(props)

const loadingCreateOrder = ref(false)
const table = ref<Record<string, any> | null>(null)
const orders = computed<Record<string, any>[]>(() =>
  table.value?.order ? [table.value.order] : table.value?.orders || [],
)
const isBillingMerged = computed(
  () => table.value?.current_merge?.type.id === 'billing' && orders.value.length > 0,
)
const allowCreateOrder = computed(
  () => can('admin.orders.create') && table.value?.status.id === 'available',
)
const allowMakeAvailable = computed(
  () => can('admin.tables.update_status') && table.value?.status.id === 'cleaning',
)
const meta = reactive({
  waiters: [] as Record<string, any>[],
})
const loading = ref(true)
const showTableMergeDialog = ref(false)
const allowMergeTable = computed(
  () =>
    table.value &&
    can('admin.tables.merge') &&
    !table.value.current_merge &&
    !['cleaning', 'merged'].includes(table.value.status.id),
)

const dialogModel = computed({
  get: () => props.modelValue,
  set: (val: boolean) => emit('update:modelValue', val),
})

onBeforeMount(async () => {
  loading.value = true
  await loadData()
  loading.value = false
})

const assignWaiterForm = useForm({
  waiter_id: null,
})

async function loadData() {
  try {
    const response = await getTableViewerDetails(currentTableId.value)
    const data = response.data.body as Record<string, any>
    table.value = data.table
    meta.waiters = data.meta?.waiters || []
    assignWaiterForm.state.waiter_id = table.value?.waiter?.id ?? null
  } catch {
    toast.error(t('core::errors.an_unexpected_error_occurred'))
  }
}

if (can('admin.tables.assign_waiter')) {
  watch(
    () => assignWaiterForm.state.waiter_id,
    async (newValue, oldValue) => {
      if (!table.value) return
      if ((newValue ?? null) === (table.value.waiter?.id ?? null)) return
      if (
        await assignWaiterForm.submit(() =>
          assignWaiter(currentTableId.value, { waiter_id: newValue }),
        )
      ) {
        if (!table.value.waiter) {
          table.value.waiter = {}
        }
        table.value.waiter.id = newValue
      } else {
        assignWaiterForm.state.waiter_id = oldValue
      }
    },
  )
}

const close = () => emit('update:modelValue', false)

async function createOrder() {
  if (table.value) {
    loadingCreateOrder.value = true
    await storeOrderType('dine_in')
    form.value.table = {
      id: table.value.id,
      name: table.value.name,
      floor: table.value.floor.name,
      zone: table.value.zone.name,
    }
    loadingCreateOrder.value = false
    emit('close-parent')
    close()
  }
}

function initOrder(response: Record<string, any>) {
  emit('init-order', response)
  emit('close-parent')
  close()
}

async function makeAvailable() {
  await updateStatusToAvailable(currentTableId.value)
  emit('refresh')
  await loadData()
}

async function refreshAll() {
  emit('refresh')
  await loadData()
}

function mergeTable() {
  showTableMergeDialog.value = true
}

async function onClickSplitTable() {
  if (table.value?.allow_split && (await splitTable(table.value?.current_merge.id))) {
    emit('refresh')
    await loadData()
  }
}

async function reload(newTableId: number) {
  if (newTableId !== currentTableId.value) {
    currentTableId.value = newTableId
    loading.value = true
    await loadData()
    loading.value = false
  }
}

defineExpose({ refresh: refreshAll })
</script>

<template>
  <VDialog
    v-model="dialogModel"
    max-width="720"
    aria-labelledby="pos-table-details-title"
    scrollable
  >
    <VCard class="pos-details-dialog pos-table-details">
      <VCardTitle class="pos-table-details-heading">
        <div class="pos-table-heading-main">
          <VIcon
            icon="tabler-armchair"
            size="24"
            aria-hidden="true"
          />
          <h2 id="pos-table-details-title">
            <strong v-if="table">{{ table.name }}</strong>
            <span>{{ t('pos::pos_viewer.workspace.table_details') }}</span>
          </h2>
          <VChip
            v-if="table"
            :color="table.status.color"
            label
            size="small"
          >
            <span
              class="pos-table-status-dot"
              aria-hidden="true"
            />
            {{ table.status.name }}
          </VChip>
        </div>
        <VBtn
          class="pos-table-close"
          :aria-label="t('admin::admin.buttons.close')"
          icon="tabler-x"
          color="default"
          variant="text"
          size="small"
          @click="close"
        />
      </VCardTitle>
      <div
        v-if="loading"
        class="pos-table-loading"
      >
        <VProgressCircular
          color="primary"
          indeterminate
          size="40"
        />
      </div>
      <VCardText
        v-else-if="table"
        class="pos-table-details-body"
      >
        <aside
          class="pos-table-context"
          :aria-label="table.name"
        >
          <dl class="pos-table-facts">
            <div>
              <VIcon
                icon="tabler-layout-grid"
                size="23"
                aria-hidden="true"
              />
              <div>
                <dt>{{ t('seatingplan::tables.table_columns.zone') }}</dt>
                <dd>{{ table.zone?.name }}</dd>
              </div>
            </div>
            <div>
              <VIcon
                icon="tabler-stack-2"
                size="23"
                aria-hidden="true"
              />
              <div>
                <dt>{{ t('seatingplan::tables.table_columns.floor') }}</dt>
                <dd>{{ table.floor?.name }}</dd>
              </div>
            </div>
            <div class="pos-table-capacity">
              <VIcon
                icon="tabler-users"
                size="23"
                aria-hidden="true"
              />
              <div>
                <dt>{{ t('seatingplan::tables.table_columns.capacity') }}</dt>
                <dd>{{ t('pos::pos_viewer.table_details.guests', table.capacity) }}</dd>
              </div>
            </div>
          </dl>
          <div class="pos-table-waiter">
            <label for="pos-table-waiter">
              {{ t('pos::pos_viewer.table_details.assigned_waiter') }}
            </label>
            <VSelect
              v-if="can('admin.tables.assign_waiter')"
              id="pos-table-waiter"
              v-model="assignWaiterForm.state.waiter_id"
              density="compact"
              hide-details="auto"
              clearable
              prepend-inner-icon="tabler-user"
              :disabled="assignWaiterForm.loading.value"
              :error="!!assignWaiterForm.errors.value?.waiter_id"
              :error-messages="assignWaiterForm.errors.value?.waiter_id"
              item-title="name"
              item-value="id"
              :items="meta.waiters"
              :loading="assignWaiterForm.loading.value"
              :menu-props="{ maxHeight: 280 }"
              :aria-label="t('pos::pos_viewer.table_details.assigned_waiter')"
              :placeholder="t('seatingplan::tables.unassigned')"
            />
            <strong
              v-else
              class="pos-table-assigned-waiter"
            >
              {{ table.waiter?.name || t('seatingplan::tables.unassigned') }}
            </strong>
          </div>
          <MergeDetails
            v-if="table.current_merge"
            :current-table-id="currentTableId"
            :merge="table.current_merge"
            @reload="reload"
          />
          <div class="pos-table-context-actions">
            <VBtn
              v-if="allowMergeTable"
              color="default"
              variant="outlined"
              @click="mergeTable"
            >
              <VIcon
                icon="tabler-arrow-merge"
                start
              />
              {{ t('seatingplan::tables.merge_table') }}
            </VBtn>
            <VBtn
              v-if="table.allow_split"
              color="default"
              :disabled="loadings.split"
              :loading="loadings.split"
              variant="outlined"
              @click="onClickSplitTable"
            >
              <VIcon
                icon="tabler-arrows-diagonal"
                start
              />
              {{ t('seatingplan::tables.split_table') }}
            </VBtn>
          </div>
        </aside>
        <div class="pos-table-order-content">
          <OrdersDetails
            :branch-id="form.branchId"
            :orders="orders"
            :is-billing-merged="isBillingMerged"
            :qintrix="qintrix"
            @cancel-order="(orderId: number | string) => $emit('cancel-order', orderId)"
            @init-order="initOrder"
            @print-order="(orderId: number | string) => $emit('print-order', orderId)"
            @refresh="refreshAll"
            @refund-order="(orderId: number | string) => $emit('refund-order', orderId)"
            @store-payment="(orderId: number | string) => $emit('store-payment', orderId)"
            @view-order="(orderId: number | string) => $emit('view-order', orderId)"
          />
        </div>
      </VCardText>
      <footer
        v-if="!loading && table && (isBillingMerged || allowCreateOrder || allowMakeAvailable)"
        class="pos-table-details-footer"
      >
        <OrderBulkActions
          v-if="isBillingMerged"
          :merge-id="table.current_merge.id"
          :orders="orders"
          @store-payment="(orderId: number | string) => $emit('store-payment', orderId)"
        />
        <div
          v-if="allowCreateOrder || allowMakeAvailable"
          class="pos-table-footer-actions"
        >
          <VBtn
            v-if="allowCreateOrder"
            color="primary"
            :disabled="loadingCreateOrder"
            :loading="loadingCreateOrder"
            variant="flat"
            @click="createOrder"
          >
            <VIcon
              icon="tabler-plus"
              start
            />
            {{ t('seatingplan::tables.create_order') }}
          </VBtn>
          <VBtn
            v-if="allowMakeAvailable"
            color="success"
            :disabled="loadings.makeAvailable"
            :loading="loadings.makeAvailable"
            variant="flat"
            @click="makeAvailable"
          >
            <VIcon
              icon="tabler-progress-check"
              start
            />
            {{ t('seatingplan::tables.make_available') }}
          </VBtn>
        </div>
      </footer>
    </VCard>
    <MergeDialog
      v-if="allowMergeTable && showTableMergeDialog"
      v-model="showTableMergeDialog"
      :pos-form="form"
      :table-id="currentTableId"
      @refresh="refreshAll"
    />
  </VDialog>
</template>

<style lang="scss" scoped>
@use '../../../../styles/theme' as pos-theme;

.pos-table-details {
  @include pos-theme.light;
  --table-context-surface: #f6f8fb;
  border: 1px solid var(--pos-border);
  border-radius: 14px !important;
  max-block-size: min(640px, calc(100dvh - 40px));
  color: var(--pos-text);
  background: var(--pos-surface);
}
.pos-table-details.v-theme--dark {
  @include pos-theme.dark;
  --table-context-surface: var(--pos-soft);
}
.pos-table-details-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 20px !important;
  border-block-end: 1px solid var(--pos-border);
  flex-shrink: 0;
}
.pos-table-close {
  color: var(--pos-text) !important;
}
.pos-table-loading {
  display: grid;
  place-items: center;
  min-block-size: 180px;
}
.pos-table-details-body.v-card-text {
  min-block-size: 0;
  overflow-y: auto;
  scroll-padding-block: 12px;
  padding: 0 !important;
}
.pos-table-context {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(160px, 0.95fr) auto;
  gap: 12px 20px;
  padding: 14px 20px;
  border-block-end: 1px solid var(--pos-border);
  background: var(--table-context-surface);
}
.pos-table-heading-main {
  display: flex;
  align-items: center;
  gap: 10px;
  min-inline-size: 0;
}
.pos-table-heading-main > .v-icon {
  color: var(--pos-muted);
  flex-shrink: 0;
}
.pos-table-details-heading h2 {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
  margin: 0;
  font-size: 14px;
  line-height: 1.4;
  font-weight: 500;
  white-space: normal;
  color: var(--pos-muted);
}
.pos-table-details-heading h2 strong {
  color: var(--pos-text);
  font-size: 22px;
  font-weight: 700;
  overflow-wrap: anywhere;
}
.pos-table-status-dot {
  inline-size: 6px;
  block-size: 6px;
  border-radius: 50%;
  background: currentColor;
  margin-inline-end: 6px;
}
.pos-table-facts {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  align-items: center;
  gap: 12px;
  margin: 0;
}
.pos-table-facts > div,
.pos-table-facts > div > div {
  min-inline-size: 0;
}
.pos-table-facts .v-icon {
  display: none;
}
.pos-table-facts dt {
  color: var(--pos-muted);
  font-size: 11px;
  line-height: 1.5;
  margin-block-end: 2px;
}
.pos-table-facts dd {
  margin: 0;
  color: var(--pos-text);
  font-size: 13px;
  line-height: 1.5;
  overflow-wrap: anywhere;
}
.pos-table-waiter {
  grid-area: 1 / 2;
  min-inline-size: 0;
}
.pos-table-waiter label {
  display: block;
  font-size: 12px;
  color: var(--pos-muted);
  margin-block-end: 5px;
}
.pos-table-assigned-waiter {
  display: block;
  font-size: 13px;
  padding-block: 8px;
}
.pos-table-waiter :deep(.v-field) {
  border-radius: 8px;
  font-size: 13px;
  color: var(--pos-text);
  background: var(--pos-surface);
}
.pos-table-waiter :deep(.v-field__input) {
  min-block-size: 40px;
  padding-block: 8px;
}
.pos-table-waiter :deep(.v-select__selection) {
  margin-block: 0;
}
.pos-table-waiter :deep(.v-field__outline) {
  color: var(--pos-border);
  --v-field-border-opacity: 1;
}
.pos-table-waiter :deep(.v-field--focused .v-field__outline) {
  color: var(--pos-accent);
}
.pos-table-waiter :deep(input:focus) {
  outline: none;
  box-shadow: none;
}
.pos-table-context-actions {
  grid-area: 1 / 3;
  display: flex;
  align-items: flex-end;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: 8px;
}
.pos-table-context-actions:empty {
  display: none;
}
.pos-table-context :deep(.pos-table-merge) {
  grid-column: 1 / -1;
  padding-block-start: 12px;
}
.pos-table-context-actions .v-btn,
.pos-table-footer-actions .v-btn {
  min-block-size: 40px;
  border-radius: 8px;
  font-size: 13px;
  letter-spacing: 0;
}
.pos-table-context-actions .v-btn {
  border-color: var(--pos-border);
  color: var(--pos-text) !important;
}
.pos-table-context-actions :deep(.v-icon),
.pos-table-footer-actions :deep(.v-icon) {
  color: inherit;
  font-size: 18px;
}
.pos-table-order-content {
  min-inline-size: 0;
  padding: 16px 20px;
}
.pos-table-details-footer {
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
  gap: 12px;
  padding: 12px 20px;
  border-block-start: 1px solid var(--pos-border);
  background: var(--pos-surface);
}
.pos-table-footer-actions {
  display: flex;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: 8px;
}
@media (max-width: 540px) {
  .pos-table-details-heading {
    padding-inline: 16px !important;
  }
  .pos-table-context {
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 12px;
    padding: 12px 16px;
  }
  .pos-table-facts {
    grid-column: 1 / -1;
  }
  .pos-table-waiter {
    grid-area: 2 / 1;
  }
  .pos-table-context-actions {
    grid-area: 2 / 2;
  }
  .pos-table-heading-main {
    gap: 8px;
  }
  .pos-table-details-heading h2 {
    display: block;
    font-size: 11px;
    line-height: 1.3;
  }
  .pos-table-details-heading h2 strong,
  .pos-table-details-heading h2 span {
    display: block;
  }
  .pos-table-context-actions .v-btn {
    padding-inline: 10px;
    font-size: 12px;
  }
  .pos-table-order-content {
    padding: 14px 16px;
  }
  .pos-table-details-footer {
    padding-inline: 16px;
  }
}
</style>
