import { http } from '@/modules/core/api/http'
import type {
  DailyFinanceFilters,
  DailyFinanceEntryKind,
  DailyFinanceEntries,
  DailyFinanceSummary,
} from '@/modules/dailyfinance/contracts/dailyFinance'

export function dailyFinance(filters: DailyFinanceFilters = {}, signal?: AbortSignal) {
  return http.get<{ body: DailyFinanceSummary }>('/v1/daily-finance', {
    params: filters,
    signal,
  })
}

export function entries(
  filters: {
    branch_id: number
    date: string
    kind: DailyFinanceEntryKind
    page: number
  },
  signal?: AbortSignal,
) {
  return http.get<{ body: DailyFinanceEntries }>('/v1/daily-finance/entries', {
    params: filters,
    signal,
  })
}
