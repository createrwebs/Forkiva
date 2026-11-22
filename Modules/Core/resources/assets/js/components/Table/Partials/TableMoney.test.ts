import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import TableMoney from './TableMoney.vue'

vi.mock('vue-i18n', () => ({
  useI18n: () => ({ locale: { value: 'en' } }),
}))

vi.mock('@/modules/auth/composables/auth.ts', () => ({
  useAuth: () => ({ user: null }),
}))

describe('TableMoney', () => {
  it('renders the backend formatted money value', () => {
    const wrapper = mount(TableMoney, {
      props: {
        column: 'amount',
        item: { amount: { formatted: '$12.34', currency: 'USD' } },
      },
      global: { stubs: { VTooltip: true } },
    })

    expect(wrapper.text()).toBe('$12.34')
  })

  it('formats raw values with the supplied currency, including zero', () => {
    const wrapper = mount(TableMoney, {
      props: {
        column: 'amount',
        currency: 'USD',
        item: { amount: 0 },
      },
      global: { stubs: { VTooltip: true } },
    })

    expect(wrapper.text()).toBe('$0.00')
  })

  it('renders a placeholder for missing values', () => {
    const wrapper = mount(TableMoney, {
      props: {
        column: 'amount',
        item: {},
      },
      global: { stubs: { VTooltip: true } },
    })

    expect(wrapper.text()).toBe('—')
  })
})
