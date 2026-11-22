import type { ChartType } from 'chart.js/auto'

export type GiftCardAnalyticsWidgetKey =
  | 'total_cards'
  | 'active_cards'
  | 'assigned_cards'
  | 'total_transactions'
  | 'total_batches'
  | 'expiring_soon_cards'
  | 'outstanding_balance'
  | 'sold_value'
  | 'redeemed_value'
  | 'refunded_value'
  | 'batch_face_value'
  | 'cards_health'
  | 'transaction_mix'
  | 'batch_activity'
  | 'branch_highlights'
  | 'expiry_watch'
  | 'sales_over_time'
  | 'redemption_over_time'
  | 'sales_by_branch'
  | 'liability_by_currency'
  | 'usage_by_day_of_week'

export type GiftCardAnalyticsWidgetCategory = 'value' | 'cards' | 'activity' | 'branches' | 'risk'
export type GiftCardAnalyticsWidgetKind =
  'metric' | 'chart' | 'cards' | 'transactions' | 'batches' | 'branches' | 'expiry'
export type GiftCardAnalyticsWidgetMove = 'up' | 'down' | 'left' | 'right' | 'first' | 'last'

export interface GiftCardAnalyticsMoney {
  amount: number
  formatted: string
  currency: string
  precision: number
}

export interface GiftCardAnalyticsMetricData {
  value: GiftCardAnalyticsMoney | number
}

export interface GiftCardAnalyticsChartData {
  labels: string[]
  series: number[]
  currency?: string
  precision?: number
}

export interface GiftCardAnalyticsWidgetDefinition {
  key: GiftCardAnalyticsWidgetKey
  category: GiftCardAnalyticsWidgetCategory
  kind: GiftCardAnalyticsWidgetKind
  icon: string
  color: string
  cols: 3 | 4 | 6 | 8 | 12
  rows?: 2 | 4 | 5 | 6
  defaultVisible: boolean
  chartType?: ChartType
  horizontal?: boolean
}

export interface GiftCardAnalyticsLayoutItem {
  key: GiftCardAnalyticsWidgetKey
  visible: boolean
  minimized: boolean
  order: number
  x: number
  y: number
  w: number
  h: number
}

export interface GiftCardAnalyticsWidgetPayload<T = unknown> {
  key: GiftCardAnalyticsWidgetKey
  permission: string
  authorized: boolean
  data: T | null
}

export interface GiftCardAnalyticsMeta {
  branch_id: number | null
  branch_locked: boolean
  from: string
  to: string
  display_currency: string
  timezone: string
  generated_at: string
}

export interface GiftCardAnalyticsResponse {
  meta: GiftCardAnalyticsMeta
  widgets: Partial<Record<GiftCardAnalyticsWidgetKey, GiftCardAnalyticsWidgetPayload>>
}

export interface GiftCardAnalyticsBranch {
  id: number
  name: string
  currency?: string
}

export interface GiftCardAnalyticsFilterSchema {
  key: 'branch_id' | 'from' | 'to'
  label: string
  type: 'select' | 'date'
  options?: GiftCardAnalyticsBranch[]
}

export interface GiftCardAnalyticsPeriodPreset {
  id: string
  name: string
  from: string
  to: string
}

export interface GiftCardAnalyticsFiltersResponse {
  filters: GiftCardAnalyticsFilterSchema[]
  presets: GiftCardAnalyticsPeriodPreset[]
  defaults: GiftCardAnalyticsFilters
  meta: {
    branch_locked: boolean
    max_range_days: number
    timezone: string
  }
}

export interface GiftCardAnalyticsFilters {
  branch_id: number | null
  from: string
  to: string
}

export interface GiftCardAnalyticsRequest extends GiftCardAnalyticsFilters {
  widgets: GiftCardAnalyticsWidgetKey[]
  refresh?: boolean
}
