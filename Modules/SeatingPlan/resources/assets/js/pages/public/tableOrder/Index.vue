<script lang="ts" setup>
import { useConfirmDialog } from '@/modules/core/composables/confirmDialog'
import { readDraft } from '@/modules/cart/services/draftStorage'
import { useCart } from '@/modules/cart/composables/cart'
import { submitDraft } from '@/modules/cart/services/submitDraft'
import { validateProductOptions } from '@/modules/cart/services/options'
import TableOrderSummary from '@/modules/seatingPlan/pages/public/tableOrder/Partials/TableOrderSummary.vue'
import axios, { type AxiosError } from 'axios'
import { useI18n } from 'vue-i18n'
import { useAppStore } from '@/modules/core/stores/appStore.ts'
import { generateUuid } from '@/modules/core/utils/uuid.ts'
import {
  getTableQrContext,
  getTableQrTracking,
  submitTableQrOrder,
  quoteTableQrOrder,
} from '@/modules/seatingPlan/api/table_qr_order.api.ts'
import TableOrderCartBar from '@/modules/seatingPlan/pages/public/tableOrder/Partials/TableOrderCartBar.vue'
import TableOrderHeader from '@/modules/seatingPlan/pages/public/tableOrder/Partials/TableOrderHeader.vue'
import TableOrderProductCard from '@/modules/seatingPlan/pages/public/tableOrder/Partials/TableOrderProductCard.vue'
import TableOrderUnavailableState from '@/modules/seatingPlan/pages/public/tableOrder/Partials/TableOrderUnavailableState.vue'

type Money = {
  amount: number
  formatted: string
  currency: string
}

type Product = {
  id: number
  name: string
  thumbnail?: string | null
  category_ids?: number[]
  options?: Record<string, any>[]
  selling_price?: Money
  price?: Money
  has_special_price?: boolean
  is_new?: boolean
}

const route = useRoute()
const appStore = useAppStore()
const { t } = useI18n()
const uuid = computed(() => String(route.params.uuid || ''))
const token = computed(() => (typeof route.query.token === 'string' ? route.query.token : null))
const cartStorageKey = computed(() => `forkiva.table_qr.visitor.${uuid.value}`)
const cartExpiryStorageKey = computed(() => `forkiva.table_qr.cart_expires_at.${uuid.value}`)

const loading = ref(true)
const mutating = ref(false)
const submitting = ref(false)
const trackingRefreshing = ref(false)
const isNotFound = ref(false)
const pageMessage = ref<string | null>(null)
const inlineError = ref<string | null>(null)
const submitError = ref<string | null>(null)
const context = ref<Record<string, any> | null>(null)
const categories = ref<Record<string, any>[]>([])
const products = ref<Product[]>([])
const localCart = useCart('table_order')
const cart = localCart.data
const searchQuery = ref('')
const selectedCategoryId = ref<number | null>(null)
const selectedProduct = ref<Product | null>(null)
const selectedQty = ref(1)
const editingLineId=ref<string|null>(null)
const visitorStorageFailed=ref(false)
const optionValues = ref<Record<string, any>>({})
const optionErrors = ref<Record<string, string>>({})
const submitErrors = ref<Record<string, string>>({})
const discountCode = ref('')
const discountError = ref<string | null>(null)
const discountLoading = ref(false)
const optionSheet = ref(false)
const cartSheet = ref(false)
const submitSheet = ref(false)
const submittedOrder = ref<Record<string, any> | null>(null)
const submitForm = reactive({
  guest_count: null as number | null,
  customer_note: '',
})
let trackingRefreshTimer: ReturnType<typeof setTimeout> | null = null
let trackingRefreshController: AbortController | null = null
let trackingRefreshPromise: Promise<void> | null = null
let initialConfiguration = true

const cartId = ref(resolveCartId())
const submittedDialog = computed({
  get: () => Boolean(submittedOrder.value),
  set: (value) => {
    if (!value) {
      submittedOrder.value = null
    }
  },
})

const cartItems = computed<Record<string, any>[]>(() => {
  const items = cart.value?.items
  return Array.isArray(items) ? items : []
})

