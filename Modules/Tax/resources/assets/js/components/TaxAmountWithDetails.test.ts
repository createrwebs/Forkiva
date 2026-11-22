import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import TaxAmountWithDetails from './TaxAmountWithDetails.vue'

vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    t: (key: string) =>
      ({
        'tax::taxes.display.tax_details': 'Tax details',
        'tax::taxes.display.view_tax_details': 'View tax details',
        'tax::taxes.display.total_tax': 'Total tax',
        'tax::taxes.display.inclusive_tax': 'Included in price',
        'tax::taxes.display.exclusive_tax': 'Added to price',
        'admin::admin.buttons.close': 'Close',
      })[key] ?? key,
  }),
}))

const moneyStub = {
  props: ['money'],
  template: '<span>{{ money.original?.formatted || money.formatted }}</span>',
}

const passthroughStub = { template: '<div><slot /></div>' }

describe('TaxAmountWithDetails', () => {
  it('shows only the total in the table and opens the product tax details dialog', async () => {
    const wrapper = mount(TaxAmountWithDetails, {
      props: {
        amount: {
          original: { amount: 15, formatted: '$15.00', currency: 'USD', precision: 2 },
          converted: { amount: 10.64, formatted: 'JOD 10.64', currency: 'JOD', precision: 2 },
        },
        taxes: [
          {
            key: 'product:1',
            name: 'Product VAT',
            rate: 15,
            type: { id: 'inclusive' },
            amount: {
              original: { amount: 15, formatted: '$15.00', currency: 'USD', precision: 2 },
              converted: {
                amount: 10.64,
                formatted: 'JOD 10.64',
                currency: 'JOD',
                precision: 2,
              },
            },
          },
        ],
      },
      global: {
        stubs: {
          Money: moneyStub,
          VCard: passthroughStub,
          VCardTitle: passthroughStub,
          VCardText: passthroughStub,
          VCardActions: passthroughStub,
          VDivider: { template: '<hr />' },
          VIcon: { template: '<span />' },
          VBtn: {
            emits: ['click'],
            template: '<button @click="$emit(\'click\')"><slot /></button>',
          },
          VDialog: {
            props: ['modelValue'],
            emits: ['update:modelValue'],
            template: '<div v-if="modelValue" data-test="tax-details-dialog"><slot /></div>',
          },
        },
      },
    })

    expect(wrapper.text()).toBe('$15.00')
    expect(wrapper.find('[data-test="tax-details-dialog"]').exists()).toBe(false)

    await wrapper.get('[data-test="open-tax-details"]').trigger('click')

    expect(wrapper.get('[data-test="tax-details-dialog"]').text()).toContain('Product VAT')
    expect(wrapper.get('[data-test="tax-details-dialog"]').text()).toContain(
      'Included in price · 15%',
    )
    expect(wrapper.get('[data-test="tax-details-dialog"]').text()).toContain('Total tax')
  })
})
