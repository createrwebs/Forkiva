import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { createI18n } from 'vue-i18n'
import { execFileSync } from 'node:child_process'
import type { Money } from '@/modules/core/contracts/Money'
import type { PosSessionDetail } from '@/modules/pos/contracts/sessionReconciliation'
import CashSummary from './CashSummary.vue'

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

const session = (difference = 0): PosSessionDetail => ({
  id: 16,
  pos_register: { id: 1, name: 'Bakery Counter' },
  branch: { id: 1, name: 'Main Branch' },
  opened_by: { id: 1, name: 'Alex' },
  closed_by: { id: 1, name: 'Alex' },
  status: { id: 'closed', name: 'Closed', color: 'success', icon: '' },
  opened_at: '2026-10-05 03:39 PM',
  closed_at: '2026-10-05 05:33 PM',
  opening_float: money(100),
  declared_cash: money(105 + difference),
  cash_over_short: money(difference),
  total_sales: money(150.93),
  total_refunds: money(0),
  orders_count: 2,
  notes: null,
  cash_reconciliation: { cash_in: money(5), cash_out: money(0), expected_cash: money(105) },
})

const render = (item: PosSessionDetail, locale = 'en', reviewMode = false) =>
  mount(CashSummary, {
    props: { item, reviewMode },
    global: {
      plugins: [createI18n({ legacy: false, locale, messages: translations })],
      stubs: { VCard: { template: '<section><slot /></section>' }, VIcon: true },
    },
  })

describe('session cash reconciliation result', () => {
  it('shows the saved balanced result alongside the closing figures', () => {
    const wrapper = render(session())
    expect(wrapper.get('.cash-result').text()).toContain('Cash is balanced')
    expect(wrapper.get('.cash-result').text()).toContain('matches the expected amount')
    expect(wrapper.get('.cash-row--actual dd').text()).toBe('$105.00')
    expect(wrapper.findAll('dd').map((value) => value.text())).toEqual([
      '$100.00',
      '$5.00',
      '$0.00',
      '$105.00',
      '$105.00',
    ])
    wrapper.unmount()
  })

  it.each([
    [-5, 'Shortage', '$100.00'],
    [5, 'Surplus', '$110.00'],
  ])(
    'discloses the saved variance of %s without claiming the drawer is balanced',
    (difference, label, actual) => {
      const wrapper = render(session(Number(difference)))
      expect(wrapper.get('.cash-result').text()).toContain(label)
      expect(wrapper.text()).not.toContain('Cash is balanced')
      expect(wrapper.get('.cash-row--actual dd').text()).toBe(actual)
      wrapper.unmount()
    },
  )

  it.each(['cash_reconciliation', 'declared_cash', 'cash_over_short'] as const)(
    'does not infer a balanced result when %s is unavailable',
    (field) => {
      const item = session()
      item[field] = null
      const wrapper = render(item)
      expect(wrapper.find('.cash-result').exists()).toBe(false)
      expect(wrapper.text()).not.toContain('Cash is balanced')
      wrapper.unmount()
    },
  )

  it('does not present an open session as reconciled, including in closing review mode', () => {
    const item = session()
    item.status.id = 'open'
    const wrapper = render(item)
    expect(wrapper.find('.cash-result').exists()).toBe(false)
    expect(wrapper.findAll('dd')).toHaveLength(1)
    wrapper.unmount()

    const review = render(item, 'en', true)
    expect(review.findAll('dd')).toHaveLength(5)
    expect(review.find('.cash-result').exists()).toBe(false)
    review.unmount()
  })

  it('accepts a saved zero cash balance and translates the result into Arabic', () => {
    const item = session()
    item.opening_float = money(0)
    item.declared_cash = money(0)
    item.cash_reconciliation = { cash_in: money(0), cash_out: money(0), expected_cash: money(0) }
    const wrapper = render(item, 'ar')
    expect(wrapper.get('.cash-result').text()).toContain('النقد متطابق')
    expect(wrapper.get('.cash-row--actual dd').text()).toBe('$0.00')
    wrapper.unmount()
  })
})
