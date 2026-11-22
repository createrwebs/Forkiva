import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import * as notificationWorkflowApi from '@/modules/notification/api/notificationWorkflow.api.ts'

export function useNotificationWorkflow() {
  const { t } = useI18n()
  const toast = useToast()
  const loading = ref(false)

  const getWorkflow = async (key: string, silent = false) => {
    loading.value = true

    try {
      const response = await notificationWorkflowApi.getNotificationWorkflow(key)
      return response.data.body
    } catch {
      if (!silent) {
        toast.error(t('core::errors.an_unexpected_error_occurred'))
      }

      return null
    } finally {
      loading.value = false
    }
  }

  return {
    loading,
    getWorkflow,
    updateWorkflow: notificationWorkflowApi.updateNotificationWorkflow,
  }
}
