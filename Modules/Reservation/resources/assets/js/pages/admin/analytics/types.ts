import type { TableFilterSchema } from '@/modules/core/contracts/Table.ts'

export type EnumValue = { id?: string; name?: string; color?: string }

export type BreakdownRow = {
  status?: EnumValue
  type?: EnumValue
  reservations_count?: number
  guests_count?: number
}

export type TrendRow = {
  date: string
  label: string
  reservations_count?: number
  guests_count?: number
}

export type PeakHourRow = {
  hour?: number
  hour_label?: string
  reservations_count?: number
  guests_count?: number
}

export type DemandRow = {
  id?: string
  day?: number
  label: string
  reservations_count?: number
  guests_count?: number
}

export type TableUtilizationRow = {
  table_id: number
  table_name: string
  reservations_count?: number
  guests_count?: number
}

export type UpcomingReservation = {
  id: number
  reservation_number: string
  customer_name: string
  reservation_date: string
  start_time: string
  guest_count?: number
  status?: EnumValue
  tables?: Array<{ id: number; name: string }>
}

export type AnalyticsMeta = {
  branch_id: number | null
  branch_locked: boolean
  from: string
  to: string
  timezone: string
  generated_at: string
}

export type AnalyticsPayload = {
  meta: AnalyticsMeta
  summary: Record<string, number>
  reservation_trend: TrendRow[]
  status_breakdown: BreakdownRow[]
  type_breakdown: BreakdownRow[]
  peak_hours: PeakHourRow[]
  weekday_demand: DemandRow[]
  party_size_distribution: DemandRow[]
  table_utilization: TableUtilizationRow[]
  upcoming_reservations: UpcomingReservation[]
}

export type AnalyticsPeriodPreset = { id: string; name: string; from: string; to: string }

export type AnalyticsFiltersResponse = {
  filters: TableFilterSchema[]
  defaults: AnalyticsFilters
  default_filters: AnalyticsFilters
  presets: AnalyticsPeriodPreset[]
  meta: { branch_locked: boolean; timezone: string }
}

export type AnalyticsFilters = { branch_id: number | null; from: string; to: string }

export type AnalyticsWidgetKey =
  | 'total_reservations'
  | 'total_guests'
  | 'average_party_size'
  | 'today_count'
  | 'assigned_count'
  | 'unassigned_count'
  | 'cancellation_rate'
  | 'no_show_rate'
  | 'completion_rate'
  | 'reservation_trend'
  | 'status_breakdown'
  | 'type_breakdown'
  | 'peak_hours'
  | 'weekday_demand'
  | 'party_size_distribution'
  | 'table_utilization'
  | 'upcoming_reservations'

export type AnalyticsWidgetPayload<T = unknown> = {
  key: AnalyticsWidgetKey
  permission: string
  authorized: boolean
  data: T | null
}

export type AnalyticsResponse = {
  meta: AnalyticsMeta
  widgets: Partial<Record<AnalyticsWidgetKey, AnalyticsWidgetPayload>>
}

export type AnalyticsRequest = AnalyticsFilters & { widgets: AnalyticsWidgetKey[] }

export type AnalyticsWidgetCategory = 'overview' | 'demand' | 'operations' | 'guests'
export type AnalyticsWidgetKind = 'metric' | 'trend' | 'distribution' | 'bar' | 'table'
export type AnalyticsWidgetMove = 'up' | 'down' | 'left' | 'right' | 'first' | 'last'

export type AnalyticsWidgetDefinition = {
  key: AnalyticsWidgetKey
  category: AnalyticsWidgetCategory
  kind: AnalyticsWidgetKind
  icon: string
  color: string
  cols: 3 | 4 | 5 | 6 | 7 | 8 | 12
  defaultVisible: boolean
  suffix?: string
  precision?: number
}

export type AnalyticsLayoutItem = {
  key: AnalyticsWidgetKey
  visible: boolean
  minimized: boolean
  order: number
  x: number
  y: number
  w: number
  h: number
}

const numberFormatter = new Intl.NumberFormat()
const palette = [
  '#F57C00',
  '#0F766E',
  '#3867D6',
  '#7C3AED',
  '#E74C3C',
  '#0891B2',
  '#D97706',
  '#64748B',
]
const namedColors: Record<string, string> = {
  primary: '#F57C00',
  secondary: '#64748B',
  success: '#16A34A',
  info: '#0891B2',
  warning: '#D97706',
  error: '#E74C3C',
}

export function emptyAnalyticsPayload(): AnalyticsPayload {
  return {
    meta: {
      branch_id: null,
      branch_locked: false,
      from: '',
      to: '',
      timezone: '',
      generated_at: '',
    },
    summary: {},
    reservation_trend: [],
    status_breakdown: [],
    type_breakdown: [],
    peak_hours: [],
    weekday_demand: [],
    party_size_distribution: [],
    table_utilization: [],
    upcoming_reservations: [],
  }
}

export function normalizeAnalytics(
  response: Partial<AnalyticsResponse> | null | undefined,
): AnalyticsPayload {
  const empty = emptyAnalyticsPayload()
  const widgetData = (key: AnalyticsWidgetKey) => response?.widgets?.[key]?.data
  const metricKeys: AnalyticsWidgetKey[] = [
    'total_reservations',
    'total_guests',
    'average_party_size',
    'today_count',
    'assigned_count',
    'unassigned_count',
    'cancellation_rate',
    'no_show_rate',
    'completion_rate',
  ]

  return {
    meta: { ...empty.meta, ...response?.meta },
    summary: Object.fromEntries(
      metricKeys.map((key) => [
        key,
        Number((widgetData(key) as { value?: number } | null)?.value ?? 0),
      ]),
    ),
    reservation_trend: (widgetData('reservation_trend') as TrendRow[] | null) || [],
    status_breakdown: (widgetData('status_breakdown') as BreakdownRow[] | null) || [],
    type_breakdown: (widgetData('type_breakdown') as BreakdownRow[] | null) || [],
    peak_hours: (widgetData('peak_hours') as PeakHourRow[] | null) || [],
    weekday_demand: (widgetData('weekday_demand') as DemandRow[] | null) || [],
    party_size_distribution: (widgetData('party_size_distribution') as DemandRow[] | null) || [],
    table_utilization: (widgetData('table_utilization') as TableUtilizationRow[] | null) || [],
    upcoming_reservations:
      (widgetData('upcoming_reservations') as UpcomingReservation[] | null) || [],
  }
}

export function formatNumber(value: unknown, suffix = '', precision = 0): string {
  const numericValue = Number(value || 0)
  const formatted = Number.isFinite(numericValue)
    ? new Intl.NumberFormat(undefined, {
        maximumFractionDigits: precision,
        minimumFractionDigits: precision,
      }).format(numericValue)
    : numberFormatter.format(0)
  return `${formatted}${suffix}`
}

export function enumName(value?: EnumValue): string {
  return value?.name || value?.id || '-'
}

export function enumColor(value: EnumValue | undefined, index: number): string {
  const color = value?.color
  return color ? namedColors[color] || color : palette[index % palette.length] || '#F57C00'
}
