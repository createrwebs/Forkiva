<script lang="ts" setup>
import type { Cart, UseCart } from '@/modules/cart/composables/cart.ts'
import type {
  PosCustomerLoadOptions,
  PosForm,
  PosMeta,
  PosProductLoadOptions,
} from '@/modules/pos/contracts/posViewer.ts'
import type { useQintrix } from '@/modules/printer/composables/qintrix.ts'
import { computed, onMounted, ref, watch } from 'vue'
import { useDisplay } from 'vuetify'
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import { usePosSound } from '@/modules/pos/composables/posSound'
import { PosSoundAction } from '@/modules/pos/enums/posSound'
import OrderDetailsDialog from '@/modules/pos/pages/admin/viewer/Pos/Dialogs/OrderDetails/Index.vue'
import PaymentDialog from '@/modules/pos/pages/admin/viewer/Pos/Dialogs/Payment/Index.vue'
import OrdersDrawer from '@/modules/pos/pages/admin/viewer/Pos/Drawers/Orders/Index.vue'
import OrderPrintDialog from './Dialogs/OrderPrint/Index.vue'
import RefundCancelDialog from './Dialogs/RefundCancelDialog.vue'
import CashMovementDrawer from './Drawers/CashMovement/Index.vue'
import TableViewerDrawer from './Drawers/TableViewer/Index.vue'
import MenuPanel from './MenuPanel/Index.vue'
import OrderPanel from './OrderPanel/Index.vue'
import Actions from './OrderPanel/Actions.vue'
import AssignWaiterDialog from './OrderPanel/AssignWaiterDialog.vue'

const props = defineProps<{
  form: PosForm
  meta: PosMeta
  cart: UseCart
  qintrix: ReturnType<typeof useQintrix>
  loadProducts: (options?: PosProductLoadOptions) => Promise<void>
  loadCustomers: (options?: PosCustomerLoadOptions) => Promise<void>
}>()

const emit = defineEmits<{
  (e: 'init-order', response: Record<string, any>): void
  (e: 'reset', cart?: Cart): void
}>()
const { can, hasRole } = useAuth()
const sound = usePosSound()
const showAssignWaiterDialog = ref(false)
const canAssignOrderWaiter = computed(
  () => !hasRole('waiter') && props.cart.data.value.orderType?.id !== 'delivery',
)
const { t } = useI18n()
const router = useRouter()
const { width } = useDisplay()
const isOperationsDocked = computed(() => width.value >= 1440)
const mobileView = ref<'menu' | 'order'>('menu')
const canTables = computed(
  () => can('admin.tables.viewer') && props.meta.orderTypes.some((type) => type.id === 'dine_in'),
)
const hasOperations = computed(
  () =>
    canTables.value ||
    canOrders ||
    can('admin.pos_cash_movements.create') ||
    canAssignOrderWaiter.value,
)
const activeOperation = computed(() =>
  showTableViewerDrawer.value
    ? 'table_viewer'
    : showOrdersDrawer.value
      ? 'orders'
      : showCashMovementDrawer.value
        ? 'manage_cash_movement'
        : null,
)

function showCustomerDisplay() {
  const route = router.resolve({
    name: 'public.pos.customer_display',
    hash: `#scope=${encodeURIComponent(props.cart.draft.value?.key || '')}`,
  })
  window.open(route.href, 'forkiva-customer-display')
}

function showCurrentTableDetails() {
  if (props.form.table?.id && can('admin.tables.show')) {
    onClickAction('table_viewer')
    tableViewerDrawerRef.value?.showTableDetails(props.form.table.id)
  }
}

function showDefaultOperation() {
  if (isOperationsDocked.value) {
    onClickAction(canTables.value ? 'table_viewer' : canOrders ? 'orders' : 'manage_cash_movement')
  }
}

onMounted(showDefaultOperation)
watch(isOperationsDocked, () => {
  showTableViewerDrawer.value = false
  showOrdersDrawer.value = false
  showCashMovementDrawer.value = false
  showDefaultOperation()
})

const canOrders = can('admin.orders.upcoming') || can('admin.orders.active')
const showOrdersDrawer = ref(false)
const showCashMovementDrawer = ref(false)
const showTableViewerDrawer = ref(false)

watch([showOrdersDrawer, showCashMovementDrawer, showTableViewerDrawer], (openPanels) => {
  if (!openPanels.some(Boolean)) showDefaultOperation()
})

const paymentDialog = ref<Record<string, any>>({ orderId: null, open: false })
const refundCancelDialog = ref<Record<string, any>>({ orderId: null, open: false })
const viewOrderDetailsDialog = ref<Record<string, any>>({ orderId: null, open: false })
const showOrderPrintDialog = ref<Record<string, any>>({ orderId: null, open: false })
const ordersDrawerRef = ref()
const tableViewerDrawerRef = ref()
const ordersDrawerVersion = ref(0)

