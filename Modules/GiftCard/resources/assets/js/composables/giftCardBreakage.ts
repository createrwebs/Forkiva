import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import * as api from '@/modules/giftcard/api/giftCardBreakage.api.ts'

export function useGiftCardBreakage() {
  const toast = useToast()
  const { t } = useI18n()

  const execute = async (request: Promise<any>) => {
    try {
      const response = await request
      toast.success(
        response.data.message ||
          t('admin::messages.resource_saved', {
            resource: t('giftcard::gift_card_breakage_batches.gift_card_breakage_batch'),
          }),
      )
      return { status: response.status, data: response.data.body }
    } catch (error: any) {
      return {
        status: error?.response?.status,
        errors: error?.response?.data?.errors,
        message: error?.response?.data?.message,
      }
    }
  }

  const getShowData = async (id: number | string) => {
    try {
      const response = await api.show(id)

      return { status: response.status, data: response.data.body }
    } catch (error: any) {
      return {
        status: error?.response?.status,
        errors: error?.response?.data?.errors,
        message: error?.response?.data?.message,
      }
    }
  }

  return {
    getShowData,
    getFormMeta: api.getFormMeta,
    store: api.store,
    review: (id: number | string, payload: Record<string, any>) => execute(api.review(id, payload)),
    post: (id: number | string) => execute(api.post(id)),
    reverse: (id: number | string, payload: Record<string, any>) =>
      execute(api.reverse(id, payload)),
  }
}
