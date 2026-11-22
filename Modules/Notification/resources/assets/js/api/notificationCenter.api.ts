import type {
  NotificationListFilters,
  NotificationListResponse,
  NotificationSummaryResponse,
  RuntimeNotificationItem,
} from '@/modules/notification/contracts/NotificationCenter.ts'
import { http } from '@/modules/core/api/http.ts'

export function getNotificationSummary() {
  return http.get<{ body: NotificationSummaryResponse }>('/v1/notifications/summary')
}

export function getNotifications(params: NotificationListFilters = {}) {
  return http.get<{ body: NotificationListResponse }>('/v1/notifications', { params })
}

export function getNotification(notificationId: string) {
  return http.get<{ body: RuntimeNotificationItem }>(`/v1/notifications/${notificationId}`)
}

export function markNotificationRead(notificationId: string) {
  return http.patch<{ body: RuntimeNotificationItem }>(`/v1/notifications/${notificationId}/read`)
}

export function markAllNotificationsRead() {
  return http.patch<{ body: { unread_count: number } }>('/v1/notifications/read-all')
}
