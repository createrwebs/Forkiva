import type * as VueI18n from 'vue-i18n'
import { flushPromises, shallowMount } from '@vue/test-utils'
import { computed, reactive, ref } from 'vue'
import { afterEach, expect, it, vi } from 'vitest'
import OrderPanel from './Index.vue'
import { pendingOrderPrints } from '@/modules/pos/services/orderPrintQueue'

const { store, getPrintMeta, printPreview, error, confirmPrice } = vi.hoisted(() => ({
  store: vi.fn(),
  getPrintMeta: vi.fn(),
  printPreview: vi.fn(),
  error: vi.fn(),
  confirmPrice: vi.fn().mockResolvedValue(true),
}))
vi.mock('@/modules/cart/services/submitDraft', () => ({
  submitDraft: async (_cart: any, payload: any, _quote: any, send: any, confirm: any) => {
    if (!(await confirm('JOD 10.000', 'Updated item: 9 → 10'))) return null
    return send(payload)
  },
}))
vi.mock('@/modules/order/composables/order.ts', () => ({
  useOrder: () => ({ store, getPrintMeta, printPreview }),
}))
vi.mock('@/modules/auth/composables/auth.ts', () => ({ useAuth: () => ({ can: () => true }) }))
vi.mock('@/modules/core/composables/confirmDialog.ts', () => ({
  confirmDialog: vi.fn(),
  useConfirmDialog: confirmPrice,
}))
vi.mock('vue-i18n', async (importOriginal) => ({
  ...(await importOriginal<typeof VueI18n>()),
  useI18n: () => ({ t: (key: string) => key }),
}))
vi.mock('vue-toastification', () => ({ useToast: () => ({ error, success: vi.fn() }) }))

