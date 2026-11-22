import { defineComponent } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { usePaymentSettlements } from './usePaymentSettlements'
import type { SettlementStatusFilter } from '../contracts/paymentSettlement'

const api = vi.hoisted(() => ({ settlementMeta: vi.fn(), settlementHistory: vi.fn() }))
vi.mock('../api/paymentSettlement.api', () => api)
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))

function render(initialStatus: SettlementStatusFilter | null = null) {
  let state!: ReturnType<typeof usePaymentSettlements>
  const wrapper = mount(
    defineComponent({
      props: { branchId: { type: Number, default: 1 } },
      setup(props) {
        state = usePaymentSettlements(() => props.branchId, initialStatus)
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
  api.settlementMeta.mockImplementation(async (id: number) => ({
    data: { body: { branch_id: id, accounts: [] } },
  }))
  api.settlementHistory.mockImplementation(
    async (filters: { branch_id: number; page: number }) => ({
      data: {
        body: {
          data: [{ id: filters.branch_id }],
          pagination: { current_page: filters.page, last_page: 2, total: 12 },
        },
      },
    }),
  )
})

describe('settlement workspace reads', () => {
  it('uses the requested outstanding filter on the first read', async () => {
    const { wrapper } = render('outstanding')
    await flushPromises()
    expect(api.settlementHistory.mock.calls[0]![0]).toMatchObject({
      status: 'outstanding',
      page: 1,
    })
    wrapper.unmount()
  })
  it('discards stale branch responses and aborts them on context changes', async () => {
    let resolve!: (value: unknown) => void
    api.settlementHistory.mockReturnValueOnce(
      new Promise((done) => {
        resolve = done
      }),
    )
    const { wrapper, state } = render()
    await wrapper.setProps({ branchId: 2 })
    await flushPromises()
    resolve({ data: { body: { data: [{ id: 99 }] } } })
    await flushPromises()
    expect(state.history.value?.data[0]?.id).toBe(2)
    expect(state.meta.value?.branch_id).toBe(2)
    expect(api.settlementHistory.mock.calls[0]![1].aborted).toBe(true)
    wrapper.unmount()
    expect(api.settlementHistory.mock.calls[1]![1].aborted).toBe(true)
  })

  it('retains method choices during same-branch loading and clears them when the branch changes', async () => {
    const { wrapper, state } = render()
    await flushPromises()
    let resolve!: (value: unknown) => void
    api.settlementMeta.mockReturnValueOnce(
      new Promise((done) => {
        resolve = done
      }),
    )
    const pending = state.load(2)
    expect(state.loading.value).toBe(true)
    expect(state.meta.value?.branch_id).toBe(1)
    expect(state.history.value).toBeNull()
    resolve({ data: { body: { branch_id: 1, accounts: [] } } })
    await pending
    api.settlementMeta.mockReturnValueOnce(new Promise(() => {}))
    await wrapper.setProps({ branchId: 2 })
    expect(state.meta.value).toBeNull()
    wrapper.unmount()
  })

  it('loads requested pages and clears stale records after a failed filter change', async () => {
    const { wrapper, state } = render()
    await flushPromises()
    await state.load(2)
    expect(state.history.value?.pagination.current_page).toBe(2)
    api.settlementHistory.mockRejectedValueOnce(new Error('Disconnected'))
    state.status.value = 'settled'
    await flushPromises()
    expect(state.history.value).toBeNull()
    expect(state.error.value).toBeTruthy()
    expect(api.settlementHistory.mock.calls[2]![0].status).toBe('settled')
    wrapper.unmount()
  })
})
