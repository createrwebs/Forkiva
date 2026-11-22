import { getApiErrorStatus } from '@/modules/core/api/errors'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import {
  assignReservationTables,
  autoAssignReservationTables,
  changeReservationStatus,
  checkAvailability,
  getReservationAnalytics,
  getReservationAnalyticsFilters,
  getReservationCalendar,
  getReservationDepositFormMeta,
  getReservationFormMeta,
  recordReservationDeposit,
  reverseReservationDeposit,
  showReservation,
  showReservationDepositTransaction,
  storeReservation,
  suggestedSlots,
  suggestedTables,
  updateReservation,
} from '@/modules/reservation/api/reservation.api.ts'

export function useReservation() {
  const toast = useToast()
  const { t } = useI18n()

  const getShowData = async (id: number | string): Promise<Record<string, any>> => {
    try {
      return { status: 200, data: (await showReservation(id)).data.body }
    } catch (error: unknown) {
      if (getApiErrorStatus(error) !== 404) {
        toast.error(t('core::errors.an_unexpected_error_occurred'))
      }
      return { status: getApiErrorStatus(error) }
    }
  }

  return {
    getShowData,
    getReservationFormMeta,
    getReservationDepositFormMeta,
    showReservationDepositTransaction,
    storeReservation,
    updateReservation,
    checkAvailability,
    suggestedTables,
    suggestedSlots,
    changeReservationStatus,
    assignReservationTables,
    autoAssignReservationTables,
    recordReservationDeposit,
    reverseReservationDeposit,
    getReservationCalendar,
    getReservationAnalytics,
    getReservationAnalyticsFilters,
  }
}
