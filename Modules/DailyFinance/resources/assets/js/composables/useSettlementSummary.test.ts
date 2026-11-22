import { defineComponent } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useSettlementSummary } from './useSettlementSummary'

const api = vi.hoisted(() => ({ settlementSummary: vi.fn() }))
vi.mock('../api/paymentSettlement.api', () => api)

function render(enabled = true) {
  let state!: ReturnType<typeof useSettlementSummary>
  const wrapper = mount(
    defineComponent({
      props: {
        branchId: { type: Number, default: 1 },
        enabled: { type: Boolean, default: enabled },
      },
      setup(props) {
        state = useSettlementSummary(
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
  api.settlementSummary.mockImplementation(async (branchId: number) => ({
    data: { body: { branch_id: branchId, open_count: 1, balances: [] } },
  }))
})

describe('current settlement summary', () => {
  it('does not request restricted data and clears it when permission is lost', async () => {
    const { wrapper, state } = render(false)
    await flushPromises()
    expect(api.settlementSummary).not.toHaveBeenCalled()
    await wrapper.setProps({ enabled: true })
    await flushPromises()
    expect(state.summary.value?.open_count).toBe(1)
    await wrapper.setProps({ enabled: false })
    expect(state.summary.value).toBeNull()
    expect(api.settlementSummary.mock.calls[0]![1].aborted).toBe(true)
    wrapper.unmount()
  })

  it('aborts and ignores earlier branch responses', async () => {
    let resolve!: (value: unknown) => void
    api.settlementSummary.mockReturnValueOnce(
      new Promise((done) => {
        resolve = done
      }),
    )
    const { wrapper, state } = render()
    await wrapper.setProps({ branchId: 2 })
    await flushPromises()
    resolve({ data: { body: { branch_id: 1, open_count: 99 } } })
    await flushPromises()
    expect(state.summary.value?.branch_id).toBe(2)
    expect(api.settlementSummary.mock.calls[0]![1].aborted).toBe(true)
    wrapper.unmount()
    expect(api.settlementSummary.mock.calls[1]![1].aborted).toBe(true)
  })

  it('does not retain stale amounts after an error and reloads after retry', async () => {
    const { wrapper, state } = render()
    await flushPromises()
    api.settlementSummary.mockRejectedValueOnce(new Error('Disconnected'))
    await state.load()
    expect(state.summary.value).toBeNull()
    expect(state.error.value).toBe(true)
    await state.load()
    expect(state.error.value).toBe(false)
    expect(state.summary.value?.open_count).toBe(1)
    wrapper.unmount()
  })
})
