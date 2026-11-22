import { describe, it, expect } from 'vitest'
import { estimateDraft } from './estimate'
import type { Draft } from '../contracts/draft'
const tax = (id: number, rate: number, type = 'inclusive', compound = false) => ({
  id,
  rate,
  type,
  compound,
})
function calculate(
  price: number,
  productTaxes: any[] = [],
  globalTaxes: any[] = [],
  options: any[] = [],
  selected: Record<string, any> = {},
) {
  const draft = {
    key: 'test',
    schema: 1,
    accountId: 1,
    revision: 0,
    updatedAt: 0,
    expiresAt: null,
    order: null,
    customer: null,
    channel: 'pos',
    context: { currency: 'JOD', precision: 3, global_taxes: globalTaxes },
    lines: [
      {
        line_id: 'test',
        product_id: 1,
        qty: 1,
        options: selected,
        product: {
          name: 'Test',
          selling_price: { amount: price },
          pricing_taxes: productTaxes,
          options,
        },
      },
    ],
    orderType: { id: 'takeaway', name: 'Takeaway' },
  } as Draft
  return estimateDraft(draft, 'en')
}
describe('local estimates match Order PricingCalculationTest reference amounts', () => {
  it.each([
    [115, [], [tax(1, 15)], 100, 115],
    [115, [tax(1, 15)], [], 100, 115],
    [125, [tax(1, 10)], [tax(2, 15)], 100, 125],
    [100, [], [tax(1, 15, 'exclusive')], 100, 115],
    [100, [], [tax(1, 10, 'exclusive'), tax(2, 5, 'exclusive', true)], 100, 115.5],
  ])('extracts inclusive and compounds exclusive taxes (%s)', (price, p, g, subtotal, total) => {
    const result = calculate(price as number, p as any[], g as any[])
    expect(result.subTotal.amount).toBeCloseTo(subtotal as number, 8)
    expect(result.total.amount).toBeCloseTo(total as number, 8)
  })
  it('prices fixed, percentage and text options and keeps JOD precision', () => {
    const result = calculate(
      10,
      [],
      [],
      [
        {
          id: 1,
          type: { id: 'select' },
          values: [{ id: 1, price_type: 'fixed', price: { amount: 0.125 } }],
        },
        { id: 2, type: { id: 'select' }, values: [{ id: 2, price_type: 'percent', price: 10 }] },
        {
          id: 3,
          type: { id: 'text' },
          values: [{ id: 3, price_type: 'fixed', price: { amount: 0.25 } }],
        },
      ],
      { 1: 1, 2: 2, 3: '0' },
    )
    expect(result.total.amount).toBe(11.375)
    expect(result.total.precision).toBe(3)
    expect(result.total.formatted).toContain('11.375')
  })
})
