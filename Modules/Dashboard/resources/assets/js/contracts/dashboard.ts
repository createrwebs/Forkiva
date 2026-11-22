export type DashboardWidgetKey =
  | 'total_sales'
  | 'total_orders'
  | 'total_active_orders'
  | 'average_order_value'
  | 'total_users'
  | 'total_menus'
  | 'total_products'
  | 'total_categories'
  | 'sales_analytics'
  | 'hourly_sales_trend'
  | 'top_selling_products'
  | 'branch_wise_sales_comparison'
  | 'best_performing_branches'
  | 'order_type_distribution'
  | 'order_total_by_status'
  | 'low_stock_alerts'
  | 'cash_movements_overview'
  | 'payments_overview'
  | 'gross_profit_margin'
  | 'customer_mix'
  | 'order_source_distribution'
  | 'fulfillment_health'
  | 'outstanding_balance'
  | 'total_guests'
  | 'revenue_per_guest'
  | 'payment_status_distribution'
  | 'sales_by_weekday'

export type DashboardWidgetCategory =
  'sales' | 'operations' | 'inventory' | 'finance' | 'customers' | 'catalog'

export type DashboardWidgetKind =
  'metric' | 'trend' | 'distribution' | 'ranking' | 'stock' | 'bar' | 'insight'

export interface DashboardMoney {
  amount: number
  formatted: string
  currency: string
  precision: number
}

export interface DashboardComparison {
  percent: number | null
  direction: -1 | 0 | 1
}

export interface DashboardMetricData {
  value: DashboardMoney | number
  comparison?: DashboardComparison
  live?: boolean
}

export interface DashboardTrendData {
  labels: string[]
  sales: number[]
  orders: number[]
  previous_sales?: number[]
  previous_orders?: number[]
  granularity?: 'hour' | 'day' | 'week' | 'month'
  date?: string
  currency: string
}

export interface DashboardDistributionItem {
  id: string
  label: string
  value: number
  formatted?: DashboardMoney
  color: string
}

export interface DashboardRankingItem {
  id: number
  label?: string
  name?: string
  thumbnail?: string | null
  orders?: number
  total_quantity?: number
  value?: DashboardMoney
  total_sales?: DashboardMoney
}

export interface DashboardStockItem {
  id: number
  name: string
  current_stock: number
  alert_quantity: number
  symbol: string
  out_of_stock: boolean
}

export interface DashboardWidgetPayload<T = unknown> {
  key: DashboardWidgetKey
  permission: string
  authorized: boolean
  data: T | null
}

export interface DashboardMeta {
  branch_id: number | null
  branch_locked: boolean
  from: string
  to: string
  comparison: {
    from: string
    to: string
  }
  display_currency: string
  granularity: 'hour' | 'day' | 'week' | 'month'
  timezone: string
  generated_at: string
}

export interface DashboardResponse {
  meta: DashboardMeta
  widgets: Partial<Record<DashboardWidgetKey, DashboardWidgetPayload>>
}

export interface DashboardFilterOption {
  id: string | number
  name: string
  currency?: string
}

export interface DashboardFilterSchema {
  key: string
  label: string
  type: 'select' | 'date'
  options?: DashboardFilterOption[]
}

export interface DashboardPeriodPreset {
  id: string
  name: string
  from: string
  to: string
}

export interface DashboardFiltersResponse {
  filters: DashboardFilterSchema[]
  presets: DashboardPeriodPreset[]
  defaults: DashboardFilters
  meta: {
    branch_locked: boolean
    max_range_days: number
    timezone: string
  }
}

export interface DashboardFilters {
  branch_id: number | null
  from: string
  to: string
}

export type DashboardWidgetOptionValue = string | number

export interface DashboardWidgetOptions {
  [widget: string]: Record<string, DashboardWidgetOptionValue>
}

export interface DashboardRequest extends DashboardFilters {
  widgets: DashboardWidgetKey[]
  widget_options: DashboardWidgetOptions
  refresh?: boolean
}

export interface DashboardWidgetFilterDefinition {
  key: 'granularity' | 'date' | 'limit'
  type: 'select' | 'date'
  default: DashboardWidgetOptionValue
  options?: Array<{
    titleKey: string
    value: DashboardWidgetOptionValue
  }>
}

export interface DashboardWidgetDefinition {
  key: DashboardWidgetKey
  permission: string
  category: DashboardWidgetCategory
  kind: DashboardWidgetKind
  icon: string
  color: string
  cols: 3 | 4 | 6 | 8 | 12
  defaultVisible: boolean
  inverseTrend?: boolean
  filter?: DashboardWidgetFilterDefinition
}

export interface DashboardLayoutItem {
  key: DashboardWidgetKey
  visible: boolean
  minimized: boolean
  order: number
  x: number
  y: number
  w: number
  h: number
}

export type DashboardWidgetMove = 'up' | 'down' | 'left' | 'right' | 'first' | 'last'
