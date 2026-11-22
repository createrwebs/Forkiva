import type { ChartType } from 'chart.js/auto'

export type InventoryAnalyticsWidgetKey =
  | 'inventory_value'
  | 'low_stock_items'
  | 'purchase_spend'
  | 'waste_rate'
  | 'fast_moving_ingredients'
  | 'stock_movement'
  | 'top_suppliers'
  | 'low_stock_ingredients'
  | 'purchase_status'
  | 'most_wasted_ingredients'
  | 'ingredient_purchases'
  | 'wastage_spoilage'

export type InventoryAnalyticsWidgetCategory = 'health' | 'movement' | 'purchasing' | 'risk'
export type InventoryAnalyticsWidgetKind = 'metric' | 'trend' | 'distribution' | 'list'
export type InventoryAnalyticsWidgetMove = 'up' | 'down' | 'left' | 'right' | 'first' | 'last'

export interface InventoryAnalyticsMoney {
  amount: number
  formatted: string
  currency: string
  precision: number
}

export interface InventoryAnalyticsMetricData {
  value: InventoryAnalyticsMoney | number
  suffix?: string
  comparison?: {
    percent: number | null
    direction: -1 | 0 | 1
  }
}

export interface InventoryAnalyticsChartData {
  labels: string[]
  datasets: Array<{
    label: string
    data: number[]
    backgroundColor?: string | string[]
    borderColor?: string | string[]
    borderWidth?: number
    tension?: number
    fill?: boolean
  }>
  currency?: string
  unitSymbols?: string[]
}

export interface InventoryAnalyticsListItem {
  id: number | string
  name: string
  value: string
  secondary?: string
  danger?: boolean
}

export interface InventoryAnalyticsWidgetDefinition {
  key: InventoryAnalyticsWidgetKey
  category: InventoryAnalyticsWidgetCategory
  kind: InventoryAnalyticsWidgetKind
  icon: string
  color: string
  cols: 3 | 4 | 6 | 8 | 12
  rows?: 2 | 4 | 5
  defaultVisible: boolean
  chartType?: ChartType
  inverseTrend?: boolean
  filter?: {
    key: 'limit'
    default: number
    options: number[]
  }
}

export interface InventoryAnalyticsLayoutItem {
  key: InventoryAnalyticsWidgetKey
  visible: boolean
  minimized: boolean
  order: number
  x: number
  y: number
  w: number
  h: number
}

export interface InventoryAnalyticsWidgetPayload<T = unknown> {
  key: InventoryAnalyticsWidgetKey
  permission: string
  authorized: boolean
  data: T | null
}

export interface InventoryAnalyticsMeta {
  branch_id: number | null
  branch_locked: boolean
  from: string
  to: string
  display_currency: string
  timezone: string
  generated_at: string
}

export interface InventoryAnalyticsResponse {
  meta: InventoryAnalyticsMeta
  widgets: Partial<Record<InventoryAnalyticsWidgetKey, InventoryAnalyticsWidgetPayload>>
}

export interface InventoryAnalyticsBranch {
  id: number
  name: string
  currency?: string
}

export interface InventoryAnalyticsFilterSchema {
  key: 'branch_id' | 'from' | 'to'
  label: string
  type: 'select' | 'date'
  options?: InventoryAnalyticsBranch[]
}

export interface InventoryAnalyticsFiltersResponse {
  branches: InventoryAnalyticsBranch[]
  filters: InventoryAnalyticsFilterSchema[]
  defaults: InventoryAnalyticsFilters
  meta: {
    branch_locked: boolean
    timezone: string
  }
}

export interface InventoryAnalyticsFilters {
  branch_id: number | null
  from: string
  to: string
}

export interface InventoryAnalyticsRequest extends InventoryAnalyticsFilters {
  widgets: InventoryAnalyticsWidgetKey[]
}
