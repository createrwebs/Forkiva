import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent } from 'vue'
import { useKitchenBoard } from './useKitchenBoard'
const { getConfiguration, getOrders, poll, invalidate } = vi.hoisted(() => ({
  getConfiguration: vi.fn(),
  getOrders: vi.fn(),
  poll: { fetchData: null as any },
  invalidate: vi.fn(),
}))
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))
vi.mock('vue-toastification', () => ({ useToast: () => ({ error: vi.fn() }) }))
vi.mock('@/modules/pos/composables/kitchenViewer', () => ({
  useKitchenViewer: () => ({ getConfiguration, getOrders }),
}))
vi.mock('@/modules/pos/composables/kitchenSound', () => ({
  useKitchenSound: () => ({ playNewOrder: vi.fn(), playOrderUpdated: vi.fn() }),
}))
vi.mock('@/modules/pos/composables/kitchenAutoReload', () => ({
  useKitchenAutoReload: (options: any) => {
    poll.fetchData = options.fetchData
    return { invalidate, isPaused: { value: false } }
  },
}))
const ticket = {
  id: 1,
  order_number: '01',
  reference_no: 'R1',
  products: [],
  status: { id: 'confirmed' },
  type: { id: 'takeaway' },
}
const response = (orders: unknown[]) => ({ data: { body: { orders, last_updated_at: 'v1' } } })
function harness() {
  let board!: ReturnType<typeof useKitchenBoard>
  const wrapper = mount(
    defineComponent({
      setup() {
        board = useKitchenBoard()
        return () => null
      },
    }),
  )
  return { board, wrapper }
}
beforeEach(() => {
  vi.clearAllMocks()
  getConfiguration.mockImplementation(async (id = 1) => ({
    data: {
      body: {
        branch_id: id,
        branches: [
          { id: 1, name: 'One' },
          { id: 2, name: 'Two' },
        ],
        order_types: [{ id: 'takeaway' }],
        order_statuses: [],
        settings: {},
      },
    },
  }))
  getOrders.mockResolvedValue(response([ticket]))
})
describe('Kitchen board response ordering', () => {
  it('hides ready and completed tickets when visibility is not explicitly enabled', async () => {
    const { board, wrapper } = harness()
    await flushPromises()

    expect(board.keepFinishedOrders.value).toBe(false)

    wrapper.unmount()
  })

  it('removes a ticket immediately after it becomes ready when finished visibility is off', async () => {
    const { board, wrapper } = harness()
    await flushPromises()
    board.meta.value.settings = { served_orders_visibility: { enabled: false, minutes: 5 } }
    board.applyOrderUpdateResponse({ order: { ...ticket, status: { id: 'ready' } } })
    expect(board.orders.value).toHaveLength(0)
    getOrders.mockResolvedValue(response([{ ...ticket, status: { id: 'served' } }, ticket]))
    await board.refresh()
    expect(board.orders.value).toEqual([ticket])
    wrapper.unmount()
  })
  it('loads the initial branch only once and ignores an old refresh after a status update', async () => {
    const { board, wrapper } = harness()
    await flushPromises()
    board.meta.value.settings = { served_orders_visibility: { enabled: true, minutes: 5 } }
    expect(getConfiguration).toHaveBeenCalledTimes(1)
    expect(getOrders).toHaveBeenCalledTimes(1)
    let resolve!: (value: unknown) => void
    getOrders.mockReturnValueOnce(
      new Promise((r) => {
        resolve = r
      }),
    )
    const refresh = board.refresh()
    board.setProcessing(true)
    board.applyOrderUpdateResponse({
      order: { ...ticket, status: { id: 'ready' } },
      last_updated_at: 'v2',
    })
    board.setProcessing(false)
    resolve(response([ticket]))
    await refresh
    expect(board.orders.value[0]?.status.id).toBe('ready')
    wrapper.unmount()
  })
  it('forces a full poll after an item update to include concurrent ticket changes', async () => {
    const { board, wrapper } = harness()
    await flushPromises()
    board.applyOrderUpdateResponse({ order: ticket, last_updated_at: 'v2' })
    await poll.fetchData({ isCurrent: () => true })
    expect(getOrders).toHaveBeenLastCalledWith(expect.objectContaining({ branchId: 1 }), null)
    wrapper.unmount()
  })
  it('recovers a sync failure only after receiving a fresh board response', async () => {
    const { board, wrapper } = harness()
    await flushPromises()
    board.onActionFailed()
    expect(board.syncError.value).toBe(true)
    expect(board.orders.value).toHaveLength(1)
    await board.refresh()
    expect(board.syncError.value).toBe(false)
    expect(board.lastSyncedAt.value).not.toBeNull()
    wrapper.unmount()
  })
})
