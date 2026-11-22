import { defineComponent } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useHandoverFollowUpSessions } from './useHandoverFollowUpSessions'
import type { CashHandoverFollowUpKind } from '../contracts/cashHandover'

const api = vi.hoisted(() => ({ handoverFollowUpSessions: vi.fn() }))
vi.mock('../api/cashHandover.api', () => api)

function render() {
  let state!: ReturnType<typeof useHandoverFollowUpSessions>
  const wrapper = mount(
    defineComponent({
      props: {
        branch: { type: Number, default: 1 },
        kind: { type: String, default: 'unhanded' },
        enabled: { type: Boolean, default: true },
      },
      setup(props) {
        state = useHandoverFollowUpSessions(
          () => props.branch,
          () => props.kind as CashHandoverFollowUpKind,
          () => props.enabled,
        )
        return () => null
      },
    }),
  )
  return { wrapper, state }
}
beforeEach(() => {
  vi.clearAllMocks()
  api.handoverFollowUpSessions.mockImplementation(
    async (branch: number, kind: string, page: number) => ({
      data: {
        body: {
          data: [{ id: branch, kind }],
          pagination: { current_page: page, last_page: 3, total: 21 },
        },
      },
    }),
  )
})

describe('cash follow-up source sessions', () => {
  it('paginates the selected branch and filter and resets on context changes', async () => {
    const { wrapper, state } = render()
    await flushPromises()
    await state.load(3)
    expect(
      api.handoverFollowUpSessions.mock.calls[
        api.handoverFollowUpSessions.mock.calls.length - 1
      ]!.slice(0, 3),
    ).toEqual([1, 'unhanded', 3])
    expect(state.result.value?.pagination.current_page).toBe(3)
    await wrapper.setProps({ kind: 'pending' })
    await flushPromises()
    expect(
      api.handoverFollowUpSessions.mock.calls[
        api.handoverFollowUpSessions.mock.calls.length - 1
      ]!.slice(0, 3),
    ).toEqual([1, 'pending', 1])
    wrapper.unmount()
  })

  it('ignores stale pages and clears records on failure or permission loss', async () => {
    let resolve!: (value: unknown) => void
    api.handoverFollowUpSessions.mockReturnValueOnce(
      new Promise((done) => {
        resolve = done
      }),
    )
    const { wrapper, state } = render()
    await state.load(2)
    resolve({ data: { body: { data: [{ id: 99 }], pagination: { current_page: 1 } } } })
    await flushPromises()
    expect(state.result.value?.pagination.current_page).toBe(2)
    expect(api.handoverFollowUpSessions.mock.calls[0]![3].aborted).toBe(true)
    api.handoverFollowUpSessions.mockRejectedValueOnce(new Error('Disconnected'))
    await state.load()
    expect(state.result.value).toBeNull()
    expect(state.error.value).toBe(true)
    await state.load()
    expect(state.error.value).toBe(false)
    const calls = api.handoverFollowUpSessions.mock.calls.length
    await wrapper.setProps({ enabled: false })
    expect(state.result.value).toBeNull()
    expect(api.handoverFollowUpSessions).toHaveBeenCalledTimes(calls)
    wrapper.unmount()
  })
})