const orderPanelStubs = {
  VAlert: true,
  VIcon: true,
  VCardTitle: true,
  VCardText: true,
  VCardActions: true,
  VCard: true,
  VDialog: true,
  VBtn: { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
}

afterEach(() => vi.clearAllMocks())

it('opens payment and resets the POS without waiting for printing, using the original register', async () => {
  let finish!: (value: any) => void
  getPrintMeta.mockReturnValue(
    new Promise((resolve) => {
      finish = resolve
    }),
  )
  let finishSave!: (value: any) => void
  store.mockReturnValue(
    new Promise((resolve) => {
      finishSave = resolve
    }),
  )
  printPreview.mockResolvedValue({
    data: { body: { content: 'pdf', printer: { qintrix_id: 'kitchen-1', paper_size: '80mm' } } },
  })
  const createJob = vi.fn().mockResolvedValue({ id: 1 })
  const submitting = ref(false)
  const form = reactive({
    mode: 'create',
    branchId: 1,
    registerId: 2,
    menuId: 3,
    sessionId: 4,
    meta: {},
    refundPaymentMethod: null,
  })
  const cart = {
    cartId: 'cart',
    draft: ref(undefined),
    recoverableDrafts: ref([]),
    data: ref({
      items: [{ id: 'a' }],
      version: 5,
      orderType: { id: 'takeaway' },
      total: { amount: 10, formatted: '10', precision: 2 },
    }),
    processing: computed(() => submitting.value),
    hasPendingMutations: ref(false),
    syncFailed: ref(false),
    pendingProducts: ref([]),
    withConfirmedCart: async (callback: () => Promise<void>) => {
      if (submitting.value) return false
      submitting.value = true
      try {
        await callback()
        return true
      } finally {
        submitting.value = false
      }
    },
  }
  const wrapper = shallowMount(OrderPanel, {
    props: {
      form,
      loadCustomers: vi.fn(),
      meta: { order: null },
      cart,
      qintrix: { isSetup: ref(true), captureJobCreator: () => createJob },
    } as any,
    global: {
      stubs: orderPanelStubs,
    },
  })
  const pay = wrapper
    .findAll('button')
    .find((button) => button.text().includes('pos::pos_viewer.pay_and_fire'))!
  await pay.trigger('click')
  await pay.trigger('click')
  await flushPromises()
  finishSave({ data: { body: { order_id: 'ORD-1', cart: { items: [] } } } })
  await flushPromises()
  expect(confirmPrice).toHaveBeenCalledWith({
    message: 'cart::local.confirm_price\nUpdated item: 9 → 10',
  })
  expect(store).toHaveBeenCalledTimes(1)
  expect(store).toHaveBeenCalledWith(
    expect.objectContaining({ register_id: 2, submit_action: 'pay_and_fire' }),
  )
  expect(wrapper.emitted('store-payment')).toEqual([['ORD-1']])
  expect(wrapper.emitted('reset')).toHaveLength(1)
  expect(pendingOrderPrints.value).toBe(1)
  form.registerId = 99
  finish({ data: { body: { contents: [{ id: 7, type: { id: 'kitchen' } }] } } })
  await flushPromises()
  expect(printPreview).toHaveBeenCalledWith('ORD-1', 'kitchen', 7, 2, 'pdf')
  expect(createJob).toHaveBeenCalledTimes(1)
  expect(pendingOrderPrints.value).toBe(0)
  cart.data.value.orderType.id = 'dine_in'
  await flushPromises()
  const kitchen = wrapper.get('.pos-submit-kitchen')
  expect(kitchen.attributes('disabled')).toBeDefined()
  await kitchen.trigger('click')
  expect(store).toHaveBeenCalledTimes(1)
  cart.data.value.orderType.id = 'takeaway'
  await flushPromises()
  expect(kitchen.attributes('disabled')).toBeUndefined()
  wrapper.unmount()
})

it('requires a refund card code and sends the destination with the edited order', async () => {
  const form = reactive({
    mode: 'edit',
    branchId: 1,
    registerId: 2,
    menuId: 3,
    sessionId: 4,
    meta: {},
    refundPaymentMethod: 'gift_card',
    refundGiftCardCode: '',
  })
  const cart = {
    draft: ref(undefined),
    recoverableDrafts: ref([]),
    data: ref({
      items: [{ id: 'a' }],
      orderType: { id: 'takeaway' },
      total: { amount: 90, precision: 2 },
    }),
    processing: ref(false),
    syncFailed: ref(false),
    pendingProducts: ref([]),
    withConfirmedCart: async (callback: () => Promise<void>) => callback(),
  }
  store.mockResolvedValue({ data: { body: { order_id: 'ORDER-1' } } })
  const wrapper = shallowMount(OrderPanel, {
    props: {
      form,
      cart,
      loadCustomers: vi.fn(),
      meta: {
        order: {
          status: { id: 'confirmed' },
          total: { amount: 100 },
          due_amount: { amount: 0, precision: 2 },
        },
      },
      qintrix: { isSetup: ref(false) },
    } as any,
    global: {
      stubs: orderPanelStubs,
    },
  })
  const send = wrapper
    .findAll('button')
    .find((button) => button.text().includes('pos::pos_viewer.send_to_kitchen'))!
  expect(send.attributes('disabled')).toBeDefined()
  form.refundGiftCardCode = '   '
  await flushPromises()
  expect(send.attributes('disabled')).toBeDefined()
  form.refundGiftCardCode = ' CARD-CODE '
  await flushPromises()
  expect(send.attributes('disabled')).toBeUndefined()
  await send.trigger('click')
  await flushPromises()
  expect(store).toHaveBeenCalledWith(
    expect.objectContaining({ refund_payment_method: 'gift_card', gift_card_code: 'CARD-CODE' }),
  )
  wrapper.unmount()
})

it('calculates edited balances in currency minor units', async () => {
  const meta = reactive({
    order: {
      total: { amount: 103.8346 },
      due_amount: { amount: 103.83, precision: 2 },
    },
  })
  const cart = {
    draft: ref(undefined),
    recoverableDrafts: ref([]),
    data: ref({
      items: [{ id: 'a' }],
      orderType: { id: 'delivery' },
      deliveryContext: { customerAddress: { id: 9 }, pricing: { available: true } },
      total: { amount: 146.515, formatted: '$146.52', currency: 'USD', precision: 2 },
    }),
    processing: ref(false),
    hasPendingMutations: ref(false),
    syncFailed: ref(false),
    pendingProducts: ref([]),
    withConfirmedCart: vi.fn(),
  }
  const wrapper = shallowMount(OrderPanel, {
    props: {
      form: {
        mode: 'edit',
        branchId: 1,
        registerId: 2,
        menuId: 3,
        sessionId: 4,
        meta: {},
        refundPaymentMethod: null,
      },
      cart,
      loadCustomers: vi.fn(),
      meta,
      qintrix: { isSetup: ref(false) },
    } as any,
    global: { stubs: orderPanelStubs },
  })

  expect((wrapper.vm as any).overpaidAmount).toBe(-146.52)
  expect((wrapper.vm as any).amountDue).toBe(0)

  meta.order.due_amount.amount = 83.83
  await flushPromises()
  expect((wrapper.vm as any).overpaidAmount).toBe(-126.52)
  expect((wrapper.vm as any).amountDue).toBe(126.52)
  wrapper.unmount()
})

it('disables delivery submission while the selected address has unavailable pricing', async () => {
  const data = ref<any>({
    items: [{ id: 'a' }],
    orderType: { id: 'delivery' },
    deliveryContext: {
      customerAddress: { id: 9 },
      pricing: { available: false, message: 'Outside delivery zones' },
    },
    total: { amount: 10, formatted: 'JOD 10.000', precision: 3 },
  })
  const cart = {
    draft: ref({ delivery: { customer_address_id: 9 } }),
    recoverableDrafts: ref([]),
    data,
    processing: ref(false),
    hasPendingMutations: ref(false),
    syncFailed: ref(false),
    pendingProducts: ref([]),
    withConfirmedCart: vi.fn(),
  }
  const wrapper = shallowMount(OrderPanel, {
    props: {
      form: {
        mode: 'create',
        branchId: 1,
        registerId: 2,
        menuId: 3,
        sessionId: 4,
        meta: {},
        refundPaymentMethod: null,
      },
      cart,
      loadCustomers: vi.fn(),
      meta: { order: null },
      qintrix: { isSetup: ref(false) },
    } as any,
    global: {
      stubs: orderPanelStubs,
    },
  })
  const send = wrapper
    .findAll('button')
    .find((button) => button.text().includes('pos::pos_viewer.send_to_kitchen'))!

  expect(send.attributes('disabled')).toBeDefined()
  data.value.deliveryContext.pricing.available = true
  await flushPromises()
  expect(send.attributes('disabled')).toBeUndefined()
  wrapper.unmount()
})

it.each(['create', 'edit'])(
  'shows unresolved submissions only after the active %s request finishes',
  async (mode) => {
    let rejectSave!: (error: Error) => void
    const draft = ref<any>(undefined)
    store.mockImplementation(() => {
      draft.value = {
        submission: { state: 'pending', payload: { submit_action: 'send_to_kitchen' } },
      }
      return new Promise((_resolve, reject) => {
        rejectSave = reject
      })
    })
    const cart = {
      draft,
      recoverableDrafts: ref([]),
      data: ref({
        items: [{ id: 'a' }],
        orderType: { id: 'takeaway' },
        total: { amount: 10, precision: 2 },
      }),
      processing: ref(false),
      syncFailed: ref(false),
      pendingProducts: ref([]),
      getCart: vi.fn(),
      withConfirmedCart: async (callback: () => Promise<void>) => callback(),
    }
    const wrapper = shallowMount(OrderPanel, {
      props: {
        form: {
          mode,
          branchId: 1,
          registerId: 2,
          menuId: 3,
          sessionId: 4,
          meta: {},
          refundPaymentMethod: null,
        },
        cart,
        loadCustomers: vi.fn(),
        meta: { order: null },
        qintrix: { isSetup: ref(false) },
      } as any,
      global: {
        stubs: {
          ...orderPanelStubs,
          VAlert: { template: '<aside><slot /></aside>' },
        },
      },
    })
    const warning = () => wrapper.text().includes('cart::local.pending')
    expect(warning()).toBe(false)
    await wrapper
      .findAll('button')
      .find((button) => button.text().includes('pos::pos_viewer.send_to_kitchen'))!
      .trigger('click')
    await flushPromises()
    expect(draft.value.submission).toBeDefined()
    expect(warning()).toBe(false)
    rejectSave(new Error('Response lost'))
    await flushPromises()
    expect(warning()).toBe(true)
    expect(draft.value.submission.payload.submit_action).toBe('send_to_kitchen')
    wrapper.unmount()
  },
)
