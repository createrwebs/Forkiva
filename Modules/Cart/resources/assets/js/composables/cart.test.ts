import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { ref, reactive } from 'vue'
import { useCart } from '@/modules/cart/composables/cart'
import { submitDraft } from '@/modules/cart/services/submitDraft'
import { writeDraft, DraftConflict } from '@/modules/cart/services/draftStorage'

const state = vi.hoisted(() => ({ records: new Map<string, any>(), auth: null as any }))
vi.mock('@/modules/auth/stores/authStore', () => ({ useAuthStore: () => state.auth }))
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key, locale: ref('en') }) }))
vi.mock('vue-toastification', () => ({ useToast: () => ({ error: vi.fn() }) }))
vi.mock('@/modules/cart/services/draftStorage', () => ({
  listDrafts: vi.fn(async () => []),
  moveDraft: vi.fn(),
  DraftConflict: class DraftConflict extends Error {},
  readDraft: vi.fn(async (key: string) => structuredClone(state.records.get(key))),
  writeDraft: vi.fn(async (draft: any, expected: number) => {
    if ((state.records.get(draft.key)?.revision ?? 0) !== expected) throw new Error('conflict')
    const saved = structuredClone({ ...draft, revision: expected + 1 })
    state.records.set(draft.key, saved)
    return saved
  }),
}))
const product = { id: 1, name: 'Soup', selling_price: { amount: 10 }, options: [] }
async function cart(channel: 'pos' | 'table_order' = 'pos', scope = '1:1:1:1') {
  const result = useCart(channel)
  await result.configure(
    scope,
    { currency: 'JOD', precision: 3 },
    { orderType: { id: 'takeaway', name: 'Takeaway' } },
  )
  return result
}
async function add(draft: ReturnType<typeof useCart>) {
  await draft.storeItem({ product_id: 1, qty: 1, options: {} }, product)
}
beforeEach(() => {
  vi.clearAllMocks()
  state.records.clear()
  setActivePinia(createPinia())
  state.auth = reactive({ activeAccountId: 1, isAuthenticated: true })
  vi.stubGlobal('BroadcastChannel', undefined)
})
afterEach(() => vi.unstubAllGlobals())
describe('local drafts', () => {
  it('starts POS empty on page entry and persists the reset for other tabs', async () => {
    const postMessage = vi.fn()
    vi.stubGlobal(
      'BroadcastChannel',
      class {
        postMessage = postMessage
      },
    )
    const draft = await cart()
    await add(draft)
    const key = draft.draft.value!.key
    const saved = state.records.get(key)
    state.records.set(key, {
      ...saved,
      orderType: { id: 'dine_in', name: 'Dine in' },
      order: { id: 42 },
      editVersion: 'old-version',
      customer: { id: 7 },
      discountId: 1,
      voucherCode: 'OLD',
      giftId: 2,
      delivery: { address_id: 3 },
      deliveryDisplay: { address: 'Old address' },
      quote: { quote_token: 'old-token' },
      pricingSnapshot: structuredClone(draft.data.value),
      context: {
        ...saved.context,
        form: { mode: 'edit', table: { id: 3 }, meta: { notes: 'Old notes' } },
        submitForm: { table_id: 3 },
        editedOrder: { id: 42 },
        refundPaymentMethods: [{ id: 'cash' }],
      },
    })
    const defaultOrderType = { id: 'takeaway', name: 'Takeaway' }
    await draft.configure('1:1:1:1', { defaultOrderType }, undefined, undefined, {
      clearDraft: true,
    })
    expect(draft.data.value.items).toEqual([])
    expect(draft.data.value.total.amount).toBe(0)
    expect(draft.draft.value).toMatchObject({
      order: null,
      customer: null,
      orderType: defaultOrderType,
      context: { editedOrder: null, refundPaymentMethods: [] },
    })
    for (const field of [
      'editVersion',
      'discountId',
      'voucherCode',
      'giftId',
      'delivery',
      'deliveryDisplay',
      'quote',
      'pricingSnapshot',
    ] as const)
      expect(draft.draft.value?.[field]).toBeUndefined()
    expect(draft.draft.value?.context.form).toBeUndefined()
    expect(draft.draft.value?.context.submitForm).toBeUndefined()
    expect(postMessage).toHaveBeenLastCalledWith({ key })
    expect((await cart()).data.value.items).toEqual([])
  })
  it.each(['pending', 'unknown'] as const)(
    'preserves a %s submission when POS starts empty',
    async (submissionState) => {
      const draft = await cart()
      await add(draft)
      await draft.setContext({ form: { table: { id: 3 } } })
      await draft.setSubmission({
        state: submissionState,
        payload: { ...draft.payload(), idempotency_key: 'original-key' },
      })
      const saved = structuredClone(state.records.get(draft.draft.value!.key))
      await draft.configure('1:1:1:1', {}, undefined, undefined, { clearDraft: true })
      expect(draft.draft.value?.submission).toEqual(saved.submission)
      expect(draft.draft.value?.lines).toEqual(saved.lines)
      expect(draft.draft.value?.context.form).toEqual(saved.context.form)
      expect(draft.processing.value).toBe(true)
    },
  )
  it('clears the QR draft on page entry', async () => {
    const draft = await cart('table_order')
    await add(draft)
    await draft.configure('1:1:1:1', {}, undefined, undefined, { clearDraft: true })
    expect(draft.data.value.items).toHaveLength(0)
  })
  it.each(['pending', 'unknown'] as const)(
    'preserves a %s table-order submission when the page reloads',
    async (submissionState) => {
      const draft = await cart('table_order')
      await add(draft)
      await draft.setSubmission({
        state: submissionState,
        payload: { ...draft.payload(), idempotency_key: 'table-order-key' },
      })
      const saved = structuredClone(state.records.get(draft.draft.value!.key))
      await draft.configure('1:1:1:1', {}, undefined, undefined, { clearDraft: true })
      expect(draft.draft.value?.submission).toEqual(saved.submission)
      expect(draft.draft.value?.lines).toEqual(saved.lines)
      expect(draft.processing.value).toBe(true)
    },
  )
  it('preserves the saved draft if clearing storage fails', async () => {
    const draft = await cart()
    await add(draft)
    const saved = structuredClone(state.records.get(draft.draft.value!.key))
    vi.mocked(writeDraft).mockRejectedValueOnce(new Error('quota'))
    await expect(
      draft.configure('1:1:1:1', {}, undefined, undefined, { clearDraft: true }),
    ).rejects.toThrow('quota')
    expect(state.records.get(saved.key)).toEqual(saved)
    expect(draft.data.value.items).toHaveLength(1)
    expect(draft.syncFailed.value).toBe(true)
  })
  it.each([
    ['submit', 'dine_in'],
    ['submit', 'delivery'],
    ['cancel', 'pickup'],
    ['reset', 'pre_order'],
  ])(
    'restores the configured default when a %s POS ticket ends from %s',
    async (action, orderType) => {
      const draft = await cart()
      const defaultOrderType = { id: 'takeaway', name: 'Takeaway' }
      await draft.setContext({ defaultOrderType })
      await draft.storeOrderType(orderType)
      await add(draft)
      if (action === 'submit') {
        await draft.setSubmission({ state: 'pending', payload: { idempotency_key: 'save' } })
        await draft.withConfirmedCart(async () => {
          expect(await draft.finishSubmission('save')).toBe(true)
        })
      } else if (action === 'cancel') await draft.clear()
      else await draft.resetCart()
      expect(draft.data.value.items).toHaveLength(0)
      expect(draft.payload().type).toBe('takeaway')
      const restored = await cart()
      expect(restored.data.value.orderType).toEqual(defaultOrderType)
    },
  )
  it('requires a new selection when POS has no non-table default', async () => {
    const draft = await cart()
    await draft.setContext({ defaultOrderType: { id: null, name: null } })
    await draft.storeOrderType('dine_in')
    await draft.clear()
    expect(draft.payload().type).toBeNull()
  })
  it('stores the configured order type name instead of its ID', async () => {
    const draft = await cart()
    await draft.setContext({
      posConfiguration: {
        order_types: [{ id: 'drive_thru', name: 'Drive-Thru' }],
      },
    })

    await draft.storeOrderType('drive_thru')

    expect(draft.data.value.orderType).toEqual({ id: 'drive_thru', name: 'Drive-Thru' })
  })
  it('keeps the table-order channel dine-in after a confirmed submission', async () => {
    const draft = await cart('table_order')
    await draft.storeOrderType('dine_in')
    await draft.setSubmission({ state: 'pending', payload: { idempotency_key: 'save' } })
    await draft.finishSubmission('save')
    expect(draft.data.value.orderType.id).toBe('dine_in')
  })
  it('restores a saved table order atomically while the edit action holds the cart lock', async () => {
    const draft = await cart()
    await add(draft)
    const snapshot = structuredClone(draft.data.value)
    snapshot.order = { id: 42 }
    snapshot.orderType = { id: 'dine_in', name: 'Dine in' }
    snapshot.items[0]!.orderProduct = { id: 7, status: { id: 'pending' } }
    snapshot.total = { ...snapshot.total, amount: 8, formatted: 'JOD 8.000' }
    const context = { editedOrder: { id: 42 }, form: { mode: 'edit', table: { id: 3 } } }
    await draft.withConfirmedCart(async () => {
      expect(await draft.resetCart(snapshot, 'revision-42', context)).toBe(true)
    })
    expect(draft.draft.value?.lines).toHaveLength(1)
    expect(draft.payload().items[0]!.order_product_id).toBe(7)
    expect(draft.payload().type).toBe('dine_in')
    expect(draft.draft.value?.context.editedOrder).toEqual({ id: 42 })
    expect(draft.data.value.total.amount).toBe(8)
    expect((await cart()).data.value.total.amount).toBe(8)
    await draft.updateItem(draft.data.value.items[0]!.id, 2)
    expect(draft.draft.value?.pricingSnapshot).toBeUndefined()
  })
  it('clears payment metadata when cancelling an edit', async () => {
    const draft = await cart()
    await add(draft)
    await draft.resetCart(draft.data.value, 'edit', {
      editedOrder: { id: 42, total: { amount: 20 }, due_amount: { amount: 0 } },
      refundPaymentMethods: [{ id: 'cash' }],
    })
    await draft.clear()
    expect(draft.draft.value?.context.editedOrder).toBeNull()
    expect(draft.draft.value?.context.refundPaymentMethods).toEqual([])
    expect(draft.data.value.total.amount).toBe(0)
    expect(draft.draft.value?.pricingSnapshot).toBeUndefined()
  })
  it('keeps the previous order and metadata when edit persistence fails', async () => {
    const draft = await cart()
    await add(draft)
    const before = structuredClone(state.records.get(draft.draft.value!.key))
    vi.mocked(writeDraft).mockRejectedValueOnce(new Error('quota'))
    await expect(
      draft.resetCart(draft.data.value, 'new-version', {
        editedOrder: { id: 42 },
        form: { mode: 'edit' },
      }),
    ).rejects.toThrow('quota')
    expect(state.records.get(draft.draft.value!.key)).toEqual(before)
    expect(draft.draft.value?.context.editedOrder).toBeUndefined()
  })
  it('never replaces an order with an unresolved submission', async () => {
    const draft = await cart()
    await add(draft)
    await draft.setSubmission({ state: 'unknown', payload: { idempotency_key: 'pending' } })
    await expect(draft.resetCart()).rejects.toThrow('submission_pending')
    expect(draft.draft.value?.lines).toHaveLength(1)
  })

  it('edits and restores items without network transport', async () => {
    const fetch = vi.fn()
    vi.stubGlobal('fetch', fetch)
    const draft = await cart()
    await add(draft)
    await add(draft)
    expect(draft.data.value.quantity).toBe(2)
    await draft.updateItem(draft.data.value.items[0]!.id, 3)
    const restored = await cart()
    expect(restored.data.value.quantity).toBe(3)
    await restored.deleteItem(restored.data.value.items[0]!.id)
    expect(restored.data.value.items).toEqual([])
    expect(fetch).not.toHaveBeenCalled()
  })
  it('expires table selections and benefits together while preserving unknown submissions', async () => {
    const draft = await cart('table_order', 'table:visitor')
    await add(draft)
    const key = draft.draft.value!.key
    const expired = {
      ...state.records.get(key),
      expiresAt: Date.now() - 1,
      voucherCode: 'OLD-CODE',
      context: { submitForm: { notes: 'Old guest' } },
    }
    state.records.set(key, expired)
    const restored = await cart('table_order', 'table:visitor')
    expect(restored.draft.value?.lines).toEqual([])
    expect(restored.draft.value?.voucherCode).toBeUndefined()
    expect(restored.draft.value?.context.submitForm).toBeUndefined()

    state.records.set(key, {
      ...expired,
      revision: restored.draft.value!.revision,
      submission: { state: 'unknown', payload: { idempotency_key: 'original-key' } },
    })
    const pending = await cart('table_order', 'table:visitor')
    expect(pending.draft.value?.lines).toHaveLength(1)
    expect(pending.draft.value?.submission?.payload.idempotency_key).toBe('original-key')
  })
  it('isolates channels, registers and accounts', async () => {
    const a = await cart()
    await add(a)
    expect((await cart('table_order')).data.value.items).toEqual([])
    expect((await cart('pos', '1:1:2:2')).data.value.items).toEqual([])
    state.auth.activeAccountId = 2
    expect((await cart('pos', '2:1:1:1')).data.value.items).toEqual([])
    await Promise.resolve()
    expect(a.data.value.items).toEqual([])
  })
  it('does not claim success when storage fails', async () => {
    const draft = await cart()
    vi.mocked(writeDraft).mockRejectedValueOnce(new Error('quota'))
    await expect(add(draft)).rejects.toThrow('quota')
    expect(draft.data.value.items).toEqual([])
    expect(draft.syncFailed.value).toBe(true)
  })
  it('rejects fractional quantities and missing option definitions before persistence', async () => {
    const draft = await cart()
    await expect(draft.storeItem({ product_id: 1, qty: 1.1 }, product)).rejects.toBeTruthy()
    await expect(draft.storeItem({ product_id: 1, qty: 1 }, { id: 1 })).rejects.toBeTruthy()
    expect(draft.data.value.items).toEqual([])
  })
  it('invalidates a quote after an item changes', async () => {
    const draft = await cart()
    await add(draft)
    await draft.setQuote({ pricing: { total: { amount: 10 } } })
    await add(draft)
    expect(draft.draft.value?.quote).toBeUndefined()
  })
  it('refreshes delivery charges after a price-affecting item change', async () => {
    const draft = await cart()
    await draft.storeOrderType('delivery')
    await draft.addCustomer(7, { id: 7, name: 'Customer' })
    await add(draft)
    const quote = vi.fn(async () => {
      const pricing = structuredClone(draft.data.value)
      pricing.charges = [
        {
          code: 'delivery_fee',
          name: 'Delivery Fee',
          amount: { amount: 3, formatted: 'JOD 3.000', currency: 'JOD', precision: 3 },
        },
      ]
      pricing.total = {
        ...pricing.total,
        amount: pricing.total.amount + 3,
        formatted: `JOD ${(pricing.total.amount + 3).toFixed(3)}`,
      }
      pricing.deliveryContext = {
        customerAddress: { id: 9 },
        pricing: { available: true, deliveryFee: 3 },
      }
      return { data: { body: { pricing, quote_token: 'delivery-quote' } } }
    })
    draft.setQuoteLoader(quote)

    await draft.updateDeliveryContext({ customer_address_id: 9 })
    expect(draft.data.value.charges[0]?.code).toBe('delivery_fee')

    await draft.updateItem(draft.data.value.items[0]!.id, 2)

    expect(quote).toHaveBeenCalledTimes(2)
    expect(draft.data.value.charges[0]?.amount.amount).toBe(3)
    expect(draft.data.value.total.amount).toBe(23)
  })
  it('keeps an unavailable delivery-address warning after quote validation fails', async () => {
    const draft = await cart()
    await draft.storeOrderType('delivery')
    await draft.addCustomer(7, { id: 7, name: 'Customer' })
    await add(draft)
    draft.setQuoteLoader(
      vi.fn().mockRejectedValue({
        response: {
          status: 422,
          data: {
            errors: {
              customer_address_id: ['The selected address is outside all active delivery zones.'],
            },
          },
        },
      }),
    )

    expect(await draft.updateDeliveryContext({ customer_address_id: 9 })).toBe(false)

    expect(draft.data.value.deliveryContext).toMatchObject({
      customerAddress: { id: 9 },
      pricing: {
        available: false,
        message: 'The selected address is outside all active delivery zones.',
      },
    })
  })
})
describe('submission recovery', () => {
  it('submits a POS draft with its displayed breakdown in one request', async () => {
    const draft = await cart()
    await add(draft)
    const quote = vi.fn()
    const send = vi.fn(async (payload) => {
      expect(draft.draft.value?.submission?.payload).toEqual(payload)
      expect(JSON.parse(payload.displayed_pricing)).toMatchObject({
        total: { amount: 10, currency: 'JOD' },
        items: [{ qty: 1 }],
      })
      return { data: { body: { id: 5 } } }
    })

    await submitDraft(draft, {}, quote, send, vi.fn(), true)

    expect(quote).not.toHaveBeenCalled()
    expect(send).toHaveBeenCalledOnce()
    expect(draft.draft.value?.lines).toHaveLength(0)
  })

  it('asks for approval and retries with the same identity when the displayed price changed', async () => {
    const draft = await cart()
    await add(draft)
    const pricing = structuredClone(draft.data.value)
    pricing.total = { ...pricing.total, amount: 11, formatted: 'JOD 11.000' }
    const conflict = {
      response: {
        status: 409,
        data: {
          errors: { reason: 'quote_changed', detail: 'price_changed' },
          body: { pricing, quote_token: 'new-quote', expires_at: '' },
        },
      },
    }
    const quote = vi.fn()
    const send = vi
      .fn()
      .mockRejectedValueOnce(conflict)
      .mockResolvedValue({ data: { body: { id: 5 } } })
    const confirm = vi.fn(() => true)

    await submitDraft(draft, {}, quote, send, confirm, true)

    expect(quote).not.toHaveBeenCalled()
    expect(confirm).toHaveBeenCalledOnce()
    expect(send).toHaveBeenCalledTimes(2)
    expect(send.mock.calls[1]![0]).toEqual({
      ...send.mock.calls[0]![0],
      quote_token: 'new-quote',
    })
    expect(draft.draft.value?.lines).toHaveLength(0)
  })

  it('keeps the POS draft when a changed displayed price is declined', async () => {
    const draft = await cart()
    await add(draft)
    const pricing = structuredClone(draft.data.value)
    pricing.total = { ...pricing.total, amount: 11, formatted: 'JOD 11.000' }
    const send = vi.fn().mockRejectedValue({
      response: {
        status: 409,
        data: {
          errors: { reason: 'quote_changed', detail: 'price_changed' },
          body: { pricing, quote_token: 'new-quote', expires_at: '' },
        },
      },
    })

    expect(await submitDraft(draft, {}, vi.fn(), send, () => false, true)).toBeNull()
    expect(send).toHaveBeenCalledOnce()
    expect(draft.draft.value?.lines).toHaveLength(1)
    expect(draft.draft.value?.submission).toBeUndefined()
  })

  it('compares an appended table selection without treating saved order lines as changes', async () => {
    const draft = await cart('table_order')
    await add(draft)
    const pricing = structuredClone(draft.data.value)
    const newLine = pricing.items[0]!
    const existing = {
      ...newLine,
      id: 'saved-line',
      line_id: 'saved-line',
      orderProduct: { id: 42 },
    }
    const quote = vi.fn().mockResolvedValue({
      data: {
        body: {
          quote_token: 'append-quote',
          pricing: {
            ...pricing,
            items: [existing, newLine],
            quantity: 2,
            total: { amount: 20, formatted: 'JOD 20.000', precision: 3 },
          },
          comparison_pricing: pricing,
        },
      },
    })
    const send = vi.fn()
    const confirm = vi.fn(() => false)
    await draft.withConfirmedCart(async () => {
      await submitDraft(draft, {}, quote, send, confirm)
    })
    expect(confirm).not.toHaveBeenCalled()
    expect(send).toHaveBeenCalledOnce()
    expect(draft.processing.value).toBe(false)
    expect(draft.draft.value?.submission).toBeUndefined()
    expect(draft.data.value.items).toEqual([])
  })
  it('shows only the current table selection while retaining the complete order quote', async () => {
    const draft = await cart('table_order')
    await add(draft)
    const selectionPricing = structuredClone(draft.data.value)
    const newLine = selectionPricing.items[0]!
    const savedLine = {
      ...newLine,
      id: 'saved-line',
      line_id: 'saved-line',
      orderProduct: { id: 42 },
    }
    const completePricing = {
      ...selectionPricing,
      items: [savedLine, newLine],
      quantity: 2,
      subTotal: { amount: 20, formatted: 'JOD 20.000', precision: 3 },
      total: { amount: 20, formatted: 'JOD 20.000', precision: 3 },
    }

    await draft.setQuote({
      pricing: completePricing,
      comparison_pricing: selectionPricing,
      quote_token: 'append-quote',
      expires_at: '',
    })

    expect(draft.draft.value?.quote?.pricing.items).toHaveLength(2)
    expect(draft.data.value.items).toHaveLength(1)
    expect(draft.data.value.items[0]?.id).toBe(newLine.id)
    expect(draft.data.value.total.amount).toBe(selectionPricing.total.amount)
  })
  it('does not describe numeric representation differences as price changes', async () => {
    const draft = await cart()
    await add(draft)
    const before = structuredClone(draft.data.value)
    before.charges = [
      { id: 1, name: 'Service', amount: { amount: 2.91333333, formatted: 'JOD 2.913' } },
    ] as any
    await draft.setQuote({ pricing: before })
    const pricing = structuredClone(before)
    pricing.total.amount = 11
    pricing.charges[0]!.amount.amount = '2.913' as any
    const confirm = vi.fn(() => false)
    await submitDraft(draft, {}, async () => ({ data: { body: { pricing } } }), vi.fn(), confirm)
    expect(confirm).toHaveBeenCalledWith(pricing.total.formatted, '')
  })

  const offer = {
    data: {
      body: {
        pricing: { total: { amount: 10, formatted: 'JOD 10.000', precision: 3 } },
        quote_token: 'signed',
      },
    },
  }
  it('reuses persisted intent after an unknown result and reload', async () => {
    const draft = await cart()
    await add(draft)
    const quote = vi.fn().mockResolvedValue(offer)
    const send = vi
      .fn()
      .mockRejectedValueOnce(new Error('lost response'))
      .mockResolvedValue({ data: { body: { id: 5 } } })
    await expect(submitDraft(draft, {}, quote, send, () => true)).rejects.toThrow('lost response')
    const pending = draft.draft.value?.submission
    expect(pending?.state).toBe('unknown')
    expect(await draft.updateItem(draft.data.value.items[0]!.id, 2)).toBe(false)
    const restored = await cart()
    await submitDraft(restored, {}, quote, send, () => true)
    expect(quote).toHaveBeenCalledTimes(1)
    expect(send.mock.calls[1]![0]).toEqual(send.mock.calls[0]![0])
    expect(restored.data.value.items).toEqual([])
  })
  it('does not submit until its identity has been saved', async () => {
    const draft = await cart()
    await add(draft)
    const quote = vi.fn(async () => {
      vi.mocked(writeDraft).mockRejectedValueOnce(new Error('quota'))
      return offer
    })
    const send = vi.fn()
    await expect(submitDraft(draft, {}, quote, send, () => true)).rejects.toThrow('quota')
    expect(send).not.toHaveBeenCalled()
    expect(draft.data.value.items).toHaveLength(1)
  })
  it('requires confirmation for a changed price and preserves a declined draft', async () => {
    const draft = await cart()
    await add(draft)
    const quote = vi.fn().mockResolvedValue({
      data: {
        body: {
          ...offer.data.body,
          pricing: { total: { amount: 11, precision: 3, formatted: 'JOD 11.000' } },
        },
      },
    })
    const send = vi.fn(),
      confirm = vi.fn(() => false)
    expect(await submitDraft(draft, {}, quote, send, confirm)).toBeNull()
    expect(confirm).toHaveBeenCalledWith('JOD 11.000', expect.any(String))
    expect(send).not.toHaveBeenCalled()
    expect(draft.data.value.items).toHaveLength(1)
  })
  it('rejects a quote when the draft changes while the request is in flight', async () => {
    const draft = await cart()
    await add(draft)
    const quote = vi.fn(async () => {
      await add(draft)
      return offer
    })
    const send = vi.fn()
    await expect(submitDraft(draft, {}, quote, send, () => true)).rejects.toBeInstanceOf(
      DraftConflict,
    )
    expect(send).not.toHaveBeenCalled()
    expect(draft.data.value.quantity).toBe(2)
  })
})

