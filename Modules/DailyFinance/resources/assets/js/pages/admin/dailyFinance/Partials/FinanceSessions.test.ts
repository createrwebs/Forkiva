import { shallowMount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import FinanceSessions from './FinanceSessions.vue'
import TableStatus from '@/modules/core/components/Table/Partials/TableStatus.vue'
import type { DailyFinanceSummary } from '@/modules/dailyfinance/contracts/dailyFinance'

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))
const { can } = vi.hoisted(() => ({ can: vi.fn(() => true) }))
vi.mock('@/modules/auth/composables/auth', () => ({ useAuth: () => ({ can }) }))

beforeEach(() => can.mockReturnValue(true))

function render(attach = false) {
  const sessions: NonNullable<DailyFinanceSummary['sessions']> = {
    open_count: 1,
    closed_count: 1,
    registers_count: 2,
    expected: null,
    counted: null,
    variance: null,
    rows: [
      {
        id: 1,
        register: 'Main',
        cashier: 'Sara',
        status: 'open',
        opened_at: '2026-10-10T22:15:00+00:00',
        opened_at_formatted: '11/10/2026 01:15',
        closed_at: null,
        latest_for_register: true,
        expected: null,
        counted: null,
        variance: null,
      },
      {
        id: 2,
        register: 'Patio',
        cashier: 'Omar',
        status: 'closed',
        opened_at: null,
        opened_at_formatted: null,
        closed_at: null,
        latest_for_register: true,
        expected: null,
        counted: null,
        variance: null,
      },
    ],
  }
  return shallowMount(FinanceSessions, {
    attachTo: attach ? document.body : undefined,
    props: { sessions },
    global: {
      renderStubDefaultSlot: true,
      stubs: {
        VTextField: true,
        VTabs: true,
        VTab: true,
        VIcon: true,
        VBtn: { template: '<button type="button"><slot /></button>' },
        VMenu: {
          name: 'VMenu',
          props: ['modelValue'],
          template: '<div><slot name="activator" :props="{}" /><slot /></div>',
        },
        VList: { template: '<div><slot /></div>' },
        VListItem: {
          name: 'VListItem',
          props: ['title', 'disabled'],
          template: '<button type="button" :disabled="disabled">{{ title }}</button>',
        },
        VTable: { template: '<table><slot /></table>' },
      },
    },
  })
}