const cartQuantity = computed(() => Number(cart.value?.quantity || 0))
const cartTotal = computed(() => cart.value?.total?.formatted || '0')
const hasUnresolvedSubmission = computed(
  () => Boolean(localCart.draft.value?.submission) && !submitting.value,
)
const session = computed(() => context.value?.session || {})
const settings = computed(() => context.value?.settings || {})
const table = computed(() => context.value?.table || {})
const activeOrder = computed<Record<string, any> | null>(() => context.value?.active_order || null)
const cartTotalLabel = computed(() =>
  t(
    activeOrder.value
      ? 'seatingplan::tables.table_order.new_items_total'
      : 'seatingplan::tables.table_order.order_total',
  ),
)
const branch = computed(() => context.value?.branch || {})
const appLogo = computed(() => appStore.logo || appStore.favicon || null)
const appLogoFallback = computed(() =>
  String(appStore.appName || branch.value.name || 'F')
    .trim()
    .charAt(0)
    .toUpperCase(),
)
const maxGuestCount = computed(() => Math.max(1, Math.min(Number(table.value.capacity || 1), 50)))
const canSubmit = computed(() => cartItems.value.length > 0 && Boolean(session.value.available))
const unavailableTitle = computed(() =>
  isNotFound.value
    ? t('seatingplan::tables.table_order.qr_not_active')
    : t('seatingplan::tables.table_order.ordering_paused'),
)
const unavailableMessage = computed(
  () => pageMessage.value || t('seatingplan::tables.table_order.ordering_unavailable'),
)
const selectedProductOptions = computed(() => normalizeCollection(selectedProduct.value?.options))
const categoryFilterIds = computed(() =>
  selectedCategoryId.value
    ? collectCategoryIds(
        categories.value.find((category) => Number(category.id) === selectedCategoryId.value),
      )
    : [],
)
const filteredProducts = computed(() => {
  const allowed = new Set(categoryFilterIds.value.map(Number))
  const query = (searchQuery.value || '').trim().toLocaleLowerCase()
  return products.value.filter(
    (product) =>
      (!selectedCategoryId.value ||
        (product.category_ids || []).some((id) => allowed.has(Number(id)))) &&
      (!query || product.name.toLocaleLowerCase().includes(query)),
  )
})

onBeforeMount(loadContext)
onMounted(() => document.addEventListener('visibilitychange', handleTrackingVisibilityChange))
onBeforeUnmount(() => {
  document.removeEventListener('visibilitychange', handleTrackingVisibilityChange)
  stopTrackingRefresh(true)
})

watch(() => activeOrder.value?.id || null, syncTrackingRefresh)

function clearFilters() {
  searchQuery.value = ''
  selectedCategoryId.value = null
}

function resolveCartId() {
  try {
  const storedId = localStorage.getItem(cartStorageKey.value)

  if (storedId) {
    return storedId
  }

  const nextId = generateUuid()
  localStorage.setItem(cartStorageKey.value, nextId)
  localStorage.setItem(cartExpiryStorageKey.value, String(Date.now() + 120 * 60 * 1000))

  return nextId
  } catch {visitorStorageFailed.value=true;return ""}
}

function refreshCartExpiry() {
  try {
  const minutes = Number(settings.value.table_qr_cart_expires_minutes || 120)
  localStorage.setItem(cartStorageKey.value, cartId.value)
  localStorage.setItem(cartExpiryStorageKey.value, String(Date.now() + minutes * 60 * 1000))
  } catch {visitorStorageFailed.value=true}
}

function normalizeCollection(value: any): any[] {
  if (!value) {
    return []
  }

  if (Array.isArray(value)) {
    return value
  }

  if (Array.isArray(value.data)) {
    return value.data
  }

  return []
}

function collectCategoryIds(category?: Record<string, any>): number[] {
  if (!category) {
    return []
  }

  return [
    Number(category.id),
    ...normalizeCollection(category.items).flatMap((item) => collectCategoryIds(item)),
  ]
}

/** Apply server context and discard an ordinary local draft only on initial page entry. */
async function applyContextPayload(body: Record<string, any>) {
  if (visitorStorageFailed.value || !cartId.value) {
    throw new Error(t('cart::local.storage_failed'))
  }

  context.value = body
  categories.value = normalizeCollection(body.categories)
  products.value = normalizeCollection(body.products)
  await localCart.configure(
    `${uuid.value}:${cartId.value}`,
    {
      currency: body.branch.currency,
      global_taxes: body.global_taxes,
      precision: body.precision,
      tableContext: { ...body, active_order: null },
    },
    body.cart,
    Number(body.settings.table_qr_cart_expires_minutes || 120),
    { clearDraft: initialConfiguration },
  )
  initialConfiguration = false

  if (localCart.draft.value?.context.submitForm) {
    Object.assign(submitForm, localCart.draft.value.context.submitForm)
  }

  refreshCartExpiry()
}

function applyTrackingPayload(body: Record<string, any>) {
  if (!context.value) {
    return
  }

  context.value = {
    ...context.value,
    ...body,
    table: {
      ...context.value.table,
      ...body.table,
    },
  }
}


async function loadContext() {
  try {
    loading.value = true
    inlineError.value = null
    pageMessage.value = null
    isNotFound.value = false

    const response = await getTableQrContext(uuid.value, token.value)
    await applyContextPayload(response.data.body)
  } catch (error: any) {
    const response = (error as AxiosError<Record<string, any>>).response
    if(visitorStorageFailed.value || localCart.syncFailed.value) {pageMessage.value=t('cart::local.storage_failed');return}
    if(!response) {
      const saved=await readDraft(`table_order:${uuid.value}:${cartId.value}`).catch(()=>undefined)
      if(saved?.context.tableContext) {
        await applyContextPayload({...saved.context.tableContext,session:{available:false,message:t('cart::local.offline'),reason:'offline'}})
        return
      }
    }
    pageMessage.value =
      response?.data?.message || t('seatingplan::tables.table_order.ordering_unavailable')
    isNotFound.value = response?.status === 404
  } finally {
    loading.value = false
  }
}

function syncTrackingRefresh() {
  stopTrackingRefresh()

  if (!activeOrder.value || document.visibilityState === 'hidden') {
    return
  }

  scheduleTrackingRefresh()
}

