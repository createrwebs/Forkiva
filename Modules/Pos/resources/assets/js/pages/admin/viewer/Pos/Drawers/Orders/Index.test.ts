import { flushPromises, mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import { beforeEach, expect, it, vi } from 'vitest'
import OrdersDrawer from './Index.vue'

const { activeOrders } = vi.hoisted(() => ({ activeOrders: vi.fn() }))

vi.mock('@/modules/order/composables/order.ts', () => ({
  useOrder: () => ({ activeOrders, upcomingOrders: vi.fn() }),
}))
vi.mock('@/modules/auth/composables/auth.ts', () => ({ useAuth: () => ({ can: () => true }) }))
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))
vi.mock('vue-toastification', () => ({ useToast: () => ({ error: vi.fn() }) }))

const response = (id: number) => ({
  data: {
    body: {
      data: [{ id }],
      pagination: { current_page: 1, last_page: 1, per_page: 15 },
      filters: {},
    },
  },
})

beforeEach(() => activeOrders.mockReset())

it('keeps the current orders visible without a loader during post-submit refresh', async () => {
  activeOrders.mockResolvedValueOnce(response(1))
  let finishRefresh!: (value: ReturnType<typeof response>) => void
  activeOrders.mockImplementationOnce(
    () =>
      new Promise((resolve) => {
        finishRefresh = resolve
      }),
  )

  const wrapper = mount(OrdersDrawer, {
    props: { modelValue: true, branchId: 1, qintrix: {} as any, version: 0 },
    global: {
      stubs: {
        WorkspacePanel: { template: '<div><slot /></div>' },
        VCard: { template: '<div><slot /></div>' },
        VCardText: { template: '<div><slot /></div>' },
        Order: { props: ['order'], template: '<div class="order-row">{{ order.id }}</div>' },
        Tabs: true,
        Filters: true,
        VIcon: true,
        VBtn: true,
        VProgressCircular: true,
        PosExtensionRenderer: true,
      },
    },
  })

  await (wrapper.vm as any).refresh()
  expect(wrapper.find('.order-row').text()).toBe('1')

  await wrapper.setProps({ version: 1 })
  await nextTick()
  expect(activeOrders).toHaveBeenCalledTimes(2)
  expect(wrapper.find('.order-row').text()).toBe('1')
  expect(wrapper.find('.orders-loading').exists()).toBe(false)

  finishRefresh(response(2))
  await flushPromises()
  expect(wrapper.find('.order-row').text()).toBe('2')
  wrapper.unmount()
})
