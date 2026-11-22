import { describe, expect, it } from 'vitest'
import { validatePayment, type PaymentInput } from './validation'
const payment = (amount: number | string | null, method = 'card'): PaymentInput => ({
  amount,
  method,
  gift_card_code: null,
})
const check = (payments: PaymentInput[], mode = 'full', given = '', due = 100, precision = 2) =>
  validatePayment(payments, mode, given, due, ['cash', 'card', 'gift_card'], precision)
describe('payment validation', () => {
  it('accepts full, split, partial and exact cash payments', () => {
    expect(check([payment(100)])).toEqual({})
    expect(check([payment(40, 'cash'), payment(60)], 'full', '50')).toEqual({})
    expect(check([payment(40)], 'partial')).toEqual({})
    expect(check([payment(100, 'cash')], 'full', '100')).toEqual({})
  })
  it.each([null, '', 0, -1, 'abc', Infinity, 1e14])('identifies invalid amount %s', (amount) => {
    expect(check([payment(amount)])['payments.0.amount']).toBe('payment_amount_positive')
  })
  it('identifies missing method, gift code and empty payments', () => {
    expect(check([payment(100, 'missing')])['payments.0.method']).toBe('payment_method_required')
    expect(check([payment(100, 'gift_card')])['payments.0.gift_card_code']).toBe(
      'payment_gift_code_required',
    )
    expect(check([]).payments).toBe('payment_required')
  })
  it('points to cash received when missing, invalid or short', () => {
    for (const given of ['', 'abc', '99.99'])
      expect(check([payment(100, 'cash')], 'full', given).customer_given_amount).toBe(
        'payment_cash_short',
      )
  })
  it('explains mismatched totals and partial payments equal to the balance', () => {
    expect(check([payment(99)])['payments.0.amount']).toBe('payment_full_total')
    expect(check([payment(101)])['payments.0.amount']).toBe('payment_full_total')
    expect(check([payment(100)], 'partial')['payments.0.amount']).toBe('payment_partial_total')
  })
  it('matches server rounding per payment in currency minor units', () => {
    expect(check([payment(0.104), payment(0.104)], 'full', '', 0.2)).toEqual({})
    expect(check([payment(0.1), payment(0.2)], 'full', '', 0.3)).toEqual({})
    expect(check([payment(0.101), payment(0.202)], 'full', '', 0.303, 3)).toEqual({})
    expect(check([payment(0.0001)], 'partial')['payments.0.amount']).toBe('payment_amount_positive')
  })
})
