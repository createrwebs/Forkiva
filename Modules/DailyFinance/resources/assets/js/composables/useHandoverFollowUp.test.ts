import { defineComponent } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useHandoverFollowUp } from './useHandoverFollowUp'

const api = vi.hoisted(() => ({ handoverFollowUp: vi.fn() }))
vi.mock('../api/cashHandover.api', () => api)

function render(enabled = true) {
  let state!: ReturnType<typeof useHandoverFollowUp>
  const wrapper = mount(
    defineComponent({
      props: {
        branchId: { type: Number, default: 1 },
        enabled: { type: Boolean, default: enabled },
      },
      setup(props) {
        state = useHandoverFollowUp(
          () => props.branchId,
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
  api.handoverFollowUp.mockImplementation(async (branchId: number) => ({
    data: { body: { branch_id: branchId, unhanded_sessions_count: 1, balances: [] } },
  }))
})

describe('current cash follow-up', () => {
  it('does not request restricted data and clears it when permission is lost', async () => {
    const { wrapper, state } = render(false)
    await flushPromises()
    expect(api.handoverFollowUp).not.toHaveBeenCalled()
    await wrapper.setProps({ enabled: true })
    await flushPromises()
    expect(state.summary.value?.unhanded_sessions_count).toBe(1)
    await wrapper.setProps({ enabled: false })
    expect(state.summary.value).toBeNull()
    expect(api.handoverFollowUp.mock.calls[0]![1].aborted).toBe(true)
    wrapper.unmount()
  })

  it('aborts and ignores earlier branch responses', async () => {
    let resolve!: (value: unknown) => void
    api.handoverFollowUp.mockReturnValueOnce(
      new Promise((done) => {
        resolve = done
      }),
    )
    const { wrapper, state } = render()
    await wrapper.setProps({ branchId: 2 })
    await flushPromises()
    resolve({ data: { body: { branch_id: 1, unhanded_sessions_count: 99 } } })
    await flushPromises()
    expect(state.summary.value?.branch_id).toBe(2)
    expect(api.handoverFollowUp.mock.calls[0]![1].aborted).toBe(true)
    wrapper.unmount()
    expect(api.handoverFollowUp.mock.calls[1]![1].aborted).toBe(true)
  })

  it('does not retain stale amounts after an error and reloads after retry', async () => {
    const { wrapper, state } = render()
    await flushPromises()
    api.handoverFollowUp.mockRejectedValueOnce(new Error('Disconnected'))
    await state.load()
    expect(state.summary.value).toBeNull()
    expect(state.error.value).toBe(true)
    await state.load()
    expect(state.error.value).toBe(false)
    expect(state.summary.value?.unhanded_sessions_count).toBe(1)
    wrapper.unmount()
  })
})
