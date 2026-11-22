import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createI18n } from 'vue-i18n'
import { createMemoryHistory, createRouter, RouterView } from 'vue-router'
import type { PosSessionDetail } from '@/modules/pos/contracts/sessionReconciliation'
import adminRoutes from '@/modules/pos/routes/admin.routes'
import Show from './Show.vue'

const { getShowData, can } = vi.hoisted(() => ({ getShowData: vi.fn(), can: vi.fn() }))
vi.mock('@/modules/pos/composables/posSession', () => ({ usePosSession: () => ({ getShowData }) }))
vi.mock('@/modules/auth/composables/auth', () => ({ useAuth: () => ({ can }) }))

function session(id = 1, closed = true): PosSessionDetail {
  const money = (amount: number) => ({
    amount,
    currency: 'USD',
    precision: 2,
    formatted: `$${amount.toFixed(2)}`,
  })
  return {
    id,
    pos_register: { id: 1, name: 'Main register' },
    branch: { id: 1, name: 'Downtown' },
    opened_by: { id: 1, name: 'Alex' },
    closed_by: { id: closed ? 1 : null, name: closed ? 'Alex' : null },
    status: {
      id: closed ? 'closed' : 'open',
      name: closed ? 'Closed' : 'Open',
      icon: '',
      color: '',
    },
    opened_at: 'October 5 09:00',
    closed_at: closed ? 'October 5 14:25' : null,
    opening_float: money(12),
    declared_cash: money(5315.72),
    cash_over_short: money(0),
    total_sales: money(5823.13),
    total_refunds: money(519.41),
    orders_count: 42,
    notes: 'Afternoon handover',
    cash_reconciliation: closed
      ? { cash_in: money(5823.13), cash_out: money(519.41), expected_cash: money(5315.72) }
      : null,
    payment_reconciliation: null,
  }
}

async function render(id = '1') {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      {
        path: '/admin/pos/sessions',
        name: 'admin.pos_sessions.index',
        component: { template: '<div>Session list</div>' },
      },
      { path: '/admin/pos/sessions/:id', name: 'admin.pos_sessions.show', component: Show },
      {
        path: '/admin/pos/sessions/:id/cash-movements',
        name: 'admin.pos_sessions.pos_cash_movements.index',
        component: { template: '<div>Cash movement list</div>' },
      },
    ],
  })
  await router.push(`/admin/pos/sessions/${id}`)
  await router.isReady()
  const wrapper = mount(RouterView, {
    global: {
      plugins: [
        router,
        createI18n({
          legacy: false,
          locale: 'en',
          missingWarn: false,
          fallbackWarn: false,
          messages: {},
        }),
      ],
      stubs: {
        VBtn: {
          props: ['to'],
          template:
            '<RouterLink v-if="to" :to="to"><slot /></RouterLink><button v-else><slot /></button>',
        },
        VCard: { template: '<section><slot /></section>' },
        VChip: { template: '<span><slot /></span>' },
        VCardText: { template: '<div><slot /></div>' },
        VCardTitle: { template: '<div><slot /></div>' },
        VAvatar: { template: '<span><slot /></span>' },
        VRow: { template: '<div><slot /></div>' },
        VCol: { template: '<div><slot /></div>' },
        VDivider: true,
        VIcon: true,
        VTable: { template: '<table><slot /></table>' },
        VAlert: { template: '<div role="alert"><slot /></div>' },
        PageStateWrapper: {
          props: ['loading', 'notFound'],
          template:
            '<div v-if="loading">Loading session</div><div v-else-if="notFound">Session not found</div><div v-else><slot /></div>',
        },
        CloseDialog: {
          template:
            '<div data-test="close-dialog"><button @click="$emit(\'saved\')">Confirm closing</button></div>',
        },
      },
    },
  })
  await flushPromises()
  return { wrapper, router }
}

beforeEach(() => {
  getShowData.mockReset()
  can.mockReset()
  can.mockReturnValue(true)
})

