import { ref } from 'vue'
import { flushPromises, shallowMount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import Index from './Index.vue'
import FinanceMetric from './Partials/FinanceMetric.vue'
import FinanceSessions from './Partials/FinanceSessions.vue'
import FinanceFilters from './Partials/FinanceFilters.vue'
import FinanceFollowUp from './Partials/FinanceFollowUp.vue'
import type { DailyFinanceSummary } from '@/modules/dailyfinance/contracts/dailyFinance'
import type { DayClosingReview } from '@/modules/dailyfinance/contracts/dayClosing'

const mocks = vi.hoisted(() => ({ load: vi.fn(), can: vi.fn(() => true) }))
const summary = ref<DailyFinanceSummary | null>(null)
const loading = ref(false)
const error = ref<number | null>(null)
const closingOverview = ref<DayClosingReview | null>(null)
const reloadClosing = vi.fn()
const acceptClosingReview = vi.fn()
const closingContext = vi.fn()
vi.mock('@/modules/dailyfinance/composables/useClosingFollowUp', () => ({
  useClosingFollowUp: (
    branchId: () => number | null,
    date: () => string | null,
    enabled: () => boolean,
  ) => {
    closingContext(branchId, date, enabled)
    return {
      summary: closingOverview,
      loading: ref(false),
      error: ref(false),
      load: reloadClosing,
      acceptReview: acceptClosingReview,
    }
  },
}))
const settlementOverview = ref({
  branch_id: 1,
  open_count: 2,
  pending_count: 1,
  partial_count: 1,
  balances: [],
})
const reloadCash = vi.fn()
const cashOverview = ref({
  branch_id: 1,
  unhanded_sessions_count: 1,
  pending_sessions_count: 1,
  pending_handovers_count: 1,
  balances: [],
})
vi.mock('@/modules/dailyfinance/composables/useHandoverFollowUp', () => ({
  useHandoverFollowUp: () => ({
    summary: cashOverview,
    loading: ref(false),
    error: ref(false),
    load: reloadCash,
  }),
}))
const reloadSettlements = vi.fn()
vi.mock('@/modules/dailyfinance/composables/useSettlementSummary', () => ({
  useSettlementSummary: () => ({
    summary: settlementOverview,
    loading: ref(false),
    error: ref(false),
    load: reloadSettlements,
  }),
}))
vi.mock('@/modules/dailyfinance/composables/useDailyFinance', () => ({
  useDailyFinance: () => ({ summary, loading, error, load: mocks.load }),
}))
vi.mock('@/modules/auth/composables/auth', () => ({ useAuth: () => ({ can: mocks.can }) }))
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key, locale: ref('en') }) }))

function response(): DailyFinanceSummary {
  return {
    meta: {
      branch_id: 1,
      date: '2026-10-11',
      today: '2026-10-11',
      timezone: 'Asia/Amman',
      currency: 'JOD',
      generated_at: '2026-10-11T09:00:00Z',
      branch_locked: false,
    },
    branches: [
      { id: 1, name: 'Main' },
      { id: 2, name: 'Second' },
    ],
    permissions: { sales: true, payments: true, movements: true, sessions: true, expenses: true },
    sales: null,
    payments: null,
    movements: null,
    expenses: null,
    sessions: {
      rows: [],
      open_count: 0,
      closed_count: 0,
      registers_count: 0,
      expected: null,
      counted: null,
      variance: null,
    },
  }
}

function render() {
  return shallowMount(Index, {
    global: {
      renderStubDefaultSlot: true,
      stubs: {
        VIcon: true,
        VAvatar: true,
        VBtn: true,
        VSelect: true,
        VCard: true,
        VAlert: true,
        FinanceLoading: false,
        FinanceFollowUp: false,
        FinanceCashFollowUpDrawer: {
          name: 'FinanceCashFollowUpDrawer',
          props: ['branchId', 'branchName', 'kind'],
          template: '<div />',
        },
        FinanceHandoverDrawer: {
          name: 'FinanceHandoverDrawer',
          props: ['sessions', 'initialId'],
          template: '<div />',
        },
        FinanceDayClosingDrawer: {
          name: 'FinanceDayClosingDrawer',
          props: ['branchId', 'date', 'branchName'],
          template: '<div />',
        },
        FinanceSettlementDrawer: {
          name: 'FinanceSettlementDrawer',
          props: ['branchId', 'date', 'branchName', 'initialStatus'],
          template: '<div />',
        },
        VTable: true,
      },
    },
  })
}

