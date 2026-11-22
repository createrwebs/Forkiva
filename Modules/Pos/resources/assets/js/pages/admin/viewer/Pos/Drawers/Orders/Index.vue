<script lang="ts" setup>
import WorkspacePanel from '../../WorkspacePanel.vue'
import type { useQintrix } from '@/modules/printer/composables/qintrix.ts'
import { watchDebounced } from '@vueuse/core'
import { computed, nextTick, onBeforeMount, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import PosExtensionRenderer from '@/modules/pos/components/PosExtensionRenderer.vue'
import Tabs from '@/modules/pos/pages/admin/viewer/Pos/Drawers/Orders/Tabs.vue'
import { useOrder } from '@/modules/order/composables/order.ts'
import Empty from './Empty.vue'
import Filters from './Filters.vue'
import Order from './Order.vue'

const props = defineProps<{
  modelValue: boolean
  embedded?: boolean
  branchId: number | null
  qintrix: ReturnType<typeof useQintrix>
  version: number
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

const { activeOrders, upcomingOrders } = useOrder()
const { t } = useI18n()
const { can } = useAuth()
const toast = useToast()

const DEFAULT_PER_PAGE = 15
const tab = ref<string>('active')
const loadingInitial = ref(false)
const loadingMore = ref(false)
const suppressNextFilterReload = ref(false)
const requestSequence = ref(0)
const ordersWrapperRef = ref<HTMLElement | null>(null)

type DrawerPagination = {
  current_page: number
  from: number | null
  last_page: number
  per_page: number
  to: number | null
  total: number
}

type DrawerMetaState = {
  statuses: { id: string; name: string }[]
  orderTypes: { id: string; name: string }[]
  paymentStatuses: { id: string; name: string }[]
}

type DrawerCacheEntry = {
  items: any[]
  pagination: DrawerPagination | null
  meta: DrawerMetaState
}

const orders = ref<any[]>([])
const pagination = ref<DrawerPagination | null>(null)
const drawerCache = reactive<Record<string, DrawerCacheEntry>>({})

const meta = reactive({
  statuses: [] as { id: string; name: string }[],
  orderTypes: [] as { id: string; name: string }[],
  paymentStatuses: [] as { id: string; name: string }[],
})

const filters = reactive({
  search: '',
  types: [] as string[],
  statuses: [] as string[],
  paymentStatuses: [] as string[],
})

onBeforeMount(() => {
  if (can('admin.orders.active')) tab.value = 'active'
  else if (can('admin.orders.upcoming')) tab.value = 'upcoming'
})

const busy = computed(() => loadingInitial.value || loadingMore.value)
const hasMore = computed(() => {
  return !!pagination.value && pagination.value.current_page < pagination.value.last_page
})
const normalizedFilters = computed(() => ({
  search: filters.search.trim(),
  types: [...filters.types].filter(Boolean).sort(),
  statuses: [...filters.statuses].filter(Boolean).sort(),
  paymentStatuses: [...filters.paymentStatuses].filter(Boolean).sort(),
}))
const requestKey = computed(() =>
  JSON.stringify({
    tab: tab.value,
    branchId: props.branchId,
    ...normalizedFilters.value,
  }),
)

const close = () => emit('update:modelValue', false)

watch(
  () => props.modelValue,
  async (newVal) => {
    if (newVal) await ensureCurrentState()
  },
)

watch(
  () => props.version,
  async () => {
    clearCache()

    if (props.modelValue) {
      await loadPage(1, { replace: true, force: true, silent: true })
    }
  },
)

function resetFilters() {
  filters.search = ''
  filters.types = []
  filters.statuses = []
  filters.paymentStatuses = []
}

function getMetaSnapshot(): DrawerMetaState {
  return {
    statuses: [...meta.statuses],
    orderTypes: [...meta.orderTypes],
    paymentStatuses: [...meta.paymentStatuses],
  }
}

function applyMeta(filtersData?: Partial<DrawerMetaState>) {
  meta.statuses = filtersData?.statuses || []
  meta.orderTypes = filtersData?.orderTypes || []
  meta.paymentStatuses = filtersData?.paymentStatuses || []
}

function applyCache(key: string) {
  const cached = drawerCache[key]
  if (!cached) {
    return false
  }

  orders.value = [...cached.items]
  pagination.value = cached.pagination ? { ...cached.pagination } : null
  applyMeta(cached.meta)

  return true
}

function storeCache(key: string) {
  drawerCache[key] = {
    items: [...orders.value],
    pagination: pagination.value ? { ...pagination.value } : null,
    meta: getMetaSnapshot(),
  }
}

function clearCache() {
  for (const key of Object.keys(drawerCache)) delete drawerCache[key]
}

async function loadPage(
  page: number,
  {
    replace = false,
    force = false,
    silent = false,
    replacePage = false,
  }: {
    replace?: boolean
    force?: boolean
    silent?: boolean
    replacePage?: boolean
  } = {},
) {
  const key = requestKey.value
  let currentRequest = 0

  if (page === 1 && !force && applyCache(key)) {
    await nextTick()
    handleOrdersScroll()

    return
  }

  try {
    if (page === 1) {
      if (!silent) {
        loadingInitial.value = true
      }
    } else if (!silent) {
      loadingMore.value = true
    }

    currentRequest = ++requestSequence.value

    const response =
      tab.value === 'upcoming'
        ? await upcomingOrders(props.branchId, {
            search: normalizedFilters.value.search,
            types: normalizedFilters.value.types,
            statuses: normalizedFilters.value.statuses,
            payment_statuses: normalizedFilters.value.paymentStatuses,
            page,
            per_page: DEFAULT_PER_PAGE,
          })
        : await activeOrders(props.branchId, {
            search: normalizedFilters.value.search,
            types: normalizedFilters.value.types,
            statuses: normalizedFilters.value.statuses,
            payment_statuses: normalizedFilters.value.paymentStatuses,
            page,
            per_page: DEFAULT_PER_PAGE,
          })

    if (currentRequest !== requestSequence.value) {
      return
    }

    const data = response.data.body
    const pageItems = data.data || []

    if (replacePage && page > 1) {
      const perPage = data.pagination?.per_page || pagination.value?.per_page || DEFAULT_PER_PAGE
      const nextOrders = [...orders.value]

      nextOrders.splice((page - 1) * perPage, perPage, ...pageItems)
      orders.value = nextOrders
    } else {
      orders.value = replace || page === 1 ? pageItems : [...orders.value, ...pageItems]
    }
    pagination.value = data.pagination || null
    applyMeta({
      statuses: data.filters?.statuses || [],
      orderTypes: data.filters?.order_types || [],
      paymentStatuses: data.filters?.payment_statuses || [],
    })
    storeCache(key)
    await nextTick()
    handleOrdersScroll()
  } catch {
    toast.error(t('core::errors.an_unexpected_error_occurred'))
  } finally {
    if (currentRequest === requestSequence.value) {
      loadingInitial.value = false
      loadingMore.value = false
    }
  }
}

async function ensureCurrentState() {
  if (applyCache(requestKey.value)) {
    await nextTick()
    handleOrdersScroll()

    return
  }

  await loadPage(1, { replace: true, force: true })
}

function getOrderPage(index: number): number {
  const perPage = pagination.value?.per_page || DEFAULT_PER_PAGE

  return Math.floor(index / perPage) + 1
}

async function refresh(options: { silent?: boolean; page?: number } | number = {}) {
  const refreshOptions = typeof options === 'number' ? { page: options, silent: true } : options
  const page = Math.max(1, refreshOptions.page || pagination.value?.current_page || 1)

  clearCache()
  await loadPage(page, {
    replace: page === 1,
    replacePage: page > 1,
    force: true,
    silent: refreshOptions.silent ?? true,
  })
}

async function loadMore() {
  if (!hasMore.value || loadingInitial.value || loadingMore.value || !pagination.value) {
    return
  }

  await loadPage(pagination.value.current_page + 1)
}

function handleOrdersScroll() {
  if (
    !props.modelValue ||
    loadingInitial.value ||
    loadingMore.value ||
    !hasMore.value ||
    !ordersWrapperRef.value
  ) {
    return
  }

  const element = ordersWrapperRef.value
  const remaining = element.scrollHeight - element.scrollTop - element.clientHeight

  if (remaining <= 120) {
    void loadMore()
  }
}

async function handleTabChange() {
  suppressNextFilterReload.value = true
  resetFilters()
  await loadPage(1, { replace: true, force: true })
}

watchDebounced(
  () => normalizedFilters.value,
  async () => {
    if (props.modelValue) {
      if (suppressNextFilterReload.value) {
        suppressNextFilterReload.value = false

        return
      }

      await loadPage(1, { replace: true, force: true })
    }
  },
  { debounce: 250, maxWait: 600 },
)

defineExpose({ refresh })

function initOrder(response: Record<string, unknown>) {
  emit('init-order', response)
  close()
}
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
              color="#2980b9"
              icon="tabler-salad"
              size="25"
            />
            {{ t('pos::pos_viewer.order_management') }}
          </h3>

          <VBtn
            v-if="!embedded"
            :aria-label="t('admin::admin.buttons.close')"
            color="grey-200"
            icon="tabler-x"
            @click="close"
          />
        </div>

        <Tabs
          v-model="tab"
          :disabled="busy"
          @on-click="handleTabChange"
        />

        <Filters
          :disabled="busy"
          :filters="filters"
          :loading="busy"
          :meta="meta"
        />

        <div
          ref="ordersWrapperRef"
          class="orders-wrapper mt-3"
          @scroll.passive="handleOrdersScroll"
        >
          <template v-if="!loadingInitial && orders.length > 0">
            <Order
              v-for="(order, index) in orders"
              :key="order.id"
              :branch-id="branchId"
              :order="order"
              :qintrix="qintrix"
              @cancel-order="(orderId: number | string) => $emit('cancel-order', orderId)"
              @init-order="initOrder"
              @print-order="(orderId: number | string) => $emit('print-order', orderId)"
              @refresh="refresh(getOrderPage(index))"
              @refund-order="(orderId: number | string) => $emit('refund-order', orderId)"
              @store-payment="(orderId: number | string) => $emit('store-payment', orderId)"
              @view-order="(orderId: number | string) => $emit('view-order', orderId)"
            />

            <div
              v-if="loadingMore"
              class="orders-loading orders-loading--append"
            >
              <VProgressCircular
                color="primary"
                indeterminate
                size="32"
              />
            </div>
          </template>

          <template v-else-if="!loadingInitial && orders.length === 0">
            <Empty :tab="tab" />
          </template>

          <div
            v-else-if="loadingInitial"
            class="orders-loading"
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

  <PosExtensionRenderer mount="orders-drawer" />
</template>

<style lang="scss" scoped>
.orders-loading {
  display: flex;
  justify-content: center;
  align-items: center;
  height: 50%;
}

.orders-loading--append {
  height: auto;
  min-height: 4rem;
}

.orders-wrapper {
  height: 79vh;
  overflow: auto;
}
</style>