describe('cashier session review', () => {
  it('opens from the whole row and keeps a native keyboard-accessible register button', async () => {
    const wrapper = render()
    expect(wrapper.findAllComponents(TableStatus)).toHaveLength(2)
    expect(wrapper.findAll('thead th')).toHaveLength(8)
    await wrapper.find('tbody tr').trigger('click')
    expect(wrapper.emitted('review')).toEqual([[1]])
    const button = wrapper.find('tbody button')
    expect(button.attributes('type')).toBe('button')
    expect(button.attributes('aria-haspopup')).toBe('dialog')
    await button.trigger('click')
    expect(wrapper.emitted('review')).toEqual([[1], [1]])
    expect(wrapper.find('.finance-session__details').exists()).toBe(false)
  })

  it('displays the system-formatted opening time without browser reformatting', () => {
    const times = render().findAll('time')
    expect(times[0]?.text()).toBe('11/10/2026 01:15')
    expect(times[0]?.attributes('datetime')).toBe('2026-10-10T22:15:00+00:00')
    expect(times[0]?.attributes('dir')).toBe('ltr')
    expect(times[1]?.text()).toBe('—')
  })

  it('opens the requested drawer through row actions without also opening session details', async () => {
    const wrapper = render()
    const rows = wrapper.findAll('tbody tr')
    const openActions = rows[0]!.findAllComponents({ name: 'VListItem' })
    expect(openActions[1]!.props('disabled')).toBe(true)
    openActions[1]!.vm.$emit('click')
    await wrapper.vm.$nextTick()
    expect(wrapper.emitted('handover')).toBeUndefined()
    const closedActions = rows[1]!.findAllComponents({ name: 'VListItem' })
    expect(closedActions[1]!.props('disabled')).toBe(false)
    await closedActions[1]!.trigger('click')
    expect(wrapper.emitted('handover')).toBeUndefined()
    rows[1]!.findComponent({ name: 'VMenu' }).vm.$emit('afterLeave')
    expect(wrapper.emitted('handover')).toEqual([[2]])
    expect(wrapper.emitted('review')).toBeUndefined()
    await closedActions[0]!.trigger('click')
    expect(wrapper.emitted('review')).toBeUndefined()
    rows[1]!.findComponent({ name: 'VMenu' }).vm.$emit('afterLeave')
    expect(wrapper.emitted('review')).toEqual([[2]])
    closedActions[0]!.vm.$emit('click', new KeyboardEvent('keydown', { key: 'Enter' }))
    rows[1]!.findComponent({ name: 'VMenu' }).vm.$emit('afterLeave')
    expect(wrapper.emitted('review')).toEqual([[2], [2]])
  })

  it('allows one menu at a time and closes it when the table is filtered', async () => {
    const wrapper = render()
    const menus = wrapper.findAllComponents({ name: 'VMenu' })
    menus[0]!.vm.$emit('update:modelValue', true)
    await wrapper.vm.$nextTick()
    expect(menus[0]!.props('modelValue')).toBe(true)
    menus[1]!.vm.$emit('update:modelValue', true)
    menus[0]!.vm.$emit('update:modelValue', false)
    await wrapper.vm.$nextTick()
    expect(menus[0]!.props('modelValue')).toBe(false)
    expect(menus[1]!.props('modelValue')).toBe(true)
    wrapper.findComponent({ name: 'VTextField' }).vm.$emit('update:modelValue', 'Omar')
    await wrapper.vm.$nextTick()
    expect(wrapper.findComponent({ name: 'VMenu' }).props('modelValue')).toBe(false)
  })

  it('focuses the persistent row trigger before opening the drawer', async () => {
    const wrapper = render(true)
    const row = wrapper.findAll('tbody tr')[1]!
    const button = row.find('button.finance-session__action-trigger')
    await row.findAllComponents({ name: 'VListItem' })[1]!.trigger('click')
    row.findComponent({ name: 'VMenu' }).vm.$emit('afterLeave')
    expect(document.activeElement).toBe(button.element)
    expect(wrapper.emitted('handover')).toEqual([[2]])
    wrapper.unmount()
  })

  it('hides handover actions without the custody permission and does not open details from the menu trigger', async () => {
    can.mockImplementation(
      (permission?: string) => permission !== 'admin.daily_finance_handovers.index',
    )
    const wrapper = render()
    const actions = wrapper.find('tbody .finance-session__actions')
    expect(actions.findAllComponents({ name: 'VListItem' })).toHaveLength(1)
    await actions.find('button').trigger('click')
    expect(wrapper.emitted('review')).toBeUndefined()
    expect(wrapper.emitted('handover')).toBeUndefined()
  })

  it('does not open restricted sessions from a row or expose an interactive control', async () => {
    can.mockReturnValue(false)
    const wrapper = render()
    await wrapper.find('tbody tr').trigger('click')
    expect(wrapper.emitted('review')).toBeUndefined()
    expect(wrapper.find('tbody button').exists()).toBe(false)
  })

  it('combines status and cashier search and safely clears search', async () => {
    const wrapper = render()
    wrapper.findComponent({ name: 'VTabs' }).vm.$emit('update:modelValue', 'closed')
    await wrapper.vm.$nextTick()
    expect(wrapper.findAll('tbody tr')).toHaveLength(1)
    expect(wrapper.find('tbody').text()).toContain('Patio')
    wrapper.findComponent({ name: 'VTextField' }).vm.$emit('update:modelValue', 'Sara')
    await wrapper.vm.$nextTick()
    expect(wrapper.find('table').exists()).toBe(false)
    expect(wrapper.text()).toContain('dailyfinance::daily_finance.sessions.no_match')
    wrapper.findComponent({ name: 'VTextField' }).vm.$emit('update:modelValue', null)
    await wrapper.vm.$nextTick()
    expect(wrapper.findAll('tbody tr')).toHaveLength(1)
  })
})
