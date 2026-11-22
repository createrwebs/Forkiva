import type { ConvertedMoney, Money } from '@/modules/core/contracts/Money.ts'

export interface DisplayMoney {
  amount?: number
  formatted: string
  currency?: string
  precision?: number
}

export interface ConvertedDisplayMoney {
  original: DisplayMoney
  converted?: DisplayMoney
}

export interface TaxSummaryLine {
  id?: number | string
  key: string
  name: string
  rate?: number
  type?: string | { id?: string }
  scope: 'product' | 'global'
  amount: ConvertedDisplayMoney | DisplayMoney
}

export interface TaxDetail extends Omit<TaxSummaryLine, 'scope' | 'amount'> {
  amount: ConvertedMoney | Money
}