function scheduleTrackingRefresh() {
  if (trackingRefreshTimer || !activeOrder.value || document.visibilityState === 'hidden') {
    return
  }

  trackingRefreshTimer = setTimeout(async () => {
    trackingRefreshTimer = null
    await loadTracking()
    scheduleTrackingRefresh()
  }, 30_000)
}

function stopTrackingRefresh(abort = false) {
  if (trackingRefreshTimer) {
    clearTimeout(trackingRefreshTimer)
    trackingRefreshTimer = null
  }

  if (abort) {
    trackingRefreshController?.abort()
    trackingRefreshController = null
  }
}

function handleTrackingVisibilityChange() {
  if (document.visibilityState === 'hidden') {
    stopTrackingRefresh(true)
    return
  }

  if (activeOrder.value) {
    void loadTracking().finally(scheduleTrackingRefresh)
  }
}

async function loadTracking(options: { manual?: boolean; force?: boolean } = {}) {
  const manual = options.manual === true

  if (
    !context.value ||
    (!options.force && !activeOrder.value) ||
    document.visibilityState === 'hidden'
  ) {
    return
  }

  if (trackingRefreshPromise) {
    return trackingRefreshPromise
  }

  const controller = new AbortController()
  trackingRefreshController = controller

  if (manual) {
    trackingRefreshing.value = true
    inlineError.value = null
  }

  trackingRefreshPromise = (async () => {
    try {
      const response = await getTableQrTracking(
        uuid.value,
        token.value,
        controller.signal,
      )
      applyTrackingPayload(response.data.body)
    } catch (error: any) {
      if (!axios.isCancel(error) && manual) {
        inlineError.value =
          error?.response?.data?.message ||
          t('seatingplan::tables.table_order.refresh_status_failed')
      }
    } finally {
      if (trackingRefreshController === controller) {
        trackingRefreshController = null
      }

      if (manual) {
        trackingRefreshing.value = false
      }
    }
  })()

  try {
    await trackingRefreshPromise
  } finally {
    trackingRefreshPromise = null
  }
}

async function refreshTracking() {
  await loadTracking({ manual: true })
}

function productPrice(product?: Product | null) {
  return product?.selling_price?.formatted || product?.price?.formatted || ''
}

function productOriginalPrice(product?: Product | null) {
  return product?.price?.formatted || product?.selling_price?.formatted || ''
}

function productHasOffer(product?: Product | null) {
  if (!product) {
    return false
  }

  return (
    Boolean(product.has_special_price) ||
    (product.selling_price != null &&
      product.price != null &&
      Number(product.selling_price.amount) < Number(product.price.amount))
  )
}

function optionType(option: Record<string, any>) {
  return option.type?.id || option.type
}

function optionKey(option: Record<string, any>) {
  return `options.${option.id}`
}

function optionError(option: Record<string, any>) {
  return optionErrors.value[optionKey(option)] || ''
}

function clearOptionError(optionId: number | string) {
  const key = `options.${optionId}`

  if (!(key in optionErrors.value)) {
    return
  }

  const nextErrors = { ...optionErrors.value }
  delete nextErrors[key]
  optionErrors.value = nextErrors
}

function optionValueLabel(value: Record<string, any>) {
  const price = Number(value.price?.amount || 0) > 0 ? ` + ${value.price.formatted}` : ''

  return `${value.label}${price}`
}

function optionItems(option: Record<string, any>) {
  return normalizeCollection(option.values).map((value) => ({
    id: value.id,
    name: optionValueLabel(value),
  }))
}

function isEmptyOptionValue(value: any) {
  return Array.isArray(value)
    ? value.length === 0
    : value === null || value === undefined || value === ''
}

function validateSelectedOptions() {
  const errors: Record<string, string> = {}

  for (const option of selectedProductOptions.value) {
    if (option.is_required && isEmptyOptionValue(optionValues.value[option.id])) {
      errors[optionKey(option)] = t('seatingplan::tables.table_order.option_required', {
        option: option.name,
      })
    }
  }

  optionErrors.value = errors

  return Object.keys(errors).length === 0
}

function firstError(value: any): string {
  if (Array.isArray(value)) {
    return String(value[0] || '')
  }

  return typeof value === 'string' ? value : ''
}

function clearSubmitError(field: string) {
  if (!(field in submitErrors.value)) {
    return
  }

  const nextErrors = { ...submitErrors.value }
  delete nextErrors[field]
  submitErrors.value = nextErrors
}

function validateSubmitForm() {
  const errors: Record<string, string> = {}

  if (settings.value.table_qr_require_guest_count) {
    const guestCount = Number(submitForm.guest_count)

    if (!Number.isInteger(guestCount) || guestCount < 1 || guestCount > maxGuestCount.value) {
      errors.guest_count = t('seatingplan::tables.table_order.guest_count_invalid', {
        max: maxGuestCount.value,
      })
    }
  }

  submitErrors.value = errors

  return Object.keys(errors).length === 0
}

