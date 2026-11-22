import { getApiErrorStatus } from '@/modules/core/api/errors'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import {
  cancelReservationWaitlist,
  convertReservationWaitlist,
  getReservationWaitlistFormMeta,
  notifyReservationWaitlist,
  showReservationWaitlist,
  storeReservationWaitlist,
  updateReservationWaitlist,
} from '@/modules/reservation/api/reservation_waitlist.api.ts'

export function useReservationWaitlist() {
  const toast = useToast()
  const { t } = useI18n()

  const getShowData = async (id: number | string): Promise<Record<string, any>> => {
    try {
      return { status: 200, data: (await showReservationWaitlist(id)).data.body }
    } catch (error: unknown) {
      if (getApiErrorStatus(error) !== 404) {
        toast.error(t('core::errors.an_unexpected_error_occurred'))
      }
      return { status: getApiErrorStatus(error) }
    }
  }

  return {
    getShowData,
    getReservationWaitlistFormMeta,
    storeReservationWaitlist,
    updateReservationWaitlist,
    notifyReservationWaitlist,
    convertReservationWaitlist,
    cancelReservationWaitlist,
  }
}
