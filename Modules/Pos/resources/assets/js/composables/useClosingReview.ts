import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import {
  getApiErrorMessage,
  getApiErrorStatus,
  getApiValidationErrors,
  getApiResponseMessage,
} from '@/modules/core/api/errors'
import { close, closingPreview } from '@/modules/pos/api/session.api'
import type { ClosingPreview } from '@/modules/pos/contracts/sessionReconciliation'

export function useClosingReview(
  sessionId: () => number,
  isOpen: () => boolean,
  onSaved: () => void,
) {
  const { t } = useI18n()
  const toast = useToast()
  const preview = ref<ClosingPreview | null>(null)
  const declaredCash = ref('')
  const closingReason = ref('')
  const varianceReason = ref('')
  const reasonErrors = ref<Record<string, string>>({})
  const step = ref<'count' | 'confirm'>('count')
  const acknowledged = ref(false)
  const loading = ref(false)
  const saving = ref(false)
  const error = ref('')
  const countError = ref('')
  const unavailable = ref(false)
  let version = 0
  const reviewedCash = ref<string | null>(null)

  const validCount = computed(() => /^\d+(?:\.\d+)?$/.test(declaredCash.value.trim()))
  const canReview = computed(
    () =>
      !!preview.value && validCount.value && !loading.value && !saving.value && !unavailable.value,
  )
  const canConfirm = computed(
    () =>
      step.value === 'confirm' &&
      acknowledged.value &&
      reviewedCash.value === declaredCash.value &&
      !!preview.value?.review.can_close &&
      !!preview.value.review.token &&
      (!preview.value.review.requires_closing_reason || validReason(closingReason.value)) &&
      (!preview.value.review.requires_variance_reason || validReason(varianceReason.value)) &&
      !loading.value &&
      !saving.value &&
      !unavailable.value,
  )

  function validReason(reason: string) {
    const length = [...reason.trim()].length
    return length >= 3 && length <= 1000
  }

  watch(
    [closingReason, varianceReason],
    () => {
      acknowledged.value = false
      reasonErrors.value = {}
    },
    { flush: 'sync' },
  )

  watch(
    declaredCash,
    () => {
      version++
      loading.value = false
      reviewedCash.value = null
      step.value = 'count'
      acknowledged.value = false
      countError.value = ''
      varianceReason.value = ''
      if (preview.value) {
        preview.value.declared_cash = null
        preview.value.cash_over_short = null
        preview.value.review = {
          ...preview.value.review,
          declared_cash_decimal: null,
          cash_difference: null,
          can_close: false,
          requires_variance_reason: false,
          token: null,
        }
      }
    },
    { flush: 'sync' },
  )

  async function requestPreview(withCount = false, keepError = false) {
    if (!isOpen() || loading.value || saving.value || unavailable.value) return
    const requestVersion = ++version
    const id = sessionId()
    const count = declaredCash.value
    loading.value = true
    acknowledged.value = false
    reviewedCash.value = null
    step.value = 'count'
    countError.value = ''
    if (!keepError) error.value = ''
    try {
      const response = await closingPreview(id, withCount ? count.trim() : undefined)
      if (requestVersion !== version || !isOpen() || id !== sessionId()) return
      preview.value = response.data.body
      if (withCount && preview.value.review.can_close && preview.value.review.token) {
        reviewedCash.value = count
        step.value = 'confirm'
      }
    } catch (failure: unknown) {
      if (requestVersion !== version || !isOpen()) return
      const status = getApiErrorStatus(failure)
      unavailable.value = status === 400 || status === 403 || status === 404
      const validation = getApiValidationErrors(failure)?.declared_cash
      countError.value = Array.isArray(validation) ? (validation[0] ?? '') : (validation ?? '')
      error.value = countError.value
        ? ''
        : unavailable.value
          ? t('pos::pos_sessions.closing.unavailable')
          : getApiErrorMessage(failure) || t('pos::pos_sessions.closing.load_error')
    } finally {
      if (requestVersion === version) loading.value = false
    }
  }

  function reviewCount() {
    if (!validCount.value) {
      countError.value = t('pos::pos_sessions.closing.count_required')
      return
    }
    if (canReview.value) return requestPreview(true)
  }

  function editCount() {
    step.value = 'count'
    acknowledged.value = false
    reviewedCash.value = null
  }

  async function confirmClose() {
    if (
      !canConfirm.value ||
      !preview.value?.review.declared_cash_decimal ||
      !preview.value.review.token
    )
      return
    const requestVersion = version
    const id = sessionId()
    const data = {
      declared_cash: preview.value.review.declared_cash_decimal,
      review_token: preview.value.review.token,
      ...(preview.value.review.requires_closing_reason && closingReason.value.trim()
        ? { closing_reason: closingReason.value.trim() }
        : {}),
      ...(preview.value.review.requires_variance_reason && varianceReason.value.trim()
        ? { variance_reason: varianceReason.value.trim() }
        : {}),
    }
    saving.value = true
    error.value = ''
    reasonErrors.value = {}
    try {
      const response = await close(id, data)
      if (requestVersion !== version || !isOpen() || id !== sessionId()) return
      toast.success(getApiResponseMessage(response) || t('pos::messages.success_closed_session'))
      onSaved()
    } catch (failure: unknown) {
      if (requestVersion !== version || !isOpen()) return
      const status = getApiErrorStatus(failure)
      const validation = getApiValidationErrors(failure)?.declared_cash
      countError.value = Array.isArray(validation) ? (validation[0] ?? '') : (validation ?? '')
      const errors = getApiValidationErrors(failure)
      for (const field of ['closing_reason', 'variance_reason']) {
        const messages = errors?.[field]
        if (messages)
          reasonErrors.value[field] = Array.isArray(messages) ? (messages[0] ?? '') : messages
      }
      editCount()
      if (status === 409) {
        error.value = t('pos::pos_sessions.closing.review_changed')
        saving.value = false
        await requestPreview(false, true)
      } else {
        unavailable.value = status === 400 || status === 403 || status === 404
        error.value = unavailable.value
          ? t('pos::pos_sessions.closing.unavailable')
          : getApiErrorMessage(failure) || t('pos::pos_sessions.closing.load_error')
      }
    } finally {
      saving.value = false
    }
  }

  watch(
    [sessionId, isOpen],
    () => {
      version++
      preview.value = null
      declaredCash.value = ''
      closingReason.value = ''
      varianceReason.value = ''
      reasonErrors.value = {}
      step.value = 'count'
      acknowledged.value = false
      loading.value = false
      saving.value = false
      error.value = ''
      countError.value = ''
      unavailable.value = false
      reviewedCash.value = null
      if (isOpen()) void requestPreview()
    },
    { immediate: true },
  )
  onBeforeUnmount(() => {
    version++
  })

  return {
    preview,
    declaredCash,
    closingReason,
    varianceReason,
    reasonErrors,
    step,
    acknowledged,
    loading,
    saving,
    error,
    countError,
    unavailable,
    canReview,
    canConfirm,
    reviewCount,
    confirmClose,
    editCount,
    refresh: () => requestPreview(),
  }
}
