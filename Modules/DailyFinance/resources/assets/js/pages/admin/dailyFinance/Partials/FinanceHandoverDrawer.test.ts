import { shallowMount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import type { DailyFinanceSession } from '@/modules/dailyfinance/contracts/dailyFinance'
import FinanceHandoverDrawer from './FinanceHandoverDrawer.vue'
import FinanceHandovers from './FinanceHandovers.vue'
import FinanceDrawer from './FinanceDrawer.vue'

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))

function session(id: number, status: 'open' | 'closed'): DailyFinanceSession {
  return {
    id,
    status,
    register: `Register ${id}`,
    cashier: 'Sara',
    opened_at: null,
    opened_at_formatted: '12/10/2026 10:00',
    closed_at: null,
    latest_for_register: true,
    expected: null,
    counted: null,
    variance: null,
  }
}

function render(
  sessions = [session(1, 'open'), session(2, 'closed'), session(3, 'closed')],
  initialId?: number,
) {
  return shallowMount(FinanceHandoverDrawer, {
    props: { sessions, initialId },
    global: {
      renderStubDefaultSlot: true,
      stubs: {
        FinanceDrawer: {
          name: 'FinanceDrawer',
          props: ['persistent'],
          template: '<div><header><slot name="header-end" /></header><main><slot /></main></div>',
        },
        VSelect: {
          name: 'VSelect',
          props: ['items', 'disabled', 'modelValue'],
          template: '<div />',
        },
        VIcon: true,
        VAvatar: true,
      },
    },
  })
}

describe('visible cash handover workspace', () => {
  it('offers closed sessions only and opens the requested source session', async () => {
    const wrapper = render(undefined, 3)
    const select = wrapper.findComponent({ name: 'VSelect' })
    expect(wrapper.find('header').findComponent({ name: 'VSelect' }).exists()).toBe(true)
    expect(wrapper.find('main').findComponent({ name: 'VSelect' }).exists()).toBe(false)
    expect(select.props('items')).toEqual([
      { id: 2, name: 'Register 2 · #2' },
      { id: 3, name: 'Register 3 · #3' },
    ])
    expect(wrapper.findComponent(FinanceHandovers).props('sessionId')).toBe(3)
    expect(wrapper.text()).toContain('12/10/2026 10:00')
    select.vm.$emit('update:modelValue', 2)
    await wrapper.vm.$nextTick()
    expect(wrapper.findComponent(FinanceHandovers).props('sessionId')).toBe(2)
  })

  it('protects the source selection while editing and the drawer during an unresolved save', async () => {
    const wrapper = render()
    wrapper.findComponent(FinanceHandovers).vm.$emit('editing', true)
    await wrapper.vm.$nextTick()
    const select = wrapper.findComponent({ name: 'VSelect' })
    expect(select.props('disabled')).toBe(true)
    select.vm.$emit('update:modelValue', 3)
    await wrapper.vm.$nextTick()
    expect(wrapper.findComponent(FinanceHandovers).props('sessionId')).toBe(2)
    wrapper.findComponent(FinanceHandovers).vm.$emit('editing', false)
    wrapper.findComponent(FinanceHandovers).vm.$emit('busy', true)
    await wrapper.vm.$nextTick()
    expect(wrapper.findComponent(FinanceDrawer).props('persistent')).toBe(true)
    expect(select.props('disabled')).toBe(true)
  })

  it('explains how to proceed when there are no closed sessions without querying a handover', () => {
    const wrapper = render([session(1, 'open')])
    expect(wrapper.findComponent(FinanceHandovers).exists()).toBe(false)
    expect(wrapper.text()).toContain('dailyfinance::cash_handovers.no_sessions_hint')
  })
})
