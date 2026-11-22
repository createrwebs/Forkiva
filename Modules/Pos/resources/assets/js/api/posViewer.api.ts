import { http } from '@/modules/core/api/http.ts'

interface PosProductFilters {
  search?: string
  category_ids?: Array<number | string>
  page?: number
  per_page?: number
}

interface PosCustomerFilters {
  branch_id?: number | null
  search?: string
  page?: number
  per_page?: number
}

export function getConfiguration(branchId?: number) {
  return http.get(`/v1/pos/viewer/configuration`, { params: { branch_id: branchId } })
}

export function getMenuItems(menuId: number) {
  return http.get(`/v1/pos/viewer/menu-items/${menuId}`)
}

export function getMenuCategories(menuId: number) {
  return http.get(`/v1/pos/viewer/menu-items/${menuId}/categories`)
}

export function getProducts(menuId: number, filters: PosProductFilters = {}) {
  return http.get(`/v1/pos/viewer/menu-items/${menuId}/products`, {
    params: filters,
  })
}

export function getCustomers(filters: PosCustomerFilters = {}) {
  return http.get(`/v1/pos/viewer/customers`, {
    params: filters,
  })
}
