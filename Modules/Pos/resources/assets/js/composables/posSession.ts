import { getApiErrorStatus } from '@/modules/core/api/errors'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { close, getFormMeta, open, show } from '@/modules/pos/api/session.api.ts'
import type { PosSessionDetail } from '@/modules/pos/contracts/sessionReconciliation'

export function usePosSession() {
  const toast = useToast()
  const { t } = useI18n()

  const getShowData = async (
    id: number,
    returnAllTranslations = false,
  ): Promise<{ status: number; data?: PosSessionDetail }> => {
    try {
      return { status: 200, data: (await show(id, returnAllTranslations)).data.body }
    } catch (error: unknown) {
      if (getApiErrorStatus(error) !== 404) {
        toast.error(t('core::errors.an_unexpected_error_occurred'))
      }
      return { status: getApiErrorStatus(error) ?? 500 }
    }
  }

  return { open, close, getFormMeta, getShowData }
}
