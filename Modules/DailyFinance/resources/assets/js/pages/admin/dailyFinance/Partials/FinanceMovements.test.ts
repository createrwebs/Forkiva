import { shallowMount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import TableEnum from '@/modules/core/components/Table/Partials/TableEnum.vue'
import type { DailyFinanceEntry } from '@/modules/dailyfinance/contracts/dailyFinance'
import FinanceMovements from './FinanceMovements.vue'

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))
const money = (amount: number) => ({
  amount,
  formatted: `${amount.toFixed(3)} JOD`,
  currency: 'JOD',
  precision: 3,
})
const entry: DailyFinanceEntry = {
  id: 42,
  reference: 'PETTY-001',
  occurred_at: '2026-10-10T21:00:00Z',
  occurred_at_formatted: '11/10/2026 00:00',
  amount: money(12.345),
  status: null,
  label: { id: 'pay_out', name: 'Pay out', icon: 'tabler-arrow-down-circle' },
  direction: { id: 'out', name: 'Out' },
  movement: { register: 'Front counter', balance_before: money(10), balance_after: money(-2.345) },
}
function render(entries = [entry]) {
  return shallowMount(FinanceMovements, { props: { entries }, global: { stubs: { VIcon: true } } })
}

describe('cash movement review', () => {
  it('preserves historical balances and precision while distinguishing cash out from cash in', async () => {
    const wrapper = render()
    expect(wrapper.find('.finance-movement__amount').text()).toBe('−12.345 JOD')
    expect(wrapper.find('.finance-movement__balances').text()).toContain('10.000 JOD')
    expect(wrapper.find('.finance-movement__after dd').text()).toBe('-2.345 JOD')
    await wrapper.setProps({ entries: [{ ...entry, direction: { id: 'in', name: 'In' } }] })
    expect(wrapper.find('.finance-movement__amount').text()).toBe('+12.345 JOD')
  })

  it('reviews the movement inline with system dates, its reference and the shared direction enum', () => {
    const wrapper = render()
    expect(wrapper.find('details > summary').text()).toContain('Front counter')
    expect(wrapper.find('details').attributes('open')).toBeUndefined()
    expect(wrapper.find('time').text()).toBe(entry.occurred_at_formatted)
    expect(wrapper.find('time').attributes('datetime')).toBe(entry.occurred_at)
    expect(wrapper.findComponent(TableEnum).props('column')).toBe('direction')
    expect(wrapper.find('.finance-movement__metadata').text()).toContain('PETTY-001')
    expect(wrapper.findAll('a')).toHaveLength(0)
  })

  it('does not invent missing balances and replaces an empty list with its empty state', async () => {
    const wrapper = render([{ ...entry, movement: undefined }])
    expect(wrapper.find('.finance-movement__balances').exists()).toBe(false)
    expect(wrapper.find('.finance-movement__unavailable').exists()).toBe(true)
    await wrapper.setProps({ entries: [] })
    expect(wrapper.find('details').exists()).toBe(false)
    expect(wrapper.find('.finance-movements__empty-icon').exists()).toBe(true)
  })
})
