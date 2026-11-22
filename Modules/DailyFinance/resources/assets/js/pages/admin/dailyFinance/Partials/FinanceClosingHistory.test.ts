import { shallowMount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import FinanceClosingHistory from './FinanceClosingHistory.vue'
import type { DayClosing, DayClosingHistory } from '@/modules/dailyfinance/contracts/dayClosing'

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))

describe('closing history', () => {
  it('opens the selected saved record and forwards page selection', async () => {
    const record = {
      id: 7,
      reference: 'DC-0007',
      date_formatted: '2026-10-11',
      created_at: '2026-10-11 10:00 PM',
      created_by: { id: 1, name: 'Manager' },
      status: { id: 'closed', name: 'Closed' },
      summary: { sales: null, payments: null, sessions: null },
    } as DayClosing
    const history: DayClosingHistory = {
      data: [record],
      pagination: { current_page: 1, last_page: 2, total: 11 },
    }
    const wrapper = shallowMount(FinanceClosingHistory, {
      props: { history },
      global: { stubs: { VIcon: true, VPagination: true, TableStatus: true } },
    })
    const item = wrapper.get('button.closing-history__item')
    expect(item.attributes('aria-haspopup')).toBe('dialog')
    expect(item.text()).toContain('DC-0007')
    expect(item.text()).toContain('Manager')
    await item.trigger('click')
    expect(wrapper.emitted('review')).toEqual([[record]])
    wrapper.findComponent({ name: 'VPagination' }).vm.$emit('update:modelValue', 2)
    expect(wrapper.emitted('page')).toEqual([[2]])
    wrapper.unmount()
  })
})
