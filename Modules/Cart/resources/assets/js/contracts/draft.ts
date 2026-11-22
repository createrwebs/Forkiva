import type { Cart } from '@/modules/cart/contracts/cart'

export type CartChannel = 'pos' | 'table_order' | 'online_order'
/** A stable editable selection plus the catalog snapshot needed for offline estimates. */
export interface DraftLine {
  line_id: string
  product_id: number
  qty: number
  options: Record<string, string | number | number[]>
  product: Record<string, any>
  order_product_id?: number
  status?: string
  gift?: any
  actions?: Record<string, number>
}
/** Versioned durable state; a pending submission freezes selections until its outcome is known. */
export interface Draft {
  key: string
  schema: 1
  channel: CartChannel
  accountId: number | null
  revision: number
  updatedAt: number
  expiresAt: number | null
  lines: DraftLine[]
  context: Record<string, any>
  order: Record<string, any> | null
  editVersion?: string
  customer: any
  discountId?: number
  voucherCode?: string
  giftId?: number
  deliveryDisplay?: any
  delivery?: Record<string, any>
  orderType: { id: string | null; name: string | null }
  /** Saved-order display prices, invalidated by selection changes; never authorize a save. */
  pricingSnapshot?: Cart
  quote?: {
    pricing: any
    comparison_pricing?: any
    quote_token: string
    expires_at: string
  }
  submission?: { payload: Record<string, any>; orderId?: number; state: 'pending' | 'unknown' }
}
