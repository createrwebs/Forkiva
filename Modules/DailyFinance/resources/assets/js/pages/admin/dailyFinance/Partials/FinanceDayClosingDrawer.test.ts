import { defineComponent, ref } from 'vue'
import { flushPromises, shallowMount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import FinanceDayClosingDrawer from './FinanceDayClosingDrawer.vue'
import type {
  DayClosing,
  DayClosingHistory,
  DayClosingReview,
} from '@/modules/dailyfinance/contracts/dayClosing'

const mocks = vi.hoisted(() => ({ save: vi.fn(), load: vi.fn(), refresh: vi.fn() }))
const review = ref<DayClosingReview | null>(null)
const history = ref<DayClosingHistory | null>(null)
const error = ref('')
const loading = ref(false)
const refreshing = ref(false)
const saving = ref(false)
vi.mock('@/modules/dailyfinance/composables/useDayClosings', () => ({
  useDayClosings: () => ({
    review,
    history,
    loading,
    refreshing,
    saving,
    uncertain: ref(false),
    error,
    errors: ref({}),
    ...mocks,
  }),
}))
vi.mock('@/modules/auth/composables/auth', () => ({ useAuth: () => ({ can: () => true }) }))
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))

function render() {
  return shallowMount(FinanceDayClosingDrawer, {
    props: { branchId: 1, date: '2026-10-12', branchName: 'Main' },
    global: {
      renderStubDefaultSlot: true,
      stubs: {
        VDialog: { props: ['modelValue'], template: '<div v-if="modelValue"><slot /></div>' },
        FinanceDrawer: defineComponent({
          name: 'FinanceDrawer',
          emits: ['close'],
          setup(_props, { emit, expose }) {
            expose({ close: () => emit('close') })
          },
          template: '<section><slot name="header-end" /><slot /></section>',
        }),
        FinanceClosingSummary: true,
        VCard: true,
        VCardText: true,
        VForm: true,
        VBtn: true,
        VPagination: true,
        VIcon: true,
        VTabs: true,
        VTab: true,
        VAlert: true,
        VTextarea: true,
        VCheckbox: true,
      },
    },
  })
}

beforeEach(() => {
  vi.clearAllMocks()
  error.value = ''
  loading.value = false
  refreshing.value = false
  saving.value = false
  history.value = null
  review.value = {
    date_formatted: '2026-10-12',
    summary: { sessions: null } as DayClosingReview['summary'],
    checks: {
      open_sessions: 0,
      closed_sessions: 0,
      unknown_counts: 0,
      pending_handovers: 0,
      session_variances: 1,
      receipt_variances: 0,
      unpaid_orders: 0,
      excluded_currencies: 0,
      future_day: 0,
    },
    blockers: [],
    warnings: ['session_variances'],
    review_hash: 'abc',
    can_close: true,
    closing: null,
  }
})

