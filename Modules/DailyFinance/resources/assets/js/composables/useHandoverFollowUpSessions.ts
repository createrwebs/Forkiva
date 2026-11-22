import { onBeforeUnmount, ref, watch } from 'vue'
import { handoverFollowUpSessions } from '../api/cashHandover.api'
import type {
  CashHandoverFollowUpKind,
  CashHandoverFollowUpSessions,
} from '../contracts/cashHandover'

export function useHandoverFollowUpSessions(
  branchId: () => number,
  kind: () => CashHandoverFollowUpKind,
  enabled: () => boolean,
) {
  const result = ref<CashHandoverFollowUpSessions | null>(null)
  const loading = ref(false)
  const error = ref(false)
  const page = ref(1)
  let revision = 0
  let controller: AbortController | null = null

  async function load(nextPage = page.value) {
    const current = ++revision
    controller?.abort()
    result.value = null
    error.value = false
    page.value = nextPage
    if (!enabled()) {
      loading.value = false
      return
    }
    controller = new AbortController()
    const signal = controller.signal
    loading.value = true
    try {
      const response = await handoverFollowUpSessions(branchId(), kind(), nextPage, signal)
      if (current === revision) result.value = response.data.body
    } catch {
      if (current === revision && !signal.aborted) error.value = true
    } finally {
      if (current === revision) loading.value = false
    }
  }

  watch([branchId, kind, enabled], () => void load(1), { immediate: true })
  onBeforeUnmount(() => {
    revision++
    controller?.abort()
  })
  return { result, loading, error, load }
}