function invalidateOrderManagement() {
  ordersDrawerVersion.value++
  if (showTableViewerDrawer.value) tableViewerDrawerRef.value?.refresh()
}

function onClickAction(action: string) {
  if (action === 'assign_waiter' && canAssignOrderWaiter.value) {
    showAssignWaiterDialog.value = true
  } else if (action === 'customer_viewer') {
    showCustomerDisplay()
  } else if (action === 'orders' && canOrders) {
    showTableViewerDrawer.value = false
    showCashMovementDrawer.value = false
    showOrdersDrawer.value = true
  } else if (action === 'manage_cash_movement' && can('admin.pos_cash_movements.create')) {
    showTableViewerDrawer.value = false
    showOrdersDrawer.value = false
    showCashMovementDrawer.value = true
  } else if (action === 'table_viewer' && can('admin.tables.viewer')) {
    showOrdersDrawer.value = false
    showCashMovementDrawer.value = false
    showTableViewerDrawer.value = true
  }
}

function storePayment(orderId: number | string) {
  if (can('admin.orders.receive_payment')) {
    paymentDialog.value.orderId = orderId
    paymentDialog.value.open = true
  }
}

function paymentAdded() {
  sound.play(PosSoundAction.PaymentCompleted)
  if (showOrdersDrawer.value) {
    ordersDrawerRef.value?.refresh()
  } else if (showTableViewerDrawer.value) {
    tableViewerDrawerRef.value?.refreshTableDetails()
  }
}

function refundCancel(action: string, orderId: number | string) {
  if (can(`admin.orders.${action}`)) {
    refundCancelDialog.value.orderId = orderId
    refundCancelDialog.value.open = true
  }
}

function viewOrderDetails(orderId: number | string) {
  if (can('admin.orders.show')) {
    viewOrderDetailsDialog.value.orderId = orderId
    viewOrderDetailsDialog.value.open = true
  }
}

function orderPrint(orderId: number | string) {
  if (can('admin.orders.print')) {
    showOrderPrintDialog.value.orderId = orderId
    showOrderPrintDialog.value.open = true
  }
}

function resolvedRefundCancel() {
  if (showOrdersDrawer.value) {
    ordersDrawerRef.value?.refresh()
  } else if (showTableViewerDrawer.value) {
    tableViewerDrawerRef.value?.refreshTableDetails()
  }
}

function handleReset(cartData?: Cart) {
  invalidateOrderManagement()
  emit('reset', cartData)
}

function handleInitOrder(response: Record<string, any>) {
  invalidateOrderManagement()
  emit('init-order', response)
}
</script>

