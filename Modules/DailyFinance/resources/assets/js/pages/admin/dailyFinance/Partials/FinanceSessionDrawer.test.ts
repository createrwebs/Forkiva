import { flushPromises, shallowMount } from '@vue/test-utils'
import { describe, expect, it, vi, beforeEach } from 'vitest'
import FinanceSessionDrawer from './FinanceSessionDrawer.vue'
import CloseDialog from '@/modules/pos/pages/admin/session/Dialogs/CloseDialog.vue'
import PaymentBreakdown from '@/modules/pos/pages/admin/session/Partials/Show/PaymentBreakdown.vue'
import FinanceSessionLoading from './FinanceSessionLoading.vue'
import ClosingAudit from '@/modules/pos/pages/admin/session/Partials/Show/ClosingAudit.vue'

const mocks = vi.hoisted(() => ({ show: vi.fn(), can: vi.fn(() => true) }))
vi.mock('@/modules/pos/api/session.api', () => ({ show: mocks.show }))
vi.mock('@/modules/auth/composables/auth', () => ({ useAuth: () => ({ can: mocks.can }) }))
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))

function session(canClose = true) {
  return {
    id: 7,
    status: { id: 'open', name: 'Open' },
    pos_register: { name: 'Main' },
    branch: { name: 'Main branch' },
    opened_by: { name: 'Sara' },
    opened_at: '2026-10-11',
    closed_by: { name: 'Omar' },
    closed_at: '2026-10-11 05:00 PM',
    can_close: canClose,
  }
}

function render() {
  return shallowMount(FinanceSessionDrawer, {
    props: { id: 7 },
    global: {
      renderStubDefaultSlot: true,
      stubs: {
        FinanceDrawer: {
          name: 'FinanceDrawer',
          template: '<div><header><slot name="header-end" /></header><main><slot /></main></div>',
        },
        VAvatar: true,
        VBtn: true,
        VIcon: true,
        VAlert: true,
        VTabs: true,
        VTab: true,
      },
    },
  })
}

beforeEach(() => {
  vi.clearAllMocks()
  mocks.can.mockReturnValue(true)
})

describe('in-page session details', () => {
  it('reuses the POS close dialog and refreshes the overview after saving', async () => {
    mocks.show.mockResolvedValue({ data: { body: session() } })
    const wrapper = render()
    await flushPromises()
    wrapper.findComponent({ name: 'VBtn' }).vm.$emit('click')
    await flushPromises()
    const close = wrapper.findComponent(CloseDialog)
    expect(close.exists()).toBe(true)
    close.vm.$emit('saved')
    await flushPromises()
    expect(wrapper.emitted('saved')).toHaveLength(1)
    expect(mocks.show).toHaveBeenCalledTimes(2)
    expect(wrapper.findComponent(CloseDialog).exists()).toBe(false)
  })

  it('honors both source close permission and the server responsibility check', async () => {
    mocks.show.mockResolvedValue({ data: { body: session(false) } })
    const wrapper = render()
    await flushPromises()
    expect(wrapper.findComponent({ name: 'VBtn' }).exists()).toBe(false)
    mocks.can.mockReturnValue(false)
    mocks.show.mockResolvedValue({ data: { body: session() } })
    await wrapper.setProps({ id: 8 })
    await flushPromises()
    expect(wrapper.findComponent({ name: 'VBtn' }).exists()).toBe(false)
  })

  it('clears old session details when a new request is denied', async () => {
    mocks.show.mockResolvedValueOnce({ data: { body: session() } })
    const wrapper = render()
    await flushPromises()
    mocks.show.mockRejectedValueOnce({ response: { status: 403 } })
    await wrapper.setProps({ id: 8 })
    await flushPromises()
    expect(wrapper.find('.finance-session-detail').exists()).toBe(false)
    expect(wrapper.text()).toContain('dailyfinance::daily_finance.errors.forbidden')
  })

  it('shows saved collections, refunds and orders only for a closed session', async () => {
    mocks.show.mockResolvedValue({
      data: {
        body: {
          ...session(),
          status: { id: 'closed', name: 'Closed' },
          total_collections: { formatted: 'JOD 15.345' },
          total_sales: { formatted: 'JOD 99.000' },
          total_refunds: { formatted: 'JOD 1.125' },
          orders_count: 0,
        },
      },
    })
    const wrapper = render()
    await flushPromises()
    const totals = wrapper.find('.finance-session-detail__totals')
    expect(totals.text()).toContain('JOD 15.345')
    expect(totals.text()).toContain('JOD 1.125')
    expect(totals.text()).not.toContain('JOD 99.000')
    expect(totals.findAll('dd')[2]?.text()).toBe('0')
    expect(wrapper.findComponent(PaymentBreakdown).exists()).toBe(false)
    wrapper.findComponent({ name: 'VTabs' }).vm.$emit('update:modelValue', 'payments')
    await flushPromises()
    expect(wrapper.findComponent(PaymentBreakdown).props('compact')).toBe(true)
    expect(wrapper.findComponent(CloseDialog).exists()).toBe(false)
    expect(wrapper.find('.finance-session-detail__timeline').text()).toContain(
      '2026-10-11 05:00 PM',
    )
    expect(wrapper.find('.finance-session-detail__information').element.tagName).toBe('SECTION')
    expect(wrapper.find('details').exists()).toBe(false)
    wrapper.findComponent({ name: 'VTabs' }).vm.$emit('update:modelValue', 'audit')
    await flushPromises()
    expect(wrapper.findComponent(ClosingAudit).exists()).toBe(true)
    expect(wrapper.findComponent(PaymentBreakdown).exists()).toBe(false)
    expect(wrapper.find('.finance-session-detail__information').isVisible()).toBe(true)
    await wrapper.setProps({ id: 8 })
    await flushPromises()
    expect(wrapper.find('.finance-session-detail__totals').exists()).toBe(true)
    expect(wrapper.findComponent(ClosingAudit).exists()).toBe(false)
  })

  it('replaces old totals with the matching loader while switching sessions', async () => {
    mocks.show.mockResolvedValueOnce({ data: { body: session() } })
    const wrapper = render()
    await flushPromises()
    mocks.show.mockReturnValueOnce(new Promise(() => {}))
    await wrapper.setProps({ id: 8 })
    expect(wrapper.findComponent(FinanceSessionLoading).exists()).toBe(true)
    expect(wrapper.find('.finance-session-detail').exists()).toBe(false)
    expect(wrapper.findComponent({ name: 'VBtn' }).exists()).toBe(false)
  })

  it('offers cash handover in the header and opens the same source in its workspace', async () => {
    mocks.show.mockResolvedValue({
      data: { body: { ...session(), status: { id: 'closed', name: 'Closed' } } },
    })
    const wrapper = render()
    await flushPromises()
    const entry = wrapper.find('header v-btn-stub')
    expect(entry.text()).toContain('dailyfinance::cash_handovers.title')
    expect(wrapper.find('.finance-session-detail__handover').exists()).toBe(false)
    await entry.trigger('click')
    expect(wrapper.emitted('handover')).toEqual([[7]])
    mocks.can.mockReturnValue(false)
    await wrapper.setProps({ id: 8 })
    await flushPromises()
    expect(wrapper.find('header v-btn-stub').exists()).toBe(false)
  })
})
