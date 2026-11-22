import { flushPromises, shallowMount } from '@vue/test-utils'
import * as Vue from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import TableOrder from './Index.vue'
import { submitDraft } from '@/modules/cart/services/submitDraft'

const mocks = vi.hoisted(() => ({
  context: vi.fn(),
  remove: vi.fn(),
  submit: vi.fn(),
  tracking: vi.fn(),
  local: null as any,
}))

vi.mock('@/modules/core/composables/confirmDialog', () => ({
  useConfirmDialog: vi.fn().mockResolvedValue(true),
}))

vi.mock('vue-i18n', () => ({
  useI18n: () => ({ t: (key: string) => key }),
}))

vi.mock('@/modules/core/stores/appStore.ts', () => ({
  useAppStore: () => ({ appName: 'Forkiva', favicon: null, logo: null }),
}))

vi.mock('@/modules/core/utils/uuid.ts', () => ({
  generateUuid: () => '71821976-20c1-4bd8-a5c3-7f759f6e053b',
}))

vi.mock('@/modules/seatingPlan/api/table_qr_order.api.ts', () => ({
  quoteTableQrOrder:vi.fn().mockResolvedValue({data:{body:{pricing:{total:{amount:0}},quote_token:'signed'}}}),
  destroyTableQrCartItem: mocks.remove,
  getTableQrContext: mocks.context,
  getTableQrTracking: mocks.tracking,
  removeTableQrDiscountCode: vi.fn(),
  storeTableQrCartItem: vi.fn(),
  submitTableQrOrder: mocks.submit,
  updateTableQrCartItem: vi.fn(),
}))

vi.mock('@/modules/cart/composables/cart.ts',()=>({useCart:()=>mocks.local}))
vi.mock('@/modules/cart/services/submitDraft',()=>({submitDraft:vi.fn()}))

const activeOrder = {
  id: 91,
  items: [],
  progress: {},
  progress_steps: [],
  status: { id: 'preparing' },
}

const contextBody = {
  active_order: activeOrder,
  branch: { id: 1, name: 'Main', currency: 'JOD' },
  cart: { items: [], quantity: 0, total: { formatted: '0' } },
  categories: [],
  menu: { id: 1, name: 'Main Menu' },
  products: [],
  session: { available: true, message: null, reason: null },
  settings: {},
  table: { uuid: 'table-uuid', name: 'T1', capacity: 4, status: { id: 'occupied' } },
}

const trackingBody = {
  active_order: activeOrder,
  session: { available: true, message: null, reason: null },
  table: { status: { id: 'occupied' } },
}

function mountTableOrder() {
  return shallowMount(TableOrder, {
    global: {
      mocks: { $t: (key: string) => key },
      stubs: {
        VAlert: true,
        VBtn: { template: '<button><slot /></button>' },
        VCard: { template: '<section><slot /></section>' },
        VCardText: { template: '<div><slot /></div>' },
        VCheckbox: true,
        VDialog: { template: '<div><slot /></div>' },
        VIcon: true,
        VImg: true,
        VProgressCircular: true,
        VRadio: true,
        VRadioGroup: true,
        VSelect: true,
        VTextarea: true,
        VTextField: true,
      },
    },
  })
}