describe('day closing confirmation', () => {
  it('shares only successful review reads with the page follow-up, including a saved closing', async () => {
    loading.value = true
    const wrapper = render()
    expect(wrapper.emitted('reviewed')).toBeUndefined()
    loading.value = false
    await flushPromises()
    expect(wrapper.emitted('reviewed')).toHaveLength(1)
    loading.value = true
    error.value = 'Read failed'
    loading.value = false
    await flushPromises()
    expect(wrapper.emitted('reviewed')).toHaveLength(1)
    saving.value = true
    error.value = ''
    await flushPromises()
    review.value!.closing = {
      id: 21,
      reference: 'DC-0021',
      created_by: { id: 9, name: 'Sara' },
      created_at: '2026-10-13 04:30 PM',
    } as DayClosing
    saving.value = false
    await flushPromises()
    expect(wrapper.emitted('reviewed')).toHaveLength(2)
    const updates = wrapper.emitted('reviewed')!
    const latest = updates[updates.length - 1]![0] as DayClosingReview
    expect(latest.closing?.reference).toBe('DC-0021')
    wrapper.unmount()
  })
  it('keeps the summary visible and disables other actions while the refresh button loads', async () => {
    const wrapper = render()
    const refreshButton = wrapper
      .findAllComponents({ name: 'VBtn' })
      .find((button) => button.text().includes('day_closings.refresh'))!
    refreshButton.vm.$emit('click')
    expect(mocks.refresh).toHaveBeenCalledTimes(1)
    loading.value = true
    refreshing.value = true
    await flushPromises()
    expect(wrapper.findComponent({ name: 'FinanceClosingSummary' }).exists()).toBe(true)
    expect(wrapper.find('[role="status"]').exists()).toBe(false)
    expect(refreshButton.attributes('loading')).toBe('true')
    expect(wrapper.findComponent({ name: 'VTabs' }).attributes('disabled')).toBe('true')
    expect(wrapper.findComponent({ name: 'FinanceDrawer' }).attributes('persistent')).toBe('true')
    expect(
      wrapper
        .findAll('button.closing__check')
        .every((button) => button.attributes('disabled') !== undefined),
    ).toBe(true)
    const closeButton = wrapper
      .findAllComponents({ name: 'VBtn' })
      .find((button) => button.text().includes('day_closings.close'))!
    expect(closeButton.attributes('disabled')).toBe('true')
    await wrapper.find('button.closing__check').trigger('click')
    expect(wrapper.emitted('sessions')).toBeUndefined()
    wrapper.unmount()
  })

  it('reviews a saved history record in a dialog and returns to the same history', async () => {
    const record = {
      id: 12,
      reference: 'DC-0012',
      branch_id: 1,
      date: '2026-10-11',
      date_formatted: '2026-10-11',
      summary: review.value!.summary,
    } as DayClosing
    history.value = { data: [record], pagination: { current_page: 2, last_page: 3, total: 21 } }
    const wrapper = render()
    wrapper.findComponent({ name: 'VTabs' }).vm.$emit('update:modelValue', 'history')
    await flushPromises()
    const list = wrapper.findComponent({ name: 'FinanceClosingHistory' })
    list.vm.$emit('review', record)
    await flushPromises()
    const dialog = wrapper.findComponent({ name: 'FinanceClosingReviewDialog' })
    expect(dialog.props('record')).toEqual(record)
    expect(wrapper.findComponent({ name: 'FinanceDrawer' }).attributes('persistent')).toBe('true')
    dialog.vm.$emit('close')
    await flushPromises()
    expect(wrapper.findComponent({ name: 'FinanceClosingReviewDialog' }).exists()).toBe(false)
    expect(list.props('history').pagination.current_page).toBe(2)
    expect(mocks.load).toHaveBeenCalledTimes(1)
    expect(mocks.save).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('requires a difference explanation and explicit acknowledgement before saving', async () => {
    const wrapper = render()
    const action = wrapper
      .findAllComponents({ name: 'VBtn' })
      .find((button) => button.text().includes('day_closings.close'))!
    action.vm.$emit('click')
    await flushPromises()
    await wrapper.find('v-form-stub').trigger('submit')
    expect(mocks.save).not.toHaveBeenCalled()
    wrapper.findComponent({ name: 'VCheckbox' }).vm.$emit('update:modelValue', true)
    await wrapper.find('v-form-stub').trigger('submit')
    expect(mocks.save).not.toHaveBeenCalled()
    wrapper.findComponent({ name: 'VTextarea' }).vm.$emit('update:modelValue', 'Reviewed shortage')
    await flushPromises()
    await wrapper.find('v-form-stub').trigger('submit')
    expect(mocks.save).not.toHaveBeenCalled()
    wrapper.findComponent({ name: 'VCheckbox' }).vm.$emit('update:modelValue', true)
    mocks.save.mockResolvedValue(true)
    await wrapper.find('v-form-stub').trigger('submit')
    expect(mocks.save).toHaveBeenCalledWith('Reviewed shortage')
    expect(wrapper.emitted('saved')).toHaveLength(1)
    wrapper.unmount()
  })

  it('disables closing when blocked and opens source review directly', async () => {
    review.value!.can_close = false
    review.value!.blockers = ['pending_handovers']
    review.value!.checks.pending_handovers = 1
    const wrapper = render()
    const action = wrapper
      .findAllComponents({ name: 'VBtn' })
      .find((button) => button.text().includes('day_closings.close'))!
    expect(action.attributes('disabled')).toBe('true')
    await wrapper.findAll('button.closing__check')[1]!.trigger('click')
    expect(wrapper.emitted('handovers')).toHaveLength(1)
    wrapper.unmount()
  })
})
