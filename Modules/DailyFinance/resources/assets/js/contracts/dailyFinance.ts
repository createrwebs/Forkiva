import type { Money } from '@/modules/core/contracts/Money'

export interface DailyFinanceFilters {
  branch_id?: number | null
  date?: string | null
}

export interface DailyFinanceSession {
  id: number
  register: string | null
  cashier: string | null
  status: 'open' | 'closed'
  opened_at: string | null
  opened_at_formatted: string | null
  closed_at: string | null
  latest_for_register: boolean
  expected: Money | null
  counted: Money | null
  variance: Money | null
}

export interface DailyFinanceSummary {
  meta: {
    branch_id: number | null
    date: string
    today: string
    timezone: string
    currency: string | null
    generated_at: string
    branch_locked: boolean
  }
  branches: { id: number; name: string }[]
  permissions: Record<'sales' | 'payments' | 'movements' | 'sessions' | 'expenses', boolean>
  sales: { total: Money; dues: Money; orders_count: number; excluded_currency_count: number } | null
  payments: {
    collections: Money
    refunds: Money
    net: Money
    excluded_currency_count: number
    methods: {
      method: { id: string; name: string; icon: string; color: string }
      collections: Money
      refunds: Money
      net: Money
    }[]
  } | null
  movements: {
    cash_in: Money
    cash_out: Money
    pay_ins: Money
    payouts: Money
    cash_drops: Money
    excluded_currency_count: number
  } | null
  sessions: {
    rows: DailyFinanceSession[]
    open_count: number
    closed_count: number
    registers_count: number
    expected: Money | null
    counted: Money | null
    variance: Money | null
  } | null
  expenses: Money | null
}

export type DailyFinanceEntryKind = 'sales' | 'collections' | 'refunds' | 'dues' | 'movements'

export interface DailyFinanceEntry {
  id: number
  reference: string | null
  occurred_at: string | null
  amount: Money
  status: { id: string; name: string } | null
  label: { id: string; name: string; color?: string; icon?: string }
  occurred_at_formatted?: string | null
  receipt?: { cashier: string | null }
  order?: {
    type: { id: string; name: string; color?: string; icon?: string }
    subtotal: Money
    discount: Money
    tax: Money
    charges: Money
    total: Money
    due: Money
  }
  direction?: { id: string; name: string; icon?: string; color?: string }
  movement?: { register: string | null; balance_before: Money; balance_after: Money }
}

export interface DailyFinanceEntries {
  data: DailyFinanceEntry[]
  pagination: { current_page: number; last_page: number; total: number }
}
