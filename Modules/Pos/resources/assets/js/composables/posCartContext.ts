import type { InjectionKey } from 'vue'
import type { UseCart } from '@/modules/cart/composables/cart'

export const posCartKey: InjectionKey<UseCart> = Symbol('pos-cart')
