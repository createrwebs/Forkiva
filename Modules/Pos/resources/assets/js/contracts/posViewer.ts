import type { MoneyValue, RefundPaymentMethod } from '@/modules/cart/composables/cart.ts'
import type { PaginationMeta } from '@/modules/core/contracts/Table.ts'
import type { PosSoundSettings } from '@/modules/pos/composables/posSound.ts'

export interface Category {
  id: number | string
  name?: string
  children?: Category[]
  products?: Product[]
  items?: Category[]
}

export interface Product {
  id: number | string
  name: string
  price?: MoneyValue
  badges?: string[]
  description?: string
  image?: string
  is_new?: boolean
  status?: { id: string; name?: string }
  category_ids?: Array<number | string>
  options?: any[]
  thumbnail?: string
  selling_price?: MoneyValue
}

export interface PosMeta {
  branches: any[]
  menus: any[]
  registers: any[]
  orderTypes: any[]
  deliveryMethods: Array<{ id: string; name: string }>
  paymentCollectionChannels: Array<{ id: string; name: string }>
  paymentCollectionChannelMap?: Record<string, Array<{ id: string; name: string }>>
  categories: Category[]
  products: Product[]
  productsPagination: PaginationMeta | null
  waiters: any[]
  customers: any[]
  customersPagination: PaginationMeta | null
  discounts: any[]
  refundPaymentMethods: RefundPaymentMethod[]
  directions: any[]
  reasons: Record<string, any>
  order: any
  currency: string
  floors?: any[]
  zones?: any[]
  table_statuses?: any[]
  sounds?: PosSoundSettings
}

export interface PosFormMeta {
  notes: string | null
  guestCount: number
  carPlate: string | null
  carDescription: string | null
  scheduledAt: string | null
}

export interface PosForm {
  mode: 'create' | 'edit'
  branchId: number | null
  menuId: number | null
  registerId: number | null
  waiter: any
  sessionId: number | null
  table: any
  skipFirst: boolean
  refundPaymentMethod: RefundPaymentMethod | string | number | null
  refundGiftCardCode?: string | null
  meta: PosFormMeta
  loading: boolean
  loadingMenuItems: boolean
  loadingProducts: boolean
  loadingMoreProducts: boolean
  loadingCustomers: boolean
  reloadBranchData: boolean
}

export interface PosProductLoadOptions {
  search?: string
  categoryIds?: Array<number | string>
  page?: number
  reset?: boolean
}

export interface PosCustomerLoadOptions {
  search?: string
  page?: number
  reset?: boolean
}