it('validates option edits locally and preserves the line identity', async () => {
  const draft = await cart()
  const configured = {
    ...product,
    options: [{ id: 2, type: { id: 'select' }, is_required: true, values: [{ id: 3 }, { id: 4 }] }],
  }
  await draft.storeItem({ product_id: 1, qty: 1, options: { 2: 3 } }, configured)
  const id = draft.data.value.items[0]!.id
  expect(() => draft.updateItemOptions(id, { 2: 99 })).toThrow()
  await draft.updateItemOptions(id, { 2: 4 })
  expect(draft.draft.value?.lines[0]?.line_id).toBe(id)
  expect(draft.draft.value?.lines[0]?.options).toEqual({ 2: 4 })
})
it('saves table option and quantity edits atomically and rejects invalid quantities', async () => {
  const draft = await cart('table_order')
  const configured = {
    ...product,
    options: [
      {
        id: 2,
        type: 'select',
        is_required: true,
        values: [
          { id: 3, price_type: 'fixed', price: 1 },
          { id: 4, price_type: 'fixed', price: 2 },
        ],
      },
    ],
  }
  await draft.storeItem({ product_id: 1, qty: 1, options: { 2: 3 } }, configured)
  const id = draft.data.value.items[0]!.id
  const beforeRevision = draft.draft.value!.revision
  await draft.updateItemOptions(id, { 2: 4 }, 3)
  expect(draft.draft.value!.revision).toBe(beforeRevision + 1)
  expect(draft.data.value.total.amount).toBe(36)
  expect(draft.data.value.quantity).toBe(3)
  expect(() => draft.updateItemOptions(id, { 2: 3 }, 100)).toThrow()
  expect(draft.draft.value?.lines[0]?.options).toEqual({ 2: 4 })
  expect(draft.data.value.quantity).toBe(3)
  vi.mocked(writeDraft).mockRejectedValueOnce(new Error('quota'))
  await expect(draft.updateItemOptions(id, { 2: 3 }, 2)).rejects.toThrow('quota')
  expect(draft.data.value.total.amount).toBe(36)
})
it('maps server option errors to a stable line without losing its selections', async () => {
  const draft = await cart()
  await add(draft)
  const payload = draft.payload()
  draft.setValidationErrors(
    { response: { data: { errors: { 'items.0.options.5': ['Choose a size'] } } } },
    payload,
  )
  expect(draft.data.value.items[0]?.validationErrors).toEqual(['Choose a size'])
  expect(draft.data.value.quantity).toBe(1)
})

