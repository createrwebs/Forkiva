import { DraftConflict } from '@/modules/cart/services/draftStorage'
import type { Draft } from '@/modules/cart/contracts/draft'
import type { Cart, CartCharge, CartTax, MoneyValue } from '@/modules/cart/contracts/cart'
import type { UseCart } from '@/modules/cart/composables/cart'
import { generateUuid } from '@/modules/core/utils/uuid'

/** Persist the exact intent before transport; unknown results must reuse this identity. */
export async function submitDraft(
  cart: UseCart,
  extra: Record<string, any>,
  quote: (payload: any) => Promise<any>,
  send: (payload: any, orderId?: number) => Promise<any>,
  confirmPrice: (total: string, changes?: string) => Promise<boolean> | boolean,
  singleRequest = false,
) {
  const activeKey = cart.draft.value?.key
  let pending = cart.draft.value?.submission
  if (!pending)
    pending =
      (await prepareSubmission(cart, extra, quote, confirmPrice, singleRequest)) ?? undefined
  if (!pending) return null
  // Refresh a rejected price quote once, retaining the original idempotency identity.
  for (let attempt = 0; attempt < 2; attempt++) {
    try {
      const response = await send(pending.payload, pending.orderId)
      if (cart.draft.value?.key !== activeKey) throw new DraftConflict('draft_context_changed')
      if (!(await cart.finishSubmission(pending.payload.idempotency_key)))
        throw new Error('submission_result_not_persisted')
      return response
    } catch (error: any) {
      if (cart.draft.value?.key !== activeKey) throw error
      cart.setValidationErrors(error, pending.payload)
      const status = error?.response?.status
      if (
        attempt === 0 &&
        status === 409 &&
        ['quote_expired', 'price_changed'].includes(error.response.data?.errors?.detail)
      ) {
        const renewed = await refreshRejectedQuote(
          cart,
          pending,
          error.response.data.body,
          confirmPrice,
        )
        if (!renewed) return null
        pending = renewed
        continue
      }
      if (status && [400, 401, 403, 404, 409, 410, 422, 429].includes(status)) {
        await cart.setSubmission(undefined)
        if (error.response.data?.errors?.reason === 'quote_changed')
          await cart.setQuote(error.response.data.body)
      } else {
        await cart.setSubmission({ ...pending, state: 'unknown' })
      }
      throw error
    }
  }
}

/** Freeze a reviewed quote and persist its retry identity before sending the order. */
async function prepareSubmission(
  cart: UseCart,
  extra: Record<string, any>,
  quote: (payload: any) => Promise<any>,
  confirmPrice: (total: string, changes?: string) => Promise<boolean> | boolean,
  singleRequest: boolean,
) {
  const key = cart.draft.value?.key
  const revision = cart.draft.value?.revision
  const payload = cart.payload(extra)
  const oldPricing = cart.data.value
  const displayed = singleRequest ? JSON.stringify(displayedPricing(oldPricing)) : null
  if (displayed && displayed.length <= 131072) {
    const pending: NonNullable<Draft['submission']> = {
      payload: {
        ...payload,
        displayed_pricing: displayed,
        idempotency_key: generateUuid(),
      },
      orderId: cart.draft.value?.order?.id,
      state: 'pending',
    }
    if (!(await cart.setSubmission(pending, revision))) throw new Error('submission_not_persisted')
    return pending
  }
  let response
  try {
    response = await quote(payload)
  } catch (error) {
    cart.setValidationErrors(error, payload)
    throw error
  }
  const offer = response.data.body
  if (cart.draft.value?.key !== key || cart.draft.value?.revision !== revision)
    throw new DraftConflict('draft_changed')
  if (!(await cart.setQuote(offer, revision))) throw new DraftConflict('draft_changed')
  const confirmedRevision = cart.draft.value?.revision
  const precision = offer.pricing.total.precision ?? 3
  const comparisonPricing = offer.comparison_pricing ?? offer.pricing
  const different =
    financialFingerprint(oldPricing, precision) !==
    financialFingerprint(comparisonPricing, precision)
  if (
    different &&
    !(await confirmPrice(
      offer.pricing.total.formatted,
      priceChanges(oldPricing, comparisonPricing, precision),
    ))
  )
    return null
  const pending: NonNullable<Draft['submission']> = {
    payload: { ...payload, quote_token: offer.quote_token, idempotency_key: generateUuid() },
    orderId: cart.draft.value?.order?.id,
    state: 'pending',
  }
  if (!(await cart.setSubmission(pending, confirmedRevision)))
    throw new Error('submission_not_persisted')
  return pending
}

