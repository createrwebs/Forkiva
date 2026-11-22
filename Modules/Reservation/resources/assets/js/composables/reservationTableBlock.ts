import { getApiErrorStatus } from '@/modules/core/api/errors'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import {
  showReservationTableBlock,
  storeReservationTableBlock,
  updateReservationTableBlock,
} from '@/modules/reservation/api/reservation_table_block.api.ts'

export function useReservationTableBlock() {
  const toast = useToast()
  const { t } = useI18n()

  const getShowData = async (
    id: number | string,
    returnAllTranslations = false,
  ): Promise<Record<string, any>> => {
    try {
      return {
        status: 200,
        data: (await showReservationTableBlock(id, returnAllTranslations)).data.body,
      }
    } catch (error: unknown) {
      if (getApiErrorStatus(error) !== 404) {
        toast.error(t('core::errors.an_unexpected_error_occurred'))
      }
      return { status: getApiErrorStatus(error) }
    }
  }

  return { getShowData, storeReservationTableBlock, updateReservationTableBlock }
}
