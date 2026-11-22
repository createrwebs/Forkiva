import type { Cart, CartItem } from '@/modules/cart/composables/cart.ts'

export interface CustomerDisplayCart extends Pick<
  Cart,
  'quantity' | 'subTotal' | 'orderType' | 'taxes' | 'charges' | 'discount' | 'total'
> {
  orderReference?: string | null
  items: Array<
    Omit<CartItem, 'actions' | 'item' | 'loyaltyGift' | 'orderProduct'> & {
      item: { name: string; thumbnail?: string | null }
      isGift?: boolean
    }
  >
  customer: { name: string; tier: string | null; points: number | null } | null
}

export type CustomerDisplayResponse =
  | { fingerprint: string; not_modified: true }
  | { fingerprint: string; not_modified: false; cart: CustomerDisplayCart }

export type DisplayConnection =
  'connecting' | 'connected' | 'reconnecting' | 'offline' | 'expired' | 'unavailable' | 'unpaired'

function money(value: unknown): boolean {
  if (!value || typeof value !== 'object') return false
  const amount = value as { amount?: unknown; formatted?: unknown }
  return (
    typeof amount.amount === 'number' &&
    Number.isFinite(amount.amount) &&
    typeof amount.formatted === 'string'
  )
}

function optionalString(value: unknown): boolean {
  return value === null || value === undefined || typeof value === 'string'
}

/** A broken response must not replace the last known valid display. */
export function isDisplayResponse(value: unknown): value is CustomerDisplayResponse {
  if (!value || typeof value !== 'object') return false
  const response = value as Record<string, any>
  if (typeof response.fingerprint !== 'string' || !/^[a-f0-9]{64}$/.test(response.fingerprint))
    return false
  if (response.not_modified === true) return true
  const cart = response.cart
  return (
    response.not_modified === false &&
    !!cart &&
    typeof cart.quantity === 'number' &&
    Number.isFinite(cart.quantity) &&
    money(cart.total) &&
    money(cart.subTotal) &&
    optionalString(cart.orderReference) &&
    !!cart.orderType &&
    (cart.orderType.name === null || typeof cart.orderType.name === 'string') &&
    Array.isArray(cart.items) &&
    cart.items.every(
      (item: any) =>
        typeof item.id === 'string' &&
        typeof item.item?.name === 'string' &&
        optionalString(item.item.thumbnail) &&
        (item.isGift === undefined || typeof item.isGift === 'boolean') &&
        typeof item.qty === 'number' &&
        Number.isFinite(item.qty) &&
        money(item.total) &&
        money(item.unitPrice) &&
        Array.isArray(item.taxes) &&
        item.taxes.every((tax: any) => money(tax.amount)) &&
        Array.isArray(item.options) &&
        item.options.every(
          (option: any) =>
            typeof option.name === 'string' &&
            Array.isArray(option.values) &&
            option.values.every(
              (entry: any) => typeof entry.label === 'string' && money(entry.price),
            ),
        ),
    ) &&
    Array.isArray(cart.charges) &&
    cart.charges.every((charge: any) => typeof charge.name === 'string' && money(charge.amount)) &&
    Array.isArray(cart.taxes) &&
    cart.taxes.every((tax: any) => typeof tax.name === 'string' && money(tax.amount)) &&
    (!cart.discount || (money(cart.discount.value) && optionalString(cart.discount.name))) &&
    (cart.customer === null ||
      (typeof cart.customer?.name === 'string' &&
        (cart.customer.tier === null || typeof cart.customer.tier === 'string') &&
        (cart.customer.points === null || typeof cart.customer.points === 'number')))
  )
}
