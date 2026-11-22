import type {
  CartItem,
  CartTax,
  CartTaxScope,
  MoneyValue,
} from '@/modules/cart/composables/cart.ts'
import { formatPrice } from '@/modules/core/utils/formatters.ts'

export interface CartTaxSummary extends Omit<CartTax, 'amount' | 'scope'> {
  key: string
  scope: CartTaxScope
  amount: MoneyValue
}

function formatTaxAmount(amount: number, money: MoneyValue): string {
  if (!money.currency) {
    return amount.toFixed(money.precision ?? 2)
  }

  return formatPrice(amount, money.currency, money.precision ?? 2)
}

/**
 * Build one display-safe list without mixing product taxes with global taxes.
 * Repeated product taxes are aggregated across cart lines.
 */
export function summarizeCartTaxes(
  cart: { items: Array<Pick<CartItem, 'taxes'>>; taxes: CartTax[] } | null | undefined,
): CartTaxSummary[] {
  if (!cart) {
    return []
  }

  const taxes: Array<CartTax & { scope: CartTaxScope }> = [
    ...(cart.items ?? []).flatMap((item) =>
      (item.taxes ?? []).map((tax) => ({ ...tax, scope: tax.scope ?? 'product' })),
    ),
    ...(cart.taxes ?? []).map((tax) => ({ ...tax, scope: tax.scope ?? 'global' })),
  ]

  const summary = new Map<string, CartTaxSummary>()

  for (const tax of taxes) {
    const key = `${tax.scope}:${tax.id}`
    const existing = summary.get(key)

    if (!existing) {
      summary.set(key, {
        ...tax,
        key,
        amount: { ...tax.amount },
      })
      continue
    }

    const amount = existing.amount.amount + tax.amount.amount
    existing.amount = {
      ...existing.amount,
      amount,
      formatted: formatTaxAmount(amount, existing.amount),
    }
  }

  return [...summary.values()]
}
