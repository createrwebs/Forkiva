import { shallowMount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import FinanceFollowUp from './FinanceFollowUp.vue'
import type { PaymentSettlementSummary } from '@/modules/dailyfinance/contracts/paymentSettlement'
import type { DayClosing, DayClosingReview } from '@/modules/dailyfinance/contracts/dayClosing'
import TableStatus from '@/modules/core/components/Table/Partials/TableStatus.vue'

vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    t: (key: string, values?: Record<string, unknown>) =>
      values ? `${key} ${Object.values(values).join(' ')}` : key,
  }),
}))
const summary: PaymentSettlementSummary = {
  branch_id: 1,
  open_count: 2,
  pending_count: 1,
  partial_count: 1,
  balances: [
    {
      currency: 'JOD',
      open_count: 1,
      remaining_decimal: '15.475',
      remaining: { amount: 15.475, formatted: 'JOD 15.475', currency: 'JOD', precision: 3 },
    },
    {
      currency: 'USD',
      open_count: 1,
      remaining_decimal: '10.00',
      remaining: { amount: 10, formatted: '$10.00', currency: 'USD', precision: 2 },
    },
  ],
}
const global = { renderStubDefaultSlot: true, stubs: { VIcon: true, VBtn: true } }
function closingReview(overrides: Partial<DayClosingReview> = {}): DayClosingReview {
  return {
    date_formatted: 'Oct 13, 2026',
    blockers: [],
    warnings: [],
    closing: null,
    can_close: true,
    ...overrides,
  } as DayClosingReview
}

