import type { AxiosError } from 'axios'
import type { RouteLocationRaw } from 'vue-router'
import type { TableAction } from '@/modules/core/contracts/Table.ts'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { http } from '@/modules/core/api/http.ts'

export function useTableDefaultActionClick(resource: string, refresh: () => void) {
  const router = useRouter()

  const toast = useToast()
  const { t } = useI18n()

  const destroy = async (id?: string | number | (string | number)[] | null) => {
    try {
      const apiResource = resolveDestroyResource(resource)
      const response = await http.delete(
        `/v1/${apiResource}/${Array.isArray(id) ? id : (id === null || id === undefined ? [] : [id]).join(',')}`,
      )
      if (response?.data?.message) {
        toast.success(response.data.message)
      }
      refresh()
    } catch (error) {
      const data = (
        error as AxiosError<{
          message?: string
          errors?: Record<string, any>
        }>
      ).response?.data
      useToast().error(
        data?.errors?.ids?.[0] || data?.message || t('core::errors.an_unexpected_error_occurred'),
      )
    }
  }

  const resolveDestroyResource = (resource: string): string => {
    if (resource === 'pos_registers') {
      return 'pos/registers'
    }

    if (resource.startsWith('accounting_')) {
      return `accounting/${resource.replace('accounting_', '').replace(/_/g, '-')}`
    }

    return resource.replace(/_/g, '-')
  }

  const handleClick = async (
    action: TableAction,
    id?: string | number | (string | number)[] | null,
  ) => {
    if (action.key === 'destroy') {
      return await destroy(id)
    }

    return router.push({
      name: `admin.${resource}.${action.key}`,
      params: id ? { id } : {},
    } as unknown as RouteLocationRaw)
  }
  return { handleClick }
}
