import { shallowMount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import type { DailyFinanceEntry } from '@/modules/dailyfinance/contracts/dailyFinance'
import FinanceSales from './FinanceSales.vue'
import TableEnum from '@/modules/core/components/Table/Partials/TableEnum.vue'
import TableStatus from '@/modules/core/components/Table/Partials/TableStatus.vue'

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))
const money = (amount: number) => ({
  amount,
  formatted: `${amount.toFixed(3)} JOD`,
  currency: 'JOD',
  precision: 3,
})
const entry: DailyFinanceEntry = {
  id: 1,
  reference: 'ORD-123',
  occurred_at: '2026-10-11T08:00:00Z',
  occurred_at_formatted: '11/10/2026 11:00',
  amount: money(22.723),
  label: { id: 'partially_paid', name: 'Partially paid' },
  status: { id: 'pending', name: 'Pending' },
  order: {
    type: { id: 'takeaway', name: 'Takeaway' },
    subtotal: money(20.123),
    discount: money(1),
    tax: money(2.1),
    charges: money(1.5),
    total: money(22.723),
    due: money(2.345),
  },
}
function render(entries = [entry], outstanding = false) {
  return shallowMount(FinanceSales, {
    props: { entries, outstanding },
    global: { stubs: { VIcon: true } },
  })
}

describe('sales order breakdown', () => {
  it('keeps order detail in an accessible native disclosure and preserves system dates and enums', () => {
    const wrapper = render()
    expect(wrapper.find('details > summary').text()).toContain('ORD-123')
    expect(wrapper.find('details').attributes('open')).toBeUndefined()
    expect(wrapper.find('time').text()).toBe('11/10/2026 11:00')
    expect(wrapper.find('time').attributes('datetime')).toBe(entry.occurred_at)
    expect(wrapper.findAllComponents(TableEnum)).toHaveLength(2)
    expect(wrapper.findComponent(TableStatus).props('item')).toEqual(entry)
    expect(wrapper.findAll('a')).toHaveLength(0)
  })

  it('shows persisted total separately from outstanding and includes charges and discount', () => {
    const wrapper = render()
    expect(wrapper.find('.finance-sale__total dd').text()).toBe('22.723 JOD')
    expect(wrapper.find('.finance-sale__due dd').text()).toBe('2.345 JOD')
    expect(wrapper.find('dl').text()).toContain('−1.000 JOD')
    expect(wrapper.find('dl').text()).toContain('1.500 JOD')
  })

  it('replaces order disclosures with a designed empty state', async () => {
    const wrapper = render()
    await wrapper.setProps({ entries: [] })
    expect(wrapper.find('details').exists()).toBe(false)
    expect(wrapper.find('.finance-sales__empty-icon').exists()).toBe(true)
    expect(wrapper.text()).toContain('sales.empty_title')
  })

  it('emphasizes the remaining balance for unpaid orders without replacing the full order total', () => {
    const wrapper = render([{ ...entry, amount: money(2.345) }], true)
    expect(wrapper.find('.finance-sale__amount bdi').text()).toBe('2.345 JOD')
    expect(wrapper.find('.finance-sale__amount-label').text()).toContain('sales.due')
    expect(wrapper.find('.finance-sale__total dd').text()).toBe('22.723 JOD')
    expect(wrapper.find('.finance-sale__due dd').text()).toBe('2.345 JOD')
    expect(wrapper.findComponent(TableEnum).props('item')).toEqual(entry.order)
  })

  it('shows a clear unpaid-orders empty state and still supports records without a breakdown', () => {
    const empty = render([], true)
    expect(empty.text()).toContain('dues.empty_title')
    expect(empty.text()).not.toContain('sales.empty_title')
    const legacy = render([{ ...entry, amount: money(2.345), order: undefined }], true)
    expect(legacy.find('.finance-sale__amount bdi').text()).toBe('2.345 JOD')
    expect(legacy.find('.finance-sale__unavailable').exists()).toBe(true)
  })
})
