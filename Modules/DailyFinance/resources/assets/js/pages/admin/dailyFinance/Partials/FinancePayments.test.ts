import { shallowMount } from '@vue/test-utils'
import { ref } from 'vue'
import { describe, expect, it, vi } from 'vitest'
import FinancePayments from './FinancePayments.vue'
import AnalyticsChart from '@/modules/core/components/Charts/AnalyticsChart.vue'
import type { DailyFinanceSummary } from '@/modules/dailyfinance/contracts/dailyFinance'
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key, locale: ref('en') }) }))
vi.mock('@/modules/auth/composables/auth', () => ({ useAuth: () => ({ can: () => false }) }))
const money = (amount: number) => ({
  amount,
  formatted: `${amount.toFixed(3)} JOD`,
  currency: 'JOD',
  precision: 3,
})
function payments(): NonNullable<DailyFinanceSummary['payments']> {
  return {
    collections: money(20),
    refunds: money(3),
    net: money(17),
    excluded_currency_count: 0,
    methods: [
      {
        method: { id: 'cash', name: 'Cash', icon: 'tabler-cash', color: '' },
        collections: money(20),
        refunds: money(0),
        net: money(20),
      },
      {
        method: { id: 'card', name: 'Card', icon: 'tabler-credit-card', color: '' },
        collections: money(0),
        refunds: money(3),
        net: money(-3),
      },
      {
        method: { id: 'bank_transfer', name: 'Bank', icon: 'tabler-building-bank', color: '' },
        collections: money(0),
        refunds: money(0),
        net: money(0),
      },
    ],
  }
}
function render(value: DailyFinanceSummary['payments']) {
  return shallowMount(FinancePayments, {
    props: { payments: value },
    global: { renderStubDefaultSlot: true, stubs: { VBtn: true, VIcon: true } },
  })
}
describe('daily payment review', () => {
  it('always shows every method, including refund-only and unused methods', () => {
    const wrapper = render(payments())
    expect(wrapper.findAll('tbody tr')).toHaveLength(3)
    expect(wrapper.text()).toContain('-3.000 JOD')
    expect(wrapper.findAll('.finance-mix__legend li')).toHaveLength(1)
    expect(wrapper.find('[aria-expanded]').exists()).toBe(false)
    expect(wrapper.findAll('tbody tr')[2]!.text()).toContain('Bank')
  })
  it('distinguishes restricted data from an empty day and never shows invalid shares', () => {
    const restricted = render(null)
    expect(restricted.text()).toContain('dailyfinance::daily_finance.restricted')
    expect(restricted.find('table').exists()).toBe(false)
    const empty = payments()
    empty.collections = money(0)
    empty.refunds = money(0)
    empty.net = money(0)
    empty.methods = empty.methods.map((row) => ({
      ...row,
      collections: money(0),
      refunds: money(0),
      net: money(0),
    }))
    const wrapper = render(empty)
    expect(wrapper.text()).toContain('dailyfinance::daily_finance.payments.empty')
    expect(wrapper.findAll('tbody tr')).toHaveLength(3)
    expect(wrapper.findComponent(AnalyticsChart).exists()).toBe(false)
    expect(wrapper.find('.finance-payments-empty__icon').exists()).toBe(true)
    expect(wrapper.text()).not.toContain('NaN')
  })
  it('charts collections only, keeping refund-only methods in the table', () => {
    const wrapper = render(payments())
    const chart = wrapper.findComponent(AnalyticsChart)
    expect(chart.props('type')).toBe('doughnut')
    expect(chart.props('labels')).toEqual(['Cash'])
    expect(chart.props('datasets')[0]?.data).toEqual([20])
    expect(chart.props('precision')).toBe(3)
    expect(wrapper.find('.finance-mix__center').text()).toContain('20.000 JOD')
  })

  it('does not describe a refund-only day as having no activity', () => {
    const value = payments()
    value.collections = money(0)
    value.net = money(-3)
    value.methods[0]!.collections = money(0)
    value.methods[0]!.net = money(0)
    const wrapper = render(value)
    expect(wrapper.findComponent(AnalyticsChart).exists()).toBe(false)
    expect(wrapper.text()).toContain('dailyfinance::daily_finance.payments.refunds_only')
    expect(wrapper.text()).not.toContain('dailyfinance::daily_finance.payments.empty_title')
    expect(wrapper.findAll('tbody tr')).toHaveLength(3)
  })
})
