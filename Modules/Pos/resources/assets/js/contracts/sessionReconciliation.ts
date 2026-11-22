import type { Money } from '@/modules/core/contracts/Money'

export interface SessionPaymentMethod {
  method: { id: string; name: string; icon: string; color: string }
  collections: Money
  refunds: Money
  net: Money
}

export interface SessionPaymentReconciliation {
  version: number
  methods: SessionPaymentMethod[]
  legacy_cash_payments_count: number
  unattributed_order_payments_count: number
  currency_mismatch_payments_count: number
}

export interface SessionPaymentSummary {
  payment_reconciliation?: SessionPaymentReconciliation | null
  system_cash_sales?: Money | null
  system_card_sales?: Money | null
  system_other_sales?: Money | null
}

export interface SessionCashier {
  id: number | null
  name: string | null
}

export interface SessionClosingCustody {
  version: number
  responsible_cashier: SessionCashier
  counted_by: SessionCashier | null
  approved_by: SessionCashier
  approved_at: string
  on_behalf: boolean
  closing_reason: string | null
  variance_reason: string | null
}

export interface PosSessionDetail extends SessionPaymentSummary {
  id: number
  pos_register: { id: number; name: string }
  branch: { id: number; name: string }
  opened_by: { id: number | null; name: string }
  closed_by: { id: number | null; name: string | null }
  responsible_cashier?: SessionCashier
  can_close?: boolean
  closing_custody?: SessionClosingCustody | null
  status: { id: 'open' | 'closed'; name: string; color: string; icon: string }
  opened_at: string | null
  closed_at: string | null
  opening_float: Money | null
  declared_cash: Money | null
  cash_over_short: Money | null
  total_sales: Money | null
  total_collections?: Money | null
  total_refunds: Money | null
  orders_count: number | null
  notes: string | null
  cash_reconciliation: {
    cash_in: Money
    cash_out: Money
    expected_cash: Money
  } | null
}

export interface ClosingPreview extends PosSessionDetail {
  review: {
    generated_at: string
    declared_cash_decimal: string | null
    cash_difference: Money | null
    allowed_difference: Money
    can_close: boolean
    token: string | null
    actor?: SessionCashier
    requires_closing_reason?: boolean
    requires_variance_reason?: boolean
  }
}
