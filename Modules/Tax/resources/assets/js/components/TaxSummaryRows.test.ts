import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import TaxSummaryRows from './TaxSummaryRows.vue'

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

describe('TaxSummaryRows', () => {
  it('renders plain cart money and converted persisted money amounts', () => {
    const wrapper = mount(TaxSummaryRows, {
      props: {
        taxes: [
          {
            key: 'product:1',
            name: 'Product VAT',
            rate: 15,
            type: 'inclusive',
            scope: 'product',
            amount: {
              amount: 15,
              formatted: '$15.00',
              currency: 'USD',
              precision: 2,
            },
          },
          {
            key: 'global:2',
            name: 'Global VAT',
            rate: 10,
            type: 'exclusive',
            scope: 'global',
            amount: {
              original: {
                amount: 10,
                formatted: '$10.00',
                currency: 'USD',
                precision: 2,
              },
              converted: {
                amount: 7.09,
                formatted: 'JOD 7.09',
                currency: 'JOD',
                precision: 2,
              },
            },
          },
        ],
      },
    })

    expect(wrapper.text()).toContain('$15.00')
    expect(wrapper.text()).toContain('$10.00')
    expect(wrapper.text()).toContain('Product tax · Included in price · 15%')
    expect(wrapper.text()).toContain('Global tax · Added to price · 10%')
  })
})
