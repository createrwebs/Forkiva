import { mount } from '@vue/test-utils'
import { reactive, ref } from 'vue'
import { describe, expect, it, vi } from 'vitest'
import Payments from './Payments.vue'
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))
describe('payment reference visibility', () => {
  it('shows transaction references only for electronic payments and clears hidden values', async () => {
    const payment = reactive({
      method: 'cash',
      amount: 30,
      transaction_id: null as string | null,
      gift_card_code: null as string | null,
    })
    const wrapper = mount(Payments, {
      props: {
        form: { state: { payments: [payment] }, errors: ref({}), loading: ref(false) },
        meta: { paymentMethods: [], order: { currency: 'USD' } },
        orderId: 1,
      },
      global: {
        stubs: {
          VRow: { template: '<div><slot /></div>' },
          VCol: { template: '<div><slot /></div>' },
          VBtn: true,
          VSelect: { name: 'VSelect', props: ['modelValue'], template: '<select />' },
          VTextField: { props: ['prefix'], template: '<input />' },
        },
        directives: { 'decimal-en': {} },
      },
    })
    const method = wrapper.findComponent({ name: 'VSelect' })
    expect(wrapper.find('[data-payment-field="payments.0.transaction_id"]').exists()).toBe(false)
    for (const value of ['card', 'bank_transfer', 'mobile_wallet']) {
      method.vm.$emit('update:modelValue', value)
      await wrapper.vm.$nextTick()
      expect(wrapper.find('[data-payment-field="payments.0.transaction_id"]').exists()).toBe(true)
    }
    payment.transaction_id = 'reference'
    method.vm.$emit('update:modelValue', 'gift_card')
    await wrapper.vm.$nextTick()
    expect(payment.transaction_id).toBeNull()
    expect(wrapper.find('[data-payment-field="payments.0.gift_card_code"]').exists()).toBe(true)
    payment.gift_card_code = 'gift'
    method.vm.$emit('update:modelValue', 'cash')
    await wrapper.vm.$nextTick()
    expect(payment.gift_card_code).toBeNull()
    expect(wrapper.find('[data-payment-field="payments.0.transaction_id"]').exists()).toBe(false)
    expect(wrapper.find('[data-payment-field="payments.0.gift_card_code"]').exists()).toBe(false)
    wrapper.unmount()
  })
})
