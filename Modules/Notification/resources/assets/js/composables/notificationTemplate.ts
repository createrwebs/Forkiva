import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import * as notificationTemplateApi from '@/modules/notification/api/notificationTemplate.api.ts'

export function useNotificationTemplate() {
  const { t } = useI18n()
  const toast = useToast()
  const loading = ref(false)

  const getTemplate = async (key: string, returnAllTranslations = false) => {
    loading.value = true
    try {
      return (await notificationTemplateApi.getNotificationTemplate(key, returnAllTranslations))
        .data.body
    } catch {
      toast.error(t('core::errors.failed_to_load_data'))
      return null
    } finally {
      loading.value = false
    }
  }

  return {
    loading,
    getTemplate,
    updateTemplate: notificationTemplateApi.updateNotificationTemplate,
  }
}
