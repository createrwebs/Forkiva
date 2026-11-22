import { defineComponent } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useClosingFollowUp } from './useClosingFollowUp'
import type { DayClosingReview } from '../contracts/dayClosing'

const api = vi.hoisted(() => ({ reviewClosing: vi.fn() }))
vi.mock('../api/dayClosing.api', () => api)

function review(branchId = 1, date = '2026-10-13'): DayClosingReview {
  return {
    date_formatted: date,
    summary: {
      meta: {
        branch_id: branchId,
        date,
        today: '2026-10-13',
        timezone: 'Asia/Amman',
        currency: 'JOD',
        generated_at: '',
        branch_locked: false,
      },
      branches: [{ id: branchId, name: 'Main' }],
      permissions: {
        sales: true,
        payments: true,
        sessions: true,
        movements: true,
        expenses: false,
      },
      sales: null,
      payments: null,
      movements: null,
      sessions: null,
      expenses: null,
    },
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
    blockers: [],
    warnings: [],
    can_close: true,
    review_hash: 'review-hash',
    closing: null,
  }
}

function render(enabled = true) {
  let state!: ReturnType<typeof useClosingFollowUp>
  const wrapper = mount(
    defineComponent({
      props: {
        branchId: { type: Number, default: 1 },
        date: { type: String, default: '2026-10-13' },
        enabled: { type: Boolean, default: enabled },
      },
      setup(props) {
        state = useClosingFollowUp(
          () => props.branchId,
          () => props.date,
          () => props.enabled,
        )
        return () => null
      },
    }),
  )
  return {
    wrapper,
    get state() {
      return state
    },
  }
}

beforeEach(() => {
  vi.clearAllMocks()
  api.reviewClosing.mockImplementation(async (branchId: number, date: string) => ({
    data: { body: review(branchId, date) },
  }))
})

describe('selected day closing follow-up', () => {
  it('waits for branch, day and source permissions and clears restricted data', async () => {
    const { wrapper, state } = render(false)
    await flushPromises()
    expect(api.reviewClosing).not.toHaveBeenCalled()
    await wrapper.setProps({ enabled: true, date: '' })
    expect(api.reviewClosing).not.toHaveBeenCalled()
    await wrapper.setProps({ date: '2026-10-13', branchId: 0 })
    expect(api.reviewClosing).not.toHaveBeenCalled()
    await wrapper.setProps({ branchId: 1 })
    await flushPromises()
    expect(state.summary.value?.summary.meta.date).toBe('2026-10-13')
    await wrapper.setProps({ enabled: false })
    state.acceptReview(review())
    expect(state.summary.value).toBeNull()
    expect(api.reviewClosing.mock.calls[0]![2].aborted).toBe(true)
    wrapper.unmount()
  })

  it.each([{ branchId: 2 }, { date: '2026-10-12' }])(
    'ignores an earlier response when the selected context changes to %o',
    async (context) => {
      let resolve!: (value: unknown) => void
      api.reviewClosing.mockReturnValueOnce(
        new Promise((done) => {
          resolve = done
        }),
      )
      const { wrapper, state } = render()
      await wrapper.setProps(context)
      await flushPromises()
      resolve({ data: { body: review() } })
      await flushPromises()
      expect(state.summary.value?.summary.meta).toMatchObject({
        branch_id: context.branchId ?? 1,
        date: context.date ?? '2026-10-13',
      })
      expect(api.reviewClosing.mock.calls[0]![2].aborted).toBe(true)
      wrapper.unmount()
      expect(api.reviewClosing.mock.calls[1]![2].aborted).toBe(true)
    },
  )

  it('clears a previous ready review on failure and allows retry', async () => {
    const { wrapper, state } = render()
    await flushPromises()
    api.reviewClosing.mockRejectedValueOnce(new Error('Disconnected'))
    await state.load()
    expect(state.summary.value).toBeNull()
    expect(state.loading.value).toBe(false)
    expect(state.error.value).toBe(true)
    await state.load()
    expect(state.error.value).toBe(false)
    expect(state.summary.value?.blockers).toEqual([])
    wrapper.unmount()
  })

  it('accepts only a matching successful drawer review and cancels an older read', async () => {
    let resolve!: (value: unknown) => void
    api.reviewClosing.mockReturnValueOnce(
      new Promise((done) => {
        resolve = done
      }),
    )
    const { wrapper, state } = render()
    state.acceptReview(review(2))
    state.acceptReview(review(1, '2026-10-12'))
    expect(state.summary.value).toBeNull()
    expect(state.loading.value).toBe(true)
    const updated = review()
    updated.blockers = ['open_sessions']
    updated.can_close = false
    state.acceptReview(updated)
    expect(api.reviewClosing.mock.calls[0]![2].aborted).toBe(true)
    expect(state.loading.value).toBe(false)
    resolve({ data: { body: review() } })
    await flushPromises()
    expect(state.summary.value?.blockers).toEqual(['open_sessions'])
    wrapper.unmount()
  })
})
