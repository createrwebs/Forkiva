import { shallowMount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import type { PosSessionDetail } from '@/modules/pos/contracts/sessionReconciliation'
import FinanceSessionCash from './FinanceSessionCash.vue'

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))

function money(amount: number) {
  return { amount, formatted: `JOD ${amount.toFixed(3)}`, currency: 'JOD', precision: 3 }
}

const session: PosSessionDetail = {
  id: 7,
  status: { id: 'closed', name: 'Closed', color: 'secondary', icon: 'tabler-check' },
  pos_register: { id: 1, name: 'Main' },
  branch: { id: 1, name: 'Main branch' },
  opened_by: { id: 1, name: 'Sara' },
  closed_by: { id: 2, name: 'Omar' },
  opened_at: '2026-10-11 09:00 AM',
  closed_at: '2026-10-11 05:00 PM',
  opening_float: money(10.125),
  declared_cash: money(24),
  cash_over_short: money(-0.345),
  cash_reconciliation: {
    cash_in: money(20.5),
    cash_out: money(6.28),
    expected_cash: money(24.345),
  },
  total_sales: money(120.25),
  total_refunds: money(1.1),
  orders_count: 3,
  notes: null,
}

function render(item: PosSessionDetail) {
  return shallowMount(FinanceSessionCash, {
    props: { item },
    global: { stubs: { VIcon: true, VChip: { template: '<span><slot /></span>' } } },
  })
}

describe('session cash position', () => {
  it('uses saved expected cash, count and variance with the original currency precision', () => {
    const wrapper = render(session)
    expect(wrapper.find('.finance-session-cash__position').text()).toContain('JOD 24.345')
    expect(wrapper.find('.finance-session-cash__position').text()).toContain('JOD 24.000')
    expect(wrapper.find('.finance-session-cash__result').text()).toContain('JOD -0.345')
    expect(wrapper.attributes('data-reconciliation')).toBe('shortage')
    expect(wrapper.text()).toContain('JOD 10.125')
  })

  it('keeps a recorded zero count and balanced variance visible', () => {
    const wrapper = render({
      ...session,
      declared_cash: money(0),
      cash_over_short: money(0),
      cash_reconciliation: { ...session.cash_reconciliation!, expected_cash: money(0) },
    })
    expect(wrapper.attributes('data-reconciliation')).toBe('balanced')
    expect(wrapper.find('.finance-session-cash__result').text()).toContain('JOD 0.000')
  })

  it('shows the original legacy amounts without inventing expected cash or a variance result', () => {
    const wrapper = render({
      ...session,
      cash_reconciliation: null,
      system_cash_sales: money(19.225),
      declared_cash: null,
    })
    expect(wrapper.find('.finance-session-cash__position').text()).not.toContain('JOD 19.225')
    expect(wrapper.find('.finance-session-cash__ledger').text()).toContain('JOD 19.225')
    expect(wrapper.find('.finance-session-cash__result').exists()).toBe(false)
    expect(wrapper.text()).toContain('pos::pos_sessions.show.legacy_cash_summary')
  })

  it('shows only opening cash for an open session, with no saved closing totals', () => {
    const wrapper = render({ ...session, status: { ...session.status, id: 'open' } })
    expect(wrapper.text()).toContain('JOD 10.125')
    expect(wrapper.text()).toContain('pos::pos_sessions.show.open_summary')
    expect(wrapper.find('.finance-session-cash__position').exists()).toBe(false)
    expect(wrapper.find('.finance-session-cash__result').exists()).toBe(false)
  })
})
