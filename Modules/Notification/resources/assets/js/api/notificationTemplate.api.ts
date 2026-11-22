import type { NotificationTemplatePayload } from '@/modules/notification/contracts/NotificationTemplate.ts'
import { http } from '@/modules/core/api/http.ts'

export function getNotificationTemplate(key: string, returnAllTranslations = false) {
  return http.get(`/v1/notification-templates/${key}`, {
    params: { return_all_translations: returnAllTranslations },
  })
}

export function updateNotificationTemplate(key: string, data: NotificationTemplatePayload) {
  return http.put(`/v1/notification-templates/${key}`, data, {
    params: {
      return_all_translations: 'true',
    },
  })
}
