import { flushPromises, shallowMount } from '@vue/test-utils'
import { reactive, ref } from 'vue'
import { expect, it, vi } from 'vitest'
import TableInfo from '@/modules/pos/pages/admin/viewer/Pos/OrderPanel/TableInfo.vue'
import OrderTypes from '@/modules/pos/pages/admin/viewer/Pos/OrderPanel/OrderTypes.vue'

vi.mock('@/modules/auth/composables/auth.ts', () => ({ useAuth: () => ({ can: () => true }) }))

it.each(['create', 'edit'])(
  'keeps table changes available but allows removal only for a new order (%s)',
  async (mode) => {
    const form = reactive({
      mode,
      table: { id: 1, name: 'T1', zone: 'Zone', floor: 'Floor' } as any,
    })
    const storeOrderType = vi.fn().mockResolvedValue(true)
    const props = {
      form,
      meta: {
        orderTypes: [
          { id: 'dine_in', name: 'Dine in' },
          { id: 'takeaway', name: 'Takeaway' },
        ],
      },
      cart: { storeOrderType, processing: ref(false), data: ref({ orderType: { id: 'dine_in' } }) },
    } as any
    const wrapper = shallowMount(TableInfo, {
      props,
      global: {
        stubs: { VIcon: true, VBtn: { template: '<button class="remove"><slot /></button>' } },
      },
    })
    await wrapper.get('.pos-current-table-select').trigger('click')
    expect(wrapper.emitted('select-table')).toHaveLength(1)
    expect(wrapper.find('.remove').exists()).toBe(mode === 'create')
    const types = shallowMount(OrderTypes, {
      props,
      global: { stubs: { VIcon: true, VProgressCircular: true } },
    })
    const takeaway = types.findAll('button')[1]!
    expect(takeaway.attributes('disabled') !== undefined).toBe(mode === 'edit')
    if (mode === 'edit') {
      await takeaway.trigger('click')
      expect(storeOrderType).not.toHaveBeenCalled()
      expect(form.table.id).toBe(1)
    } else {
      await wrapper.get('.remove').trigger('click')
      await flushPromises()
      expect(form.table).toBeNull()
      expect(storeOrderType).toHaveBeenCalledWith('takeaway')
    }
    types.unmount()
    wrapper.unmount()
  },
)
