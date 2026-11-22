import { onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  getApiErrorMessage,
  getApiErrorStatus,
  getApiValidationErrors,
} from '@/modules/core/api/errors'
import { closeDay, closingHistory, reviewClosing } from '../api/dayClosing.api'
import type { CloseDay, DayClosingHistory, DayClosingReview } from '../contracts/dayClosing'

export function useDayClosings(context: () => { branchId: number; date: string }) {
  const { t } = useI18n()
  const review = ref<DayClosingReview | null>(null)
  const history = ref<DayClosingHistory | null>(null)
  const loading = ref(false)
  const refreshing = ref(false)
  const saving = ref(false)
  const uncertain = ref(false)
  const error = ref('')
  const errors = ref<Record<string, string | string[]>>({})
  let revision = 0
  let controller: AbortController | null = null
  let submitted: CloseDay | null = null
  let requestKey = crypto.randomUUID()

  async function load(tab: 'review' | 'history' = 'review', page = 1, preserveReview = false) {
    if (saving.value || uncertain.value) return

    const current = ++revision
    controller?.abort()
    const signal = (controller = new AbortController()).signal
    loading.value = true
    refreshing.value = preserveReview && tab === 'review' && review.value !== null
    error.value = ''
    errors.value = {}
    if (!refreshing.value) review.value = null
    history.value = null
    try {
      const { branchId, date } = context()
      if (tab === 'history') {
        const response = await closingHistory(branchId, page, signal)
        if (current === revision) history.value = response.data.body
      } else {
        const response = await reviewClosing(branchId, date, signal)
        if (current === revision) review.value = response.data.body
      }
    } catch (failure: unknown) {
      if (current === revision && !signal.aborted) {
        if (refreshing.value && review.value) review.value.can_close = false
        error.value = getApiErrorMessage(failure) || t('dailyfinance::day_closings.load_error')
      }
    } finally {
      if (current === revision) {
        loading.value = false
        refreshing.value = false
      }
    }
  }

  function refresh() {
    if (loading.value) return

    return load('review', 1, true)
  }

  async function save(notes: string | null) {
    if (saving.value || loading.value || (!uncertain.value && !review.value?.can_close))
      return false
    if (!uncertain.value) {
      const { branchId, date } = context()
      submitted = {
        branch_id: branchId,
        date,
        review_hash: review.value!.review_hash,
        request_key: requestKey,
        notes,
      }
    }
    if (!submitted) return false

    saving.value = true
    error.value = ''
    errors.value = {}
    try {
      const response = await closeDay(submitted)
      if (review.value) {
        review.value.closing = response.data.body
        review.value.summary = response.data.body.summary
        review.value.can_close = false
      }
      uncertain.value = false
      submitted = null
      requestKey = crypto.randomUUID()

      return true
    } catch (failure: unknown) {
      errors.value = getApiValidationErrors(failure) || {}
      error.value = getApiErrorMessage(failure) || t('dailyfinance::day_closings.save_error')
      const status = getApiErrorStatus(failure)
      uncertain.value = status === undefined || status >= 500
      if (status === 409 && review.value) review.value.can_close = false

      return false
    } finally {
      saving.value = false
    }
  }

  watch(
    context,
    () => {
      void load()
    },
    { immediate: true },
  )
  onBeforeUnmount(() => {
    revision++
    controller?.abort()
  })

  return {
    review,
    history,
    loading,
    refreshing,
    saving,
    uncertain,
    error,
    errors,
    load,
    refresh,
    save,
  }
}
