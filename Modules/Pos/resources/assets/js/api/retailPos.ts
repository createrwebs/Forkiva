import { http } from '@/modules/core/api/http.ts'

export interface OfflineOrderPayload {
  client_uuid: string
  offline_reference: string
  payment_method: string
  total: string
  items: Array<{ product_id: number; quantity: number; unit_price: string }>
  stock_shortage?: boolean
}

export interface OfflineSyncResultItem {
  client_uuid: string
  status: 'synced' | 'duplicate'
  receipt_id: number
  needs_stock_reconciliation: boolean
}

export interface HeldCartRecord {
  id: number
  branch_id: number
  label: string
  items: Array<Record<string, unknown>>
}

export async function lookupProductByBarcode(code: string) {
  const response = await http.get(`/v1/pos/products/by-barcode/${encodeURIComponent(code)}`)
  return response.data.body
}

export async function holdRetailCart(branchId: number, label: string, items: Array<Record<string, unknown>>) {
  const response = await http.post('/v1/pos/hold-carts', {
    branch_id: branchId,
    label,
    items,
  })
  return response.data.body as HeldCartRecord
}

export async function listHeldRetailCarts(branchId: number): Promise<HeldCartRecord[]> {
  const response = await http.get('/v1/pos/hold-carts', { params: { branch_id: branchId } })
  return response.data.body
}

export async function resumeHeldRetailCart(id: number): Promise<HeldCartRecord> {
  const response = await http.post(`/v1/pos/hold-carts/${id}/resume`)
  return response.data.body
}

export async function syncOfflineOrders(
  branchId: number,
  orders: OfflineOrderPayload[],
): Promise<{ results: OfflineSyncResultItem[] }> {
  const response = await http.post('/v1/pos/offline-sync', {
    branch_id: branchId,
    orders,
  })
  return response.data.body
}
