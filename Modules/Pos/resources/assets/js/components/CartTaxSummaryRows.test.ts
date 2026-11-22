import type { Cart } from '@/modules/cart/composables/cart.ts'
import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import CartTaxSummaryRows from './CartTaxSummaryRows.vue'

vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    t: (key: string) =>
      ({
        'tax::taxes.display.product_tax': 'Product tax',
        'tax::taxes.display.global_tax': 'Global tax',
        'tax::taxes.display.inclusive_tax': 'Included in price',
        'tax::taxes.display.exclusive_tax': 'Added to price',
      })[key] ?? key,
  }),
}))

vi.mock('@/modules/core/components/Money.vue', () => ({
  default: {
    props: ['money'],
    template: '<span>{{ money.original?.formatted || money.formatted }}</span>',
  },
}))

const cart = {
  items: [
    {
      id: 'line-1',
      item: { name: 'Product' },
      qty: 1,
      unitPrice: { amount: 125, formatted: '$ 125.00' },
      subtotal: { amount: 100, formatted: '$ 100.00' },
      taxTotal: { amount: 10, formatted: '$ 10.00' },
      taxes: [
        {
          id: 1,
          name: 'Product VAT',
          rate: 10,
          type: 'inclusive',
          scope: 'product',
          amount: { amount: 10, formatted: '$ 10.00' },
        },
      ],
      total: { amount: 110, formatted: '$ 110.00' },
      actions: [],
    },
  ],
  quantity: 1,
  subTotal: { amount: 100, formatted: '$ 100.00' },
  orderType: { id: 'dine-in', name: 'Dine In' },
  taxes: [
    {
      id: 2,
      name: 'Global VAT',
      rate: 15,
      type: 'exclusive',
      scope: 'global',
      amount: { amount: 15, formatted: '$ 15.00' },
    },
  ],
  charges: [],
  total: { amount: 125, formatted: '$ 125.00' },
  customer: null,
} satisfies Cart

describe('CartTaxSummaryRows', () => {
  it('renders product and global tax amounts with their calculation modes', () => {
    const wrapper = mount(CartTaxSummaryRows, { props: { cart } })

    expect(wrapper.text()).toContain('Product VAT')
    expect(wrapper.text()).toContain('Product tax · Included in price · 10%')
    expect(wrapper.text()).toContain('$ 10.00')
    expect(wrapper.text()).toContain('Global VAT')
    expect(wrapper.text()).toContain('Global tax · Added to price · 15%')
    expect(wrapper.text()).toContain('$ 15.00')
  })
})
