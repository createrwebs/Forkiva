import { watch, type Ref } from 'vue'
import type { UseCart } from '@/modules/cart/composables/cart'
import type { PosForm } from '@/modules/pos/contracts/posViewer'

/** Persist POS form selections without replaying older local writes over newer keystrokes. */
export function usePosDraftForm(cart: UseCart, form: Ref<PosForm>, resetForm?: () => void) {
  let pendingWrites = 0
  let restoreAfterFailure = false

  /** Apply external draft updates only when local form writes have settled. */
  function restore() {
    const saved = cart.draft.value?.context.form
    if (form.value.loading || pendingWrites) return
    if (!saved) {
      // A refresh in another tab clears the draft and its table/edit selections together.
      const draft = cart.draft.value
      if (draft && !draft.lines.length && !draft.order && !draft.submission) resetForm?.()
      return
    }
    for (const [key, value] of Object.entries(saved)) {
      if (JSON.stringify((form.value as any)[key]) !== JSON.stringify(value))
        (form.value as any)[key] = value
    }
  }

  watch(() => cart.draft.value?.context.form, restore)
  watch(
    () => ({
      mode: form.value.mode,
      menuId: form.value.menuId,
      meta: form.value.meta,
      table: form.value.table,
      waiter: form.value.waiter,
      refundPaymentMethod: form.value.refundPaymentMethod,
      refundGiftCardCode: form.value.refundGiftCardCode ?? null,
    }),
    async (value) => {
      if (!cart.draft.value || form.value.loading || cart.draft.value.submission) return
      pendingWrites++
      try {
        if (!(await cart.setContext({ form: JSON.parse(JSON.stringify(value)) })))
          restoreAfterFailure = true
      } catch {
        // The cart reports storage errors; restore its authoritative state after queued writes settle.
        restoreAfterFailure = true
      } finally {
        pendingWrites--
        if (!pendingWrites && restoreAfterFailure) {
          restoreAfterFailure = false
          restore()
        }
      }
    },
    { deep: true },
  )
}
