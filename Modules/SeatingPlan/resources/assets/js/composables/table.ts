import { getApiErrorStatus } from '@/modules/core/api/errors'
import type { AxiosError } from 'axios'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { useConfirmDialog } from '@/modules/core/composables/confirmDialog.ts'
import {
  assignWaiter,
  getFormMeta,
  getMergeMeta,
  getTableViewer,
  getTableViewerDetails,
  makeAvailable,
  merge,
  regenerateQrToken,
  show,
  split,
  store,
  update,
} from '@/modules/seatingPlan/api/table.api.ts'

export function useTable() {
  const toast = useToast()
  const { t } = useI18n()
  const loadings = reactive({
    makeAvailable: false,
    qrToken: false,
    split: false,
  })

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

  const updateStatusToAvailable = async (tableId: number) => {
    try {
      loadings.makeAvailable = true
      toast.success((await makeAvailable(tableId)).data.message)
    } catch (error: unknown) {
      toast.error(
        (
          error as AxiosError<{
            message?: string
          }>
        ).response?.data?.message || t('core::errors.an_unexpected_error_occurred'),
      )
    } finally {
      loadings.makeAvailable = false
    }
  }

  const splitTable = async (mergeId: number) => {
    if (
      await useConfirmDialog({
        title: t('seatingplan::tables.split_confirmation.title'),
        message: t('seatingplan::tables.split_confirmation.message'),
        confirmButtonText: t('seatingplan::tables.split_confirmation.confirm_button_text'),
      })
    ) {
      try {
        loadings.split = true
        toast.success((await split(mergeId)).data.message)
        return true
      } catch (error: unknown) {
        toast.error(
          (
            error as AxiosError<{
              message?: string
            }>
          ).response?.data?.message || t('core::errors.an_unexpected_error_occurred'),
        )
      } finally {
        loadings.split = false
      }
    }
    return false
  }

  const regenerateTableQrToken = async (tableId: number) => {
    if (
      await useConfirmDialog({
        title: t('seatingplan::tables.regenerate_qr_confirmation.title'),
        message: t('seatingplan::tables.regenerate_qr_confirmation.message'),
        confirmButtonText: t('seatingplan::tables.regenerate_qr_confirmation.confirm_button_text'),
        confirmColor: 'warning',
      })
    ) {
      try {
        loadings.qrToken = true
        const response = await regenerateQrToken(tableId)

        toast.success(response.data.message)
        return response.data.body
      } catch (error: unknown) {
        toast.error(
          (
            error as AxiosError<{
              message?: string
            }>
          ).response?.data?.message || t('core::errors.an_unexpected_error_occurred'),
        )
      } finally {
        loadings.qrToken = false
      }
    }

    return null
  }

  return {
    getShowData,
    update,
    store,
    getFormMeta,
    getTableViewer,
    getTableViewerDetails,
    assignWaiter,
    updateStatusToAvailable,
    loadings,
    getMergeMeta,
    regenerateTableQrToken,
    splitTable,
    merge,
  }
}
