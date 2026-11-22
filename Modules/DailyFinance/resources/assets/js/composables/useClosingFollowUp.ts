import { onBeforeUnmount, ref, watch } from 'vue'
import { reviewClosing } from '../api/dayClosing.api'
import type { DayClosingReview } from '../contracts/dayClosing'

/** Read the selected day's authoritative closing checks without preparing a closing request. */
export function useClosingFollowUp(
  branchId: () => number | null,
  date: () => string | null,
  enabled: () => boolean,
) {
  const summary = ref<DayClosingReview | null>(null)
  const loading = ref(false)
  const error = ref(false)
  let revision = 0
  let controller: AbortController | null = null

  async function load() {
    const current = ++revision
    controller?.abort()
    summary.value = null
    error.value = false
    const id = branchId()
    const day = date()
    if (!id || !day || !enabled()) {
      loading.value = false
      return
    }
    controller = new AbortController()
    const signal = controller.signal
    loading.value = true
    try {
      const response = await reviewClosing(id, day, signal)
      if (current === revision) summary.value = response.data.body
    } catch {
      if (current === revision && !signal.aborted) error.value = true
    } finally {
      if (current === revision) loading.value = false
    }
  }

  /** Reuse a successful drawer review only while its branch and day still match the page. */
  function acceptReview(review: DayClosingReview) {
    if (
      !enabled() ||
      review.summary.meta.branch_id !== branchId() ||
      review.summary.meta.date !== date()
    )
      return
    revision++
    controller?.abort()
    summary.value = review
    error.value = false
    loading.value = false
  }

  watch(
    [branchId, date, enabled],
    () => {
      void load()
    },
    { immediate: true },
  )
  onBeforeUnmount(() => {
    revision++
    controller?.abort()
  })
  return { summary, loading, error, load, acceptReview }
}
