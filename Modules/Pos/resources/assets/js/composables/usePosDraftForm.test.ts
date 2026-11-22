import { effectScope, nextTick, ref } from 'vue'
import { expect, it, vi } from 'vitest'
import { usePosDraftForm } from '@/modules/pos/composables/usePosDraftForm'
import type { PosForm } from '@/modules/pos/contracts/posViewer'
import type { UseCart } from '@/modules/cart/composables/cart'

it('clears table/edit selections when another tab resets the draft', async () => {
  const form = ref({ loading: false, mode: 'edit', table: { id: 3 } } as PosForm)
  const draft = ref<any>({ context: { form: { mode: 'edit', table: { id: 3 } } } })
  const cart = { draft, setContext: vi.fn(async () => true) }
  const reset = vi.fn(() => {
    form.value.mode = 'create'
    form.value.table = null
  })
  const scope = effectScope()
  scope.run(() => usePosDraftForm(cart as unknown as UseCart, form, reset))
  draft.value = { context: {}, lines: [], order: null }
  await nextTick()
  expect(reset).toHaveBeenCalledOnce()
  expect(form.value.mode).toBe('create')
  expect(form.value.table).toBeNull()
  scope.stop()
})

it('does not restore an older local write over a newly entered gift-card code', async () => {
  const form = ref({
    loading: false,
    refundPaymentMethod: 'gift_card',
    refundGiftCardCode: '',
  } as PosForm)
  const draft = ref({ context: { form: {} } })
  const commits: Array<() => void> = []
  const cart = {
    draft,
    setContext: vi.fn(
      (context) =>
        new Promise<boolean>((resolve) => {
          commits.push(() => {
            draft.value.context = context
            resolve(true)
          })
        }),
    ),
  }
  const scope = effectScope()
  scope.run(() => usePosDraftForm(cart as unknown as UseCart, form))
  form.value.refundGiftCardCode = 'GC-A'
  await nextTick()
  form.value.refundGiftCardCode = 'GC-ABCDE'
  await nextTick()
  commits[0]!()
  await nextTick()
  expect(form.value.refundGiftCardCode).toBe('GC-ABCDE')
  commits[1]!()
  await nextTick()
  await nextTick()
  expect(form.value.refundGiftCardCode).toBe('GC-ABCDE')
  expect(cart.setContext).toHaveBeenCalledTimes(2)
  draft.value.context = { form: { refundGiftCardCode: 'EXTERNAL-CARD' } }
  await nextTick()
  expect(form.value.refundGiftCardCode).toBe('EXTERNAL-CARD')
  scope.stop()
})
