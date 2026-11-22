import { computed, getCurrentScope, onScopeDispose, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { useAuthStore } from '@/modules/auth/stores/authStore'
import { generateUuid } from '@/modules/core/utils/uuid'
import type { CartChannel, Draft } from '@/modules/cart/contracts/draft'
import { useDraftStore } from '@/modules/cart/stores/drafts'
import {
  DraftConflict,
  listDrafts,
  moveDraft,
  readDraft,
  writeDraft,
} from '@/modules/cart/services/draftStorage'

import { normalizeOptions, validateProductOptions } from '@/modules/cart/services/options'
import { estimateDraft } from '@/modules/cart/services/estimate'

import type { Cart } from '@/modules/cart/contracts/cart'

// Preserve existing consumer imports while keeping transport/display shapes in contracts.
export type * from '@/modules/cart/contracts/cart'

/** Manage an ownership-scoped local draft; all mutations commit through revision checks. */
export function useCart(channel: CartChannel = 'pos') {
  const store = useDraftStore()
  const auth = useAuthStore()
  const { t, locale } = useI18n()
  const toast = useToast()
  const key = ref('')
  const loading = ref(false)
  const submitting = ref(false)
  const syncFailed = ref(false)
  const recoverableDrafts = ref<Draft[]>([])
  const draft = computed(() => store.drafts[key.value])
  const empty = (): Draft => ({
    key: key.value,
    schema: 1,
    channel,
    accountId: channel === 'pos' ? auth.activeAccountId : null,
    revision: 0,
    updatedAt: Date.now(),
    expiresAt: null,
    lines: [],
    context: {},
    order: null,
    customer: null,
    orderType: { id: null, name: null },
  })
  const lineErrors = ref<Record<string, string[]>>({})
  const data = computed<Cart>(() => {
    const result = estimateDraft(draft.value || empty(), locale.value)
    result.items = result.items.map((item) => ({
      ...item,
      validationErrors: lineErrors.value[item.id] || [],
    }))
    return result
  })
  /** Map indexed API validation paths back to stable line identities in the submitted snapshot. */
  function setValidationErrors(error: any, payload: Record<string, any>) {
    const mapped: Record<string, string[]> = {}
    for (const [path, messages] of Object.entries(error?.response?.data?.errors || {})) {
      const index = path.match(/^items\.(\d+)(?:\.|$)/)?.[1]
      const id = index === undefined ? undefined : payload.items?.[Number(index)]?.line_id
      if (id)
        mapped[id] = [
          ...(mapped[id] || []),
          ...(Array.isArray(messages) ? messages.map(String) : [String(messages)]),
        ]
    }
    lineErrors.value = mapped
  }
  const pendingProducts = ref<Array<{ token: number; name: string; qty: number }>>([])
  const processing = computed(
    () =>
      loading.value ||
      submitting.value ||
      !draft.value ||
      !!draft.value?.submission ||
      (channel === 'pos' &&
        (!auth.isAuthenticated || auth.activeAccountId !== draft.value?.accountId)),
  )
  const hasPendingMutations = computed(() => loading.value)
  const visibleItems = computed(() => data.value.items)
  let queue: Promise<unknown> = Promise.resolve()
  let quoteLoader: (() => Promise<any>) | undefined
  const setQuoteLoader = (loader: () => Promise<any>) => {
    quoteLoader = loader
  }
  const deliveryPricingError = (error: any): string | null => {
    if (error?.response?.status !== 422) return null
    const value = error.response.data?.errors?.customer_address_id
    if (Array.isArray(value)) return value[0] ? String(value[0]) : null
    return value ? String(value) : null
  }
  async function preview() {
    if (!quoteLoader || !draft.value?.lines.length) return true
    const revision = draft.value.revision
    try {
      const result = await quoteLoader()
      if (draft.value?.revision === revision) await setQuote(result.data.body)
      return true
    } catch (error) {
      const message = deliveryPricingError(error)
      if (
        message &&
        draft.value?.revision === revision &&
        draft.value.orderType.id === 'delivery' &&
        draft.value.delivery?.customer_address_id
      ) {
        await change(
          (next) => {
            next.deliveryDisplay = {
              ...next.deliveryDisplay,
              pricing: {
                available: false,
                issue: 'delivery_quote_invalid',
                message,
              },
            }
          },
          true,
          revision,
        )
      }
      throw error
    }
  }
  async function benefit(action: (next: Draft) => void) {
    const previous = draft.value ? copy(draft.value) : null
    if (!(await change(action))) return false
    try {
      return await preview()
    } catch (error) {
      if (previous && draft.value?.revision === previous.revision + 1)
        await change((next) => {
          next.discountId = previous.discountId
          next.voucherCode = previous.voucherCode
          next.giftId = previous.giftId
        })
      throw error
    }
  }
  const bus =
    typeof BroadcastChannel === 'undefined' ? null : new BroadcastChannel('forkiva:drafts')
  const copy = <T>(value: T): T => JSON.parse(JSON.stringify(value))
  const showError = (error: any) =>
    toast.error(
      error?.response?.data?.message ||
        t(
          error instanceof DraftConflict
            ? error.message === 'submission_pending'
              ? 'cart::local.pending'
              : 'cart::local.conflict'
            : 'cart::local.storage_failed',
        ),
    )
  async function getCart() {
    if (!key.value) return false
    const loaded = await readDraft(key.value)
    if (loaded) store.set(loaded)
    else delete store.drafts[key.value]
    syncFailed.value = false
    return true
  }
  if (bus) {
    bus.onmessage = (event) => {
      if (event.data.accountRemoved === draft.value?.accountId) {
        store.forgetAccount(event.data.accountRemoved)
        return
      }
      if (event.data.key === key.value) void getCart().catch(showError)
    }
  }
  /** Restore the selected scope, optionally starting empty without losing an unresolved save. */
  async function configure(
    scope: string,
    context: Record<string, any>,
    snapshot?: any,
    expiresMinutes?: number,
    options: { clearDraft?: boolean } = {},
  ) {
    await queue
    lineErrors.value = {}
    try {
      key.value = `${channel}:${scope}`
      const loaded = await readDraft(key.value)
      const initial = loaded || {
        ...empty(),
        context,
        orderType: snapshot?.orderType || empty().orderType,
      }
      if (initial.schema !== 1) throw new Error('draft_schema_unsupported')
      const clearDraft = Boolean(options.clearDraft && !initial.submission)
      if (
        !initial.submission &&
        (clearDraft || (initial.expiresAt && initial.expiresAt <= Date.now()))
      ) {
        initial.lines = []
        initial.quote = undefined
        initial.pricingSnapshot = undefined
        initial.voucherCode = undefined
        initial.discountId = undefined
        initial.giftId = undefined
        initial.customer = null
        initial.delivery = undefined
        initial.deliveryDisplay = undefined
        initial.order = null
        initial.editVersion = undefined
        if (clearDraft) {
          initial.orderType = snapshot?.orderType || context.defaultOrderType || empty().orderType
          initial.expiresAt = null
        }
        initial.context = {
          ...initial.context,
          form: undefined,
          submitForm: undefined,
          editedOrder: null,
          refundPaymentMethods: [],
        }
      }
      initial.context = { ...initial.context, ...context }
      if (expiresMinutes) initial.expiresAt = Date.now() + expiresMinutes * 60000
      store.set(await writeDraft(initial, loaded?.revision ?? 0))
      if (clearDraft) bus?.postMessage({ key: key.value })
      syncFailed.value = false
      recoverableDrafts.value =
        channel === 'pos'
          ? (await listDrafts())
              .filter(
                (candidate) =>
                  candidate.key !== key.value &&
                  candidate.channel === channel &&
                  candidate.accountId === auth.activeAccountId &&
                  candidate.context.branch_id === context.branch_id &&
                  candidate.context.register_id === context.register_id &&
                  (candidate.lines.length > 0 || !!candidate.submission),
              )
              .sort((a, b) => b.updatedAt - a.updatedAt)
          : []
    } catch (error) {
      syncFailed.value = true
      showError(error)
      if (error instanceof DraftConflict) {
        await getCart()
        return
      }
      throw error
    }
  }
  /** Atomically move an explicitly selected prior-session draft without losing pending intent. */
  async function recover(sourceKey: string) {
    await queue
    const source = recoverableDrafts.value.find((candidate) => candidate.key === sourceKey)
    if (!source || !draft.value || processing.value || draft.value.lines.length) return false
    try {
      const restored = await moveDraft(copy(source), copy(draft.value))
      store.set(restored)
      recoverableDrafts.value = recoverableDrafts.value.filter(
        (candidate) => candidate.key !== sourceKey,
      )
      bus?.postMessage({ key: sourceKey })
      bus?.postMessage({ key: key.value })
      return true
    } catch (error) {
      showError(error)
      throw error
    }
  }
  const detach = () => {
    key.value = ''
    recoverableDrafts.value = []
  }
  /** Serialize local writes, reject stale revisions and invalidate quotes after selection changes. */
  async function change(
    action: (next: Draft) => void,
    internal = false,
    expectedRevision?: number,
  ) {
    if (!internal && processing.value) return false
    const activeKey = key.value
    const run = async () => {
      if (!activeKey || activeKey !== key.value || !draft.value) return false
      if (!internal && draft.value.submission) return false
      if (
        channel === 'pos' &&
        (!auth.isAuthenticated || auth.activeAccountId !== draft.value.accountId)
      )
        return false
      if (expectedRevision !== undefined && draft.value.revision !== expectedRevision)
        throw new DraftConflict('draft_changed')
      const next = copy(draft.value)
      action(next)
      if (JSON.stringify(next) === JSON.stringify(draft.value)) return true
      if (!internal) {
        delete next.quote
        delete next.pricingSnapshot
        lineErrors.value = {}
      }
      try {
        store.set(await writeDraft(next, next.revision))
        bus?.postMessage({ key: activeKey })
        syncFailed.value = false
        return true
      } catch (error) {
        syncFailed.value = true
        if (error instanceof DraftConflict) await getCart()
        showError(error)
        throw error
      }
    }
    const pending = queue.then(run, run)
    queue = pending.catch(() => undefined)
    return pending
  }
  /** Re-quote delivery orders after a price-affecting local cart mutation. */
  async function pricingChange(action: (next: Draft) => void) {
    const changed = await change(action)
    if (
      changed &&
      channel === 'pos' &&
      draft.value?.orderType.id === 'delivery' &&
      draft.value.delivery?.customer_address_id &&
      draft.value.lines.length
    ) {
      try {
        await preview()
      } catch (error) {
        // The persisted inline warning is sufficient for an unavailable address; the cart edit succeeded.
        if (!deliveryPricingError(error)) throw error
      }
    }
    return changed
  }
  const storeItem = async (params: Record<string, any>, product?: Record<string, any>) => {
    if (!product) throw new Error('product_required')
    const errors = validateProductOptions(product, params.options || {})
    const max = channel === 'table_order' ? 99 : 9999
    if (!Number.isInteger(params.qty) || params.qty < 1 || params.qty > max)
      errors.qty = ['cart::local.invalid_quantity']
    if (Object.keys(errors).length)
      throw {
        response: {
          status: 422,
          data: {
            errors: Object.fromEntries(
              Object.entries(errors).map(([k, v]) => [k, v.map((message) => t(message))]),
            ),
          },
        },
      }
    return pricingChange((next) => {
      const options = normalizeOptions(params.options || {})
      const existing = next.lines.find(
        (line) =>
          !line.order_product_id &&
          !line.gift &&
          line.product_id === Number(params.product_id) &&
          JSON.stringify(normalizeOptions(line.options)) === JSON.stringify(options),
      )
      if (existing) {
        if (existing.qty + params.qty > max) throw new Error(t('cart::local.invalid_quantity'))
        existing.qty += params.qty
      } else
        next.lines.unshift({
          line_id: generateUuid(),
          product_id: Number(params.product_id),
          qty: params.qty,
          options,
          product: copy(product),
        })
    })
  }
  /** Save edited choices and an optional quantity together so the displayed price matches both. */
  const updateItemOptions = (id: string, options: Record<string, any>, qty?: number) => {
    const line = draft.value?.lines.find((row) => row.line_id === id)
    if (!line || line.gift || (line.order_product_id && line.status !== 'pending'))
      return Promise.resolve(false)
    const errors = validateProductOptions(line.product, options)
    if (
      qty !== undefined &&
      (!Number.isInteger(qty) || qty < 1 || qty > (channel === 'table_order' ? 99 : 9999))
    )
      errors.qty = ['cart::local.invalid_quantity']
    if (Object.keys(errors).length)
      throw {
        response: {
          status: 422,
          data: {
            errors: Object.fromEntries(
              Object.entries(errors).map(([key, messages]) => [
                key,
                messages.map((message) => t(message)),
              ]),
            ),
          },
        },
      }
    return pricingChange((next) => {
      const current = next.lines.find((row) => row.line_id === id)
      if (current) {
        current.options = normalizeOptions(options)
        if (qty !== undefined) current.qty = qty
      }
    })
  }
  const updateItem = (id: string, qty: number) =>
    pricingChange((next) => {
      if (!Number.isInteger(qty) || qty < 1 || qty > (channel === 'table_order' ? 99 : 9999))
        throw new Error(t('cart::local.invalid_quantity'))
      const line = next.lines.find((l) => l.line_id === id)
      if (line && !line.gift && (!line.order_product_id || line.status === 'pending'))
        line.qty = qty
    })
  const deleteItem = (id: string) =>
    pricingChange((next) => {
      if (
        next.quote?.pricing.items?.some(
          (item: any) => (item.line_id || item.id) === id && item.loyaltyGift?.id === next.giftId,
        )
      )
        next.giftId = undefined
      next.lines = next.lines.filter(
        (l) => l.line_id !== id || (!!l.order_product_id && l.status !== 'pending'),
      )
    })
  /** Start every new POS ticket with the configured default order type. */
  function resetPosOrderType(next: Draft) {
    if (channel === 'pos')
      next.orderType = next.context.defaultOrderType || { id: null, name: null }
  }
  const clear = () =>
    change((next) => {
      resetPosOrderType(next)
      Object.assign(next, {
        lines: [],
        order: null,
        customer: null,
        discountId: undefined,
        voucherCode: undefined,
        giftId: undefined,
        delivery: undefined,
        deliveryDisplay: undefined,
        editVersion: undefined,
      })
      next.context.editedOrder = null
      next.context.refundPaymentMethods = []
    })
  const storeOrderType = (id: string) =>
    change((next) => {
      const configuredOrderType = next.context.posConfiguration?.order_types?.find(
        (type: { id: string; name: string }) => type.id === id,
      )
      const defaultName =
        next.context.defaultOrderType?.id === id ? next.context.defaultOrderType.name : null
      next.orderType = {
        id,
        name: configuredOrderType?.name ?? defaultName ?? (channel === 'pos' ? null : id),
      }
      next.delivery = undefined
    })
  const removeOrderType = () =>
    change((next) => {
      next.orderType = { id: null, name: null }
    })
  const applyDiscount = (id: number) =>
    benefit((next) => {
      next.discountId = id
      next.voucherCode = undefined
    })
  const applyVoucher = (code: string) =>
    benefit((next) => {
      next.voucherCode = code
      next.discountId = undefined
    })
  const removeDiscount = () =>
    pricingChange((next) => {
      next.discountId = undefined
      next.voucherCode = undefined
    })
  const applyGift = (id: number) =>
    benefit((next) => {
      next.giftId = id
    })
  const addCustomer = (id: number, customer?: Record<string, any>) =>
    change((next) => {
      next.customer = customer ? copy(customer) : { id, name: String(id) }
      next.delivery = undefined
    })
  const removeCustomer = () =>
    change((next) => {
      next.customer = null
      next.delivery = undefined
      next.giftId = undefined
    })
  const updateDeliveryContext = async (value: Record<string, any>) => {
    const changed = await change((next) => {
      next.delivery = { ...next.delivery, ...value }
    })
    if (!changed) return false
    try {
      await preview()
      return true
    } catch (error) {
      // Delivery validation is rendered persistently beside the order instead of becoming an unhandled watcher error.
      if (deliveryPricingError(error)) return false
      throw error
    }
  }
  const refreshDeliveryFee = preview
  const storeAction = (id: string, action: string, qty: number) =>
    pricingChange((next) => {
      const line = next.lines.find((l) => l.line_id === id)
      if (!line || !Number.isInteger(qty) || qty < 1 || qty > line.qty) return
      line.actions = { ...line.actions, [action]: (line.actions?.[action] || 0) + qty }
      line.qty -= qty
    })
  const removeAction = (id: string, action: string) =>
    pricingChange((next) => {
      const line = next.lines.find((l) => l.line_id === id)
      if (line) {
        line.qty += line.actions?.[action] || 0
        delete line.actions?.[action]
      }
    })
  /** Atomically restore an order under the edit lock, without replacing an unresolved submission. */
  async function resetCart(
    snapshot?: any,
    editVersion?: string,
    context: Record<string, any> = {},
  ) {
    return change((next) => {
      if (next.submission) throw new DraftConflict('submission_pending')
      next.quote = undefined
      next.pricingSnapshot = snapshot
      next.giftId = undefined
      next.context = { ...next.context, ...context }
      if (!snapshot) {
        resetPosOrderType(next)
        next.lines = []
        next.order = null
        next.editVersion = undefined
        next.discountId = undefined
        next.voucherCode = undefined
        next.customer = null
        next.delivery = undefined
        next.deliveryDisplay = undefined
        next.context.editedOrder = null
        next.context.refundPaymentMethods = []
        return
      }
      next.lines = snapshot.items.map((item: any) => ({
        line_id: item.line_id || item.id,
        product_id: Number(item.item.id),
        qty: item.qty,
        product: item.catalog_product || {
          ...item.item,
          price: item.unitPrice,
          selling_price: item.unitPrice,
          options: [],
        },
        options: item.option_input || {},
        order_product_id: item.orderProduct?.id,
        status: item.orderProduct?.status?.id,
        gift: item.loyaltyGift,
        actions: {},
      }))
      next.order = snapshot.order
      next.editVersion = editVersion
      next.orderType = snapshot.orderType
      next.customer = snapshot.customer
      next.discountId =
        snapshot.discount?.type?.id === 'discount' ? snapshot.discount.id : undefined
      next.voucherCode =
        snapshot.discount?.type?.id === 'voucher' ? snapshot.discount.name : undefined
      next.deliveryDisplay = snapshot.deliveryContext
      next.delivery = snapshot.deliveryContext
        ? {
            customer_address_id: snapshot.deliveryContext.customerAddress?.id,
            delivery_method: snapshot.deliveryContext.deliveryMethod,
            payment_collection_channel: snapshot.deliveryContext.paymentCollectionChannel,
            note: snapshot.deliveryContext.note,
          }
        : undefined
    }, true)
  }
  /** Send identifiers and selections only; prices are recomputed by the server. */
  function payload(extra: Record<string, any> = {}) {
    const current = draft.value
    if (!current) throw new Error('draft_unavailable')
    return {
      ...extra,
      ...(channel === 'pos' ? { type: current.orderType.id } : {}),
      items: current.lines
        .filter((l) => !l.gift || l.order_product_id)
        .map(({ line_id, product_id, qty, options, order_product_id, actions }) => ({
          line_id,
          product_id,
          qty,
          options,
          ...(order_product_id ? { order_product_id, actions } : {}),
        })),
      ...(channel === 'pos'
        ? {
            customer_id: current.customer?.id ?? null,
            discount_id: current.discountId,
            gift_id: current.giftId,
            delivery: current.delivery,
            edit_version: current.editVersion,
          }
        : {}),
      voucher_code: current.voucherCode,
    }
  }
  const withConfirmedCart = async (callback: () => Promise<void>) => {
    if (
      submitting.value ||
      (channel === 'pos' &&
        (!auth.isAuthenticated || auth.activeAccountId !== draft.value?.accountId))
    )
      return false
    await queue
    submitting.value = true
    try {
      await callback()
      return true
    } finally {
      submitting.value = false
    }
  }
  const setQuote = (quote: any, revision?: number) =>
    change(
      (next) => {
        if (next.submission) throw new DraftConflict('submission_pending')
        next.quote = quote
        next.deliveryDisplay = quote.pricing.deliveryContext
      },
      true,
      revision,
    )
  /** Persist the exact retry identity before transport and prevent a competing attempt. */
  const setSubmission = (submission: Draft['submission'], revision?: number) =>
    change(
      (next) => {
        if (
          submission &&
          next.submission &&
          submission.payload.idempotency_key !== next.submission.payload.idempotency_key
        )
          throw new DraftConflict('submission_pending')
        next.submission = submission
      },
      true,
      revision,
    )
  /** Clear only the matching confirmed submission after its result is known. */
  const finishSubmission = (idempotencyKey: string) =>
    change((next) => {
      if (next.submission?.payload.idempotency_key !== idempotencyKey)
        throw new DraftConflict('submission_changed')
      resetPosOrderType(next)
      next.lines = []
      next.order = null
      next.context.editedOrder = null
      next.submission = undefined
      next.quote = undefined
      next.pricingSnapshot = undefined
      next.deliveryDisplay = undefined
      next.editVersion = undefined
      next.discountId = undefined
      next.voucherCode = undefined
      next.giftId = undefined
      next.delivery = undefined
      next.customer = null
    }, true)
  const setContext = (context: Record<string, any>) =>
    change((next) => {
      if (next.submission) return
      next.context = { ...next.context, ...context }
      if (
        ('form' in context &&
          JSON.stringify(context.form) !== JSON.stringify(draft.value?.context.form)) ||
        ('submitForm' in context &&
          JSON.stringify(context.submitForm) !== JSON.stringify(draft.value?.context.submitForm))
      ) {
        next.quote = undefined
        next.pricingSnapshot = undefined
      }
    }, true)
  watch(
    () => auth.activeAccountId,
    () => {
      if (channel === 'pos' && draft.value?.accountId !== auth.activeAccountId) key.value = ''
    },
  )
  if (getCurrentScope()) onScopeDispose(() => bus?.close())
  return {
    data,
    setValidationErrors,
    updateItemOptions,
    draft,
    recoverableDrafts,
    recover,
    detach,
    setQuoteLoader,
    preview,
    configure,
    payload,
    setQuote,
    setSubmission,
    finishSubmission,
    setContext,
    storeItem,
    updateItem,
    deleteItem,
    clear,
    storeOrderType,
    removeOrderType,
    applyDiscount,
    applyVoucher,
    removeDiscount,
    applyGift,
    addCustomer,
    removeCustomer,
    updateDeliveryContext,
    refreshDeliveryFee,
    storeAction,
    removeAction,
    resetCart,
    getCart,
    showError,
    withConfirmedCart,
    processing,
    hasPendingMutations,
    syncFailed,
    visibleItems,
    pendingProducts,
    getItemQuantity: (_id: string, fallback: number) => fallback,
    isItemMutating: (_id: string, _action?: string) => false,
    isProductMutating: (_id: string | number) => false,
    isCartMutationLoading: (_key: string) => false,
  }
}
export type UseCart = ReturnType<typeof useCart>
