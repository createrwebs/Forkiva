/** Shared display contracts for local estimates and authoritative checkout quotes. */
export interface MoneyValue {
  amount: number
  formatted: string
  currency?: string
  precision?: number
}

export interface CartItemAction {
  id: string
  quantity: number
}

export interface CartCharge {
  code: string
  name: string
  amount: MoneyValue
  translations?: Record<string, string>
  meta?: Record<string, any>
}

export type CartTaxScope = 'product' | 'global'
export type CartTaxType = 'inclusive' | 'exclusive'

export interface CartTax {
  id: number | string
  name: string
  rate?: number
  type?: CartTaxType
  scope?: CartTaxScope
  amount: MoneyValue
}

export interface CustomerAddress {
  id: number | string
  created_at?: string | null
  updated_at?: string | null
  label?: string | null
  customer_id?: number | string | null
  contactName?: string | null
  contact_name?: string | null
  phone?: string | null
  line1?: string | null
  line2?: string | null
  city?: string | null
  state?: string | null
  postalCode?: string | null
  postal_code?: string | null
  notes?: string | null
  latitude?: number | null
  longitude?: number | null
  fullAddress?: string | null
  full_address?: string | null
  is_default?: boolean
  is_active?: boolean
}

export interface DeliveryZoneSummary {
  id: number | string
  name: string
  geometryType?: string | null
  priority?: number | null
}

export interface DeliveryPricingRuleSummary {
  id: number | string
  zoneId?: number | string | null
  calculationMethod?: string | null
  baseFee?: number | null
  freeDeliveryAbove?: number | null
  smallOrderThreshold?: number | null
  smallOrderSurcharge?: number | null
}

export interface DeliveryPricingChargeSummary {
  code: string
  amount: number
}

export interface DeliveryPricingSummary {
  available?: boolean
  issue?: string | null
  message?: string | null
  subtotal?: number | null
  discountAmount?: number | null
  deliveryFee?: number | null
  smallOrderSurcharge?: number | null
  freeDeliveryApplied?: boolean
  charges?: DeliveryPricingChargeSummary[]
  zone?: DeliveryZoneSummary | null
  rule?: DeliveryPricingRuleSummary | null
  snapshot?: Record<string, any> | null
  override?: Record<string, any> | null
  currency?: string | null
}

export interface CartDeliveryContext {
  customerAddress?: CustomerAddress | null
  resolvedZone?: DeliveryZoneSummary | null
  pricing?: DeliveryPricingSummary | null
  deliveryMethod?: string | null
  paymentCollectionChannel?: string | null
  note?: string | null
  status?: string | null
  dispatchStatus?: string | null
}

export interface CartItem {
  validationErrors?: string[]
  id: string
  item: { id?: number | string; name: string; thumbnail?: string }
  qty: number
  unitPrice: MoneyValue
  subtotal: MoneyValue
  taxTotal: MoneyValue
  taxes: CartTax[]
  total: MoneyValue
  actions: CartItemAction[]
  loyaltyGift?: unknown
  options?: Array<{
    id: string | number
    name?: string
    values: Array<{ id: string | number; label?: string; price?: MoneyValue }>
  }>
  orderProduct?: { id?: number; status?: { id: string } }
}

export interface Cart {
  version?: number
  items: CartItem[]
  quantity: number
  subTotal: MoneyValue
  orderType: {
    id: string | null
    name: string | null
  }
  taxes: CartTax[]
  charges: CartCharge[]
  total: MoneyValue
  customer: any
  deliveryContext?: CartDeliveryContext | null
  discount?: {
    id?: string | number
    name?: string
    type?: { id: string; name?: string } | null
    amount?: MoneyValue
    value?: MoneyValue
  } | null
  order?: Record<string, any> | null
}

export interface RefundPaymentMethod {
  id: number | string
  name: string
  icon?: string
  color?: string
}