function applyServerSubmitErrors(error: any) {
  const responseErrors = error?.response?.data?.errors
  const errors: Record<string, string> = {}

  if (responseErrors && typeof responseErrors === 'object') {
    const guestCountError = firstError(responseErrors.guest_count)
    const customerNoteError = firstError(responseErrors.customer_note)
    const reason = firstError(responseErrors.reason)

    if (guestCountError) {
      errors.guest_count = guestCountError
    } else if (['guest_count_invalid', 'guest_count_required'].includes(reason)) {
      errors.guest_count =
        error?.response?.data?.message ||
        t('seatingplan::tables.table_order.guest_count_invalid', { max: maxGuestCount.value })
    }

    if (customerNoteError) {
      errors.customer_note = customerNoteError
    }
  }

  submitErrors.value = errors

  return Object.keys(errors).length > 0
}

function submitFailureMessage(error: any) {
  const status = Number(error?.response?.status || 0)

  if (status >= 400 && status < 500) {
    return error?.response?.data?.message || t('seatingplan::tables.table_order.submit_failed')
  }

  return t('seatingplan::tables.table_order.submit_failed')
}

function applyServerOptionErrors(error: any) {
  const errors = error?.response?.data?.errors
  const nextErrors: Record<string, string> = {}

  if (errors && typeof errors === 'object') {
    for (const [key, value] of Object.entries(errors)) {
      if (key.startsWith('options.')) {
        nextErrors[key] = firstError(value)
      }
    }

    if (
      Object.keys(nextErrors).length === 0 &&
      errors.reason === 'invalid_product_options' &&
      selectedProductOptions.value.length > 0
    ) {
      nextErrors[optionKey(selectedProductOptions.value[0])] =
        error?.response?.data?.message || t('seatingplan::tables.table_order.review_option')
    }
  }

  optionErrors.value = nextErrors

  return Object.keys(nextErrors).length > 0
}

function openProduct(product: Product) {
  if (mutating.value || submitting.value || discountLoading.value || (!session.value.available && session.value.reason !== 'offline'))
    return
  editingLineId.value=null
  selectedProduct.value = product
  selectedQty.value = 1
  optionValues.value = {}
  optionErrors.value = {}
  inlineError.value = null

  for (const option of normalizeCollection(product.options)) {
    optionValues.value[option.id] = ['checkbox', 'multiple_select'].includes(optionType(option))
      ? []
      : null
  }

  if (selectedProductOptions.value.length === 0) {
    addSelectedProduct()
    return
  }

  optionSheet.value = true
}

function editLine(item:Record<string,any>) {
  const line=localCart.draft.value?.lines.find(row=>row.line_id===item.id)
  if(!line || localCart.processing.value) return
  selectedProduct.value=line.product as Product
  selectedQty.value=line.qty
  editingLineId.value=line.line_id
  optionValues.value=JSON.parse(JSON.stringify(line.options))
  optionErrors.value={}
  optionSheet.value=true
}

function buildOptionsPayload() {
  const payload: Record<string, any> = {}

  for (const option of selectedProductOptions.value) {
    const value = optionValues.value[option.id]

    if (Array.isArray(value) && value.length === 0) {
      continue
    }

    if (value !== null && value !== undefined && value !== '') {
      payload[option.id] = value
    }
  }

  return payload
}

async function addSelectedProduct() {
  if(localCart.processing.value) return
  if (
    !selectedProduct.value ||
    mutating.value ||
    submitting.value ||
    discountLoading.value ||
    (!session.value.available && session.value.reason !== 'offline')
  ) {
    return
  }

  const errors=validateProductOptions(selectedProduct.value,buildOptionsPayload())
  if(Object.keys(errors).length) {optionErrors.value=Object.fromEntries(Object.entries(errors).map(([key,value])=>[key,t(value[0] as string)]));return}
  if (!validateSelectedOptions()) {
    return
  }

  try {
    mutating.value = true
    inlineError.value = null
    if(editingLineId.value) {
      await localCart.updateItemOptions(
        editingLineId.value,
        buildOptionsPayload(),
        selectedQty.value,
      )
    } else await localCart.storeItem({product_id:selectedProduct.value.id,qty:selectedQty.value,options:buildOptionsPayload()},selectedProduct.value)
    refreshCartExpiry()
    optionSheet.value = false
    optionErrors.value = {}
  } catch (error: any) {
    if (!applyServerOptionErrors(error)) {
      inlineError.value =
        error?.response?.data?.message || t('seatingplan::tables.table_order.review_selection')
    }
  } finally {
    mutating.value = false
  }
}

async function changeQty(item: Record<string, any>, delta: number) {
  if(localCart.processing.value) return
  if (mutating.value || discountLoading.value || submitting.value) return
  const nextQty = Number(item.qty || 0) + delta

  if (nextQty <= 0) {
    await removeItem(item)
    return
  }

  try {
    mutating.value = true
    inlineError.value = null
    await localCart.updateItem(item.id,nextQty)
    refreshCartExpiry()
  } catch (error: any) {
    inlineError.value =
      error?.response?.data?.message || t('seatingplan::tables.table_order.update_cart_failed')
  } finally {
    mutating.value = false
  }
}

