import type { Directive } from 'vue'

declare module 'vue' {
  interface GlobalDirectives {
    integerEn: Directive<HTMLInputElement>
    decimalEn: Directive<HTMLInputElement>
  }
}
