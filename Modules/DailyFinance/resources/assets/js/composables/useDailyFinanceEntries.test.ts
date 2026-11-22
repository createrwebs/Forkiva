import { defineComponent, h } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useDailyFinanceEntries } from './useDailyFinanceEntries'

const api = vi.hoisted(() => ({ entries: vi.fn() }))
vi.mock('@/modules/dailyfinance/api/dailyFinance.api', () => api)

function setup() {
  let state!: ReturnType<typeof useDailyFinanceEntries>
  const wrapper = mount(
    defineComponent({
      setup() {
        state = useDailyFinanceEntries()
        return () => h('div')
      },
    }),
  )
  return { state, wrapper }
}

beforeEach(() => vi.resetAllMocks())

describe('daily finance entry loading', () => {
  it('does not show another branch data while loading and ignores an older response', async () => {
    const resolve: ((value: unknown) => void)[] = []
    api.entries.mockImplementation(() => new Promise((done) => resolve.push(done)))
    const { state, wrapper } = setup()
    const first = state.load({ branch_id: 1, date: '2026-10-11', kind: 'sales', page: 1 })
    const second = state.load({ branch_id: 2, date: '2026-10-11', kind: 'sales', page: 1 })
    expect(api.entries.mock.calls[0]?.[1].aborted).toBe(true)
    expect(state.result.value).toBeNull()
    resolve[1]!({
      data: {
        body: { data: [{ id: 2 }], pagination: { current_page: 1, last_page: 1, total: 1 } },
      },
    })
    await second
    resolve[0]!({
      data: {
        body: { data: [{ id: 1 }], pagination: { current_page: 1, last_page: 1, total: 1 } },
      },
    })
    await first
    expect(state.result.value?.data[0]?.id).toBe(2)
    expect(state.loading.value).toBe(false)
    wrapper.unmount()
  })

  it('clears stale totals on failure and recovers on retry', async () => {
    api.entries.mockResolvedValueOnce({
      data: {
        body: { data: [{ id: 1 }], pagination: { current_page: 1, last_page: 1, total: 1 } },
      },
    })
    const { state, wrapper } = setup()
    await state.load({ branch_id: 1, date: '2026-10-11', kind: 'sales', page: 1 })
    api.entries.mockRejectedValueOnce({ response: { status: 403 } })
    await state.load({ branch_id: 1, date: '2026-10-11', kind: 'sales', page: 1 })
    expect(state.error.value).toBe(403)
    expect(state.result.value).toBeNull()
    api.entries.mockResolvedValueOnce({
      data: {
        body: { data: [{ id: 2 }], pagination: { current_page: 1, last_page: 1, total: 1 } },
      },
    })
    await state.load({ branch_id: 2, date: '2026-10-11', kind: 'sales', page: 1 })
    expect(state.error.value).toBeNull()
    expect(state.result.value?.data[0]?.id).toBe(2)
    wrapper.unmount()
  })

  it('aborts and ignores updates after navigation away', async () => {
    let resolve!: (value: unknown) => void
    api.entries.mockReturnValue(
      new Promise((done) => {
        resolve = done
      }),
    )
    const { state, wrapper } = setup()
    void state.load({ branch_id: 1, date: '2026-10-11', kind: 'sales', page: 1 })
    wrapper.unmount()
    expect(api.entries.mock.calls[0]?.[1].aborted).toBe(true)
    resolve({ data: { body: {} } })
    await flushPromises()
    expect(state.result.value).toBeNull()
  })
})