describe('dedicated session detail page', () => {
  it('hides closing for another cashier when the server denies session custody', async () => {
    getShowData.mockResolvedValue({ status: 200, data: { ...session(1, false), can_close: false } })
    const { wrapper } = await render()
    expect(wrapper.findAll('button')).toHaveLength(0)
    expect(wrapper.text()).toContain('not_responsible')
    expect(wrapper.text()).toContain('Alex')
    wrapper.unmount()
  })

  it('shows saved count, approval and explanation evidence without inventing a legacy count', async () => {
    const current = session()
    current.closing_custody = {
      version: 1,
      responsible_cashier: { id: 1, name: 'Original cashier' },
      counted_by: { id: 2, name: 'Counting manager' },
      approved_by: { id: 2, name: 'Counting manager' },
      approved_at: 'October 5 14:25',
      on_behalf: true,
      closing_reason: 'Cashier finished shift.',
      variance_reason: 'Recounted shortage.',
    }
    getShowData.mockResolvedValue({ status: 200, data: current })
    const { wrapper } = await render()
    const audit = wrapper.get('.closing-audit')
    expect(audit.text()).toContain('Original cashier')
    expect(audit.text()).toContain('Counting manager')
    expect(audit.text()).toContain('Cashier finished shift.')
    expect(audit.text()).toContain('Recounted shortage.')
    wrapper.unmount()
    getShowData.mockResolvedValue({ status: 200, data: session() })
    const legacy = await render()
    expect(legacy.wrapper.get('.closing-audit').text()).toContain('legacy_closing_audit')
    legacy.wrapper.unmount()
  })
  it('uses explicit collections while remaining compatible with legacy session responses', async () => {
    const current = session()
    current.total_collections = { ...current.total_sales!, amount: 200, formatted: '$200.00' }
    getShowData.mockResolvedValue({ status: 200, data: current })
    const { wrapper } = await render()
    expect(wrapper.text()).toContain('$200.00')
    expect(wrapper.get('.session-metrics').text()).not.toContain('$5823.13')
    wrapper.unmount()

    getShowData.mockResolvedValue({ status: 200, data: session() })
    const legacy = await render()
    expect(legacy.wrapper.text()).toContain('$5823.13')
    legacy.wrapper.unmount()
  })

  it('registers a permission-protected page beside the cash movement route', () => {
    const parent = adminRoutes.find((route) => route.name === 'admin.pos_sessions')
    const show = parent?.children?.find((route) => route.name === 'admin.pos_sessions.show')
    expect(show?.path).toBe(':id')
    expect(show?.meta?.permission).toBe('admin.pos_sessions.show')
    expect(
      parent?.children?.find(
        (route) => route.name === 'admin.pos_sessions.pos_cash_movements.index',
      )?.path,
    ).toBe(':id/cash-movements')
  })

  it('loads a bookmarkable page and navigates to cash movements without duplicating the layout navigation', async () => {
    getShowData.mockResolvedValue({ status: 200, data: session() })
    const { wrapper, router } = await render()
    expect(getShowData).toHaveBeenCalledWith(1)
    expect(wrapper.find('h1').exists()).toBe(false)
    expect(wrapper.find('a[href="/admin/pos/sessions"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('$5315.72')
    expect(wrapper.text()).toContain('Downtown')
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    expect(wrapper.findAll('button')).toHaveLength(0)
    await wrapper.get('a[href="/admin/pos/sessions/1/cash-movements"]').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.name).toBe('admin.pos_sessions.pos_cash_movements.index')
    wrapper.unmount()
  })

  it('shows a retryable error instead of a blank page', async () => {
    getShowData
      .mockResolvedValueOnce({ status: 500 })
      .mockResolvedValueOnce({ status: 200, data: session() })
    const { wrapper } = await render()
    expect(wrapper.text()).toContain('load_error')
    await wrapper.get('button').trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Main register')
    wrapper.unmount()
  })

  it('handles missing and invalid sessions without showing stale figures', async () => {
    getShowData.mockResolvedValue({ status: 404 })
    const { wrapper, router } = await render()
    expect(wrapper.text()).toContain('Session not found')
    expect(wrapper.text()).not.toContain('$')
    await router.push('/admin/pos/sessions/not-an-id')
    await flushPromises()
    expect(wrapper.text()).toContain('Session not found')
    expect(getShowData).toHaveBeenCalledTimes(1)
    wrapper.unmount()
  })

  it('does not apply an older response after the route changes', async () => {
    let completeFirst: (value: { status: number; data: PosSessionDetail }) => void = () => {}
    getShowData
      .mockReturnValueOnce(
        new Promise((resolve) => {
          completeFirst = resolve
        }),
      )
      .mockResolvedValueOnce({
        status: 200,
        data: { ...session(2), pos_register: { id: 2, name: 'Second register' } },
      })
    const { wrapper, router } = await render()
    expect(wrapper.text()).toContain('Loading session')
    await router.push('/admin/pos/sessions/2')
    await flushPromises()
    expect(wrapper.text()).toContain('Second register')
    completeFirst({ status: 200, data: session() })
    await flushPromises()
    expect(wrapper.text()).toContain('Second register')
    expect(wrapper.text()).not.toContain('Main register')
    wrapper.unmount()
  })

  it('shows the open-session state, respects action permissions and refreshes after closing', async () => {
    getShowData
      .mockResolvedValueOnce({ status: 200, data: session(1, false) })
      .mockResolvedValueOnce({ status: 200, data: session() })
    const { wrapper } = await render()
    expect(wrapper.text()).toContain('open_summary')
    expect(wrapper.text()).not.toContain('payment_breakdown.title')
    expect(wrapper.find('.session-metrics').exists()).toBe(false)
    await wrapper.get('button').trigger('click')
    await wrapper.get('[data-test="close-dialog"] button').trigger('click')
    await flushPromises()
    expect(wrapper.text()).not.toContain('open_summary')
    expect(wrapper.find('[data-test="close-dialog"]').exists()).toBe(false)
    expect(wrapper.find('.session-metrics').exists()).toBe(true)
    wrapper.unmount()
    can.mockImplementation((permission: string) => permission === 'admin.pos_sessions.show')
    getShowData.mockResolvedValue({ status: 200, data: session(1, false) })
    const denied = await render()
    expect(denied.wrapper.findAll('a, button')).toHaveLength(0)
    denied.wrapper.unmount()
  })

  it('shows access denial without a retry or session data', async () => {
    getShowData.mockResolvedValue({ status: 403 })
    const { wrapper } = await render()
    expect(wrapper.text()).toContain('access_denied')
    expect(wrapper.findAll('button')).toHaveLength(0)
    expect(wrapper.text()).not.toContain('Main register')
    wrapper.unmount()
  })
})
