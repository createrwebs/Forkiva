import { getApiErrorMessage } from '@/modules/core/api/errors'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import {
  exportAllTranslations,
  exportTranslations,
  getTranslations,
  importTranslation,
  previewImportTranslation,
  type TranslationListParams,
  updateTranslation,
} from '@/modules/translation/api/translation.api.ts'

export function useLanguage() {
  const toast = useToast()
  const { t } = useI18n()

  const loadData = async (
    params: TranslationListParams = {},
  ): Promise<Record<string, any> | false> => {
    try {
      return (await getTranslations(params)).data.body
    } catch {
      toast.error(t('core::errors.failed_to_load_data'))
      return false
    }
  }

  const update = async (key: string, locale: string, value: string | null): Promise<boolean> => {
    try {
      const response = await updateTranslation(key, locale, value)
      toast.success(response.data.message)
      return true
    } catch (error: unknown) {
      toast.error(getApiErrorMessage(error) || t('core::errors.an_unexpected_error_occurred'))
      return false
    }
  }

  const downloadBlob = (blob: Blob, filename: string) => {
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', filename)
    document.body.append(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  }

  const exportLocale = async (locale: string, mode: 'values' | 'empty_all' | 'empty_missing') => {
    const response = await exportTranslations({ _locale: locale, mode })
    downloadBlob(response.data, `translations-${locale}-${mode}.json`)
  }

  const exportAll = async (mode: 'values' | 'empty_all' | 'empty_missing') => {
    const response = await exportAllTranslations({ mode })
    downloadBlob(response.data, `translations-${mode}.zip`)
  }

  const parseTitlePage = (title: string | null, params: Record<string, string> | null): string => {
    if (title === null) {
      return ''
    }

    const resolvedParams: Record<string, string> = {}

    if (params) {
      for (const [key, value] of Object.entries(params)) {
        resolvedParams[key] = t(value)
      }
    }

    return t(title, resolvedParams)
  }

  return {
    loadData,
    parseTitlePage,
    update,
    exportLocale,
    exportAll,
    previewImportTranslation,
    importTranslation,
  }
}
