import { describe, expect, it, vi } from 'vitest'
import {
  useOfflinePosQueue,
  type OfflineQueueStorage,
} from '@/modules/pos/composables/useOfflinePosQueue.ts'

function createMemoryStorage(): OfflineQueueStorage {
  const store = new Map<string, string>()

  return {
    getItem: (key) => store.get(key) ?? null,
    setItem: (key, value) => {
      store.set(key, value)
    },
  }
}

describe('useOfflinePosQueue', () => {
  it('enqueues offline orders with a client_uuid and flushes synced items', async () => {
    const storage = createMemoryStorage()
    const offline = useOfflinePosQueue(1, storage)

    const queued = offline.enqueue({
      offline_reference: 'OFF-R1-001',
      payment_method: 'cash',
      total: '150.00',
      items: [{ product_id: 1, quantity: 2, unit_price: '75.00' }],
    })

    expect(queued.client_uuid).toBeTruthy()
    expect(offline.pendingCount.value).toBe(1)

    const mockSync = vi.fn().mockResolvedValue({
      results: [
        {
          client_uuid: queued.client_uuid,
          status: 'synced',
          receipt_id: 99,
          needs_stock_reconciliation: false,
        },
      ],
    })

    const results = await offline.flush(mockSync)

    expect(mockSync).toHaveBeenCalledTimes(1)
    expect(results).toHaveLength(1)
    expect(offline.pendingCount.value).toBe(0)
  })
})
