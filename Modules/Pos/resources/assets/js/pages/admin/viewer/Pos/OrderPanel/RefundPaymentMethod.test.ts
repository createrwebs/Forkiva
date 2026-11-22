import { flushPromises, shallowMount } from '@vue/test-utils'
import { reactive } from 'vue'
import { expect, it, vi } from 'vitest'
import RefundPaymentMethod from '@/modules/pos/pages/admin/viewer/Pos/OrderPanel/RefundPaymentMethod.vue'

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))
vi.mock('@/modules/core/utils/formatters.ts', () => ({
  formatPrice: (amount: number) => String(amount),
}))

it('collects a card code only for gift-card refunds and freezes it during submission', async () => {
  const form = reactive({ refundPaymentMethod: 'gift_card', refundGiftCardCode: null })
  const wrapper = shallowMount(RefundPaymentMethod, {
    props: {
      form,
      meta: { currency: 'USD', refundPaymentMethods: [] },
      overpaidAmount: 6.8,
    } as any,
    global: {
      stubs: {
        VAlert: true,
        VIcon: true,
        VSlideGroup: true,
        VSlideGroupItem: true,
        VTextField: {
          props: ['modelValue', 'disabled'],
          emits: ['update:modelValue'],
          template:
            '<input :value="modelValue" :disabled="disabled" @input="$emit(\'update:modelValue\', $event.target.value)" />',
        },
      },
    },
  })
  await wrapper.get('input').setValue('CARD-CODE')
  expect(form.refundGiftCardCode).toBe('CARD-CODE')
  await wrapper.setProps({ disabled: true })
  expect(wrapper.get('input').attributes('disabled')).toBeDefined()
  form.refundPaymentMethod = 'cash'
  await flushPromises()
  expect(form.refundGiftCardCode).toBeNull()
  expect(wrapper.find('input').exists()).toBe(false)
  wrapper.unmount()
})
