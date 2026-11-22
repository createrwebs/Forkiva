import type { PaginationMeta } from '@/modules/core/contracts/Table.ts'

export interface RuntimeNotificationItem {
  id: string
  type: string | null
  type_id: string | number | null
  title: string | null
  subject: string | null
  content: string | null
  read_at: string | null
  created_at: string | null
  updated_at: string | null
}

export interface NotificationSummaryResponse {
  unread_count: number
  recent: RuntimeNotificationItem[]
}

export interface NotificationListResponse {
  data: RuntimeNotificationItem[]
  pagination: PaginationMeta
}

export interface NotificationListFilters {
  page?: number
  per_page?: number
  status?: 'all' | 'unread' | 'read'
  search?: string
}
