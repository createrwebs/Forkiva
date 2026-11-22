import { onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  getApiErrorMessage,
  getApiErrorStatus,
  getApiValidationErrors,
} from '@/modules/core/api/errors'
import { handovers, createHandover, receiveHandover, cancelHandover } from '../api/cashHandover.api'
import type { CashHandoverSummary, CreateCashHandover } from '../contracts/cashHandover'

export function useCashHandovers(sessionId: () => number) {
  const { t } = useI18n()
  const summary = ref<CashHandoverSummary | null>(null)
  const loading = ref(false)
  const saving = ref(false)
  const error = ref('')
  const errors = ref<Record<string, string | string[]>>({})
  const uncertain = ref(false)
  let revision = 0
  let controller: AbortController | null = null
  let requestKey = crypto.randomUUID()
  let submitted: CreateCashHandover | null = null

  async function load(page = 1) {
    const current = ++revision
    controller?.abort()
    controller = new AbortController()
    loading.value = true
    error.value = ''
    summary.value = null
    try {
      const response = await handovers(sessionId(), page, controller.signal)
      if (current === revision) summary.value = response.data.body
    } catch (failure: unknown) {
      if (current === revision && !controller.signal.aborted)
        error.value = getApiErrorMessage(failure) || t('dailyfinance::cash_handovers.load_error')
    } finally {
      if (current === revision) loading.value = false
    }
  }

  async function mutate(operation: () => Promise<unknown>) {
    if (saving.value || loading.value) return false

    const current = revision
    saving.value = true
    error.value = ''
    errors.value = {}
    try {
      await operation()
      if (current !== revision) return false
      uncertain.value = false
      submitted = null
      requestKey = crypto.randomUUID()
      await load()

      return true
    } catch (failure: unknown) {
      if (current === revision) {
        errors.value = getApiValidationErrors(failure) || {}
        error.value = getApiErrorMessage(failure) || t('dailyfinance::cash_handovers.save_error')
        const status = getApiErrorStatus(failure)
        uncertain.value = status === undefined || status >= 500
      }

      return false
    } finally {
      saving.value = false
    }
  }

  function create(data: Omit<CreateCashHandover, 'request_key'>) {
    if (saving.value || loading.value) return Promise.resolve(false)

    if (!uncertain.value) submitted = { ...data, request_key: requestKey }

    return mutate(() => createHandover(sessionId(), submitted!))
  }

  function receive(id: number, amount: string, reason: string | null) {
    return mutate(() =>
      receiveHandover(sessionId(), id, { received_amount: amount, variance_reason: reason }),
    )
  }

  function cancel(id: number, reason: string) {
    return mutate(() => cancelHandover(sessionId(), id, reason))
  }

  watch(
    sessionId,
    () => {
      submitted = null
      requestKey = crypto.randomUUID()
      uncertain.value = false
      void load()
    },
    { immediate: true },
  )
  onBeforeUnmount(() => {
    revision++
    controller?.abort()
  })

  return { summary, loading, saving, error, errors, uncertain, load, create, receive, cancel }
}
