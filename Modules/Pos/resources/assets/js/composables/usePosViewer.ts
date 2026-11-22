import { usePosDraftForm } from '@/modules/pos/composables/usePosDraftForm'
import { listDrafts } from '@/modules/cart/services/draftStorage'
import { quote } from '@/modules/order/api/order.api'
import { useAuthStore } from '@/modules/auth/stores/authStore'
import type { Cart, UseCart } from '@/modules/cart/composables/cart.ts'
import type {
  PosCustomerLoadOptions,
  PosForm,
  PosMeta,
  PosProductLoadOptions,
} from '@/modules/pos/contracts/posViewer.ts'
import type { AxiosError } from 'axios'
import { onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { useCart } from '@/modules/cart/composables/cart.ts'
import {
  getCustomers as fetchCustomers,
  getProducts as fetchProducts,
  getConfiguration,
  getMenuCategories,
} from '@/modules/pos/api/posViewer.api.ts'
import { useQintrix } from '@/modules/printer/composables/qintrix.ts'

const PRODUCT_PER_PAGE = 24
const CUSTOMER_PER_PAGE = 20

const defaultMeta: PosMeta = {
  branches: [],
  menus: [],
  registers: [],
  orderTypes: [],
  deliveryMethods: [],
  paymentCollectionChannels: [],
  categories: [],
  products: [],
  productsPagination: null,
  waiters: [],
  customers: [],
  customersPagination: null,
  discounts: [],
  refundPaymentMethods: [],
  directions: [],
  reasons: {},
  order: null,
  currency: 'JOD',
  sounds: undefined,
}

const defaultForm: PosForm = {
  mode: 'create',
  branchId: null,
  menuId: null,
  registerId: null,
  waiter: null,
  sessionId: null,
  table: null,
  skipFirst: false,
  refundPaymentMethod: null,
  refundGiftCardCode: null,
  meta: {
    notes: null,
    guestCount: 1,
    carPlate: null,
    carDescription: null,
    scheduledAt: null,
  },
  loading: false,
  loadingMenuItems: false,
  loadingProducts: false,
  loadingMoreProducts: false,
  loadingCustomers: false,
  reloadBranchData: false,
}

export function usePosViewer() {
  const qintrix = useQintrix()
  let pricingContext: Record<string, any> = {}
  // Clear once on page entry; later register/branch configuration keeps its own draft.
  let initialConfiguration = true
  const cart: UseCart = useCart('pos')
  let display: BroadcastChannel | null = null
  function publishDisplay() {
    if (!cart.draft.value || !useAuthStore().isAuthenticated) return
    const value = cart.data.value
    display?.postMessage({
      type: 'snapshot',
      cart: JSON.parse(
        JSON.stringify({
          items: value.items.map((item) => ({
            id: item.id,
            item: { name: item.item.name, thumbnail: item.item.thumbnail },
            qty: item.qty,
            unitPrice: item.unitPrice,
            subtotal: item.subtotal,
            taxTotal: item.taxTotal,
            total: item.total,
            options: item.options,
            taxes: item.taxes,
            actions: [],
            isGift: !!item.loyaltyGift,
          })),
          quantity: value.quantity,
          subTotal: value.subTotal,
          total: value.total,
          taxes: value.taxes,
          charges: value.charges,
          discount: value.discount,
          orderType: value.orderType,
          customer: null,
          orderReference: cart.draft.value.order?.reference_no,
        }),
      ),
    })
  }
  watch(
    () => cart.draft.value?.key,
    (key) => {
      display?.postMessage({ type: 'closed' })
      display?.close()
      display = null
      if (key && typeof BroadcastChannel !== 'undefined') {
        display = new BroadcastChannel(`forkiva:customer-display:${key}`)
        display.onmessage = (event) => {
          if (event.data?.type === 'request-snapshot') publishDisplay()
        }
        publishDisplay()
      }
    },
  )
  watch(cart.data, publishDisplay, { deep: true })
  const heartbeat = setInterval(publishDisplay, 5000)
  onBeforeUnmount(() => {
    display?.postMessage({ type: 'closed' })
    display?.close()
    clearInterval(heartbeat)
  })

  const toast = useToast()
  const { t } = useI18n()

  const meta = ref<PosMeta>(structuredClone(defaultMeta))
  const form = ref<PosForm>(structuredClone(defaultForm))
  cart.setQuoteLoader(() =>
    quote(
      cart.payload({
        branch_id: form.value.branchId,
        menu_id: form.value.menuId,
        register_id: form.value.registerId,
        session_id: form.value.sessionId,
        table_id: form.value.table?.id,
        waiter_id: form.value.waiter?.id,
        submit_action: 'hold_order',
        guest_count: form.value.meta.guestCount,
        notes: form.value.meta.notes,
        scheduled_at: form.value.meta.scheduledAt,
        car_plate: form.value.meta.carPlate,
        car_description: form.value.meta.carDescription,
      }),
      cart.draft.value?.order?.id,
    ),
  )
  const skipNextMenuLoad = ref(false)
  const menuItemsRequestSequence = ref(0)
  const productRequestSequence = ref(0)
  const customerRequestSequence = ref(0)
  const productQuery = ref<{ search: string; categoryIds: Array<number | string> }>({
    search: '',
    categoryIds: [],
  })
  const customerQuery = ref<{ search: string }>({ search: '' })

  const hasOpenSession = computed(
    () =>
      meta.value.registers.length > 0 &&
      form.value.sessionId !== null &&
      form.value.sessionId !== undefined,
  )

  const hasConfigurationErrors = computed(
    () =>
      meta.value.branches.length === 0 ||
      meta.value.menus.length === 0 ||
      meta.value.registers.length === 0 ||
      !form.value.registerId ||
      !form.value.sessionId,
  )

  watch(
    () => form.value.branchId,
    async (newValue, oldValue) => {
      if (oldValue && newValue !== oldValue) {
        cart.detach()
        await loadConfiguration(newValue as number)
      }
    },
  )

  watch(
    () => form.value.registerId,
    async (newValue) => {
      const register = meta.value.registers.find(
        (register: Record<string, any>) => register.id === newValue,
      )
      form.value.sessionId = register && register?.session ? register.session.id : null
    },
  )

  watch(
    () => form.value.menuId,
    async (newValue, oldValue) => {
      if (skipNextMenuLoad.value) {
        skipNextMenuLoad.value = false
        return
      }

      if (!hasConfigurationErrors.value && newValue && newValue !== oldValue) {
        await loadMenuItems()
      }
    },
  )

  const loadConfiguration = async (branchId?: number) => {
    if (branchId) {
      form.value.reloadBranchData = true
    }
    form.value.loading = true
    let shouldLoadMenuItems = false
    let shouldLoadCustomers = false
    try {
      let response: Record<string, any>
      try {
        response = (await getConfiguration(branchId)).data.body
      } catch (error: any) {
        if (error?.response) throw error
        const cached = (await listDrafts())
          .filter(
            (candidate) =>
              candidate.channel === 'pos' &&
              candidate.accountId === useAuthStore().activeAccountId &&
              (!branchId || candidate.context.branch_id === branchId),
          )
          .sort((a, b) => b.updatedAt - a.updatedAt)[0]
        if (!cached?.context.posConfiguration) throw error
        response = cached.context.posConfiguration
        toast.info(t('cart::local.offline'))
      }
      if (!branchId) {
        meta.value.branches = response.branches
        meta.value.directions = response.directions
        meta.value.reasons = response.reasons
      }

      meta.value.waiters = response.waiters
      meta.value.orderTypes = response.order_types
      meta.value.deliveryMethods = response.delivery_methods || []
      meta.value.paymentCollectionChannels = response.payment_collection_channels || []
      meta.value.paymentCollectionChannelMap = response.payment_collection_channel_map || {}
      meta.value.menus = response.menus
      meta.value.discounts = response.discounts
      meta.value.registers = response.registers
      meta.value.currency = response.currency
      meta.value.sounds = response.sounds
      meta.value.categories = []
      meta.value.products = []
      meta.value.productsPagination = null
      meta.value.customers = []
      meta.value.customersPagination = null

      skipNextMenuLoad.value = true
      form.value.branchId = response.branch_id
      form.value.menuId = response.menu_id
      form.value.registerId = response.register_id
      form.value.sessionId = response.session_id
      const defaultOrderType =
        meta.value.orderTypes.find(
          (type) => type.id !== 'dine_in' && type.id === response.cart?.orderType?.id,
        ) || meta.value.orderTypes.find((type) => type.id !== 'dine_in')
      pricingContext = {
        currency: response.currency,
        precision: response.precision,
        global_taxes: response.global_taxes,
        defaultOrderType: defaultOrderType
          ? { id: defaultOrderType.id, name: defaultOrderType.name }
          : { id: null, name: null },
      }
      await cart.configure(
        `${useAuthStore().activeAccountId}:${response.branch_id}:${response.register_id}:${response.session_id}`,
        {
          ...pricingContext,
          branch_id: response.branch_id,
          register_id: response.register_id,
          session_id: response.session_id,
        },
        { ...response.cart, orderType: pricingContext.defaultOrderType },
        undefined,
        { clearDraft: initialConfiguration },
      )
      initialConfiguration = false
      const { agent: _agent, ...safeConfiguration } = response
      await cart.setContext({ posConfiguration: safeConfiguration })
      if (cart.draft.value?.context.form)
        form.value = { ...form.value, ...cart.draft.value.context.form }

      // Repair an older unfinished ticket that retained dine-in after its table was cleared.
      if (
        cart.data.value.orderType?.id === 'dine_in' &&
        !form.value.table &&
        !cart.draft.value?.order &&
        !cart.draft.value?.submission
      ) {
        if (defaultOrderType?.id) await cart.storeOrderType(defaultOrderType.id)
        else await cart.removeOrderType()
      }

      if (response.agent) {
        qintrix.initClient(response.agent.base_url, response.agent.api_key)
      }
      shouldLoadMenuItems = !!response.menu_id
      shouldLoadCustomers = !!response.branch_id
    } catch (error: unknown) {
      toast.error(
        (
          error as AxiosError<{
            message?: string
          }>
        ).response?.data?.message || t('core::errors.an_unexpected_error_occurred'),
      )
    } finally {
      form.value.loading = false
      form.value.reloadBranchData = false
    }

    if (shouldLoadMenuItems) {
      void loadMenuItems()
    }

    if (shouldLoadCustomers) {
      void loadCustomers({ reset: true, search: '' })
    }
  }

  const loadMenuItems = async () => {
    if (!form.value.menuId) {
      return
    }
    const menuId = form.value.menuId
    const currentRequest = ++menuItemsRequestSequence.value
    form.value.loadingMenuItems = true
    meta.value.categories = []
    meta.value.products = []
    meta.value.productsPagination = null
    try {
      const response = (await getMenuCategories(menuId)).data.body
      if (currentRequest !== menuItemsRequestSequence.value) {
        return
      }
      meta.value.categories = response.categories
      await loadProducts({ reset: true, search: '', categoryIds: [] })
    } catch (error: any) {
      if (currentRequest === menuItemsRequestSequence.value) {
        if (!error?.response)
          meta.value.products = [
            ...new Map(
              (cart.draft.value?.lines || []).map((line) => [line.product_id, line.product]),
            ).values(),
          ] as PosMeta['products']
        toast.error(
          (
            error as AxiosError<{
              message?: string
            }>
          ).response?.data?.message || t('core::errors.an_unexpected_error_occurred'),
        )
      }
    } finally {
      if (currentRequest === menuItemsRequestSequence.value) {
        form.value.loadingMenuItems = false
      }
    }
  }

  const normalizeCategoryIds = (categoryIds?: Array<number | string>) => [
    ...new Set((categoryIds || []).filter((id) => id !== null && id !== undefined && id !== '')),
  ]

  const appendUniqueById = (currentItems: any[], nextItems: any[]) => {
    const seen = new Set(currentItems.map((item) => item.id))
    const merged = [...currentItems]

    for (const item of nextItems) {
      if (!seen.has(item.id)) {
        seen.add(item.id)
        merged.push(item)
      }
    }

    return merged
  }

  const loadProducts = async (options: PosProductLoadOptions = {}) => {
    const menuId = form.value.menuId

    if (!menuId) {
      return
    }

    const search = options.search ?? productQuery.value.search
    const categoryIds =
      options.categoryIds === undefined
        ? productQuery.value.categoryIds
        : normalizeCategoryIds(options.categoryIds)
    const page =
      options.page ?? (options.reset ? 1 : (meta.value.productsPagination?.current_page || 0) + 1)
    const currentRequest = ++productRequestSequence.value

    productQuery.value = { search, categoryIds }

    if (options.reset || page === 1) {
      meta.value.products = []
      meta.value.productsPagination = null
      form.value.loadingProducts = true
    } else {
      form.value.loadingMoreProducts = true
    }

    try {
      const response = (
        await fetchProducts(menuId, {
          search: search || undefined,
          category_ids: categoryIds,
          page,
          per_page: PRODUCT_PER_PAGE,
        })
      ).data.body

      if (currentRequest !== productRequestSequence.value) {
        return
      }

      const products = response.data || []
      meta.value.products =
        options.reset || page === 1 ? products : appendUniqueById(meta.value.products, products)
      meta.value.productsPagination = response.pagination || null
    } catch (error: unknown) {
      if (currentRequest === productRequestSequence.value) {
        toast.error(
          (
            error as AxiosError<{
              message?: string
            }>
          ).response?.data?.message || t('core::errors.an_unexpected_error_occurred'),
        )
      }
    } finally {
      if (currentRequest === productRequestSequence.value) {
        form.value.loadingProducts = false
        form.value.loadingMoreProducts = false
      }
    }
  }

  const loadCustomers = async (options: PosCustomerLoadOptions = {}) => {
    const search = options.search ?? customerQuery.value.search
    const page =
      options.page ?? (options.reset ? 1 : (meta.value.customersPagination?.current_page || 0) + 1)
    const currentRequest = ++customerRequestSequence.value

    customerQuery.value = { search }

    if (options.reset || page === 1) {
      meta.value.customers = []
      meta.value.customersPagination = null
    }
    form.value.loadingCustomers = true

    try {
      const response = (
        await fetchCustomers({
          branch_id: form.value.branchId,
          search: search || undefined,
          page,
          per_page: CUSTOMER_PER_PAGE,
        })
      ).data.body

      if (currentRequest !== customerRequestSequence.value) {
        return
      }

      const customers = response.data || []
      meta.value.customers =
        options.reset || page === 1 ? customers : appendUniqueById(meta.value.customers, customers)
      meta.value.customersPagination = response.pagination || null
    } catch (error: unknown) {
      if (currentRequest === customerRequestSequence.value) {
        toast.error(
          (
            error as AxiosError<{
              message?: string
            }>
          ).response?.data?.message || t('core::errors.an_unexpected_error_occurred'),
        )
      }
    } finally {
      if (currentRequest === customerRequestSequence.value) {
        form.value.loadingCustomers = false
      }
    }
  }

  /** Restore order selections and edit metadata in one durable write before updating the form. */
  const initOrder = async (response: Record<string, any>) => {
    if (cart.draft.value?.submission) return
    const savedForm = {
      mode: 'edit',
      menuId: form.value.menuId,
      meta: response.form.meta,
      table: response.form.table,
      waiter: response.form.waiter,
      refundPaymentMethod: null,
      refundGiftCardCode: null,
    }
    const restored = await cart
      .resetCart(response.cart, response.edit_version, {
        editedOrder: response.order,
        refundPaymentMethods: response.refund_payment_methods,
        form: savedForm,
      })
      .catch(() => false)
    if (!restored) return
    meta.value.order = response.order
    meta.value.refundPaymentMethods = response.refund_payment_methods

    form.value = {
      ...form.value,
      ...response.form,
      refundPaymentMethod: null,
      refundGiftCardCode: null,
      mode: 'edit',
      skipFirst: true,
    }
  }

  watch(
    () => cart.draft.value?.context.editedOrder,
    (order) => {
      meta.value.order = order || null
      meta.value.refundPaymentMethods = cart.draft.value?.context.refundPaymentMethods || []
    },
  )
  const reset = (cartData?: Cart) => {
    if (cartData) {
      cart.resetCart(cartData)
    }
    form.value = {
      ...form.value,
      meta: { ...structuredClone(defaultForm).meta },
      mode: 'create',
      refundPaymentMethod: null,
      refundGiftCardCode: null,
      waiter: null,
      table: null,
    }
  }

  usePosDraftForm(cart, form, reset)
  watch(
    () => [form.value.registerId, form.value.sessionId],
    async () => {
      if (!form.value.loading && form.value.branchId)
        await cart.configure(
          `${useAuthStore().activeAccountId}:${form.value.branchId}:${form.value.registerId}:${form.value.sessionId}`,
          {
            ...pricingContext,
            branch_id: form.value.branchId,
            register_id: form.value.registerId,
            session_id: form.value.sessionId,
          },
        )
    },
  )
  return {
    cart,
    reset,
    initOrder,
    loadConfiguration,
    loadMenuItems,
    loadProducts,
    loadCustomers,
    hasConfigurationErrors,
    hasOpenSession,
    meta,
    qintrix,
    form,
  }
}
