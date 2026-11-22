import type { Money } from '@/modules/core/contracts/Money'

export interface CashHandover {
  id: number
  reference: string
  status: { id: 'pending' | 'received' | 'cancelled'; name: string; color?: string }
  amount: Money
  amount_decimal: string
  received_amount: Money | null
  received_amount_decimal: string | null
  variance: Money | null
  cashier: { id: number; name: string }
  created_by: { id: number; name: string }
  receiver: { id: number; name: string }
  created_at: string
  received_at: string | null
  cancelled_at: string | null
  notes: string | null
  variance_reason: string | null
  cancellation_reason: string | null
  can_receive: boolean
  can_cancel: boolean
}

export interface CashHandoverSummary {
  currency: string
  precision: number
  counted: Money | null
  handed_over: Money
  received: Money
  pending: Money
  variance: Money
  available: Money | null
  available_decimal: string | null
  can_create: boolean
  receivers: { id: number; name: string }[]
  data: CashHandover[]
  pagination: { current_page: number; last_page: number; total: number }
}

export interface CreateCashHandover {
  request_key: string
  amount: string
  receiver_id: number
  notes?: string | null
}

export type CashHandoverFollowUpKind = 'unhanded' | 'pending'

export interface CashHandoverFollowUp {
  branch_id: number
  unhanded_sessions_count: number
  pending_sessions_count: number
  pending_handovers_count: number
  balances: {
    currency: string
    unhanded: Money
    unhanded_decimal: string
    pending: Money
    pending_decimal: string
  }[]
}

export interface CashHandoverFollowUpSession {
  id: number
  register: string | null
  cashier: string | null
  opened_at_formatted: string | null
  closed_at_formatted: string | null
  currency: string
  unhanded: Money
  pending: Money
  pending_count: number
}

export interface CashHandoverFollowUpSessions {
  data: CashHandoverFollowUpSession[]
  pagination: { current_page: number; last_page: number; total: number }
}