beforeEach(() => {
  vi.clearAllMocks()
  summary.value = response()
  closingOverview.value = {
    summary: response(),
    date_formatted: '2026-10-11',
    blockers: [],
    warnings: [],
    closing: null,
    can_close: true,
    review_hash: 'review-hash',
    checks: {
      open_sessions: 0,
      closed_sessions: 0,
      unknown_counts: 0,
      pending_handovers: 0,
      session_variances: 0,
      receipt_variances: 0,
      unpaid_orders: 0,
      excluded_currencies: 0,
      future_day: 0,
    },
  }
  loading.value = false
  error.value = null
  mocks.can.mockReturnValue(true)
  mocks.load.mockResolvedValue(undefined)
})

describe('daily finance screen', () => {
  it('opens outstanding settlement history from the follow-up and refreshes its balance after a change', async () => {
    const wrapper = render()
    await flushPromises()
    wrapper.findComponent(FinanceFollowUp).vm.$emit('reviewSettlements')
    await flushPromises()
    const drawer = wrapper.findComponent({ name: 'FinanceSettlementDrawer' })
    expect(drawer.props('initialStatus')).toBe('outstanding')
    drawer.vm.$emit('changed')
    expect(reloadSettlements).toHaveBeenCalled()
    wrapper.unmount()
    mocks.can.mockImplementation((permission?: string) => permission !== 'admin.payments.index')
    const restricted = render()
    await flushPromises()
    expect(restricted.findComponent(FinanceFollowUp).props('settlements')).toBeUndefined()
    restricted.unmount()
  })
  it('opens all-date cash follow-up independently of the selected day and refreshes after a handover', async () => {
    const wrapper = render()
    await flushPromises()
    wrapper.findComponent(FinanceFollowUp).vm.$emit('reviewCash', 'unhanded')
    await flushPromises()
    const drawer = wrapper.findComponent({ name: 'FinanceCashFollowUpDrawer' })
    expect(drawer.props()).toMatchObject({ branchId: 1, branchName: 'Main', kind: 'unhanded' })
    drawer.vm.$emit('saved')
    expect(reloadCash).toHaveBeenCalled()
    expect(reloadClosing).toHaveBeenCalled()
    wrapper.findComponent(FinanceFilters).vm.$emit('branch', 2)
    await flushPromises()
    expect(wrapper.findComponent({ name: 'FinanceCashFollowUpDrawer' }).exists()).toBe(false)
    wrapper.unmount()
    mocks.can.mockImplementation(
      (permission?: string) => permission !== 'admin.daily_finance_handovers.index',
    )
    const restricted = render()
    await flushPromises()
    expect(restricted.findComponent(FinanceFollowUp).props('cash')).toBeUndefined()
    restricted.unmount()
  })
  it('opens closing for the selected branch and day and respects its source permissions', async () => {
    const wrapper = render()
    await flushPromises()
    const action = wrapper
      .findAllComponents({ name: 'VBtn' })
      .find((button) => button.text().includes('day_closings.title'))!
    action.vm.$emit('click')
    await flushPromises()
    expect(wrapper.findComponent({ name: 'FinanceDayClosingDrawer' }).props()).toMatchObject({
      branchId: 1,
      date: '2026-10-11',
      branchName: 'Main',
    })
    wrapper.unmount()
    mocks.can.mockImplementation((permission?: string) => permission !== 'admin.payments.index')
    const restricted = render()
    await flushPromises()
    expect(restricted.text()).not.toContain('day_closings.title')
    expect(restricted.findComponent(FinanceFollowUp).props('closing')).toBeUndefined()
    expect(closingContext.mock.calls[closingContext.mock.calls.length - 1]![2]()).toBe(false)
    restricted.unmount()
  })

  it('opens closing from follow-up, accepts refreshed evidence and closes it when the day changes', async () => {
    const wrapper = render()
    await flushPromises()
    expect(wrapper.findComponent(FinanceFollowUp).props('closing')?.summary).toEqual(
      closingOverview.value,
    )
    wrapper.findComponent(FinanceFollowUp).vm.$emit('reviewClosing')
    await flushPromises()
    const drawer = wrapper.findComponent({ name: 'FinanceDayClosingDrawer' })
    expect(drawer.props()).toMatchObject({ branchId: 1, date: '2026-10-11' })
    drawer.vm.$emit('reviewed', closingOverview.value)
    expect(acceptClosingReview).toHaveBeenCalledWith(closingOverview.value)
    wrapper.findComponent(FinanceFollowUp).vm.$emit('retryClosing')
    expect(reloadClosing).toHaveBeenCalledTimes(1)
    mocks.load.mockImplementationOnce(() => {
      summary.value!.meta.date = '2026-10-10'
    })
    wrapper.findComponent(FinanceFilters).vm.$emit('date', '2026-10-10')
    await flushPromises()
    expect(drawer.exists()).toBe(false)
    expect(closingContext.mock.calls[0]![1]()).toBe('2026-10-10')
    wrapper.unmount()
  })

  it('refreshes closing checks after a POS session save and a manual page refresh', async () => {
    const wrapper = render()
    await flushPromises()
    wrapper.findComponent(FinanceSessions).vm.$emit('review', 7)
    await flushPromises()
    wrapper.findComponent({ name: 'FinanceSessionDrawer' }).vm.$emit('saved')
    await flushPromises()
    expect(reloadClosing).toHaveBeenCalledTimes(1)
    await wrapper.find('.finance-header__actions v-btn-stub').trigger('click')
    await flushPromises()
    expect(reloadClosing).toHaveBeenCalledTimes(2)
    wrapper.unmount()
  })

  it('shows unavailable expenses rather than zero and keeps session review in the workspace', async () => {
    const wrapper = render()
    await flushPromises()
    const expense = wrapper.findAllComponents(FinanceMetric)[3]!
    expect(expense.props('value')).toBeNull()
    expect(expense.props('unavailable')).toBe('dailyfinance::daily_finance.unavailable')
    expect(wrapper.findComponent(FinanceSessions).exists()).toBe(true)
    expect(wrapper.text()).toContain('dailyfinance::daily_finance.actions.review_sessions')
  })

  it('reloads with the selected branch and day', async () => {
    const wrapper = render()
    await flushPromises()
    wrapper.findComponent(FinanceFilters).vm.$emit('branch', 2)
    await flushPromises()
    expect(mocks.load).toHaveBeenLastCalledWith({ branch_id: 2, date: '2026-10-11' })
    wrapper.findComponent(FinanceFilters).vm.$emit('date', '2026-10-10')
    await flushPromises()
    expect(mocks.load).toHaveBeenLastCalledWith({ branch_id: 1, date: '2026-10-10' })
  })

  it('hides financial data on errors and hides disallowed action links', async () => {
    error.value = 403
    mocks.can.mockReturnValue(false)
    const wrapper = render()
    await flushPromises()
    expect(wrapper.findAllComponents(FinanceMetric)).toHaveLength(0)
    expect(wrapper.text()).toContain('dailyfinance::daily_finance.errors.forbidden')
    expect(wrapper.text()).not.toContain('dailyfinance::daily_finance.actions.review_sessions')
  })

  it('replaces stale amounts with the page skeleton until loading finishes', async () => {
    loading.value = true
    const wrapper = render()
    expect(wrapper.find('[role="status"]').exists()).toBe(true)
    expect(wrapper.attributes('aria-busy')).toBe('true')
    expect(wrapper.findAllComponents(FinanceMetric)).toHaveLength(0)
    loading.value = false
    await flushPromises()
    expect(wrapper.find('[role="status"]').exists()).toBe(false)
    expect(wrapper.attributes('aria-busy')).toBe('false')
    expect(wrapper.findAllComponents(FinanceMetric)).toHaveLength(4)
  })

  it('keeps header actions visible and disabled without a refresh spinner on initial load', async () => {
    summary.value = null
    loading.value = true
    const wrapper = render()
    const actions = wrapper.findAll('.finance-header__actions v-btn-stub')
    expect(actions).toHaveLength(5)
    expect(actions[0]!.attributes('loading')).toBe('false')
    expect(actions[0]!.attributes('disabled')).toBe('true')
    expect(actions[1]!.attributes('disabled')).toBe('true')
    expect(actions[2]!.attributes('disabled')).toBe('true')
    expect(actions[3]!.attributes('disabled')).toBe('true')
    expect(actions[4]!.attributes('disabled')).toBe('true')

    summary.value = response()
    loading.value = false
    await flushPromises()
    expect(actions[0]!.attributes('disabled')).toBe('false')
    expect(actions[1]!.attributes('disabled')).toBe('false')
    expect(actions[2]!.attributes('disabled')).toBe('false')

    summary.value = null
    loading.value = true
    await flushPromises()
    expect(wrapper.findAll('.finance-header__actions v-btn-stub')).toHaveLength(5)
    expect(actions[1]!.attributes('disabled')).toBe('true')
    error.value = 500
    loading.value = false
    await flushPromises()
    expect(actions[0]!.attributes('disabled')).toBe('false')
    expect(actions[1]!.attributes('disabled')).toBe('true')
  })

  it('opens cash handovers directly from the header and clears the drawer when changing branch', async () => {
    const wrapper = render()
    await flushPromises()
    await wrapper.findAll('.finance-header__actions v-btn-stub')[2]!.trigger('click')
    await flushPromises()
    expect(wrapper.findComponent({ name: 'FinanceHandoverDrawer' }).exists()).toBe(true)
    expect(wrapper.findComponent({ name: 'FinanceHandoverDrawer' }).props('sessions')).toEqual([])
    wrapper.findComponent(FinanceFilters).vm.$emit('branch', 2)
    await flushPromises()
    expect(wrapper.findComponent({ name: 'FinanceHandoverDrawer' }).exists()).toBe(false)
  })

  it('opens handovers for the closed session selected from its table actions', async () => {
    summary.value!.sessions!.rows = [
      {
        id: 7,
        status: 'closed',
        register: 'Main',
        cashier: 'Sara',
        opened_at: null,
        opened_at_formatted: null,
        closed_at: null,
        latest_for_register: true,
        expected: null,
        counted: null,
        variance: null,
      },
    ]
    const wrapper = render()
    await flushPromises()
    wrapper.findComponent(FinanceSessions).vm.$emit('handover', 7)
    await flushPromises()
    expect(wrapper.findComponent({ name: 'FinanceHandoverDrawer' }).props('initialId')).toBe(7)
  })

  it('hides the handover entry when its permission is missing', async () => {
    mocks.can.mockImplementation(
      (permission?: string) => permission !== 'admin.daily_finance_handovers.index',
    )
    const wrapper = render()
    await flushPromises()
    expect(wrapper.findAll('.finance-header__actions v-btn-stub')).toHaveLength(3)
    expect(wrapper.text()).not.toContain('dailyfinance::cash_handovers.title')
  })

  it('shows the refresh spinner only for a manual refresh', async () => {
    const wrapper = render()
    await flushPromises()
    let finishRefresh!: () => void
    mocks.load.mockImplementationOnce(() => {
      loading.value = true
      return new Promise<void>((resolve) => {
        finishRefresh = resolve
      })
    })
    const refresh = wrapper.find('.finance-header__actions v-btn-stub')
    await refresh.trigger('click')
    expect(refresh.attributes('loading')).toBe('true')
    expect(refresh.attributes('disabled')).toBe('true')
    loading.value = false
    finishRefresh()
    await flushPromises()
    expect(refresh.attributes('loading')).toBe('false')
    expect(refresh.attributes('disabled')).toBe('false')
  })

  it.each(['admin.pos_sessions.index', 'admin.pos_sessions.show'])(
    'hides session review when %s is missing, including during loading',
    (permission) => {
      mocks.can.mockImplementation((value?: string) => value !== permission)
      summary.value = null
      loading.value = true
      const wrapper = render()
      expect(wrapper.findAll('.finance-header__actions v-btn-stub')).toHaveLength(2)
    },
  )

  it('opens branch settlement workspace and protects the entry with both source and module permissions', async () => {
    const wrapper = render()
    await flushPromises()
    wrapper
      .findAllComponents({ name: 'VBtn' })
      .find((button) => button.text().includes('payment_settlements.title'))!
      .vm.$emit('click')
    await flushPromises()
    expect(wrapper.findComponent({ name: 'FinanceSettlementDrawer' }).props()).toMatchObject({
      branchId: 1,
      date: '2026-10-11',
      branchName: 'Main',
    })
    wrapper.findComponent(FinanceFilters).vm.$emit('date', '2026-10-10')
    await flushPromises()
    expect(wrapper.findComponent({ name: 'FinanceSettlementDrawer' }).exists()).toBe(false)
    wrapper.unmount()
    for (const permission of ['admin.daily_finance_settlements.index', 'admin.payments.index']) {
      mocks.can.mockImplementation((value?: string) => value !== permission)
      const restricted = render()
      expect(restricted.text()).not.toContain('dailyfinance::payment_settlements.title')
      restricted.unmount()
    }
  })
})
