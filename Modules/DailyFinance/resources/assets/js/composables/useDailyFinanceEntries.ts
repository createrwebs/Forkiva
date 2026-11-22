import axios from 'axios'
import { onBeforeUnmount, ref } from 'vue'
import { getApiErrorStatus } from '@/modules/core/api/errors'
import { entries } from '@/modules/dailyfinance/api/dailyFinance.api'
import type {
  DailyFinanceEntries,
  DailyFinanceEntryKind,
} from '@/modules/dailyfinance/contracts/dailyFinance'

export function useDailyFinanceEntries() {
  const result = ref<DailyFinanceEntries | null>(null)
  const loading = ref(false)
  const error = ref<number | null>(null)
  let controller: AbortController | null = null
  let revision = 0

  async function load(filters: {
    branch_id: number
    date: string
    kind: DailyFinanceEntryKind
    page: number
  }) {
    const current = ++revision
    controller?.abort()
    controller = new AbortController()
    result.value = null
    error.value = null
    loading.value = true
    try {
      const response = await entries(filters, controller.signal)
      if (current === revision) result.value = response.data.body
    } catch (cause: unknown) {
      if (current === revision && !axios.isCancel(cause))
        error.value = getApiErrorStatus(cause) || 500
    } finally {
      if (current === revision) loading.value = false
    }
  }

  onBeforeUnmount(() => {
    revision++
    controller?.abort()
  })

  return { result, loading, error, load }
}
