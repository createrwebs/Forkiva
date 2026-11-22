import { defineComponent, h } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useDailyFinance } from './useDailyFinance'

const api = vi.hoisted(() => ({ dailyFinance: vi.fn() }))
vi.mock('@/modules/dailyfinance/api/dailyFinance.api', () => api)

function setup() {
  let state!: ReturnType<typeof useDailyFinance>
  const wrapper = mount(
    defineComponent({
      setup() {
        state = useDailyFinance()
        return () => h('div')
      },
    }),
  )
  return { state, wrapper }
}

beforeEach(() => vi.resetAllMocks())

describe('daily finance loading', () => {
  it('does not show another branch data while loading and ignores an older response', async () => {
    const resolve: ((value: unknown) => void)[] = []
    api.dailyFinance.mockImplementation(() => new Promise((done) => resolve.push(done)))
    const { state, wrapper } = setup()
    const first = state.load({ branch_id: 1 })
    const second = state.load({ branch_id: 2 })
    expect(api.dailyFinance.mock.calls[0]?.[1].aborted).toBe(true)
    expect(state.summary.value).toBeNull()
    resolve[1]!({ data: { body: { meta: { branch_id: 2 } } } })
    await second
    resolve[0]!({ data: { body: { meta: { branch_id: 1 } } } })
    await first
    expect(state.summary.value?.meta.branch_id).toBe(2)
    expect(state.loading.value).toBe(false)
    wrapper.unmount()
  })

  it('clears stale totals on failure and recovers on retry', async () => {
    api.dailyFinance.mockResolvedValueOnce({ data: { body: { meta: { branch_id: 1 } } } })
    const { state, wrapper } = setup()
    await state.load()
    api.dailyFinance.mockRejectedValueOnce({ response: { status: 403 } })
    await state.load()
    expect(state.error.value).toBe(403)
    expect(state.summary.value).toBeNull()
    api.dailyFinance.mockResolvedValueOnce({ data: { body: { meta: { branch_id: 2 } } } })
    await state.load({ branch_id: 2 })
    expect(state.error.value).toBeNull()
    expect(state.summary.value?.meta.branch_id).toBe(2)
    wrapper.unmount()
  })

  it('aborts and ignores updates after navigation away', async () => {
    let resolve!: (value: unknown) => void
    api.dailyFinance.mockReturnValue(
      new Promise((done) => {
        resolve = done
      }),
    )
    const { state, wrapper } = setup()
    void state.load()
    wrapper.unmount()
    expect(api.dailyFinance.mock.calls[0]?.[1].aborted).toBe(true)
    resolve({ data: { body: {} } })
    await flushPromises()
    expect(state.summary.value).toBeNull()
  })
})
