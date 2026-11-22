import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent, ref } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import Payment from './Index.vue'
import OrderSummary from './OrderSummary/Index.vue'
const { storePayment, getPaymentMeta } = vi.hoisted(() => ({
  storePayment: vi.fn(),
  getPaymentMeta: vi.fn(),
}))
vi.mock('@/modules/order/composables/order.ts', () => ({
  useOrder: () => ({ storePayment, getPaymentMeta, printPreview: vi.fn() }),
}))
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))
vi.mock('vue-toastification', () => ({ useToast: () => ({ error: vi.fn(), success: vi.fn() }) }))
vi.mock('vuetify', () => ({
  useDisplay: () => ({ smAndDown: ref(false) }),
  useTheme: () => ({ current: ref({ colors: { primary: '#f57c00' } }) }),
}))
const money = (amount: number) => ({ amount, formatted: `$${amount}` })
const formFields = defineComponent({
  props: ['form'],
  template: `<div>
    <div v-for="(payment, index) in form.state.payments" :data-payment-field="'payments.' + index + '.amount'"><input :aria-label="index === 0 ? 'amount' : 'amount-' + index" v-model="payment.amount" /></div>
    <select aria-label="mode" v-model="form.state.payment_mode"><option value="full">Full</option><option value="partial">Partial</option></select>
    <div data-payment-field="customer_given_amount"><input aria-label="given" v-model="form.state.customer_given_amount" /></div>
  </div>`,
})
let wrapper: ReturnType<typeof mount>
beforeEach(() => {
  vi.clearAllMocks()
  Element.prototype.scrollIntoView = vi.fn()
  getPaymentMeta.mockResolvedValue({
    data: {
      body: {
        payment_methods: [{ id: 'cash', name: 'Cash' }],
        payment_modes: [],
        order: {
          currency: 'USD',
          precision: 2,
          due_amount: money(100),
          total_products: 1,
          sub_total: money(100),
          total_tax: money(0),
          grand_total: money(100),
          total_paid: money(0),
        },
      },
    },
  })
})
afterEach(() => wrapper?.unmount())
async function setup() {
  wrapper = mount(Payment, {
    attachTo: document.body,
    props: {
      modelValue: true,
      orderId: 1,
      registerId: 1,
      sessionId: 1,
      qintrix: { isSetup: ref(false) } as never,
    },
    global: {
      stubs: {
        VDialog: { template: '<div><slot /></div>' },
        VCard: { template: '<div><slot /></div>' },
        VRow: { template: '<div><slot /></div>' },
        VCol: { template: '<div><slot /></div>' },
        VCardText: { template: '<div><slot /></div>' },
        VIcon: true,
        VProgressCircular: true,
        VBtn: {
          props: ['disabled', 'loading'],
          template: '<button :disabled="disabled"><slot /></button>',
        },
        FinalizePayment: formFields,
      },
    },
  })
  await flushPromises()
  return wrapper
}
function action(label: string) {
  return wrapper.findAll('button').find((button) => button.text() === label)!
}
describe('payment submission feedback', () => {
  it('keeps both actions enabled and focuses invalid cash without submitting', async () => {
    await setup()
    const submit = action('order::orders.pay')
    const print = action('order::orders.pay_and_print')
    expect(submit.attributes('disabled')).toBeUndefined()
    expect(print.attributes('disabled')).toBeUndefined()
    await submit.trigger('click')
    await flushPromises()
    expect(storePayment).not.toHaveBeenCalled()
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    expect(
      wrapper.findComponent(formFields).props('form').errors.value.customer_given_amount,
    ).toContain('payment_cash_short')
    expect(document.activeElement).toBe(wrapper.get('[aria-label="given"]').element)
    await print.trigger('click')
    await flushPromises()
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    expect(
      wrapper.findComponent(formFields).props('form').errors.value.customer_given_amount,
    ).toContain('payment_cash_short')
    expect(storePayment).not.toHaveBeenCalled()
  })
  it('submits once after correction and prevents duplicate requests during processing', async () => {
    let finish!: (value: unknown) => void
    storePayment.mockImplementation(
      () =>
        new Promise((resolve) => {
          finish = resolve
        }),
    )
    await setup()
    await wrapper.get('[aria-label="given"]').setValue('120')
    wrapper.findComponent(OrderSummary).vm.$emit('submit')
    wrapper.findComponent(OrderSummary).vm.$emit('submit')
    await flushPromises()
    expect(storePayment).toHaveBeenCalledTimes(1)
    expect(storePayment.mock.calls[0]![1].change_return).toBe('20.00')
    expect(action('order::orders.pay').attributes('disabled')).toBeDefined()
    expect(action('order::orders.pay_and_print').attributes('disabled')).toBeDefined()
    finish({ data: {} })
    await flushPromises()
    expect(wrapper.emitted('payment-added')).toHaveLength(1)
  })
  it('recalculates change when the cash allocation changes', async () => {
    await setup()
    await wrapper.get('[aria-label="given"]').setValue('120')
    expect(wrapper.findComponent(OrderSummary).props('changeReturn')).toBe('20.00')
    await wrapper.get('[aria-label="amount"]').setValue('80')
    expect(wrapper.findComponent(OrderSummary).props('changeReturn')).toBe('40.00')
  })
  it('passes the print choice and preserves server errors for that action', async () => {
    storePayment.mockRejectedValue({
      response: { status: 400, data: { message: 'Payment unavailable.' } },
    })
    await setup()
    await wrapper.get('[aria-label="given"]').setValue('100')
    await action('order::orders.pay_and_print').trigger('click')
    await flushPromises()
    expect(storePayment).toHaveBeenCalledTimes(1)
    expect(storePayment.mock.calls[0]![1].with_print).toBe(true)
    expect(wrapper.get('[role="alert"]').text()).toContain('Payment unavailable.')
    expect(action('order::orders.pay_and_print').attributes('disabled')).toBeUndefined()
  })
  it('handles cash + card + partial payment with an earlier payment', async () => {
    getPaymentMeta.mockResolvedValueOnce({
      data: {
        body: {
          payment_methods: [
            { id: 'cash', name: 'Cash' },
            { id: 'card', name: 'Card' },
          ],
          payment_modes: [],
          order: {
            currency: 'USD',
            precision: 2,
            due_amount: money(100),
            total_products: 2,
            sub_total: money(160),
            total_tax: money(0),
            grand_total: money(160),
            total_paid: money(60),
          },
        },
      },
    })
    storePayment.mockResolvedValue({ data: {} })
    await setup()
    const fields = wrapper.findComponent(formFields)
    await wrapper.get('[aria-label="amount"]').setValue('30')
    fields.vm.$emit('add-payment')
    await flushPromises()
    expect(fields.props('form').state.payments[1]).toMatchObject({ method: 'card', amount: 70 })
    await wrapper.get('[aria-label="amount-1"]').setValue('20')
    await wrapper.get('[aria-label="mode"]').setValue('partial')
    fields.vm.$emit('set-active-input', 'customer_given_amount')
    fields.vm.$emit('quick-pay', 10)
    fields.vm.$emit('quick-pay', 20)
    await flushPromises()
    expect(wrapper.findComponent(OrderSummary).props()).toMatchObject({
      customerGivenAmount: '30.00',
      changeReturn: '0.00',
      currentTotalPaid: 50,
      finalDueAmount: 50,
    })
    fields.vm.$emit('set-active-input', 'payments.1.amount')
    fields.vm.$emit('quick-pay', 10)
    await flushPromises()
    expect(fields.props('form').state.payments[1].amount).toBe('30.00')
    await wrapper.get('[aria-label="amount-1"]').setValue('20')
    expect(fields.props('form').state.customer_given_amount).toBe('30.00')
    expect(wrapper.findComponent(OrderSummary).props('order').total_paid.amount).toBe(60)
    expect(wrapper.get('.amount-due-banner').text()).toContain('$100')
    await action('order::orders.pay').trigger('click')
    await flushPromises()
    expect(storePayment).toHaveBeenCalledTimes(1)
    expect(storePayment.mock.calls[0]![1]).toMatchObject({
      payment_mode: 'partial',
      customer_given_amount: '30.00',
      change_return: '0.00',
      payments: [
        { method: 'cash', amount: '30' },
        { method: 'card', amount: '20' },
      ],
    })
  })
  it('shows server field errors at the affected input and permits retry', async () => {
    storePayment.mockRejectedValue({
      response: {
        status: 422,
        data: { errors: { 'payments.0.amount': ['Balance changed. Check the amount.'] } },
      },
    })
    await setup()
    await wrapper.get('[aria-label="given"]').setValue('100')
    await action('order::orders.pay').trigger('click')
    await flushPromises()
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    expect(wrapper.findComponent(formFields).props('form').errors.value['payments.0.amount']).toBe(
      'Balance changed. Check the amount.',
    )
    expect(document.activeElement).toBe(wrapper.get('[aria-label="amount"]').element)
    expect(action('order::orders.pay').attributes('disabled')).toBeUndefined()
  })
  it('shows general server failures in the dialog without closing it', async () => {
    storePayment.mockRejectedValue({
      response: { status: 400, data: { message: 'Register session is closed.' } },
    })
    await setup()
    await wrapper.get('[aria-label="given"]').setValue('100')
    await action('order::orders.pay').trigger('click')
    await flushPromises()
    expect(wrapper.get('[role="alert"]').text()).toContain('Register session is closed.')
    expect(document.activeElement).toBe(wrapper.get('[role="alert"]').element)
    expect(wrapper.emitted('payment-added')).toBeUndefined()
    expect(action('order::orders.pay').attributes('disabled')).toBeUndefined()
  })
})