async function removeItem(item: Record<string, any>) {
  if(localCart.processing.value) return
  if (mutating.value || discountLoading.value || submitting.value) return
  try {
    mutating.value = true
    inlineError.value = null
    await localCart.deleteItem(item.id)
    refreshCartExpiry()
  } catch (error: any) {
    inlineError.value =
      error?.response?.data?.message || t('seatingplan::tables.table_order.remove_item_failed')
  } finally {
    mutating.value = false
  }
}

async function applyDiscountCode() {
  const code = discountCode.value.trim()

  if (!code || discountLoading.value) {
    return
  }

  try {
    discountLoading.value = true
    discountError.value = null
    await localCart.applyVoucher(code)
    const preview=await quoteTableQrOrder(uuid.value,token.value,localCart.payload({guest_count:submitForm.guest_count,customer_note:submitForm.customer_note}))
    await localCart.setQuote(preview.data.body)
    refreshCartExpiry()
    discountCode.value = ''
  } catch (error: any) {
    discountError.value =
      error?.response?.data?.message || t('seatingplan::tables.table_order.apply_discount_failed')
  } finally {
    discountLoading.value = false
  }
}

async function removeDiscountCode() {
  if (discountLoading.value) {
    return
  }

  try {
    discountLoading.value = true
    discountError.value = null
    await localCart.removeDiscount()
    refreshCartExpiry()
  } catch (error: any) {
    discountError.value =
      error?.response?.data?.message || t('seatingplan::tables.table_order.remove_discount_failed')
  } finally {
    discountLoading.value = false
  }
}

function openSubmit() {
  inlineError.value = null
  submitError.value = session.value.message || null
  submitErrors.value = {}

  if (!canSubmit.value || mutating.value || discountLoading.value || submitting.value) {
    return
  }

  if (submitForm.guest_count === null && settings.value.table_qr_require_guest_count) {
    submitForm.guest_count = 1
  }

  cartSheet.value = false
  submitSheet.value = true
}

function closeSubmit(returnToCart = false) {
  submitSheet.value = false
  submitError.value = null
  submitErrors.value = {}

  if (returnToCart && cartItems.value.length > 0) {
    cartSheet.value = true
  }
}

async function submitOrder() {
  if (submitting.value || mutating.value || discountLoading.value || (!canSubmit.value && !localCart.draft.value?.submission)) {
    return
  }

  if (!localCart.draft.value?.submission && !validateSubmitForm()) {
    return
  }

  try {
    submitting.value = true
    submitError.value = null
    const response = await submitDraft(localCart,{
      guest_count:submitForm.guest_count,customer_note:settings.value.table_qr_allow_order_notes ? submitForm.customer_note : null,
    },(payload)=>quoteTableQrOrder(uuid.value,token.value,payload),
    (payload)=>submitTableQrOrder(uuid.value,token.value,payload),
    (total, changes) => useConfirmDialog({
      message: [t('cart::local.confirm_price', { total }), changes].filter(Boolean).join('\n'),
    }))
    if (!response) {
      closeSubmit()
      return
    }
    submittedOrder.value=response.data.body.order
    cartSheet.value = false
    submitSheet.value = false
    await loadTracking({ force: true })
  } catch (error: any) {
    if (!applyServerSubmitErrors(error)) {
      submitError.value = submitFailureMessage(error)
    }
  } finally {
    submitting.value = false
  }
}
watch(submitForm,(value)=>{if(localCart.draft.value && !localCart.draft.value.submission) void localCart.setContext({submitForm:{...value}})},{deep:true})
</script>

