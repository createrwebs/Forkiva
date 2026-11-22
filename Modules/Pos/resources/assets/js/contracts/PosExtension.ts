import type { AsyncComponentLoader, Component } from 'vue'
import type { PosForm, PosMeta } from '@/modules/pos/contracts/posViewer.ts'
import type { UseCart } from '@/modules/cart/composables/cart.ts'

export type PosExtensionMount =
  | 'order-panel-notices'
  | 'order-panel-header'
  | 'order-panel-additional-information'
  | 'order-actions'
  | 'orders-drawer'

export interface PosExtensionProps {
  cart: UseCart
  form: PosForm
  meta: PosMeta
}

export interface PosExtensionRegistration {
  key: string
  mount: PosExtensionMount
  loader?: AsyncComponentLoader
  component?: Component
}
