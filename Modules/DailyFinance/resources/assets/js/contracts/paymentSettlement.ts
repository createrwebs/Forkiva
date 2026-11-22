import type { Money } from '@/modules/core/contracts/Money'

export type ElectronicMethod = 'card' | 'bank_transfer' | 'mobile_wallet'
export type SettlementStatus = 'pending' | 'partial' | 'settled' | 'cancelled'
export type SettlementStatusFilter = SettlementStatus | 'outstanding'

export interface PaymentSettlementSummary {
  branch_id: number
  open_count: number
  pending_count: number
  partial_count: number
  balances: { currency: string; open_count: number; remaining: Money; remaining_decimal: string }[]
}

export interface SettlementPage<T> {
  data: T[]
  pagination: { current_page: number; last_page: number; total: number }
}

export interface SettlementMeta {
  branch_id: number
  currency: string
  precision: number
  today: string
  methods: { id: ElectronicMethod; name: string; icon: string; color: string }[]
  accounts: { key: string; name: string }[]
  linked_accounts_available: boolean
}

export interface SettlementCandidate {
  id: number
  reference: string
  transaction_id: string | null
  collected_at: string
  amount: Money
  amount_decimal: string
  selectable: boolean
  source_hash: string
}

export interface PaymentSettlement {
  id: number
  reference: string
  batch_reference: string
  branch_id: number
  method: { id: ElectronicMethod; name: string; icon: string; color: string }
  status: { id: SettlementStatus; name: string }
  currency: string
  destination_name: string
  today: string
  account_key: string | null
  gross: Money
  fees: Money
  expected: Money
  actual: Money
  remaining: Money
  expected_decimal: string
  actual_decimal: string
  remaining_decimal: string
  variance: Money | null
  items_count: number
  created_at: string
  created_by: { id: number; name: string | null }
  notes: string | null
  cancelled_at: string | null
  cancelled_by: { id: number; name: string } | null
  cancellation_reason: string | null
  items?: { id: number; reference: string; amount: Money; collected_at: string }[]
  receipts?: {
    id: number
    reference: string
    amount: Money
    received_date: string
    received_date_formatted: string
    is_final: boolean
    variance_reason: string | null
    created_at: string
    created_by: { id: number; name: string | null }
  }[]
  abilities: { receive: boolean; cancel: boolean }
}

export interface SettlementCandidateFilters {
  branch_id: number
  method: ElectronicMethod
  from: string
  to: string
  search?: string
  page?: number
}

export interface CreateSettlement {
  branch_id: number
  method: ElectronicMethod
  payment_ids: number[]
  source_hashes: Record<number, string>
  fees: string
  account_key: string | null
  destination_name: string | null
  reference: string
  notes: string | null
  request_key: string
}

export interface ReceiveSettlement {
  branch_id: number
  amount: string
  received_date: string
  reference: string
  is_final: boolean
  variance_reason: string | null
  request_key: string
}

export interface CancelSettlement {
  branch_id: number
  cancellation_reason: string
}
