import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  getApiErrorMessage,
  getApiErrorStatus,
  getApiValidationErrors,
} from '@/modules/core/api/errors'
import type { PaymentSettlement } from '../contracts/paymentSettlement'

export function usePaymentSettlementMutation<T>(
  submit: (payload: T) => Promise<{ data: { body: PaymentSettlement } }>,
) {
  const { t } = useI18n()
  const saving = ref(false)
  const uncertain = ref(false)
  const error = ref('')
  const errors = ref<Record<string, string | string[]>>({})
  let submitted: T | null = null

  async function save(payload: T): Promise<PaymentSettlement | null> {
    if (saving.value) return null
    if (!uncertain.value) submitted = structuredClone(payload)
    if (submitted === null) return null

    saving.value = true
    error.value = ''
    errors.value = {}
    try {
      const response = await submit(submitted)
      uncertain.value = false
      submitted = null

      return response.data.body
    } catch (failure: unknown) {
      error.value = getApiErrorMessage(failure) || t('dailyfinance::payment_settlements.save_error')
      errors.value = getApiValidationErrors(failure) || {}
      const status = getApiErrorStatus(failure)
      uncertain.value = status === undefined || status >= 500

      return null
    } finally {
      saving.value = false
    }
  }

  return { saving, uncertain, error, errors, save }
}
