import { getApiErrorStatus } from '@/modules/core/api/errors'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { getFormMeta, show, store, update } from '../api/branch.api.ts'

export function useBranch() {
  const toast = useToast()
  const { t } = useI18n()

  const getShowData = async (
    id: number,
    returnAllTranslations = false,
  ): Promise<Record<string, any>> => {
    try {
      return { status: 200, data: (await show(id, returnAllTranslations)).data.body }
    } catch (error: unknown) {
      if (getApiErrorStatus(error) !== 404) {
        toast.error(t('core::errors.an_unexpected_error_occurred'))
      }
      return { status: getApiErrorStatus(error) }
    }
  }

  return { getShowData, update, store, getFormMeta }
}
