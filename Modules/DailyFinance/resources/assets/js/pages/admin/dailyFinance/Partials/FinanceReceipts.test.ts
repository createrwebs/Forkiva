import { shallowMount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import TableEnum from '@/modules/core/components/Table/Partials/TableEnum.vue'
import type { DailyFinanceEntry } from '@/modules/dailyfinance/contracts/dailyFinance'
import FinanceReceipts from './FinanceReceipts.vue'

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))
const entry: DailyFinanceEntry = {
  id: 42,
  reference: 'ORD-123',
  occurred_at: '2026-10-10T21:00:00Z',
  occurred_at_formatted: '11/10/2026 00:00',
  amount: { amount: 12.345, formatted: '12.345 JOD', currency: 'JOD', precision: 3 },
  status: null,
  label: { id: 'cash', name: 'Cash', icon: 'tabler-cash' },
  receipt: { cashier: 'Ayman' },
}
function render(entries = [entry], kind: 'collections' | 'refunds' = 'collections') {
  return shallowMount(FinanceReceipts, {
    props: { entries, kind },
    global: { stubs: { VIcon: true } },
  })
}

describe('daily finance receipts', () => {
  it('identifies each receipt separately from its order with system dates and inline details', () => {
    const wrapper = render([entry, { ...entry, id: 43 }])
    expect(wrapper.findAll('details > summary')).toHaveLength(2)
    expect(wrapper.find('summary').text()).toContain('ORD-123')
    expect(wrapper.find('summary').text()).toContain('#42')
    expect(wrapper.find('details').attributes('open')).toBeUndefined()
    expect(wrapper.find('time').text()).toBe(entry.occurred_at_formatted)
    expect(wrapper.find('time').attributes('datetime')).toBe(entry.occurred_at)
    expect(wrapper.findComponent(TableEnum).props('item')).toEqual(entry)
    expect(wrapper.find('dl').text()).toContain('Ayman')
    expect(wrapper.findAll('a')).toHaveLength(0)
  })

  it('preserves source precision and labels refunds separately without changing the amount sign', async () => {
    const wrapper = render()
    expect(wrapper.find('.finance-receipt__amount').text()).toBe('12.345 JOD')
    expect(wrapper.find('.finance-receipt__footer').text()).toContain('amount_collections')
    await wrapper.setProps({ kind: 'refunds', entries: [{ ...entry, receipt: { cashier: null } }] })
    expect(wrapper.find('.finance-receipt__footer').text()).toContain('amount_refunds')
    expect(wrapper.find('.finance-receipt__footer strong').text()).toBe('12.345 JOD')
    expect(wrapper.findAll('dd')[1]?.text()).toBe('—')
  })

  it('shows distinct empty collections and refunds states with an icon', async () => {
    const wrapper = render([])
    expect(wrapper.find('details').exists()).toBe(false)
    expect(wrapper.find('.finance-receipts__empty-icon').exists()).toBe(true)
    expect(wrapper.text()).toContain('empty_collections')
    await wrapper.setProps({ kind: 'refunds' })
    expect(wrapper.text()).toContain('empty_refunds')
  })
})
