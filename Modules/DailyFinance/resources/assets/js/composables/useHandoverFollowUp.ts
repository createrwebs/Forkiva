import { onBeforeUnmount, ref, watch } from 'vue'
import { handoverFollowUp } from '../api/cashHandover.api'
import type { CashHandoverFollowUp } from '../contracts/cashHandover'

/** Read current branch custody balances without tying them to the selected sales day. */
export function useHandoverFollowUp(branchId: () => number | null, enabled: () => boolean) {
  const summary = ref<CashHandoverFollowUp | null>(null)
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
    if (!id || !enabled()) {
      loading.value = false
      return
    }
    controller = new AbortController()
    const signal = controller.signal
    loading.value = true
    try {
      const response = await handoverFollowUp(id, signal)
      if (current === revision) summary.value = response.data.body
    } catch {
      if (current === revision && !signal.aborted) error.value = true
    } finally {
      if (current === revision) loading.value = false
    }
  }

  watch(
    [branchId, enabled],
    () => {
      void load()
    },
    { immediate: true },
  )
  onBeforeUnmount(() => {
    revision++
    controller?.abort()
  })
  return { summary, loading, error, load }
}
