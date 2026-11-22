import { reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import {
  getApiErrorMessage,
  getApiErrorStatus,
  getApiErrorUrl,
  getApiResponseMessage,
  getApiValidationErrors,
} from '@/modules/core/api/errors'

interface FormSubmitOptions {
  withResponse?: boolean
  showErrorToast?: boolean
  showSuccessToast?: boolean
}

export function useForm<T extends object>(initialFields: T) {
  const { t } = useI18n()
  const state = reactive<T>({ ...initialFields })
  const loading = ref(false)

  const errors = ref<Record<keyof T | 'general' | string, string>>(
    Object.keys(initialFields).reduce(
      (acc, key) => {
        acc[key as keyof T] = ''
        return acc
      },
      {} as Record<keyof T | string, string>,
    ),
  )

  function resetErrors() {
    for (const key in errors.value) {
      errors.value[key] = ''
    }
  }

  function showErrorToast(
    message: string,
    options: FormSubmitOptions,
    toast: ReturnType<typeof useToast>,
  ) {
    if (options.showErrorToast !== false) {
      toast.error(message)
    }
  }

  function handleSubmitError(
    error: unknown,
    options: FormSubmitOptions,
    toast: ReturnType<typeof useToast>,
  ) {
    resetErrors()
    const status = getApiErrorStatus(error)
    const message = getApiErrorMessage(error)
    const apiErrors = getApiValidationErrors(error)
    const isLoginRequest = getApiErrorUrl(error)?.includes('/auth/login')

    if ((status === 422 || isLoginRequest) && apiErrors) {
      const formKeys = new Set(Object.keys(state))
      const nonFormErrors: string[] = []

      for (const field in apiErrors) {
        const rootField = field.split('.', 1)[0]!
        const message = Array.isArray(apiErrors[field]) ? apiErrors[field][0] : apiErrors[field]

        if (formKeys.has(rootField) && message) {
          errors.value[field as keyof T] = message
        } else if (message) {
          nonFormErrors.push(message)
        }
      }

      if (nonFormErrors.length > 0) {
        errors.value.general = nonFormErrors[0]!
        showErrorToast(nonFormErrors[0]!, options, toast)
      }
    } else if (message) {
      errors.value.general = message
      if (isLoginRequest) {
        errors.value['identifier'] = message
      } else {
        showErrorToast(message, options, toast)
      }
    } else if (status === 401) {
      errors.value.general = t('core::errors.unauthorized')
      showErrorToast(errors.value.general, options, toast)
    } else {
      errors.value.general = t('core::errors.an_unexpected_error_occurred')
      showErrorToast(errors.value.general, options, toast)
    }
  }

  async function submit<TResult>(
    callback: () => Promise<TResult>,
    options: boolean | FormSubmitOptions = false,
  ): Promise<boolean | TResult> {
    loading.value = true
    const toast = useToast()
    const submitOptions: FormSubmitOptions =
      typeof options === 'boolean' ? { withResponse: options } : options

    try {
      const response = await callback()
      const message = getApiResponseMessage(response)
      if (message && submitOptions.showSuccessToast !== false) {
        toast.success(message)
      }
      resetErrors()
      if (submitOptions.withResponse) {
        return response
      }
    } catch (error: unknown) {
      handleSubmitError(error, submitOptions, toast)
      return false
    } finally {
      loading.value = false
    }
    return true
  }

  return {
    state,
    loading,
    errors,
    resetErrors,
    submit,
  }
}
