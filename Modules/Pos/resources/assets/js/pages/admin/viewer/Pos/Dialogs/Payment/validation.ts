export type PaymentInput = {
  method: string | null
  amount: number | string | null
  gift_card_code: string | null
}

export function validatePayment(
  payments: PaymentInput[],
  mode: string,
  given: string,
  due: number,
  methods: string[],
  precision: number,
): Record<string, string> {
  const errors: Record<string, string> = {}
  const units = (amount: number) => Math.round(amount * 10 ** precision)
  if (!payments.length) errors.payments = 'payment_required'
  payments.forEach((payment, index) => {
    if (!payment.method || !methods.includes(payment.method)) {
      errors[`payments.${index}.method`] = 'payment_method_required'
    }
    const amount = Number(payment.amount)
    if (!Number.isFinite(amount) || amount < 0.0001 || amount > 99999999999999) {
      errors[`payments.${index}.amount`] = 'payment_amount_positive'
    }
    if (payment.method === 'gift_card' && !payment.gift_card_code?.trim()) {
      errors[`payments.${index}.gift_card_code`] = 'payment_gift_code_required'
    }
  })
  if (!['full', 'partial'].includes(mode)) errors.payment_mode = 'payment_mode_required'
  const total = payments.reduce((sum, payment) => sum + units(Number(payment.amount)), 0)
  if (!Object.keys(errors).some((key) => key.endsWith('.amount')) && payments.length) {
    if (total <= 0) {
      errors['payments.0.amount'] = 'payment_amount_positive'
    } else if (mode === 'full' && total !== units(due)) {
      errors[`payments.${payments.length - 1}.amount`] = 'payment_full_total'
    } else if (mode === 'partial' && total >= units(due)) {
      errors[`payments.${payments.length - 1}.amount`] = 'payment_partial_total'
    }
  }
  const cash = payments
    .filter((payment) => payment.method === 'cash')
    .reduce((sum, payment) => sum + Number(payment.amount), 0)
  if (
    cash > 0 &&
    (!given.trim() || !Number.isFinite(Number(given)) || units(Number(given)) < units(cash))
  ) {
    errors.customer_given_amount = 'payment_cash_short'
  }
  return errors
}