describe('public table-order tracking polling', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    vi.mocked(submitDraft).mockImplementation(async (_cart, _extra, _quote, send) => send({}))
    vi.useFakeTimers()
    window.localStorage.clear()
    Object.defineProperty(document, 'visibilityState', {
      configurable: true,
      value: 'visible',
    })
    mocks.local={data:Vue.ref({items:[],quantity:0,total:{formatted:'0'}}),draft:Vue.ref(undefined),processing:Vue.ref(false),syncFailed:Vue.ref(false),configure:vi.fn(async(_scope:any,_context:any,snapshot:any)=>{mocks.local.data.value=snapshot}),deleteItem:vi.fn(async(id:string)=>{await mocks.remove(id);mocks.local.data.value={items:[],quantity:0,total:{formatted:'$0.00'}}}),setContext:vi.fn(),payload:()=>({})}
    mocks.context.mockResolvedValue({ data: { body: contextBody } })
    mocks.tracking.mockResolvedValue({ data: { body: trackingBody } })

    for (const name of [
      'computed',
      'onBeforeMount',
      'onBeforeUnmount',
      'onMounted',
      'reactive',
      'ref',
      'watch',
    ] as const) {
      vi.stubGlobal(name, Vue[name])
    }
    vi.stubGlobal('useRoute', () => ({
      params: { uuid: '7f8875ca-54e5-4bdb-9b89-d3db51c85d7f' },
      query: { token: 'signed-token' },
    }))
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.unstubAllGlobals()
  })

  it('returns to the editable cart when price confirmation is cancelled', async () => {
    const line = { id: 'cake', item: { name: 'Cake' }, qty: 1 }
    mocks.context.mockResolvedValueOnce({ data: { body: {
      ...contextBody, cart: { items: [line], quantity: 1, total: { formatted: '$10.00' } },
    } } })
    vi.mocked(submitDraft).mockResolvedValueOnce(null)
    const wrapper = mountTableOrder()
    await flushPromises()
    wrapper.findComponent({ name: 'TableOrderSummary' }).vm.$emit('review')
    await Vue.nextTick()
    await wrapper.find('.submit-sheet .dialog-primary-action').trigger('click')
    await flushPromises()
    expect((wrapper.vm as any).submitSheet).toBe(false)
    expect((wrapper.vm as any).submitting).toBe(false)
    expect((wrapper.vm as any).cartSheet).toBe(false)
    expect(mocks.local.data.value.items).toEqual([line])
    expect(mocks.submit).not.toHaveBeenCalled()
    expect(wrapper.findComponent({ name: 'TableOrderSummary' }).props('busy')).toBe(false)
    wrapper.unmount()
  })

  it('shows the previous-submission warning only after the active request becomes unresolved', async () => {
    const line = { id: 'cake', item: { name: 'Cake' }, qty: 1 }
    mocks.context.mockResolvedValueOnce({
      data: {
        body: {
          ...contextBody,
          cart: { items: [line], quantity: 1, total: { formatted: '$10.00' } },
        },
      },
    })
    let rejectSubmission!: (error: Error) => void
    vi.mocked(submitDraft).mockImplementationOnce(async () => {
      mocks.local.draft.value = {
        submission: { state: 'pending', payload: { idempotency_key: 'attempt' } },
      }
      return new Promise((_resolve, reject) => {
        rejectSubmission = reject
      })
    })
    const wrapper = mountTableOrder()
    await flushPromises()
    wrapper.findComponent({ name: 'TableOrderSummary' }).vm.$emit('review')
    await Vue.nextTick()
    await wrapper.find('.submit-sheet .dialog-primary-action').trigger('click')
    await Vue.nextTick()

    expect((wrapper.vm as any).submitting).toBe(true)
    expect((wrapper.vm as any).hasUnresolvedSubmission).toBe(false)
    expect(
      wrapper
        .findAllComponents({ name: 'VAlert' })
        .filter((alert) => alert.attributes('type') === 'warning'),
    ).toHaveLength(0)

    rejectSubmission(new Error('Response lost'))
    await flushPromises()
    await Vue.nextTick()

    expect((wrapper.vm as any).submitting).toBe(false)
    expect(mocks.local.draft.value?.submission).toBeDefined()
    expect((wrapper.vm as any).hasUnresolvedSubmission).toBe(true)
    expect(
      wrapper
        .findAllComponents({ name: 'VAlert' })
        .filter((alert) => alert.attributes('type') === 'warning'),
    ).toHaveLength(1)
    wrapper.unmount()
  })

  it('removes the selected cart line once and applies the returned totals', async () => {
    const line = { id: 'cake', item: { name: 'Cake' }, qty: 3 }
    mocks.context.mockResolvedValueOnce({
      data: {
        body: {
          ...contextBody,
          cart: { items: [line], quantity: 3, total: { formatted: '$30.00' } },
        },
      },
    })
    let finish!: (value: unknown) => void
    mocks.remove.mockReturnValueOnce(
      new Promise((resolve) => {
        finish = resolve
      }),
    )
    const wrapper = mountTableOrder()
    await flushPromises()
    const summary = wrapper.findComponent({ name: 'TableOrderSummary' })
    summary.vm.$emit('remove-item', line)
    summary.vm.$emit('remove-item', line)
    await Vue.nextTick()
    expect(mocks.remove).toHaveBeenCalledOnce()
    expect(mocks.remove.mock.calls[0]?.[0]).toBe('cake')
    expect(summary.props('busy')).toBe(true)
    finish({ data: { body: { cart: { items: [], quantity: 0, total: { formatted: '$0.00' } } } } })
    await flushPromises()
    expect(summary.props('cart').items).toEqual([])
    expect(summary.props('cart').total.formatted).toBe('$0.00')
    expect(summary.props('canSubmit')).toBe(false)
    wrapper.unmount()
  })

  it('keeps the cart item and shows the server message if removal fails', async () => {
    const line = { id: 'cake', item: { name: 'Cake' }, qty: 1 }
    mocks.context.mockResolvedValueOnce({
      data: { body: { ...contextBody, cart: { items: [line], quantity: 1 } } },
    })
    mocks.remove.mockRejectedValueOnce({ response: { data: { message: 'Please try again.' } } })
    const wrapper = mountTableOrder()
    await flushPromises()
    const summary = wrapper.findComponent({ name: 'TableOrderSummary' })
    summary.vm.$emit('remove-item', line)
    await flushPromises()
    expect(summary.props('cart').items).toEqual([line])
    expect(summary.props('inlineError')).toBe('Please try again.')
    expect(summary.props('busy')).toBe(false)
    wrapper.unmount()
  })

  it('shows guest-count submission errors on the guest input', async () => {
    const line = { id: 'cake', item: { name: 'Cake' }, qty: 1 }
    mocks.context.mockResolvedValueOnce({
      data: {
        body: {
          ...contextBody,
          cart: { items: [line], quantity: 1, total: { formatted: '$10.00' } },
          settings: { table_qr_require_guest_count: true },
        },
      },
    })
    mocks.submit.mockRejectedValueOnce({
      response: {
        data: {
          errors: { reason: 'guest_count_invalid' },
          message: 'Please enter a valid guest count for this table.',
        },
      },
    })
    const wrapper = mountTableOrder()
    await flushPromises()
    wrapper.findComponent({ name: 'TableOrderSummary' }).vm.$emit('review')
    await Vue.nextTick()

    const guestField = wrapper
      .findAllComponents({ name: 'VTextField' })
      .find((field) => field.attributes('type') === 'number')
    expect(guestField).toBeDefined()
    guestField!.vm.$emit('update:modelValue', 4)
    await Vue.nextTick()
    const confirmButton = wrapper.find('.submit-sheet .dialog-primary-action')
    expect(confirmButton.exists()).toBe(true)
    await confirmButton.trigger('click')
    await flushPromises()

    expect(mocks.submit).toHaveBeenCalledOnce()
    const updatedGuestField = wrapper
      .findAllComponents({ name: 'VTextField' })
      .find((field) => field.attributes('type') === 'number')
    expect(updatedGuestField!.attributes('error')).toBe('true')
    expect(updatedGuestField!.attributes('error-messages')).toBe(
      'Please enter a valid guest count for this table.',
    )
    expect(wrapper.text()).not.toContain('Please enter a valid guest count for this table.')
    wrapper.unmount()
  })

  it('shows an unexpected submission failure once without exposing the server exception', async () => {
    const line = { id: 'cake', item: { name: 'Cake' }, qty: 1 }
    mocks.context.mockResolvedValueOnce({
      data: {
        body: {
          ...contextBody,
          cart: { items: [line], quantity: 1, total: { formatted: '$10.00' } },
        },
      },
    })
    mocks.submit.mockRejectedValueOnce({
      response: {
        status: 500,
        data: { message: 'Trying to clone an uncloneable object of class Internal\\Enum' },
      },
    })
    const wrapper = mountTableOrder()
    await flushPromises()
    wrapper.findComponent({ name: 'TableOrderSummary' }).vm.$emit('review')
    await Vue.nextTick()

    await wrapper.find('.submit-sheet .dialog-primary-action').trigger('click')
    await flushPromises()

    expect((wrapper.vm as any).submitError).toBe(
      'seatingplan::tables.table_order.submit_failed',
    )
    expect(wrapper.findAllComponents({ name: 'VAlert' })).toHaveLength(1)
    expect(wrapper.findComponent({ name: 'TableOrderSummary' }).props('inlineError')).toBeNull()
    wrapper.unmount()
  })

  it('combines search with category filtering and clears an empty search safely', async () => {
    mocks.context.mockResolvedValueOnce({
      data: {
        body: {
          ...contextBody,
          categories: [
            { id: 1, name: 'Food', items: [{ id: 2, name: 'Burgers' }] },
            { id: 3, name: 'Drinks' },
          ],
          products: [
            { id: 1, name: 'Classic burger', category_ids: [2] },
            { id: 2, name: 'Lemonade', category_ids: [3] },
          ],
        },
      },
    })
    const wrapper = mountTableOrder()
    await flushPromises()
    const cards = () => wrapper.findAllComponents({ name: 'TableOrderProductCard' })
    expect(cards()).toHaveLength(2)
    await wrapper.findAll('.category-pill')[1]!.trigger('click')
    expect(cards()).toHaveLength(1)
    expect(cards()[0]!.props('product').name).toBe('Classic burger')
    const search = wrapper.findComponent({ name: 'VTextField' })
    search.vm.$emit('update:modelValue', 'LEMON')
    await Vue.nextTick()
    expect(cards()).toHaveLength(0)
    expect(wrapper.find('.menu-empty').exists()).toBe(true)
    await wrapper.findAll('.category-pill')[0]!.trigger('click')
    expect(cards()[0]!.props('product').name).toBe('Lemonade')
    search.vm.$emit('update:modelValue', null)
    await Vue.nextTick()
    expect(cards()).toHaveLength(2)
    wrapper.unmount()
  })

  it('loads the catalog once and uses only tracking responses afterward', async () => {
    const wrapper = mountTableOrder()
    await flushPromises()

    expect(mocks.context).toHaveBeenCalledOnce()
    expect(mocks.tracking).not.toHaveBeenCalled()

    vi.advanceTimersByTime(30_000)
    await flushPromises()

    expect(mocks.tracking).toHaveBeenCalledOnce()
    expect(mocks.context).toHaveBeenCalledOnce()
    wrapper.unmount()
  })

  it('does not overlap tracking requests when a response is slow', async () => {
    let resolveTracking!: (value: unknown) => void
    mocks.tracking.mockReturnValueOnce(
      new Promise((resolve) => {
        resolveTracking = resolve
      }),
    )
    const wrapper = mountTableOrder()
    await flushPromises()

    vi.advanceTimersByTime(30_000)
    await Vue.nextTick()
    vi.advanceTimersByTime(90_000)

    expect(mocks.tracking).toHaveBeenCalledOnce()

    resolveTracking({ data: { body: trackingBody } })
    await flushPromises()
    vi.advanceTimersByTime(30_000)
    await flushPromises()

    expect(mocks.tracking).toHaveBeenCalledTimes(2)
    wrapper.unmount()
  })

  it('pauses while hidden and refreshes immediately when visible again', async () => {
    const wrapper = mountTableOrder()
    await flushPromises()

    Object.defineProperty(document, 'visibilityState', {
      configurable: true,
      value: 'hidden',
    })
    document.dispatchEvent(new Event('visibilitychange'))
    vi.advanceTimersByTime(60_000)
    await flushPromises()
    expect(mocks.tracking).not.toHaveBeenCalled()

    Object.defineProperty(document, 'visibilityState', {
      configurable: true,
      value: 'visible',
    })
    document.dispatchEvent(new Event('visibilitychange'))
    await flushPromises()
    expect(mocks.tracking).toHaveBeenCalledOnce()

    vi.advanceTimersByTime(30_000)
    await flushPromises()
    expect(mocks.tracking).toHaveBeenCalledTimes(2)
    wrapper.unmount()
  })

  it('aborts an in-flight tracking request when the page unmounts', async () => {
    let requestSignal: AbortSignal | undefined
    mocks.tracking.mockImplementationOnce(
      (_uuid: string, _token: string, signal: AbortSignal) => {
        requestSignal = signal
        return new Promise((_resolve, reject) => {
          signal.addEventListener('abort', () => reject({ __CANCEL__: true }))
        })
      },
    )
    const wrapper = mountTableOrder()
    await flushPromises()

    vi.advanceTimersByTime(30_000)
    await Vue.nextTick()
    expect(requestSignal?.aborted).toBe(false)

    wrapper.unmount()
    await flushPromises()
    expect(requestSignal?.aborted).toBe(true)
  })
})
