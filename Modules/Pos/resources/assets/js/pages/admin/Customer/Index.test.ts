import { ref } from 'vue'
import { expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createI18n } from 'vue-i18n'
import CustomerDisplay from './Index.vue'
const state = vi.hoisted(() => ({ display: null as any, context: null as any }))
vi.mock('vue-router', () => ({
  useRoute: () => ({ hash: '#scope=pos%3A1%3A2%3A3%3A4' }),
  useRouter: () => ({}),
}))
vi.mock('@/modules/pos/composables/useCustomerDisplay.ts', () => ({
  useCustomerDisplay: (context: any) => {
    state.context = context
    return state.display
  },
}))
vi.mock('@/modules/core/stores/appStore', () => ({
  useAppStore: () => ({ appName: 'Forkiva', logo: null, isRtl: true }),
}))
vi.mock('@vueuse/core', () => ({
  useFullscreen: () => ({ isFullscreen: false, isSupported: false, toggle: vi.fn() }),
}))
it('renders the local scoped display in Arabic without a pairing token', () => {
  state.display = {
    cart: ref(null),
    connection: ref('connecting'),
    lastUpdated: ref(null),
    loading: ref(false),
    terminal: ref(false),
    retry: vi.fn(),
  }
  const wrapper = mount(CustomerDisplay, {
    global: {
      plugins: [
        createI18n({ legacy: false, locale: 'ar', missingWarn: false, fallbackWarn: false }),
      ],
      stubs: { VIcon: true, DisplayWelcome: true, DisplayCustomer: true, TaxSummaryRows: true },
    },
  })
  expect(state.context().scope).toBe('pos:1:2:3:4')
  expect(state.context()).not.toHaveProperty('token')
  expect(wrapper.find('main').exists()).toBe(true)
  wrapper.unmount()
})