/** Persist a refreshed quote and require approval if its financial breakdown changed. */
async function refreshRejectedQuote(
  cart: UseCart,
  pending: NonNullable<Draft['submission']>,
  offer: NonNullable<Draft['quote']>,
  confirmPrice: (total: string, changes?: string) => Promise<boolean> | boolean,
) {
  const previous = cart.data.value
  // This response confirms no order was saved; it is safe to release the old attempt.
  if (!(await cart.setSubmission(undefined))) throw new Error('submission_not_persisted')
  const revision = cart.draft.value?.revision
  if (!(await cart.setQuote(offer, revision))) throw new DraftConflict('draft_changed')
  const confirmedRevision = cart.draft.value?.revision
  const precision = offer.pricing.total.precision ?? 3
  const changed =
    !previous ||
    financialFingerprint(previous, precision) !== financialFingerprint(offer.pricing, precision)
  if (
    changed &&
    !(await confirmPrice(
      offer.pricing.total.formatted,
      priceChanges(previous || {}, offer.pricing, precision),
    ))
  )
    return null
  const renewed = {
    ...pending,
    payload: { ...pending.payload, quote_token: offer.quote_token },
    state: 'pending' as const,
  }
  if (!(await cart.setSubmission(renewed, confirmedRevision)))
    throw new Error('submission_not_persisted')
  return renewed
}

/** Include only the monetary breakdown the cashier could review, never catalog or customer data. */
function displayedPricing(pricing: Cart) {
  const money = (value?: MoneyValue) => ({
    amount: value?.amount,
    currency: value?.currency,
    precision: value?.precision,
  })
  const adjustment = (row: CartTax | CartCharge) => ({
    id: 'id' in row ? row.id : undefined,
    code: 'code' in row ? row.code : undefined,
    rate: 'rate' in row ? row.rate : undefined,
    type: 'type' in row ? row.type : undefined,
    scope: 'scope' in row ? row.scope : undefined,
    amount: money(row.amount),
  })
  return {
    items: pricing.items.map((line) => ({
      line_id: line.id,
      qty: line.qty,
      unitPrice: money(line.unitPrice),
      subtotal: money(line.subtotal),
      taxTotal: money(line.taxTotal),
      total: money(line.total),
      taxes: (line.taxes || []).map(adjustment),
    })),
    quantity: pricing.quantity,
    subTotal: money(pricing.subTotal),
    total: money(pricing.total),
    taxes: pricing.taxes.map(adjustment),
    charges: (pricing.charges || []).map(adjustment),
    discount: pricing.discount?.id
      ? {
          id: pricing.discount.id,
          type: pricing.discount.type,
          value: money(pricing.discount.value ?? pricing.discount.amount),
        }
      : null,
  }
}

/** Compare the financial breakdown at the server currency precision, independently of display formatting. */
function financialFingerprint(pricing: any, precision: number): string {
  const amount = (value: any) => Math.round(Number(value?.amount || 0) * 10 ** precision)
  const adjustments = (rows: any[] = []) =>
    rows
      .map((row) => ({ id: String(row.code ?? row.id), amount: amount(row.amount) }))
      .sort((a, b) => a.id.localeCompare(b.id))
  return JSON.stringify({
    total: amount(pricing.total),
    subtotal: amount(pricing.subTotal),
    discount: amount(pricing.discount?.value ?? pricing.discount?.amount),
    taxes: adjustments(pricing.taxes),
    charges: adjustments(pricing.charges),
    items: (pricing.items || [])
      .map((line: any) => ({
        id: line.line_id || line.id,
        qty: line.qty,
        unit: amount(line.unitPrice),
        subtotal: amount(line.subtotal),
        total: amount(line.total),
        taxes: adjustments(line.taxes),
      }))
      .sort((a: any, b: any) => String(a.id).localeCompare(String(b.id))),
  })
}

/** Describe changed line prices and adjustments for explicit user confirmation. */
function priceChanges(previous: any, current: any, precision: number): string {
  const amount = (value: any) => Math.round(Number(value?.amount || 0) * 10 ** precision)
  const changes: string[] = []
  const before = new Map((previous.items || []).map((line: any) => [line.line_id || line.id, line]))
  for (const line of current.items || []) {
    const old = before.get(line.line_id || line.id) as any
    if (
      !old ||
      amount(old.unitPrice) !== amount(line.unitPrice) ||
      amount(old.total) !== amount(line.total)
    )
      changes.push(
        `${line.item?.name || ''}: ${displayAmount(old?.total)} → ${displayAmount(line.total)}`,
      )
  }
  for (const category of ['taxes', 'charges']) {
    const oldRows = new Map((previous[category] || []).map((row: any) => [row.id ?? row.code, row]))
    for (const row of current[category] || []) {
      const old = oldRows.get(row.id ?? row.code) as any
      if (amount(old?.amount) !== amount(row.amount))
        changes.push(
          `${row.name || row.code}: ${displayAmount(old?.amount)} → ${displayAmount(row.amount)}`,
        )
    }
  }
  return changes.join('\n')
}

/** Render an available formatted amount or a placeholder for a newly added charge. */
function displayAmount(value: any): string {
  return value?.formatted || '—'
}
