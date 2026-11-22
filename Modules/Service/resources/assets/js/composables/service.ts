import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import * as serviceApi from '@/modules/service/api/service.api.ts'

export function useService() {
  const { t } = useI18n()
  const toast = useToast()
  const loading = ref(false)

  const getServices = async () => {
    loading.value = true
    try {
      return (await serviceApi.getServices()).data.body
    } catch {
      toast.error(t('core::errors.failed_to_load_data'))
      return []
    } finally {
      loading.value = false
    }
  }

  const getService = async (category: string, service: string) => {
    loading.value = true
    try {
      return (await serviceApi.getService(category, service)).data.body
    } catch {
      toast.error(t('core::errors.failed_to_load_data'))
      return null
    } finally {
      loading.value = false
    }
  }

  return {
    loading,
    getServices,
    getService,
    updateServiceSettings: serviceApi.updateServiceSettings,
  }
}
