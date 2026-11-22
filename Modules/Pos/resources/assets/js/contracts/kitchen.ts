import type { InjectionKey, Ref } from 'vue'

export interface KitchenEnum {
  id: string
  name: string
  icon?: string
  color?: string
}
export interface KitchenProduct {
  id: number
  product: { id: number; name: string }
  quantity: number
  status: KitchenEnum
  next_status: KitchenEnum | null
  prep_time_minutes?: number
  preparation_started_at?: string | null
  prepared_at?: string | null
  expected_ready_at?: string | null
  options: {
    id: number
    name: string
    value: string | null
    values: { id: number; label: string }[]
  }[]
}
export interface KitchenOrder {
  id: number
  order_number: string
  reference_no: string
  status: KitchenEnum
  type: KitchenEnum
  source_badge?: KitchenEnum | null
  table?: { id: number; name: string | null } | null
  customer?: { name: string } | null
  notes?: string | null
  created_at?: string | null
  preparation_started_at?: string | null
  prepared_at?: string | null
  expected_ready_at?: string | null
  prep_time_minutes?: number
  time: string
  date: string
  products: KitchenProduct[]
}
export type KitchenView = 'active' | 'new' | 'preparing' | 'ready' | 'delayed' | 'completed'
export type KitchenSort = 'priority' | 'oldest' | 'newest'
export const kitchenClockKey: InjectionKey<Ref<number>> = Symbol('kitchen-clock')
