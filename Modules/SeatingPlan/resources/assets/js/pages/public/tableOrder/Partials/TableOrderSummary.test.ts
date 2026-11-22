import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import type { Cart, CartItem, MoneyValue } from '@/modules/cart/composables/cart.ts'
import TableOrderSummary from './TableOrderSummary.vue'

vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    t: (key: string) =>
      ({
        'seatingplan::tables.table_order.options': 'Options',
        'tax::taxes.display.product_tax': 'Product tax',
        'tax::taxes.display.global_tax': 'Global tax',
        'tax::taxes.display.inclusive_tax': 'Included in price',
        'tax::taxes.display.exclusive_tax': 'Added to price',
      })[key] ?? key,
  }),
}))
vi.mock('@/modules/core/components/Money.vue', () => ({
  default: { props: ['money'], template: '<span>{{ money.formatted }}</span>' },
}))

const money = (amount: number): MoneyValue => ({
  amount,
  formatted: amount.toFixed(2),
  precision: 2,
})
function item(id: string, tax: number): CartItem {
  return {
    id,
    item: { name: 'Burger' },
    qty: 1,
    unitPrice: money(11),
    subtotal: money(10),
    taxTotal: money(tax),
    total: money(11),
    actions: [],
    taxes: [
      { id: 1, name: 'VAT', scope: 'product', type: 'inclusive', rate: 10, amount: money(tax) },
    ],
  }
}
function mountSummary(cart: Partial<Cart>, closable = false) {
  return mount(TableOrderSummary, {
    props: {
      cart,
      tableName: 'Table 07',
      allowDiscountCode: true,
      canSubmit: true,
      busy: false,
      submitting: false,
      discountLoading: false,
      discountError: null,
      inlineError: null,
      discountCode: '',
      closable,
    },
    global: {
      stubs: {
        VCard: { template: '<section><slot /></section>' },
        VCardText: { template: '<div><slot /></div>' },
        VBtn: { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
        VAlert: true,
        VIcon: true,
        VTextField: true,
      },
    },
  })
}

describe('table order receipt', () => {
  it('uses only the editable cart taxes in the summary', () => {
    const wrapper = mountSummary({
      items: [item('new', 1)],
      quantity: 1,
      subTotal: money(10),
      total: money(11),
      taxes: [],
    })
    expect(wrapper.find('.tax-summary-amount').text()).toBe('1.00')
    expect(wrapper.find('.sheet-total').text()).toContain('11.00')
    wrapper.unmount()
  })
  it.each([false, true])(
    'shows all POS tax scopes without recalculating server totals (closable: %s)',
    (closable) => {
      const wrapper = mountSummary(
        {
          items: [item('one', 1), item('two', 2)],
          quantity: 2,
          subTotal: money(30),
          taxes: [
            { id: 1, name: 'VAT', scope: 'global', type: 'exclusive', rate: 5, amount: money(1.5) },
          ],
          charges: [{ code: 'service', name: 'Service charge', amount: money(2) }],
          discount: { id: 4, name: 'WELCOME', value: money(4) },
          total: money(32.5),
        },
        closable,
      )
      const taxes = wrapper.findAll('.tax-summary-row')
      expect(taxes).toHaveLength(2)
      expect(taxes[0]!.text()).toContain('Product tax · Included in price · 10%')
      expect(taxes[0]!.text()).toContain('3.00')
      expect(taxes[1]!.text()).toContain('Global tax · Added to price · 5%')
      expect(taxes[1]!.text()).toContain('1.50')
      expect(wrapper.find('.sheet-summary').text()).toContain('WELCOME')
      expect(wrapper.find('.sheet-summary').text()).toContain('Service charge')
      expect(wrapper.find('.sheet-total').text()).toContain('32.50')
    },
  )

  it('updates taxes after quantity changes and hides obsolete taxes in an empty cart', async () => {
    const wrapper = mountSummary({ items: [item('one', 1)], quantity: 1, total: money(11) })
    await wrapper.findAll('.qty-stepper__button')[1]!.trigger('click')
    expect(wrapper.emitted('change-qty')?.[0]).toEqual([item('one', 1), 1])
    await wrapper.setProps({
      cart: { items: [item('one', 2)], quantity: 2, total: money(22) },
      busy: true,
    })
    expect(wrapper.find('.tax-summary-amount').text()).toBe('2.00')
    expect(
      wrapper
        .findAll('.qty-stepper__button')
        .every((button) => button.attributes('disabled') !== undefined),
    ).toBe(true)
    expect(wrapper.find('.dialog-primary-action').attributes('disabled')).toBeDefined()
    await wrapper.setProps({ cart: { items: [], quantity: 0, total: money(0) }, canSubmit: false })
    expect(wrapper.findAll('.tax-summary-row')).toHaveLength(0)
    expect(wrapper.find('.empty-cart').exists()).toBe(true)
    expect(wrapper.find('.sheet-total').text()).toContain('0.00')
  })

  it('emits a full-line removal and disables it while a cart request is pending', async () => {
    const line = item('cake', 1)
    const wrapper = mountSummary({ items: [line], total: money(11) })
    await wrapper.find('.cart-item__remove').trigger('click')
    expect(wrapper.emitted('remove-item')?.[0]).toEqual([line])
    await wrapper.setProps({ busy: true })
    expect(wrapper.find('.cart-item__remove').attributes('disabled')).toBeDefined()
  })

  it('keeps item options collapsed until requested and keeps totals outside the item scroller', async () => {
    const line = item('customized-burger', 1)
    line.options = {
      1: {
        id: 'crust',
        name: 'Bread type',
        values: [{ id: 'white', label: 'White' }],
      },
    } as unknown as CartItem['options']
    const wrapper = mountSummary({
      items: [line],
      quantity: 1,
      subTotal: money(10),
      total: money(11),
    })
    const toggle = wrapper.find('.cart-item__options-toggle')
    const options = wrapper.find('.cart-item__options')

    expect(toggle.attributes('aria-expanded')).toBe('false')
    expect(options.isVisible()).toBe(false)
    expect(wrapper.find('.cart-item__meta').exists()).toBe(false)
    expect(wrapper.find('.sheet-body .order-sheet__breakdown').exists()).toBe(false)
    expect(wrapper.find('.order-sheet__breakdown').text()).toContain('10.00')

    await toggle.trigger('click')

    expect(toggle.attributes('aria-expanded')).toBe('true')
    expect(options.attributes('style')).not.toContain('display: none')
    expect(options.text()).toContain('Bread type')
    expect(options.text()).toContain('White')
  })

  it('retains zero-rate tax labels and does not invent tax on untaxed items', async () => {
    const zeroRated = item('zero', 0)
    zeroRated.taxes[0]!.rate = 0
    const wrapper = mountSummary({ items: [zeroRated], total: money(10) })
    expect(wrapper.find('.tax-summary-row').text()).toContain('0%')
    expect(wrapper.find('.tax-summary-amount').text()).toBe('0.00')
    await wrapper.setProps({ cart: { items: [{ ...zeroRated, taxes: [] }], total: money(10) } })
    expect(wrapper.findAll('.tax-summary-row')).toHaveLength(0)
  })
})
