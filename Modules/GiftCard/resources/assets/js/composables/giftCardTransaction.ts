import { getApiErrorStatus } from '@/modules/core/api/errors'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import {
  financialAction,
  index,
  reverse,
  show,
} from '@/modules/giftcard/api/giftCardTransaction.api.ts'

export function useGiftCardTransaction() {
  const toast = useToast()
  const { t } = useI18n()

  const getShowData = async (id: number | string): Promise<Record<string, any>> => {
    try {
      return { status: 200, data: (await show(id)).data.body }
    } catch (error: unknown) {
      if (getApiErrorStatus(error) !== 404) {
        toast.error(t('core::errors.an_unexpected_error_occurred'))
      }

      return { status: getApiErrorStatus(error) }
    }
  }

  const getByGiftCard = async (giftCardId: number | string) => {
    const response = await index({ 'filters[gift_card_id]': giftCardId })
    return response.data.body?.data || response.data.body || []
  }

  const submitFinancialAction = async (
    giftCardId: number | string,
    payload: Record<string, any>,
  ) => {
    try {
      const response = await financialAction(giftCardId, payload)
      toast.success(
        response.data.message ||
          t('admin::messages.resource_saved', {
            resource: t('giftcard::gift_card_transactions.gift_card_transaction'),
          }),
      )
      return { status: response.status, data: response.data.body }
    } catch (error: any) {
      return {
        status: error?.response?.status,
        errors: error?.response?.data?.errors || {},
        message: error?.response?.data?.message,
      }
    }
  }

  const reverseTransaction = async (id: number | string, payload: Record<string, any>) => {
    try {
      const response = await reverse(id, payload)
      toast.success(
        response.data.message ||
          t('admin::messages.resource_saved', {
            resource: t('giftcard::gift_card_transactions.gift_card_transaction'),
          }),
      )
      return { status: response.status, data: response.data.body }
    } catch (error: any) {
      return {
        status: error?.response?.status,
        errors: error?.response?.data?.errors || {},
        message: error?.response?.data?.message,
      }
    }
  }

  return { getShowData, getByGiftCard, submitFinancialAction, reverseTransaction }
}
