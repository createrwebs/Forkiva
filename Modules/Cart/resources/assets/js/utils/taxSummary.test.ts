import type { Cart, CartTax, MoneyValue } from '@/modules/cart/composables/cart.ts'
import { describe, expect, it } from 'vitest'
import { summarizeCartTaxes } from './taxSummary.ts'

const money = (amount: number): MoneyValue => ({
  amount,
  formatted: `$ ${amount.toFixed(2)}`,
  currency: 'USD',
  precision: 2,
})

const tax = (id: number, amount: number, scope?: 'product' | 'global'): CartTax => ({
  id,
  name: 'VAT',
  rate: 15,
  type: 'inclusive',
  scope,
  amount: money(amount),
})

function cartWithTaxes(globalTaxes: CartTax[], productTaxes: CartTax[][]): Cart {
  return {
    items: productTaxes.map((taxes, index) => ({
      id: String(index + 1),
      item: { name: `Product ${index + 1}` },
      qty: 1,
      unitPrice: money(115),
      subtotal: money(100),
      taxTotal: money(15),
      taxes,
      total: money(115),
      actions: [],
    })),
    quantity: productTaxes.length,
    subTotal: money(100 * productTaxes.length),
    orderType: { id: 'dine-in', name: 'Dine In' },
    taxes: globalTaxes,
    charges: [],
    total: money(115 * productTaxes.length),
    customer: null,
  }
}

describe('summarizeCartTaxes', () => {
  it('aggregates the same product tax across cart lines', () => {
    const result = summarizeCartTaxes(cartWithTaxes([], [[tax(1, 15)], [tax(1, 15)]]))

    expect(result).toHaveLength(1)
    expect(result[0]).toMatchObject({ key: 'product:1', scope: 'product' })
    expect(result[0]?.amount.amount).toBe(30)
    expect(result[0]?.amount.formatted).toBe('$ 30.00')
  })

  it('keeps product and global taxes as separate summary rows', () => {
    const result = summarizeCartTaxes(
      cartWithTaxes([tax(2, 15, 'global')], [[tax(1, 10, 'product')]]),
    )

    expect(result.map(({ key, scope, amount }) => ({ key, scope, amount: amount.amount }))).toEqual(
      [
        { key: 'product:1', scope: 'product', amount: 10 },
        { key: 'global:2', scope: 'global', amount: 15 },
      ],
    )
  })
})
