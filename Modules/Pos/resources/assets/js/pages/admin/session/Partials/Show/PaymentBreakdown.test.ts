import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { createI18n } from 'vue-i18n'
import { execFileSync } from 'node:child_process'
import type { Money } from '@/modules/core/contracts/Money'
import type { SessionPaymentSummary } from '@/modules/pos/contracts/sessionReconciliation'
import PaymentBreakdown from './PaymentBreakdown.vue'

const translations = Object.fromEntries(
  ['en', 'ar'].map((locale) => [
    locale,
    {
      'pos::pos_sessions': JSON.parse(
        execFileSync(
          'php',
          ['-r', `echo json_encode(require 'Modules/Pos/lang/${locale}/pos_sessions.php');`],
          { encoding: 'utf8' },
        ),
      ),
    },
  ]),
)

const money = (amount: number): Money => ({
  amount,
  formatted: `$${amount.toFixed(2)}`,
  currency: 'USD',
  precision: 2,
})

const summary = (): SessionPaymentSummary => ({
  payment_reconciliation: {
    version: 1,
    methods: [
      {
        method: { id: 'card', name: 'Card', icon: '', color: '' },
        collections: money(120),
        refunds: money(20),
        net: money(100),
      },
      {
        method: { id: 'mobile_wallet', name: 'Mobile Wallet', icon: '', color: '' },
        collections: money(30),
        refunds: money(5),
        net: money(25),
      },
    ],
    legacy_cash_payments_count: 0,
    unattributed_order_payments_count: 0,
    currency_mismatch_payments_count: 0,
  },
})

const render = (item: SessionPaymentSummary | null, locale = 'en', compact = false) =>
  mount(PaymentBreakdown, {
    props: { item, compact },
    global: {
      plugins: [createI18n({ legacy: false, locale, messages: translations })],
      stubs: {
        VTable: { template: '<table><slot /></table>' },
        VIcon: true,
        VAlert: { template: '<div role="alert"><slot /></div>' },
      },
    },
  })

describe('session payment breakdown', () => {
  it('displays separate collections, refunds, and net for each method', () => {
    const wrapper = render(summary())
    const rows = wrapper.findAll('tbody tr')
    expect(rows).toHaveLength(2)
    const card = wrapper.get('tbody tr:nth-child(1)').text()
    const wallet = wrapper.get('tbody tr:nth-child(2)').text()
    expect(card).toContain('Card')
    expect(card).toContain('$120.00')
    expect(card).toContain('$20.00')
    expect(card).toContain('$100.00')
    expect(wallet).toContain('Mobile Wallet')
    expect(wallet).toContain('$25.00')
    expect(wrapper.findAll('[role="alert"]')).toHaveLength(0)
    wrapper.unmount()
  })

  it('shows incomplete-data warnings and removes them when the next session is loaded', async () => {
    const item = summary()
    if (!item.payment_reconciliation) throw new Error('Fixture requires reconciliation')
    item.payment_reconciliation.unattributed_order_payments_count = 2
    item.payment_reconciliation.currency_mismatch_payments_count = 1
    const wrapper = render(item)
    expect(wrapper.findAll('[role="alert"]')).toHaveLength(2)
    expect(wrapper.text()).toContain('2 unassigned payments')
    expect(wrapper.text()).not.toContain(':count')
    expect(wrapper.text()).not.toContain('{count}')
    expect(wrapper.text()).toContain('1 payment uses another currency')
    await wrapper.setProps({ item: summary() })
    expect(wrapper.findAll('[role="alert"]')).toHaveLength(0)
    wrapper.unmount()
  })

  it('preserves the saved legacy summary without inventing a detailed breakdown', () => {
    const wrapper = render({
      payment_reconciliation: null,
      system_cash_sales: money(10),
      system_card_sales: money(20),
      system_other_sales: money(30),
    })
    expect(wrapper.find('table').exists()).toBe(false)
    expect(wrapper.text()).toContain('$10.00')
    expect(wrapper.text()).toContain('$20.00')
    expect(wrapper.text()).toContain('$30.00')
    expect(wrapper.text()).toContain('Other collections')
    expect(wrapper.text()).toContain('detailed breakdown is unavailable')
    wrapper.unmount()
  })

  it('interpolates the actual excluded counts in Arabic', () => {
    const item = summary()
    if (!item.payment_reconciliation) throw new Error('Fixture requires reconciliation')
    item.payment_reconciliation.unattributed_order_payments_count = 7
    item.payment_reconciliation.currency_mismatch_payments_count = 3
    const wrapper = render(item, 'ar')
    expect(wrapper.text()).toContain('تم استبعاد 7 دفعة')
    expect(wrapper.text()).toContain('تم استبعاد 3 دفعة')
    expect(wrapper.text()).not.toMatch(/[:{]count/)
    wrapper.unmount()
  })

  it('keeps bank transfer, wallet, gift card and deposit amounts in their own rows', () => {
    const item = summary()
    if (!item.payment_reconciliation) throw new Error('Fixture requires reconciliation')
    item.payment_reconciliation.methods = [
      'cash',
      'card',
      'bank_transfer',
      'mobile_wallet',
      'gift_card',
      'reservation_deposit',
    ].map((method, index) => ({
      method: { id: method, name: method, icon: '', color: '' },
      collections: money((index + 1) * 10),
      refunds: money(index),
      net: money((index + 1) * 10 - index),
    }))
    const wrapper = render(item)
    expect(wrapper.findAll('tbody tr')).toHaveLength(6)
    for (const method of item.payment_reconciliation.methods) {
      const cells = wrapper
        .get(`[data-method="${method.method.id}"]`)
        .findAll('td')
        .map((cell) => cell.get('.payment-value').text())
      expect(cells).toEqual([
        method.collections.formatted,
        method.refunds.formatted,
        method.net.formatted,
      ])
    }
    wrapper.unmount()
  })

  it('retains payment amounts and exclusion disclosures in the compact closing presentation', () => {
    const item = summary()
    if (!item.payment_reconciliation) throw new Error('Fixture requires reconciliation')
    item.payment_reconciliation.unattributed_order_payments_count = 3
    const wrapper = render(item, 'en', true)
    expect(wrapper.text()).toContain('3 unassigned payments')
    expect(wrapper.findAll('thead th').map((cell) => cell.text())).toEqual([
      'Method',
      'Collected',
      'Refunded',
      'Net',
    ])
    expect(
      wrapper
        .get('[data-method="card"]')
        .findAll('.payment-value')
        .map((cell) => cell.text()),
    ).toEqual(['$120.00', '$20.00', '$100.00'])
    expect(wrapper.text()).toContain('tender applications, not new cash')
    wrapper.unmount()
  })

  it('handles the initial unloaded state', () => {
    const wrapper = render(null)
    expect(wrapper.find('table').exists()).toBe(false)
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    wrapper.unmount()
  })
})
