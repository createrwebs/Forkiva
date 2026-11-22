import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import { useCustomerDisplay } from './useCustomerDisplay'
let bus: any
let wrapper: ReturnType<typeof mount>
beforeEach(() => {
  vi.useFakeTimers()
  vi.stubGlobal(
    'BroadcastChannel',
    class {
      onmessage: any
      postMessage = vi.fn()
      close = vi.fn()
      constructor(public name: string) {
        Object.assign(globalThis, { __displayTestBus: this })
        bus = (globalThis as any).__displayTestBus
      }
    },
  )
})
afterEach(() => {
  wrapper?.unmount()
  vi.useRealTimers()
  vi.unstubAllGlobals()
})
it('requests a local snapshot, receives it, then removes stale customer data', async () => {
  let display!: ReturnType<typeof useCustomerDisplay>
  wrapper = mount(
    defineComponent({
      setup() {
        display = useCustomerDisplay(() => ({ scope: 'pos:1:2:3:4', token: '', locale: 'en' }))
        return () => null
      },
    }),
  )
  expect(bus.name).toBe('forkiva:customer-display:pos:1:2:3:4')
  expect(bus.postMessage).toHaveBeenCalledWith({ type: 'request-snapshot' })
  bus.onmessage({ data: { type: 'snapshot', cart: { items: [], quantity: 0 } } })
  expect(display.connection.value).toBe('connected')
  expect(display.cart.value).not.toBeNull()
  await vi.advanceTimersByTimeAsync(20000)
  expect(display.connection.value).toBe('reconnecting')
  expect(display.cart.value).toBeNull()
  wrapper.unmount()
  expect(bus.close).toHaveBeenCalled()
})
it('clears the display when its owner changes account or closes', () => {
  let display!: ReturnType<typeof useCustomerDisplay>
  wrapper = mount(
    defineComponent({
      setup() {
        display = useCustomerDisplay(() => ({ scope: 'pos:1', token: '', locale: 'en' }))
        return () => null
      },
    }),
  )
  bus.onmessage({ data: { type: 'snapshot', cart: { items: [] } } })
  bus.onmessage({ data: { type: 'closed' } })
  expect(display.cart.value).toBeNull()
  expect(display.connection.value).toBe('reconnecting')
})
