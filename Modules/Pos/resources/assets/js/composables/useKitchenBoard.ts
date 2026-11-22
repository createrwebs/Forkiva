import type { AxiosError } from 'axios'
import { watchDebounced } from '@vueuse/core'
import { computed, onBeforeMount, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'

import { useKitchenAutoReload } from '@/modules/pos/composables/kitchenAutoReload'
import { useKitchenSound } from '@/modules/pos/composables/kitchenSound'
import { useKitchenViewer } from '@/modules/pos/composables/kitchenViewer'
import type { KitchenOrder } from '@/modules/pos/contracts/kitchen'
import { isKitchenOrderVisible } from '@/modules/pos/utils/kitchenBoard'

export function useKitchenBoard() {
  const toast = useToast()
  const { t } = useI18n()
  const { getConfiguration, getOrders } = useKitchenViewer()
  const KITCHEN_ORDER_LIMIT = 150
  const ORDER_EFFECT_DURATION = 2800

  const orders = ref<KitchenOrder[]>([])
  const orderEffects = ref<Record<string, 'new' | 'updated'>>({})
  const lastUpdatedAt = ref<string | null>(null)
  const lastSyncedAt = ref<number | null>(null)
  const syncError = ref(false)
  const hasMore = ref(false)
  const soundMuted = ref(false)
  const processingCount = ref(0)
  let configurationSequence = 0
  let disposed = false

  const meta = ref<{
    branches: any[]
    orderTypes: any[]
    orderStatuses: any[]
    settings: any
  }>({
    branches: [],
    orderTypes: [],
    orderStatuses: [],
    settings: {},
  })

  const loading = ref(true)
  const keepFinishedOrders = computed(
    () => meta.value.settings?.served_orders_visibility?.enabled === true,
  )
  const loadingConfiguration = ref(true)
  const refreshing = ref(false)
  const reloadBranchData = ref(false)

  const filters = ref<{
    branchId: number | null
    type: string | null
    status: string | null
    searchQuery: string | null
  }>({
    branchId: null,
    type: null,
    status: null,
    searchQuery: null,
  })

  let autoReload: ReturnType<typeof useKitchenAutoReload> | null = null
  let ordersRequestSequence = 0
  let hasLoadedOrders = false
  const orderEffectTimers = new Map<string, number>()
  const orderAlertIgnoredKeys = new Set(['status', 'next_status', 'payment_status'])

  const normalizedOrderFilters = computed(() => ({
    branchId: filters.value.branchId,
    search: filters.value.searchQuery?.trim() || null,
    type: filters.value.type || null,
    status: filters.value.status || null,
    limit: KITCHEN_ORDER_LIMIT,
  }))

  const makeOrdersQueryKey = (filterPayload = normalizedOrderFilters.value) =>
    JSON.stringify(filterPayload)

  const ordersQueryKey = computed(() => makeOrdersQueryKey())
  const filtersQueryKey = computed(() =>
    JSON.stringify({
      search: normalizedOrderFilters.value.search,
      type: normalizedOrderFilters.value.type,
      status: normalizedOrderFilters.value.status,
    }),
  )
  const hasActiveFilters = computed(() =>
    Boolean(
      normalizedOrderFilters.value.search ||
      normalizedOrderFilters.value.type ||
      normalizedOrderFilters.value.status,
    ),
  )
  const soundSettings = computed(() =>
    meta.value.settings?.sounds
      ? {
          ...meta.value.settings.sounds,
          enabled: meta.value.settings.sounds.enabled && !soundMuted.value,
        }
      : null,
  )
  const { playNewOrder, playOrderUpdated } = useKitchenSound(soundSettings)

  function normalizedOrderAlertPayload(value: any): any {
    if (Array.isArray(value)) {
      return value.map((item) => normalizedOrderAlertPayload(item))
    }

    if (value && typeof value === 'object') {
      return Object.keys(value)
        .sort()
        .reduce<Record<string, any>>((payload, key) => {
          if (!orderAlertIgnoredKeys.has(key)) {
            payload[key] = normalizedOrderAlertPayload(value[key])
          }

          return payload
        }, {})
    }

    return value
  }

  function orderSignature(order: Record<string, any>): string {
    return JSON.stringify(normalizedOrderAlertPayload(order))
  }

  function clearOrderEffects() {
    for (const timerId of orderEffectTimers.values()) window.clearTimeout(timerId)
    orderEffectTimers.clear()
    orderEffects.value = {}
  }

  function markOrderEffect(orderId: string | number, effect: 'new' | 'updated') {
    const key = String(orderId)
    const existingTimer = orderEffectTimers.get(key)

    if (existingTimer) {
      window.clearTimeout(existingTimer)
    }

    orderEffects.value = {
      ...orderEffects.value,
      [key]: effect,
    }

    orderEffectTimers.set(
      key,
      window.setTimeout(() => {
        const { [key]: _removed, ...remainingEffects } = orderEffects.value

        orderEffects.value = remainingEffects
        orderEffectTimers.delete(key)
      }, ORDER_EFFECT_DURATION),
    )
  }

  function playOrderChangeSound(incomingOrders: Record<string, any>[]) {
    if (!hasLoadedOrders) {
      return
    }

    const currentOrderSignatures = new Map(
      orders.value.map((order) => [String(order.id), orderSignature(order)]),
    )
    let hasNewOrder = false
    let hasUpdatedOrder = false

    for (const order of incomingOrders) {
      const previousSignature = currentOrderSignatures.get(String(order.id))

      if (!previousSignature) {
        hasNewOrder = true
        markOrderEffect(order.id, 'new')
        continue
      }

      if (previousSignature !== orderSignature(order)) {
        hasUpdatedOrder = true
        markOrderEffect(order.id, 'updated')
      }
    }

    if (hasNewOrder) {
      playNewOrder()
    }

    if (hasUpdatedOrder) {
      window.setTimeout(playOrderUpdated, hasNewOrder ? 450 : 0)
    }
  }

  function applyOrdersResponse(body: Record<string, any>) {
    syncError.value = false
    lastSyncedAt.value = Date.now()
    if (!body.not_modified) hasMore.value = Boolean(body.has_more)
    if (!body.not_modified) {
      const incomingOrders = (body.orders || []).filter((order: KitchenOrder) =>
        isKitchenOrderVisible(order, keepFinishedOrders.value),
      )

      playOrderChangeSound(incomingOrders)
      orders.value = incomingOrders
      hasLoadedOrders = true
    }

    lastUpdatedAt.value = body.last_updated_at || null
  }

  function orderMatchesCurrentFilters(order: KitchenOrder): boolean {
    if (!isKitchenOrderVisible(order, keepFinishedOrders.value)) return false
    const filters = normalizedOrderFilters.value

    if (filters.type && order.type?.id !== filters.type) {
      return false
    }

    if (filters.status && order.status?.id !== filters.status) {
      return false
    }

    if (filters.search) {
      const search = filters.search.toLowerCase()
      const orderNumber = String(order.order_number || '').toLowerCase()
      const referenceNo = String(order.reference_no || '').toLowerCase()

      return orderNumber.includes(search) || referenceNo.includes(search)
    }

    return true
  }

  function patchOrder(order: KitchenOrder) {
    const index = orders.value.findIndex((item) => item.id === order.id)

    if (!orderMatchesCurrentFilters(order)) {
      if (index !== -1) {
        orders.value.splice(index, 1)
      }
      return
    }

    if (index !== -1) {
      if (orders.value[index] && orderSignature(orders.value[index]) !== orderSignature(order)) {
        markOrderEffect(order.id, 'updated')
      }

      orders.value[index] = order

      return
    }

    markOrderEffect(order.id, 'new')
    orders.value = [order, ...orders.value].slice(0, KITCHEN_ORDER_LIMIT)
  }

  function applyOrderUpdateResponse(body: Record<string, any>) {
    autoReload?.invalidate()
    ordersRequestSequence += 1
    refreshing.value = false

    if (body.order) {
      patchOrder(body.order)
    }

    // A single ticket response cannot acknowledge changes to other tickets.
    lastUpdatedAt.value = null
  }

  async function loadOrders(branchId?: number | null, isRefreshing = false) {
    if (processingCount.value) return
    autoReload?.invalidate()
    const requestId = ++ordersRequestSequence
    const requestFilters = {
      ...normalizedOrderFilters.value,
      branchId: branchId === undefined ? normalizedOrderFilters.value.branchId : branchId,
    }
    const requestKey = makeOrdersQueryKey(requestFilters)

    if (isRefreshing) refreshing.value = true
    else loading.value = true

    try {
      const response = await getOrders(requestFilters)

      if (!disposed && requestId === ordersRequestSequence && ordersQueryKey.value === requestKey) {
        applyOrdersResponse(response.data.body)
      }
    } catch (error) {
      if (!disposed && requestId === ordersRequestSequence) {
        syncError.value = true
        toast.error(
          (
            error as AxiosError<{
              message?: string
            }>
          ).response?.data?.message || t('core::errors.an_unexpected_error_occurred'),
        )
      }
    } finally {
      if (requestId === ordersRequestSequence) {
        loading.value = false
        refreshing.value = false
      }
    }
  }

  async function loadConfiguration(branchId?: number | null) {
    const configurationId = ++configurationSequence
    autoReload?.invalidate()
    ordersRequestSequence += 1
    if (branchId) reloadBranchData.value = true
    else loadingConfiguration.value = true

    try {
      orders.value = []
      clearOrderEffects()
      hasLoadedOrders = false
      lastUpdatedAt.value = null
      lastSyncedAt.value = null
      hasMore.value = false
      meta.value.orderTypes = []
      const response = await getConfiguration(branchId)

      if (disposed || configurationId !== configurationSequence) return

      if (!branchId) {
        meta.value.branches = response.data.body.branches
      }

      meta.value.settings = response.data.body.settings
      meta.value.orderTypes = response.data.body.order_types
      meta.value.orderStatuses = response.data.body.order_statuses
      filters.value.branchId = response.data.body.branch_id

      await loadOrders(filters.value.branchId)
    } catch (error) {
      if (disposed || configurationId !== configurationSequence) return
      syncError.value = true
      loading.value = false
      toast.error(
        (
          error as AxiosError<{
            message?: string
          }>
        ).response?.data?.message || t('core::errors.an_unexpected_error_occurred'),
      )
    } finally {
      if (configurationId === configurationSequence) {
        loadingConfiguration.value = false
        reloadBranchData.value = false
      }
    }
  }

  const autoReloadSettings = computed(() => meta.value.settings?.auto_refresh ?? null)

  autoReload = useKitchenAutoReload({
    settings: autoReloadSettings,
    fetchData: async ({ isCurrent }) => {
      if (
        loadingConfiguration.value ||
        reloadBranchData.value ||
        loading.value ||
        refreshing.value ||
        processingCount.value ||
        !filters.value.branchId
      )
        return { last_updated_at: lastUpdatedAt.value }
      const requestFilters = normalizedOrderFilters.value
      const requestKey = ordersQueryKey.value
      const requestLastUpdatedAt = lastUpdatedAt.value
      let response
      try {
        response = await getOrders(requestFilters, requestLastUpdatedAt)
      } catch (error) {
        if (isCurrent()) syncError.value = true
        throw error
      }
      const body = response.data.body

      if (
        isCurrent() &&
        ordersQueryKey.value === requestKey &&
        lastUpdatedAt.value === requestLastUpdatedAt
      ) {
        applyOrdersResponse(body)
      }

      return {
        last_updated_at: body.last_updated_at || lastUpdatedAt.value,
      }
    },
  })

  watch(
    () => filters.value.branchId,
    async (newVal, oldVal) => {
      if (newVal && oldVal !== null && newVal !== oldVal) {
        await loadConfiguration(newVal)
      }
    },
  )

  watchDebounced(
    () => filtersQueryKey.value,
    async (newVal, oldVal) => {
      if (!filters.value.branchId || newVal === oldVal) {
        return
      }

      lastUpdatedAt.value = null
      clearOrderEffects()
      hasLoadedOrders = false
      await loadOrders(filters.value.branchId, true)
    },
    { debounce: 250, maxWait: 600 },
  )

  function setProcessing(active: boolean) {
    processingCount.value = Math.max(0, processingCount.value + (active ? 1 : -1))
    if (active) {
      autoReload?.invalidate()
      ordersRequestSequence += 1
      refreshing.value = false
    }
  }

  async function refresh() {
    if (!filters.value.branchId || !meta.value.orderTypes.length) await loadConfiguration()
    else await loadOrders(filters.value.branchId, true)
  }

  onBeforeMount(loadConfiguration)
  onBeforeUnmount(() => {
    disposed = true
    ordersRequestSequence += 1
    configurationSequence += 1
    clearOrderEffects()
  })

  return {
    keepFinishedOrders,
    orders,
    orderEffects,
    filters,
    meta,
    hasActiveFilters,
    hasMore,
    lastSyncedAt,
    syncError,
    soundMuted,
    loading,
    loadingConfiguration,
    refreshing,
    reloadBranchData,
    processingCount,
    refresh,
    applyOrderUpdateResponse,
    onActionFailed: () => {
      syncError.value = true
      lastUpdatedAt.value = null
    },
    setProcessing,
    isPaused: autoReload.isPaused,
  }
}
