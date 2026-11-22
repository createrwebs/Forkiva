import { flushPromises, shallowMount } from '@vue/test-utils'
import { beforeEach, expect, it, vi } from 'vitest'
import Discount from './Discount.vue'
import { ref } from 'vue'

const { toastError } = vi.hoisted(() => ({ toastError: vi.fn() }))
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))
vi.mock('vue-toastification', () => ({ useToast: () => ({ error: toastError }) }))

beforeEach(() => vi.resetAllMocks())

const field = {
  props: ['modelValue', 'errorMessages', 'readonly'],
  emits: ['update:modelValue'],
  template:
    '<div><input :value="modelValue" :readonly="readonly" @input="$emit(\'update:modelValue\', $event.target.value)" /><span role="alert">{{ errorMessages }}</span></div>',
}

it.each([
  ['discount', 403, { message: 'This discount is not applicable to your cart' }],
  ['voucher', 404, { message: 'Voucher does not exist' }],
  ['voucher', 422, { message: 'Invalid input', errors: { code: ['Invalid voucher code'] } }],
] as const)(
  'shows %s rejection (%s) beside the input without refreshing or clearing it',
  async (type, status, data) => {
    const request = vi.fn()
    const cart = {
      applyDiscount: request,
      applyVoucher: request,
      processing: ref(false),
      data: ref({ discount: null }),
      syncFailed: ref(false),
      isCartMutationLoading: (_key: string) => false,
    }
    vi.mocked(request).mockRejectedValue({ response: { status, data } })
    const wrapper = shallowMount(Discount, {
      props: { cart, meta: { discounts: [] } } as any,
      global: {
        stubs: {
          VIcon: true,
          VSelect: field,
          VTextField: field,
          VBtn: { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
        },
      },
    })
    if (type === 'voucher') await wrapper.findAll('.pos-discount-tabs button')[1]!.trigger('click')
    await wrapper.get('input').setValue(type === 'discount' ? '16' : 'SAVE10')
    const original = wrapper.get('input').element.value
    await wrapper.get('.pos-discount-apply').trigger('click')
    await flushPromises()

    const expected = 'errors' in data ? data.errors.code[0] : data.message
    expect(wrapper.get('[role="alert"]').text()).toBe(expected)
    expect(wrapper.get('input').element.value).toBe(original)
    expect(request).toHaveBeenCalledOnce()
    expect(toastError).not.toHaveBeenCalled()
    expect(cart.syncFailed.value).toBe(false)
    expect(cart.processing.value).toBe(false)
    expect(cart.isCartMutationLoading('cart')).toBe(false)

    await wrapper.get('input').setValue('corrected')
    expect(wrapper.get('[role="alert"]').text()).toBe('')
    vi.mocked(request).mockResolvedValue({ data: { body: cart.data.value } } as any)
    await wrapper.get('.pos-discount-apply').trigger('click')
    await flushPromises()
    expect(wrapper.get('input').element.value).toBe('')
    wrapper.unmount()
  },
)
