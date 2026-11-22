import { getApiErrorStatus } from '@/modules/core/api/errors'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { show } from '@/modules/payment/api/payment.api.ts'

export function useProduct() {
  const toast = useToast()
  const { t } = useI18n()

  const getShowData = async (id: number): Promise<Record<string, any>> => {
    try {
      return { status: 200, data: (await show(id)).data.body }
    } catch (error: unknown) {
      if (getApiErrorStatus(error) !== 404) {
        toast.error(t('core::errors.an_unexpected_error_occurred'))
      }
      return { status: getApiErrorStatus(error) }
    }
  }

  return { getShowData }
}
