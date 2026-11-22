import type { DailyFinanceSummary } from './dailyFinance'

export type DayClosingCheck =
  | 'open_sessions'
  | 'closed_sessions'
  | 'unknown_counts'
  | 'pending_handovers'
  | 'session_variances'
  | 'receipt_variances'
  | 'unpaid_orders'
  | 'excluded_currencies'
  | 'future_day'

export interface DayClosing {
  id: number
  reference: string
  branch_id: number
  date: string
  date_formatted: string
  status: { id: 'closed'; name: string }
  created_at: string
  created_by: { id: number; name: string }
  notes: string | null
  summary: DailyFinanceSummary
  checks: Record<DayClosingCheck, number>
}

export interface DayClosingReview {
  date_formatted: string
  summary: DailyFinanceSummary
  checks: Record<DayClosingCheck, number>
  blockers: DayClosingCheck[]
  warnings: DayClosingCheck[]
  review_hash: string
  can_close: boolean
  closing: DayClosing | null
}

export interface CloseDay {
  branch_id: number
  date: string
  request_key: string
  review_hash: string
  notes: string | null
}

export interface DayClosingHistory {
  data: DayClosing[]
  pagination: { current_page: number; last_page: number; total: number }
}
