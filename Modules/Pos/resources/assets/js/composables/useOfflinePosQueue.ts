import { computed, ref } from 'vue'
import {
  syncOfflineOrders,
  type OfflineOrderPayload,
  type OfflineSyncResultItem,
} from '@/modules/pos/api/retailPos.ts'

export interface OfflineQueueStorage {
  getItem(key: string): string | null
  setItem(key: string, value: string): void
}

const STORAGE_KEY = 'forkiva:pos:offline-order-queue'

function resolveDefaultStorage(): OfflineQueueStorage {
  const memory = new Map<string, string>()

  if (
    typeof globalThis !== 'undefined' &&
    typeof globalThis.localStorage?.getItem === 'function' &&
    typeof globalThis.localStorage?.setItem === 'function'
  ) {
    return globalThis.localStorage
  }

  return {
    getItem: (key) => memory.get(key) ?? null,
    setItem: (key, value) => {
      memory.set(key, value)
    },
  }
}

export function useOfflinePosQueue(
  branchId: number,
  storage: OfflineQueueStorage = resolveDefaultStorage(),
) {
  const queue = ref<OfflineOrderPayload[]>(loadQueue())
  const isSyncing = ref(false)
  const lastSyncError = ref<string | null>(null)

  const pendingCount = computed(() => queue.value.length)

  function loadQueue(): OfflineOrderPayload[] {
    try {
      const raw = storage.getItem(STORAGE_KEY)
      return raw ? (JSON.parse(raw) as OfflineOrderPayload[]) : []
    } catch {
      return []
    }
  }

  function persistQueue(): void {
    storage.setItem(STORAGE_KEY, JSON.stringify(queue.value))
  }

  function enqueue(
    order: Omit<OfflineOrderPayload, 'client_uuid'> & { client_uuid?: string },
  ): OfflineOrderPayload {
    const record: OfflineOrderPayload = {
      ...order,
      client_uuid: order.client_uuid || crypto.randomUUID(),
    }

    queue.value = [...queue.value, record]
    persistQueue()

    return record
  }

  async function flush(
    syncFn: typeof syncOfflineOrders = syncOfflineOrders,
  ): Promise<OfflineSyncResultItem[]> {
    if (queue.value.length === 0 || isSyncing.value) {
      return []
    }

    isSyncing.value = true
    lastSyncError.value = null

    try {
      const batch = [...queue.value]
      const { results } = await syncFn(branchId, batch)
      const completedIds = new Set(results.map((item) => item.client_uuid))

      queue.value = queue.value.filter((item) => !completedIds.has(item.client_uuid))
      persistQueue()

      return results
    } catch (error) {
      lastSyncError.value = error instanceof Error ? error.message : 'Offline sync failed.'
      throw error
    } finally {
      isSyncing.value = false
    }
  }

  return {
    queue,
    pendingCount,
    isSyncing,
    lastSyncError,
    enqueue,
    flush,
  }
}
