import { getApiErrorStatus } from '@/modules/core/api/errors'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { show } from '@/modules/invoice/api/invoice.api.ts'

export function useInvoice() {
  const { t } = useI18n()

  const getShowData = async (id: number | string): Promise<Record<string, any>> => {
    try {
      return { status: 200, data: (await show(id)).data.body }
    } catch (error: unknown) {
      if (getApiErrorStatus(error) !== 404) {
        useToast().error(t('core::errors.an_unexpected_error_occurred'))
      }
      return { status: getApiErrorStatus(error) }
    }
  }

  const print = (invoice: Record<string, any>) => {
    window.open(invoice.pdf_url)
  }

  const download = (invoice: Record<string, any>) => {
    window.open(invoice.download_url)
  }

  return {
    print,
    download,
    getShowData,
  }
}