describe('settlement follow-up', () => {
  it('keeps currencies separate and opens review from the entire row', async () => {
    const wrapper = shallowMount(FinanceFollowUp, {
      props: { settlements: { summary, loading: false, error: false } },
      global,
    })
    expect(wrapper.findAll('bdi').map((amount) => amount.text())).toEqual(['JOD 15.475', '$10.00'])
    expect(wrapper.text()).toContain('followup.awaiting: 1')
    expect(wrapper.text()).toContain('tabs.partial: 1')
    await wrapper.get('button').trigger('click')
    expect(wrapper.emitted('reviewSettlements')).toHaveLength(1)
  })

  it('opens each cash follow-up independently and does not mix its currencies or reservations', async () => {
    const wrapper = shallowMount(FinanceFollowUp, {
      props: {
        cash: {
          loading: false,
          error: false,
          summary: {
            branch_id: 1,
            unhanded_sessions_count: 2,
            pending_sessions_count: 1,
            pending_handovers_count: 1,
            balances: [
              {
                currency: 'JOD',
                unhanded: { amount: 4, formatted: 'JOD 4.000', currency: 'JOD', precision: 3 },
                unhanded_decimal: '4.000',
                pending: { amount: 6.345, formatted: 'JOD 6.345', currency: 'JOD', precision: 3 },
                pending_decimal: '6.345',
              },
              {
                currency: 'USD',
                unhanded: { amount: 10, formatted: '$10.00', currency: 'USD', precision: 2 },
                unhanded_decimal: '10.00',
                pending: { amount: 0, formatted: '$0.00', currency: 'USD', precision: 2 },
                pending_decimal: '0.00',
              },
            ],
          },
        },
      },
      global,
    })
    const buttons = wrapper.findAll('button')
    expect(buttons[0]!.findAll('bdi').map((amount) => amount.text())).toEqual([
      'JOD 4.000',
      '$10.00',
    ])
    expect(buttons[1]!.findAll('bdi').map((amount) => amount.text())).toEqual(['JOD 6.345'])
    await buttons[0]!.trigger('click')
    await buttons[1]!.trigger('click')
    expect(wrapper.emitted('reviewCash')).toEqual([['unhanded'], ['pending']])
  })

  it('distinguishes empty balances from an unavailable read and offers retry', async () => {
    const wrapper = shallowMount(FinanceFollowUp, {
      props: {
        settlements: {
          summary: { ...summary, open_count: 0, pending_count: 0, partial_count: 0, balances: [] },
          loading: false,
          error: false,
        },
      },
      global,
    })
    expect(wrapper.text()).toContain('followup.no_balance')
    await wrapper.setProps({ settlements: { summary: null, error: true, loading: false } })
    expect(wrapper.findAll('bdi')).toHaveLength(0)
    expect(wrapper.get('[role="alert"]').text()).toContain('entries.error')
    wrapper.findComponent({ name: 'VBtn' }).vm.$emit('click')
    expect(wrapper.emitted('retrySettlements')).toHaveLength(1)
  })

  it('opens selected-day review and distinguishes readiness from closing permission', async () => {
    const wrapper = shallowMount(FinanceFollowUp, {
      props: {
        settlements: { summary, loading: false, error: false },
        closing: { summary: closingReview({ can_close: false }), loading: false, error: false },
      },
      global,
    })
    expect(wrapper.findComponent(TableStatus).props('item').status).toMatchObject({
      id: 'ready',
      color: 'success',
      name: 'dailyfinance::day_closings.ready',
    })
    expect(wrapper.find('header .finance-followup__scope').exists()).toBe(false)
    expect(wrapper.findAll('button')[0]!.text()).toContain('followup.all_dates')
    const row = wrapper.findAll('button')[1]!
    expect(row.text()).toContain('Oct 13, 2026')
    expect(wrapper.classes()).not.toContain('finance-followup--clear')
    await row.trigger('click')
    expect(wrapper.emitted('reviewClosing')).toEqual([[]])
  })

  it('shows the authoritative reason and distinguishes blockers from explainable warnings', async () => {
    const wrapper = shallowMount(FinanceFollowUp, {
      props: {
        closing: {
          summary: closingReview({ blockers: ['pending_handovers'], warnings: ['unpaid_orders'] }),
          loading: false,
          error: false,
        },
      },
      global,
    })
    expect(wrapper.findComponent(TableStatus).props('item').status.id).toBe('attention')
    expect(wrapper.text()).toContain('checks.pending_handovers')
    expect(wrapper.text()).toContain('followup.more 1')
    expect(wrapper.text()).toContain('followup.blockers 1')
    await wrapper.setProps({
      closing: {
        summary: closingReview({ warnings: ['session_variances'] }),
        loading: false,
        error: false,
      },
    })
    expect(wrapper.text()).toContain('checks.session_variances')
    expect(wrapper.text()).toContain('followup.explanation')
    expect(wrapper.text()).not.toContain('followup.blockers')
  })

  it('shows approved closing evidence even when its saved snapshot contains warnings', () => {
    const wrapper = shallowMount(FinanceFollowUp, {
      props: {
        closing: {
          summary: closingReview({
            warnings: ['session_variances'],
            closing: {
              reference: 'DC-0021',
              created_by: { id: 9, name: 'Sara' },
              created_at: '2026-10-13 04:30 PM',
            } as DayClosing,
          }),
          loading: false,
          error: false,
        },
      },
      global,
    })
    expect(wrapper.findComponent(TableStatus).props('item').status.id).toBe('closed')
    expect(wrapper.text()).toContain('DC-0021 Sara 2026-10-13 04:30 PM')
    expect(wrapper.text()).not.toContain('checks.session_variances')
    expect(wrapper.classes()).toContain('finance-followup--clear')
  })

  it('never displays ready or closed after a failed read or during loading', async () => {
    const wrapper = shallowMount(FinanceFollowUp, {
      props: { closing: { summary: null, loading: true, error: false } },
      global,
    })
    expect(wrapper.findComponent(TableStatus).exists()).toBe(false)
    expect(wrapper.find('button').exists()).toBe(false)
    expect(wrapper.find('[role="status"]').exists()).toBe(true)
    await wrapper.setProps({ closing: { summary: null, loading: false, error: true } })
    expect(wrapper.findComponent(TableStatus).exists()).toBe(false)
    expect(wrapper.find('[role="alert"]').exists()).toBe(true)
    wrapper.findComponent({ name: 'VBtn' }).vm.$emit('click')
    expect(wrapper.emitted('retryClosing')).toEqual([[]])
  })

  it('keeps ready and cleared items accessible below the outstanding work', async () => {
    const wrapper = shallowMount(FinanceFollowUp, {
      props: {
        settlements: { summary, loading: false, error: false },
        closing: { summary: closingReview(), loading: false, error: false },
      },
      global,
    })
    const quiet = wrapper.get('.finance-followup__list--quiet')
    expect(quiet.findAll('button')).toHaveLength(1)
    expect(quiet.get('button').attributes('aria-label')).toBe('dailyfinance::day_closings.title')
    expect(quiet.text()).not.toContain('followup.pending')
    await quiet.get('button').trigger('click')
    expect(wrapper.emitted('reviewClosing')).toEqual([[]])

    await wrapper.setProps({
      settlements: {
        summary: { ...summary, open_count: 0, pending_count: 0, partial_count: 0, balances: [] },
        loading: false,
        error: false,
      },
    })
    expect(wrapper.findAll('.finance-followup__list')).toHaveLength(1)
    expect(wrapper.findAll('.finance-followup__list--quiet button')).toHaveLength(2)
    await wrapper
      .get('button[aria-label="dailyfinance::payment_settlements.followup.review"]')
      .trigger('click')
    expect(wrapper.emitted('reviewSettlements')).toEqual([[]])
    expect(wrapper.classes()).not.toContain('finance-followup--clear')
  })

  it('preserves the quiet loading layout without showing stale readiness and brings errors into attention', async () => {
    const state = { summary: closingReview(), loading: false, error: false }
    const wrapper = shallowMount(FinanceFollowUp, { props: { closing: state }, global })
    expect(wrapper.find('.finance-followup__list--quiet').exists()).toBe(true)
    await wrapper.setProps({ closing: { ...state, loading: true } })
    expect(wrapper.find('.finance-followup__list--quiet').exists()).toBe(true)
    expect(wrapper.find('[role="status"]').exists()).toBe(true)
    expect(wrapper.findComponent(TableStatus).exists()).toBe(false)
    await wrapper.setProps({ closing: { ...state, error: true } })
    expect(wrapper.find('.finance-followup__list--quiet').exists()).toBe(false)
    expect(wrapper.find('[role="alert"]').exists()).toBe(true)
    expect(wrapper.findComponent(TableStatus).exists()).toBe(false)
  })

  it('loads the initial two follow-up rows and lower status strip without reporting balances or readiness', () => {
    const wrapper = shallowMount(FinanceFollowUp, {
      props: {
        settlements: { summary: null, loading: true, error: false },
        cash: { summary: null, loading: true, error: false },
        closing: { summary: null, loading: true, error: false },
      },
      global,
    })
    const groups = wrapper.findAll('.finance-followup__list')
    expect(groups).toHaveLength(2)
    expect(groups[0]!.findAll('[role="status"]')).toHaveLength(2)
    expect(groups[1]!.findAll('[role="status"]')).toHaveLength(2)
    expect(groups[1]!.classes()).toContain('finance-followup__list--quiet')
    expect(groups[1]!.find('.finance-followup__outcome').exists()).toBe(false)
    expect(wrapper.find('button').exists()).toBe(false)
    expect(wrapper.find('bdi').exists()).toBe(false)
    expect(wrapper.findComponent(TableStatus).exists()).toBe(false)
    expect(wrapper.classes()).not.toContain('finance-followup--clear')
  })
})