it.each([
  { changed: false, accept: true, reason: 'quote_expired' },
  { changed: true, accept: true, reason: 'quote_expired' },
  { changed: true, accept: false, reason: 'quote_expired' },
  { changed: false, accept: true, reason: 'price_changed' },
  { changed: true, accept: true, reason: 'price_changed' },
  { changed: true, accept: false, reason: 'price_changed' },
])(
  'renews expired pricing with explicit approval only when needed: %o',
  async ({ changed, accept, reason }) => {
    const draft = await cart()
    await add(draft)
    const pricing = structuredClone(draft.data.value)
    await draft.setQuote({ pricing, quote_token: 'expired', expires_at: '' })
    await draft.setSubmission({
      state: 'unknown',
      payload: { ...draft.payload(), quote_token: 'expired', idempotency_key: 'same-attempt' },
    })
    const renewed = structuredClone(pricing)
    if (changed) renewed.total = { ...renewed.total, amount: 11, formatted: 'JOD 11.000' }
    const expiry = {
      response: {
        status: 409,
        data: {
          errors: { reason: 'quote_changed', detail: reason },
          body: { pricing: renewed, quote_token: 'renewed', expires_at: '' },
        },
      },
    }
    const quote = vi.fn()
    const confirm = vi.fn(async () => accept)
    const send = vi
      .fn()
      .mockRejectedValueOnce(expiry)
      .mockImplementation(async (payload) => {
        expect(draft.draft.value?.submission?.payload).toEqual(payload)
        return { data: { body: { id: 5 } } }
      })
    await submitDraft(draft, {}, quote, send, confirm)
    expect(quote).not.toHaveBeenCalled()
    expect(confirm).toHaveBeenCalledTimes(changed ? 1 : 0)
    expect(send).toHaveBeenCalledTimes(changed && !accept ? 1 : 2)
    if (!changed || accept) {
      expect(send.mock.calls[1]![0]).toEqual({ ...send.mock.calls[0]![0], quote_token: 'renewed' })
      expect(draft.draft.value?.lines).toHaveLength(0)
    } else {
      expect(draft.draft.value?.lines).toHaveLength(1)
    }
    expect(draft.draft.value?.submission).toBeUndefined()
  },
)

it('stops automatic renewal after one retry', async () => {
  const draft = await cart()
  await add(draft)
  const pricing = structuredClone(draft.data.value)
  const offer = { pricing, quote_token: 'renewed', expires_at: '' }
  const expiry = {
    response: {
      status: 409,
      data: { errors: { reason: 'quote_changed', detail: 'quote_expired' }, body: offer },
    },
  }
  const send = vi.fn().mockRejectedValue(expiry)
  await expect(
    submitDraft(draft, {}, async () => ({ data: { body: offer } }), send, vi.fn()),
  ).rejects.toEqual(expiry)
  expect(send).toHaveBeenCalledTimes(2)
  expect(draft.draft.value?.submission).toBeUndefined()
  expect(draft.draft.value?.lines).toHaveLength(1)
})
