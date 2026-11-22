import type { Draft } from '@/modules/cart/contracts/draft'
import type { Cart } from '@/modules/cart/composables/cart'
/** Estimate prices from the cached catalog; an accepted server quote supplies authoritative totals. */
export function estimateDraft(draft: Draft, locale: string): Cart {
  const currency = draft.context.currency || 'JOD'
  const precision =
    draft.context.precision ??
    new Intl.NumberFormat(locale, { style: 'currency', currency }).resolvedOptions()
      .maximumFractionDigits
  const money = (amount: number) => ({
    amount,
    currency,
    precision,
    formatted: new Intl.NumberFormat(locale, {
      style: 'currency',
      currency,
      minimumFractionDigits: precision,
      maximumFractionDigits: precision,
    }).format(amount),
  })
  const applicable = (tax: any) =>
    !tax.order_types?.length || tax.order_types.includes(draft.orderType.id)
  const globals = (draft.context.global_taxes || []).filter(applicable)
  const taxRows = (taxes: any[], base: number, scope: 'product' | 'global') => {
    let prior = 0
    return [...taxes]
      .sort((a, b) => Number(a.compound) - Number(b.compound) || a.id - b.id)
      .map((tax) => {
        const amount =
          ((base + (tax.type !== 'inclusive' && tax.compound ? prior : 0)) * Number(tax.rate)) / 100
        prior += amount
        return { ...tax, scope, amount: money(amount) }
      })
  }
  const items = draft.lines.map((line) => {
    const p = line.product
    const basePrice = Number(p.selling_price?.amount ?? p.price?.amount ?? 0)
    let optionPrice = 0
    const options = (p.options || [])
      .filter((o: any) => line.options[o.id] !== null && line.options[o.id] !== undefined)
      .map((o: any) => {
        const input = line.options[o.id]
        const selected = Array.isArray(input) ? input : [input]
        const field = ['text', 'textarea', 'date', 'time'].includes(o.type?.id ?? o.type)
        const choices = field
          ? o.values?.length
            ? [{ ...o.values[0], label: String(input) }]
            : [{ id: 0, label: String(input), price: 0, price_type: 'fixed' }]
          : (o.values || []).filter((v: any) => selected.map(String).includes(String(v.id)))
        const values = choices.map((v: any) => {
          const amount =
            (v.price_type?.id ?? v.price_type) === 'fixed'
              ? Number(v.price?.amount ?? v.price ?? 0)
              : (basePrice * Number(v.price?.amount ?? v.price ?? 0)) / 100
          optionPrice += amount
          return { ...v, price: money(amount) }
        })
        return { id: o.id, name: o.name, values }
      })
    const unit = line.gift ? 0 : basePrice + optionPrice
    const productTaxes = (p.pricing_taxes || []).filter(applicable)
    const inclusive = [
      ...new Map(
        [...productTaxes, ...globals]
          .filter((t: any) => t.type === 'inclusive')
          .map((t: any) => [t.id, t]),
      ).values(),
    ].reduce((n: number, t: any) => n + Number(t.rate), 0)
    const subtotal = (unit * line.qty) / (1 + inclusive / 100)
    const taxes = taxRows(productTaxes, subtotal, 'product')
    const taxTotal = taxes.reduce((n, t) => n + t.amount.amount, 0)
    return {
      id: line.line_id,
      item: { id: line.product_id, name: p.name, thumbnail: p.thumbnail },
      qty: line.qty,
      unitPrice: money(unit),
      subtotal: money(subtotal),
      taxTotal: money(taxTotal),
      total: money(subtotal + taxTotal),
      taxes,
      options,
      loyaltyGift: line.gift,
      actions: Object.entries(line.actions || {}).map(([id, quantity]) => ({ id, quantity })),
      orderProduct: line.order_product_id
        ? { id: line.order_product_id, status: { id: line.status || 'pending' } }
        : undefined,
    }
  })
  const subtotal = items.reduce((n, item) => n + item.subtotal.amount, 0)
  const taxes = taxRows(globals, subtotal, 'global')
  const total =
    subtotal +
    items.reduce((n, item) => n + item.taxTotal.amount, 0) +
    taxes.reduce((n, t) => n + t.amount.amount, 0)
  const cart: Cart = {
    items,
    quantity: items.reduce((n, item) => n + item.qty, 0),
    subTotal: money(subtotal),
    taxes,
    charges: [],
    total: money(total),
    orderType: draft.orderType,
    customer: draft.customer,
    order: draft.order,
    deliveryContext: draft.delivery
      ? {
          ...draft.deliveryDisplay,
          customerAddress: draft.delivery.customer_address_id
            ? { ...draft.deliveryDisplay?.customerAddress, id: draft.delivery.customer_address_id }
            : null,
          deliveryMethod: draft.delivery.delivery_method,
          paymentCollectionChannel: draft.delivery.payment_collection_channel,
          note: draft.delivery.note,
          pricing: draft.deliveryDisplay?.pricing || null,
        }
      : null,
    discount: null,
  }
  // An appended table order is priced together with the saved order for submission security.
  // Its cart still presents only the guest's current, editable selection.
  const q =
    (draft.channel === 'table_order'
      ? draft.quote?.comparison_pricing || draft.quote?.pricing
      : draft.quote?.pricing) || draft.pricingSnapshot
  if (q) {
    if (draft.channel === 'table_order' && q.items) {
      const quoted = q.items.map((item: any) => ({ ...item, id: item.line_id || item.id }))
      const byId = new Map(quoted.map((item: any) => [item.id, item]))
      // Keep draft order and edit controls even if an older quote lacks a matching line.
      cart.items = items.map((item) => (byId.get(item.id) as typeof item | undefined) || item)
    }
    if (draft.channel === 'pos' && q.items) {
      cart.items = q.items.map((item: any) => ({ ...item, id: item.line_id || item.id }))
      cart.quantity = q.quantity
    }
    Object.assign(cart, {
      subTotal: q.subTotal,
      taxes: q.taxes,
      charges: q.charges,
      total: q.total,
      discount: q.discount,
      deliveryContext: q.deliveryContext,
    })
  }
  return cart
}
