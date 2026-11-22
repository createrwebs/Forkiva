<script lang="ts" setup>
import WorkspacePanel from '../../WorkspacePanel.vue'
import type { UseCart } from '@/modules/cart/composables/cart.ts'
import type { PosForm } from '@/modules/pos/contracts/posViewer.ts'
import type { useQintrix } from '@/modules/printer/composables/qintrix.ts'
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import { useTableViewerData } from '@/modules/pos/composables/useTableViewerData'
import DetailsDialog from './DetailsDialog/Index.vue'
import Empty from './Empty.vue'
import Filters from './Filters.vue'
import Table from './Table.vue'

const props = defineProps<{
  modelValue: boolean
  embedded?: boolean
  branchId: number | null
  form: PosForm
  cart: UseCart
  qintrix: ReturnType<typeof useQintrix>
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (
    e: 'store-payment' | 'refund-order' | 'cancel-order' | 'view-order' | 'print-order',
    value: string | number,
  ): void
  (e: 'init-order', response: Record<string, unknown>): void
}>()

const open = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val),
})

const { t } = useI18n()
const toast = useToast()
const {
  loading,
  tables,
  meta,
  filters,
  refresh: loadData,
} = useTableViewerData(props, () => {
  toast.error(t('core::errors.an_unexpected_error_occurred'))
})
const { can } = useAuth()

const tableLayout = ref<'grid' | 'list'>('grid')
const ordersDetailsRef = ref()

const tableDetailsDialog = reactive<{
  tableId: number | null
  show: boolean
}>({
  tableId: null,
  show: true,
})

const close = () => emit('update:modelValue', false)

function showTableDetails(tableId: number) {
  if (can('admin.tables.show')) {
    tableDetailsDialog.tableId = tableId
    tableDetailsDialog.show = true
  }
}

defineExpose({
  showTableDetails,
  refresh: () => loadData({ refreshing: true }),
  refreshTableDetails: () => {
    if (can('admin.tables.show') && tableDetailsDialog.show && tableDetailsDialog.tableId) {
      ordersDetailsRef.value?.refresh()
    }
  },
})
</script>

<template>
  <WorkspacePanel
    v-model="open"
    :embedded="embedded"
  >
    <VCard
      class="pos-panel-card"
      flat
    >
      <VCardText class="pos-panel-body">
        <div
          class="pos-panel-heading"
          :class="{ 'pos-panel-heading--embedded': embedded }"
        >
          <h3 class="d-flex align-center ga-1">
            <VIcon
              color="#8e44ad"
              icon="tabler-brand-airtable"
              size="25"
            />
            {{ t('pos::pos_viewer.table_viewer') }}
          </h3>
          <VBtn
            v-if="!embedded"
            :aria-label="t('admin::admin.buttons.close')"
            color="grey-200"
            icon="tabler-x"
            @click="close"
          />
        </div>
        <div class="pos-table-toolbar">
          <Filters
            :disabled="loading"
            :filters="filters"
            :meta="meta"
          />
          <div class="pos-view-toggle">
            <button
              type="button"
              :class="{ active: tableLayout === 'grid' }"
              :aria-label="t('pos::pos_viewer.workspace.grid')"
              :aria-pressed="tableLayout === 'grid'"
              @click="tableLayout = 'grid'"
            >
              <VIcon icon="tabler-layout-grid" />
            </button>
            <button
              type="button"
              :class="{ active: tableLayout === 'list' }"
              :aria-label="t('pos::pos_viewer.workspace.list')"
              :aria-pressed="tableLayout === 'list'"
              @click="tableLayout = 'list'"
            >
              <VIcon icon="tabler-list" />
            </button>
          </div>
        </div>
        <div class="tables-wrapper mt-3">
          <div
            v-if="!loading && tables.length > 0"
            class="pos-table-grid"
            :class="{ 'pos-table-grid--list': tableLayout === 'list' }"
          >
            <Table
              v-for="table in tables"
              :key="table.id"
              :table="table"
              :selected="table.id === form.table?.id"
              @show-details="showTableDetails"
            />
          </div>
          <Empty v-else-if="!loading && tables.length === 0" />
          <div
            v-else-if="loading"
            class="tables-loading"
          >
            <VProgressCircular
              color="primary"
              indeterminate
              size="50"
            />
          </div>
        </div>
      </VCardText>
    </VCard>
  </WorkspacePanel>
  <DetailsDialog
    v-if="can('admin.tables.show') && tableDetailsDialog.show && tableDetailsDialog.tableId"
    ref="ordersDetailsRef"
    v-model="tableDetailsDialog.show"
    :cart="cart"
    :form="form"
    :qintrix="qintrix"
    :table-id="tableDetailsDialog.tableId"
    @cancel-order="(orderId: number | string) => $emit('cancel-order', orderId)"
    @close-parent="close"
    @init-order="(response: Record<string, unknown>) => $emit('init-order', response)"
    @print-order="(orderId: number | string) => $emit('print-order', orderId)"
    @refresh="loadData({ refreshing: true })"
    @refund-order="(orderId: number | string) => $emit('refund-order', orderId)"
    @store-payment="(orderId: number | string) => $emit('store-payment', orderId)"
    @view-order="(orderId: number | string) => $emit('view-order', orderId)"
  />
</template>