<template>
  <main class="table-order-page">
    <div
      v-if="loading"
      class="table-order-state"
    >
      <VProgressCircular
        color="primary"
        indeterminate
        size="44"
      />
    </div>

    <TableOrderUnavailableState
      v-else-if="pageMessage"
      :message="unavailableMessage"
      :title="unavailableTitle"
      @retry="loadContext"
    />

    <template v-else-if="context">
      <VAlert
        v-if="hasUnresolvedSubmission"
        class="ma-4"
        type="warning"
      >
        {{ t('cart::local.pending') }}
        <VBtn @click="submitOrder">{{ t('pos::pos_viewer.refresh') }}</VBtn>
      </VAlert>
      <VAlert
        v-if="visitorStorageFailed || localCart.syncFailed.value"
        class="ma-4"
        type="error"
      >
        {{ t('cart::local.storage_failed') }}
      </VAlert>
      <TableOrderHeader
        :active-order="activeOrder"
        :app-logo="appLogo"
        :app-logo-fallback="appLogoFallback"
        :branch="branch"
        :session="session"
        :table="table"
        :tracking-refreshing="trackingRefreshing"
        @refresh-tracking="refreshTracking"
      />

      <VAlert
        v-if="session.message"
        class="mx-4 mb-3"
        density="comfortable"
        type="warning"
        variant="tonal"
      >
        {{ session.message }}
      </VAlert>

      <VAlert
        v-if="inlineError"
        class="mx-4 mb-3"
        closable
        density="comfortable"
        type="error"
        variant="tonal"
        @click:close="inlineError = null"
      >
        {{ inlineError }}
      </VAlert>

      <div class="table-order-layout">
        <section
          class="table-order-menu"
          :aria-label="t('seatingplan::tables.table_order.menu')"
        >
          <div class="menu-toolbar">
            <div class="menu-intro">
              <h1>{{ t('seatingplan::tables.table_order.menu') }}</h1>
            </div>
            <VTextField
              v-model="searchQuery"
              class="menu-search"
              :aria-label="t('seatingplan::tables.table_order.search_menu')"
              :placeholder="t('seatingplan::tables.table_order.search_menu')"
              prepend-inner-icon="tabler-search"
              clearable
              hide-details
              @click:clear="searchQuery = ''"
            />
          </div>
          <nav
            :aria-label="t('seatingplan::tables.table_order.categories')"
            class="category-rail"
          >
            <button
              :aria-pressed="selectedCategoryId === null"
              class="category-pill"
              :class="{ 'category-pill--active': selectedCategoryId === null }"
              type="button"
              @click="selectedCategoryId = null"
            >
              {{ t('seatingplan::tables.table_order.all') }}
            </button>

            <button
              v-for="category in categories"
              :key="category.id"
              :aria-pressed="selectedCategoryId === Number(category.id)"
              class="category-pill"
              :class="{ 'category-pill--active': selectedCategoryId === Number(category.id) }"
              type="button"
              @click="selectedCategoryId = Number(category.id)"
            >
              {{ category.name }}
            </button>
          </nav>
          <section
            v-if="filteredProducts.length"
            class="product-grid"
          >
            <TableOrderProductCard
              v-for="product in filteredProducts"
              :key="product.id"
              :product="product"
              :disabled="mutating || discountLoading || submitting || (!session.available && session.reason !== 'offline')"
              @open="openProduct"
            />
          </section>
          <div
            v-else
            class="menu-empty"
            role="status"
          >
            <VIcon
              icon="tabler-search"
              size="32"
            />
            <h2>{{ t('seatingplan::tables.table_order.no_items') }}</h2>
            <p>{{ t('seatingplan::tables.table_order.no_items_message') }}</p>
            <VBtn
              v-if="searchQuery || selectedCategoryId"
              variant="tonal"
              color="primary"
              @click="clearFilters"
            >
              {{ t('seatingplan::tables.table_order.clear_filters') }}
            </VBtn>
          </div>
        </section>
        <aside class="table-order-sidebar">
          <TableOrderSummary
            v-model:discount-code="discountCode"
            :cart="cart"
            :total-label="cartTotalLabel"
            :table-name="table.name || ''"
            :allow-discount-code="Boolean(settings.table_qr_allow_discount_code)"
            :can-submit="canSubmit"
            :busy="localCart.processing.value || mutating || discountLoading || submitting"
            :submitting="submitting"
            :discount-loading="discountLoading"
            :discount-error="discountError"
            :inline-error="inlineError"
            @change-qty="changeQty"
            @edit-options="editLine"
            @remove-item="removeItem"
            @apply-discount="applyDiscountCode"
            @remove-discount="removeDiscountCode"
            @clear-discount-error="discountError = null"
            @review="openSubmit"
          />
        </aside>
      </div>

      <TableOrderCartBar
        :disabled="cartItems.length === 0"
        :quantity="cartQuantity"
        :total="cartTotal"
        @open="cartSheet = true"
      />

      <VDialog
        v-model="optionSheet"
        class="table-order-dialog"
        max-width="520"
        scrollable
      >
        <VCard class="sheet-card option-sheet">
          <header class="option-sheet-header">
            <div class="option-sheet-title-row">
              <h2>{{ t('seatingplan::tables.table_order.customize_item') }}</h2>
              <VBtn
                :aria-label="t('seatingplan::tables.table_order.close')"
                icon="tabler-x"
                variant="text"
                :disabled="mutating"
                @click="optionSheet = false"
              />
            </div>

            <div
              v-if="selectedProduct"
              class="option-sheet-purchase"
            >
              <div class="option-sheet-thumbnail">
                <VImg
                  v-if="selectedProduct.thumbnail"
                  :src="selectedProduct.thumbnail"
                  :alt="selectedProduct.name"
                  height="80"
                  width="80"
                >
                  <template #error>
                    <VIcon
                      icon="tabler-photo-off"
                      size="28"
                    />
                  </template>
                </VImg>
                <VIcon
                  v-else
                  icon="tabler-bowl-spoon"
                  size="28"
                />
              </div>
              <div class="option-sheet-product">
                <h3>{{ selectedProduct.name }}</h3>
                <div
                  class="option-sheet-price"
                  :class="{ 'option-sheet-price--offer': productHasOffer(selectedProduct) }"
                >
                  <span
                    v-if="productHasOffer(selectedProduct)"
                    class="option-sheet-price__current"
                  >
                    {{ productPrice(selectedProduct) }}
                  </span>

                  <span
                    class="option-sheet-price__original"
                    :class="{ 'is-struck': productHasOffer(selectedProduct) }"
                  >
                    {{
                      productHasOffer(selectedProduct)
                        ? productOriginalPrice(selectedProduct)
                        : productPrice(selectedProduct)
                    }}
                  </span>

                  <span
                    v-if="productHasOffer(selectedProduct)"
                    class="option-sheet-price__badge"
                  >
                    <VIcon
                      icon="tabler-rosette-discount-check"
                      size="14"
                    />
                    {{ t('seatingplan::tables.table_order.offer') }}
                  </span>
                </div>
              </div>
            </div>
          </header>

          <VCardText class="option-sheet-body">
            <VAlert
              v-if="inlineError"
              class="mb-4"
              type="error"
              variant="tonal"
            >
              {{ inlineError }}
            </VAlert>

            <div
              v-for="option in selectedProductOptions"
              :key="option.id"
              class="option-group"
            >
              <VSelect
                v-if="['select', 'multiple_select'].includes(optionType(option))"
                v-model="optionValues[option.id]"
                :disabled="mutating"
                :chips="optionType(option) === 'multiple_select'"
                :clearable="!option.is_required"
                :error="!!optionError(option)"
                :error-messages="optionError(option)"
                item-title="name"
                item-value="id"
                :items="optionItems(option)"
                :multiple="optionType(option) === 'multiple_select'"
                @update:model-value="clearOptionError(option.id)"
              >
                <template #label>
                  {{ option.name }}&nbsp;
                  <span
                    v-if="option.is_required"
                    class="text-error"
                  >
                    *
                  </span>
                </template>
              </VSelect>

              <VRadioGroup
                v-else-if="optionType(option) === 'radio'"
                v-model="optionValues[option.id]"
                :disabled="mutating"
                :error="!!optionError(option)"
                :error-messages="optionError(option)"
                @update:model-value="clearOptionError(option.id)"
              >
                <template #label>
                  {{ option.name }}&nbsp;
                  <span
                    v-if="option.is_required"
                    class="text-error"
                  >
                    *
                  </span>
                </template>
                <VRadio
                  v-for="value in normalizeCollection(option.values)"
                  :key="value.id"
                  :label="optionValueLabel(value)"
                  :value="value.id"
                />
              </VRadioGroup>

              <div
                v-else-if="optionType(option) === 'checkbox'"
                class="option-group__checks"
                role="group"
                :aria-labelledby="`option-label-${option.id}`"
              >
                <div :id="`option-label-${option.id}`">
                  {{ option.name }}&nbsp;
                  <span
                    v-if="option.is_required"
                    class="text-error"
                  >
                    *
                  </span>
                </div>
                <VCheckbox
                  v-for="value in normalizeCollection(option.values)"
                  :key="value.id"
                  v-model="optionValues[option.id]"
                  :disabled="mutating"
                  hide-details
                  :label="optionValueLabel(value)"
                  :value="value.id"
                  @update:model-value="clearOptionError(option.id)"
                />

                <div
                  v-if="optionError(option)"
                  class="option-group__error"
                >
                  {{ optionError(option) }}
                </div>
              </div>

              <VTextarea
                v-else-if="optionType(option) === 'textarea'"
                v-model="optionValues[option.id]"
                :disabled="mutating"
                auto-grow
                :error="!!optionError(option)"
                :error-messages="optionError(option)"
                rows="2"
                @update:model-value="clearOptionError(option.id)"
              >
                <template #label>
                  {{ option.name }}&nbsp;
                  <span
                    v-if="option.is_required"
                    class="text-error"
                  >
                    *
                  </span>
                </template>
              </VTextarea>

              <VTextField
                v-else
                v-model="optionValues[option.id]"
                :disabled="mutating"
                :error="!!optionError(option)"
                :error-messages="optionError(option)"
                @update:model-value="clearOptionError(option.id)"
              >
                <template #label>
                  {{ option.name }}&nbsp;
                  <span
                    v-if="option.is_required"
                    class="text-error"
                  >
                    *
                  </span>
                </template>
              </VTextField>
            </div>
          </VCardText>

          <footer class="sheet-footer option-sheet-footer">
            <div class="option-quantity-row">
              <strong>{{ t('seatingplan::tables.table_order.quantity') }}</strong>
              <div
                :aria-label="t('seatingplan::tables.table_order.quantity')"
                class="qty-stepper"
              >
                <VBtn
                  class="qty-stepper__button"
                  color="primary"
                  icon
                  size="small"
                  variant="tonal"
                  :aria-label="
                    t('seatingplan::tables.table_order.decrease_quantity', {
                      product: selectedProduct?.name,
                    })
                  "
                  :disabled="!!editingLineId || mutating || selectedQty <= 1"
                  @click="selectedQty = Math.max(1, selectedQty - 1)"
                >
                  <VIcon icon="tabler-minus" />
                </VBtn>

                <strong>{{ selectedQty }}</strong>

                <VBtn
                  class="qty-stepper__button"
                  color="primary"
                  icon
                  size="small"
                  variant="tonal"
                  :aria-label="
                    t('seatingplan::tables.table_order.increase_quantity', {
                      product: selectedProduct?.name,
                    })
                  "
                  :disabled="mutating || !!editingLineId || selectedQty >= 99"
                  @click="selectedQty += 1"
                >
                  <VIcon icon="tabler-plus" />
                </VBtn>
              </div>
            </div>

            <div class="sheet-footer--actions">
              <VBtn
                class="dialog-secondary-action"
                color="secondary"
                size="large"
                variant="tonal"
                :disabled="mutating"
                @click="optionSheet = false"
              >
                <VIcon
                  icon="tabler-x"
                  start
                />
                {{ t('seatingplan::tables.table_order.close') }}
              </VBtn>

              <VBtn
                class="dialog-primary-action"
                color="primary"
                :loading="mutating"
                size="large"
                variant="flat"
                @click="addSelectedProduct"
              >
                <VIcon
                  icon="tabler-plus"
                  start
                />
                {{ editingLineId ? t('cart::local.save_options') : t('seatingplan::tables.table_order.add_to_order') }}
              </VBtn>
            </div>
          </footer>
        </VCard>
      </VDialog>

      <VDialog
        v-model="cartSheet"
        class="table-order-dialog"
        max-width="520"
        scrollable
      >
        <TableOrderSummary
          v-model:discount-code="discountCode"
          :cart="cart"
          :total-label="cartTotalLabel"
          :table-name="table.name || ''"
          :allow-discount-code="Boolean(settings.table_qr_allow_discount_code)"
          :can-submit="canSubmit"
          :busy="localCart.processing.value || mutating || discountLoading || submitting"
          :submitting="submitting"
          :discount-loading="discountLoading"
          :discount-error="discountError"
          :inline-error="inlineError"
          @change-qty="changeQty"
            @edit-options="editLine"
          @remove-item="removeItem"
          @apply-discount="applyDiscountCode"
          @remove-discount="removeDiscountCode"
          @clear-discount-error="discountError = null"
          @review="openSubmit"
          closable
          @close="cartSheet = false"
        />
      </VDialog>

      <VDialog
        v-model="submitSheet"
        scrollable
        class="table-order-dialog"
        max-width="460"
      >
        <VCard class="sheet-card submit-sheet">
          <header class="sheet-header">
            <div class="sheet-header__copy">
              <h2>{{ t('seatingplan::tables.table_order.send_order') }}</h2>
            </div>
          </header>

          <VCardText class="sheet-body submit-sheet__body">
            <div class="submit-review">
              <span>
                {{ table.name }} ·
                {{ t('seatingplan::tables.table_order.items_count', cartQuantity) }}
                <small class="d-block">{{ cartTotalLabel }}</small>
              </span>
              <strong>{{ cartTotal }}</strong>
            </div>
            <VTextField
              v-if="settings.table_qr_require_guest_count"
              v-model.number="submitForm.guest_count"
              class="submit-field"
              :error="!!submitErrors.guest_count"
              :error-messages="submitErrors.guest_count"
              :label="t('seatingplan::tables.table_order.guests_max', { max: maxGuestCount })"
              :max="maxGuestCount"
              min="1"
              type="number"
              @update:model-value="clearSubmitError('guest_count')"
            />

            <VTextarea
              v-if="settings.table_qr_allow_order_notes"
              v-model="submitForm.customer_note"
              auto-grow
              class="submit-field"
              counter="500"
              :error="!!submitErrors.customer_note"
              :error-messages="submitErrors.customer_note"
              :label="t('seatingplan::tables.table_order.note_for_staff')"
              maxlength="500"
              rows="3"
              @update:model-value="clearSubmitError('customer_note')"
            />

            <VAlert
              v-if="submitError"
              class="mt-2"
              type="error"
              variant="tonal"
            >
              {{ submitError }}
            </VAlert>
          </VCardText>

          <footer class="sheet-footer sheet-footer--actions">
            <VBtn
              class="dialog-secondary-action"
              color="secondary"
              size="large"
              variant="tonal"
              :disabled="submitting || !!localCart.draft.value?.submission"
              @click="closeSubmit(true)"
            >
              <VIcon
                icon="tabler-arrow-left"
                start
              />
              {{ t('seatingplan::tables.table_order.close') }}
            </VBtn>

            <VBtn
              class="dialog-primary-action"
              color="primary"
              :loading="submitting"
              size="large"
              variant="flat"
              @click="submitOrder"
            >
              <VIcon
                icon="tabler-send"
                start
              />
              {{ t('seatingplan::tables.table_order.confirm_order') }}
            </VBtn>
          </footer>
        </VCard>
      </VDialog>

      <VDialog
        v-model="submittedDialog"
        class="table-order-dialog"
        max-width="420"
      >
        <section
          aria-live="polite"
          class="success-dialog"
        >
          <div class="success-dialog__mark">
            <VIcon
              icon="tabler-check"
              size="32"
            />
          </div>

          <h2>{{ t('seatingplan::tables.table_order.order_sent') }}</h2>
          <p>{{ t('seatingplan::tables.table_order.order_sent_message') }}</p>

          <div class="success-dialog__reference">
            <span>{{ t('seatingplan::tables.table_order.order') }}</span>
            <strong>{{ submittedOrder?.reference_no || submittedOrder?.order_number }}</strong>
          </div>

          <VBtn
            block
            class="success-dialog__action"
            color="primary"
            size="large"
            variant="flat"
            @click="submittedOrder = null"
          >
            <VIcon
              icon="tabler-check"
              start
            />
            {{ t('seatingplan::tables.table_order.done') }}
          </VBtn>
        </section>
      </VDialog>
    </template>
  </main>
</template>

<style lang="scss" src="./table-order.scss"></style>
