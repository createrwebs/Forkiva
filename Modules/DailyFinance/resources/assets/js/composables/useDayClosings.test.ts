import { defineComponent } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useDayClosings } from './useDayClosings'

const api = vi.hoisted(() => ({
  reviewClosing: vi.fn(),
  closingHistory: vi.fn(),
  closeDay: vi.fn(),
}))
vi.mock('../api/dayClosing.api', () => api)
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))

function render() {
  let state!: ReturnType<typeof useDayClosings>
  const wrapper = mount(
    defineComponent({
      props: {
        branchId: { type: Number, default: 1 },
        date: { type: String, default: '2026-10-12' },
      },
      setup(props) {
        state = useDayClosings(() => ({ branchId: props.branchId, date: props.date }))
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
  api.reviewClosing.mockImplementation(async () => ({
    data: { body: { can_close: true, review_hash: 'abc', closing: null } },
  }))
})

describe('day closing requests', () => {
  it('keeps the reviewed figures visible during refresh and blocks saving until it completes', async () => {
    const { state, wrapper } = render()
    await flushPromises()
    const previous = state.review.value
    let resolve!: (value: unknown) => void
    api.reviewClosing.mockReturnValueOnce(
      new Promise((done) => {
        resolve = done
      }),
    )
    const refresh = state.refresh()
    expect(state.review.value).toBe(previous)
    expect(state.refreshing.value).toBe(true)
    expect(state.loading.value).toBe(true)
    expect(await state.save(null)).toBe(false)
    await state.refresh()
    expect(api.reviewClosing).toHaveBeenCalledTimes(2)
    resolve({ data: { body: { can_close: true, review_hash: 'updated', closing: null } } })
    await refresh
    expect(state.review.value?.review_hash).toBe('updated')
    expect(state.refreshing.value).toBe(false)
    expect(state.loading.value).toBe(false)
    wrapper.unmount()
  })

  it('retains the figures after a failed refresh without allowing an unverified closing', async () => {
    const { state, wrapper } = render()
    await flushPromises()
    const previous = state.review.value
    api.reviewClosing.mockRejectedValueOnce(new Error('Connection interrupted'))
    await state.refresh()
    expect(state.review.value).toBe(previous)
    expect(state.review.value?.can_close).toBe(false)
    expect(state.refreshing.value).toBe(false)
    expect(state.error.value).toBeTruthy()
    expect(await state.save(null)).toBe(false)
    wrapper.unmount()
  })

  it('clears retained review when branch changes during a refresh and discards its stale response', async () => {
    const { state, wrapper } = render()
    await flushPromises()
    let resolve!: (value: unknown) => void
    api.reviewClosing.mockReturnValueOnce(
      new Promise((done) => {
        resolve = done
      }),
    )
    const refresh = state.refresh()
    let resolveBranch!: (value: unknown) => void
    api.reviewClosing.mockReturnValueOnce(
      new Promise((done) => {
        resolveBranch = done
      }),
    )
    await wrapper.setProps({ branchId: 2 })
    expect(state.review.value).toBeNull()
    expect(state.refreshing.value).toBe(false)
    resolve({ data: { body: { review_hash: 'old' } } })
    await refresh
    expect(state.review.value).toBeNull()
    expect(state.loading.value).toBe(true)
    resolveBranch({ data: { body: { review_hash: 'branch-2' } } })
    await flushPromises()
    expect(state.review.value?.review_hash).toBe('branch-2')
    wrapper.unmount()
  })

  it('preserves the original reviewed payload and UUID after an uncertain result', async () => {
    const { state, wrapper } = render()
    await flushPromises()
    api.closeDay.mockRejectedValueOnce(new Error('Network interrupted'))
    expect(await state.save('Reviewed cash')).toBe(false)
    const original = api.closeDay.mock.calls[0]![0]
    expect(original.request_key).toMatch(/^[0-9a-f-]{36}$/)
    expect(state.uncertain.value).toBe(true)
    await state.load('history')
    expect(api.closingHistory).not.toHaveBeenCalled()
    api.closeDay.mockResolvedValueOnce({ data: { body: { id: 1, summary: {} } } })
    expect(await state.save('Changed notes')).toBe(true)
    expect(api.closeDay.mock.calls[1]![0]).toEqual(original)
    expect(state.review.value?.can_close).toBe(false)
    wrapper.unmount()
  })

  it('blocks concurrent submissions and marks a stale review as unavailable', async () => {
    const { state, wrapper } = render()
    await flushPromises()
    let reject!: (value: unknown) => void
    api.closeDay.mockReturnValueOnce(
      new Promise((_resolve, fail) => {
        reject = fail
      }),
    )
    const first = state.save(null)
    expect(await state.save(null)).toBe(false)
    expect(api.closeDay).toHaveBeenCalledTimes(1)
    reject({ response: { status: 409, data: { message: 'Refresh the review' } } })
    await first
    expect(state.review.value?.can_close).toBe(false)
    expect(state.uncertain.value).toBe(false)
    expect(await state.save(null)).toBe(false)
    await state.load()
    expect(state.review.value?.can_close).toBe(true)
    wrapper.unmount()
  })

  it('discards stale branch responses and clears previous review while loading', async () => {
    let resolve!: (value: unknown) => void
    api.reviewClosing.mockReturnValueOnce(
      new Promise((done) => {
        resolve = done
      }),
    )
    const { state, wrapper } = render()
    await wrapper.setProps({ branchId: 2 })
    await flushPromises()
    resolve({ data: { body: { review_hash: 'old' } } })
    await flushPromises()
    expect(state.review.value?.review_hash).toBe('abc')
    expect(api.reviewClosing.mock.calls[0]![2].aborted).toBe(true)
    wrapper.unmount()
  })
})
