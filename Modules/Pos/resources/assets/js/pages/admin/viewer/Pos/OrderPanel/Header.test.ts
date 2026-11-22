import type * as VueI18n from 'vue-i18n'
import { flushPromises, shallowMount } from '@vue/test-utils'
import { ref } from 'vue'
import { expect, it, vi } from 'vitest'
import Header from './Header.vue'

vi.mock('@/modules/core/components/Form/SearchSelect.vue', () => ({
  default: {
    name: 'SearchSelect',
    emits: ['update:modelValue'],
    methods: { open: vi.fn() },
    template: '<div />',
  },
}))
vi.mock('@/modules/pos/components/PosExtensionRenderer.vue', () => ({
  default: { template: '<div />' },
}))
vi.mock('@/modules/pos/pages/admin/viewer/Pos/Dialogs/AvailableGifts/Index.vue', () => ({
  default: { template: '<div />' },
}))
vi.mock('@/modules/pos/pages/admin/viewer/Pos/Dialogs/Rewards/Index.vue', () => ({
  default: { template: '<div />' },
}))
vi.mock('@/modules/pos/pages/admin/viewer/Pos/OrderPanel/AddCustomerDialog.vue', () => ({
  default: { template: '<div />' },
}))

vi.mock('@/modules/auth/composables/auth.ts', () => ({
  useAuth: () => ({ can: () => true }),
}))
vi.mock('vue-i18n', async (importOriginal) => ({
  ...(await importOriginal<typeof VueI18n>()),
  useI18n: () => ({ t: (key: string) => key }),
}))

it('stores the selected customer and completes a pending delivery selection', async () => {
  const data = ref<any>({
    customer: null,
    orderType: { id: 'takeaway', name: 'Takeaway' },
  })
  const addCustomer = vi.fn(async (_id, customer) => {
    data.value.customer = customer
    return true
  })
  const storeOrderType = vi.fn(async (id) => {
    data.value.orderType = { id, name: 'Delivery' }
    return true
  })
  const wrapper = shallowMount(Header, {
    props: {
      form: { mode: 'create', loadingCustomers: false },
      meta: {
        customers: [{ id: 7, name: 'Customer Seven' }],
        orderTypes: [
          { id: 'takeaway', name: 'Takeaway' },
          { id: 'delivery', name: 'Delivery' },
        ],
      },
      cart: {
        data,
        processing: ref(false),
        addCustomer,
        removeCustomer: vi.fn(),
        storeOrderType,
        removeOrderType: vi.fn(),
      },
      pendingOrderTypeSelection: 'delivery',
      loadCustomers: vi.fn(),
    } as any,
    global: {
      stubs: {
        SearchSelect: false,
        VBtn: true,
        VIcon: true,
        VList: true,
        VListItem: true,
        VMenu: true,
      },
    },
  })

  wrapper.findComponent({ name: 'SearchSelect' }).vm.$emit('update:modelValue', {
    id: 7,
    name: 'Customer Seven',
  })
  await flushPromises()

  expect(addCustomer).toHaveBeenCalledWith(7, { id: 7, name: 'Customer Seven' })
  expect(storeOrderType).toHaveBeenCalledWith('delivery')
  expect(wrapper.emitted('pending-order-type-resolved')).toHaveLength(1)
  expect(data.value.customer).toEqual({ id: 7, name: 'Customer Seven' })
})
