import { http } from '@/modules/core/api/http.ts'
import type { NotificationWorkflowFormPayload } from '@/modules/notification/contracts/NotificationWorkflow.ts'

export function getNotificationWorkflow(key: string) {
  return http.get(`/v1/notification-workflows/${key}`)
}

export function updateNotificationWorkflow(key: string, payload: NotificationWorkflowFormPayload) {
  return http.put(`/v1/notification-workflows/${key}`, payload)
}