<template>
  <div
    class="pos-workspace"
    :class="{
      'pos-workspace--docked': isOperationsDocked && hasOperations,
      'pos-workspace--order': mobileView === 'order',
    }"
  >
    <section
      class="pos-surface pos-catalog"
      :aria-label="t('pos::pos_viewer.workspace.menu')"
    >
      <MenuPanel
        :cart="cart"
        :form="form"
        :load-products="loadProducts"
        :meta="meta"
      />
    </section>
    <section
      class="pos-surface pos-ticket"
      :aria-label="t('pos::pos_viewer.workspace.current_order')"
    >
      <OrderPanel
        :cart="cart"
        :form="form"
        :load-customers="loadCustomers"
        :meta="meta"
        :qintrix="qintrix"
        @on-click-action="onClickAction"
        @reset="handleReset"
        @store-payment="storePayment"
      />
    </section>
    <aside
      v-if="hasOperations"
      class="pos-surface pos-operations"
    >
      <Actions
        :show-customer-viewer="!isOperationsDocked"
        :show-assign-waiter="!isOperationsDocked && canAssignOrderWaiter"
        :processing="cart.processing.value"
        :form="form"
        :meta="meta"
        :active="activeOperation"
        @on-click-action="onClickAction"
      />
      <div class="pos-operation-content">
        <TableViewerDrawer
          v-if="can('admin.tables.viewer')"
          ref="tableViewerDrawerRef"
          :embedded="isOperationsDocked"
          v-model="showTableViewerDrawer"
          :branch-id="form.branchId"
          :cart="cart"
          :form="form"
          :qintrix="qintrix"
          @cancel-order="(orderId: number | string) => refundCancel('cancel', orderId)"
          @init-order="(response: Record<string, any>) => $emit('init-order', response)"
          @print-order="(orderId: number | string) => orderPrint(orderId)"
          @refund-order="(orderId: number | string) => refundCancel('refund', orderId)"
          @store-payment="storePayment"
          @view-order="(orderId: number | string) => viewOrderDetails(orderId)"
        />
        <OrdersDrawer
          v-if="canOrders"
          ref="ordersDrawerRef"
          :embedded="isOperationsDocked"
          v-model="showOrdersDrawer"
          :branch-id="form.branchId"
          :qintrix="qintrix"
          :version="ordersDrawerVersion"
          @cancel-order="(orderId: number | string) => refundCancel('cancel', orderId)"
          @init-order="handleInitOrder"
          @print-order="(orderId: number | string) => orderPrint(orderId)"
          @refund-order="(orderId: number | string) => refundCancel('refund', orderId)"
          @store-payment="storePayment"
          @view-order="(orderId: number | string) => viewOrderDetails(orderId)"
        />
        <CashMovementDrawer
          v-if="can('admin.pos_cash_movements.create')"
          :embedded="isOperationsDocked"
          v-model="showCashMovementDrawer"
          :meta="meta"
          :register-id="form.registerId"
        />
      </div>
      <div
        v-if="isOperationsDocked"
        class="pos-shortcuts"
      >
        <button
          v-if="can('admin.tables.show')"
          type="button"
          class="pos-shortcut pos-shortcut--blue"
          :disabled="!form.table?.id"
          @click="showCurrentTableDetails"
        >
          <VIcon icon="tabler-clipboard-list" />
          <span>{{ t('pos::pos_viewer.workspace.table_details') }}</span>
        </button>
        <button
          v-if="canAssignOrderWaiter"
          type="button"
          class="pos-shortcut pos-shortcut--purple"
          :disabled="cart.processing.value"
          :title="t('seatingplan::tables.assign_waiter')"
          @click="onClickAction('assign_waiter')"
        >
          <VIcon icon="tabler-user-pentagon" />
          <span>{{ form.waiter?.name || t('seatingplan::tables.assign_waiter') }}</span>
        </button>
        <button
          v-if="can('admin.pos_cash_movements.create')"
          type="button"
          class="pos-shortcut pos-shortcut--orange"
          @click="onClickAction('manage_cash_movement')"
        >
          <VIcon icon="tabler-cash" />
          <span>{{ t('pos::pos_viewer.actions.manage_cash_movement') }}</span>
        </button>
        <button
          type="button"
          class="pos-shortcut pos-shortcut--green"
          @click="showCustomerDisplay"
        >
          <VIcon icon="tabler-device-desktop" />
          <span>{{ t('pos::pos_viewer.customer_viewer') }}</span>
        </button>
      </div>
    </aside>
    <nav
      class="pos-mobile-switch"
      :aria-label="t('pos::pos_viewer.pos_viewer')"
    >
      <button
        type="button"
        :class="{ active: mobileView === 'menu' }"
        @click="mobileView = 'menu'"
      >
        <VIcon icon="tabler-tools-kitchen-2" />
        {{ t('pos::pos_viewer.workspace.menu') }}
      </button>
      <button
        type="button"
        :class="{ active: mobileView === 'order' }"
        @click="mobileView = 'order'"
      >
        <VIcon icon="tabler-shopping-cart" />
        {{ t('pos::pos_viewer.order') }}
        <strong>{{ cart.data.value.total.formatted }}</strong>
      </button>
    </nav>
  </div>
  <AssignWaiterDialog
    v-if="showAssignWaiterDialog && canAssignOrderWaiter"
    v-model="showAssignWaiterDialog"
    :form="form"
    :meta="meta"
    :disabled="cart.processing.value"
  />
  <OrderDetailsDialog
    v-if="can('admin.orders.show') && viewOrderDetailsDialog.open"
    v-model="viewOrderDetailsDialog.open"
    :order-id="viewOrderDetailsDialog.orderId"
  />

  <PaymentDialog
    v-if="
      can('admin.orders.receive_payment') &&
      form.sessionId &&
      form.registerId &&
      paymentDialog.orderId !== null &&
      paymentDialog.orderId !== undefined &&
      paymentDialog.open
    "
    v-model="paymentDialog.open"
    :order-id="paymentDialog.orderId"
    :qintrix="qintrix"
    :register-id="form.registerId"
    :session-id="form.sessionId"
    @payment-added="paymentAdded"
  />

  <RefundCancelDialog
    v-if="
      (can('admin.orders.cancel') || can('admin.orders.refund')) &&
      form.registerId &&
      refundCancelDialog.orderId !== null &&
      refundCancelDialog.orderId !== undefined &&
      refundCancelDialog.open
    "
    v-model="refundCancelDialog.open"
    :order-id="refundCancelDialog.orderId"
    :register-id="form.registerId"
    @resolved="resolvedRefundCancel"
  />

  <OrderPrintDialog
    v-if="
      can('admin.orders.print') &&
      form.registerId &&
      form.branchId &&
      showOrderPrintDialog.orderId !== null &&
      showOrderPrintDialog.orderId !== undefined &&
      showOrderPrintDialog.open
    "
    v-model="showOrderPrintDialog.open"
    :branch-id="form.branchId"
    :order-id="showOrderPrintDialog.orderId"
    :register-id="form.registerId"
  />
</template>
