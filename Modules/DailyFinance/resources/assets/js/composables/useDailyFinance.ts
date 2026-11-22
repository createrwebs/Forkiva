import axios from 'axios'
import { onBeforeUnmount, ref } from 'vue'
import { getApiErrorStatus } from '@/modules/core/api/errors'
import { dailyFinance } from '@/modules/dailyfinance/api/dailyFinance.api'
import type {
  DailyFinanceFilters,
  DailyFinanceSummary,
} from '@/modules/dailyfinance/contracts/dailyFinance'

export function useDailyFinance() {
  const summary = ref<DailyFinanceSummary | null>(null)
  const loading = ref(false)
  const error = ref<number | null>(null)
  let controller: AbortController | null = null
  let revision = 0

  async function load(filters: DailyFinanceFilters = {}) {
    const requestRevision = ++revision
    controller?.abort()
    controller = new AbortController()
    summary.value = null
    error.value = null
    loading.value = true
    try {
      const response = await dailyFinance(filters, controller.signal)
      if (requestRevision === revision) summary.value = response.data.body
    } catch (cause: unknown) {
      if (requestRevision === revision && !axios.isCancel(cause)) {
        error.value = getApiErrorStatus(cause) || 500
      }
    } finally {
      if (requestRevision === revision) loading.value = false
    }
  }

  onBeforeUnmount(() => {
    revision++
    controller?.abort()
  })

  return { summary, loading, error, load }
}
