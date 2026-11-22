import { getApiErrorStatus } from '@/modules/core/api/errors'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import * as stockTransferApi from '@/modules/inventory/api/stockTransfer.api.ts'

export function useStockTransfer() {
  const toast = useToast()
  const { t } = useI18n()

  function showActionError(error: any): void {
    const errors = error?.response?.data?.errors
    const firstError = errors ? Object.values(errors).flat().find(Boolean) : null
    const message = firstError || error?.response?.data?.message

    toast.error(String(message || t('core::errors.an_unexpected_error_occurred')))
  }

  async function getStockTransfer(id: number): Promise<Record<string, any>> {
    try {
      const response = await stockTransferApi.show(id)
      return { status: 200, data: response.data.body }
    } catch (error: unknown) {
      if (getApiErrorStatus(error) !== 404) {
        toast.error(t('core::errors.an_unexpected_error_occurred'))
      }
      return { status: getApiErrorStatus(error) }
    }
  }

  return {
    getStockTransfer,
    showActionError,
    getStockTransferFormMeta: stockTransferApi.getFormMeta,
    storeStockTransfer: stockTransferApi.store,
    updateStockTransfer: stockTransferApi.update,
    approveStockTransfer: stockTransferApi.approve,
    dispatchStockTransfer: stockTransferApi.dispatch,
    receiveStockTransfer: stockTransferApi.receive,
    closeStockTransfer: stockTransferApi.close,
    cancelStockTransfer: stockTransferApi.cancel,
    voidStockTransfer: stockTransferApi.voidDispatch,
  }
}
