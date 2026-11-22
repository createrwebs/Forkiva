import { getApiErrorStatus } from '@/modules/core/api/errors'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import * as inventoryCountApi from '@/modules/inventory/api/inventoryCount.api.ts'

export function useInventoryCount() {
  const toast = useToast()
  const { t } = useI18n()

  async function getInventoryCount(id: number): Promise<Record<string, any>> {
    try {
      const response = await inventoryCountApi.show(id)
      return { status: 200, data: response.data.body }
    } catch (error: unknown) {
      if (getApiErrorStatus(error) !== 404) {
        toast.error(t('core::errors.an_unexpected_error_occurred'))
      }
      return { status: getApiErrorStatus(error) }
    }
  }

  return {
    getInventoryCount,
    getInventoryCountFormMeta: inventoryCountApi.getFormMeta,
    storeInventoryCount: inventoryCountApi.store,
    updateInventoryCount: inventoryCountApi.update,
    approveInventoryCount: inventoryCountApi.approve,
    cancelInventoryCount: inventoryCountApi.cancel,
  }
}
