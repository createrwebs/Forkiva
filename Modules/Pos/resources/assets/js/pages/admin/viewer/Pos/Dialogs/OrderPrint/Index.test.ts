import { shallowMount, flushPromises } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import OrderPrint from './Index.vue'
import PrintContent from './PrintContent.vue'

const { getPrintMeta, initClient } = vi.hoisted(() => ({
  getPrintMeta: vi.fn(),
  initClient: vi.fn(),
}))
vi.mock('@/modules/auth/composables/auth.ts', () => ({
  useAuth: () => ({ user: { assigned_to_branch: false } }),
}))
vi.mock('@/modules/order/composables/order.ts', () => ({ useOrder: () => ({ getPrintMeta }) }))
vi.mock('@/modules/printer/composables/qintrix.ts', () => ({ useQintrix: () => ({ initClient }) }))
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))

const contents = [
  { type: { id: 'bill', name: 'Bill' }, label: 'Print Bill' },
  { type: { id: 'kitchen', name: 'Kitchen' }, id: 7, label: 'Hot kitchen' },
  { type: { id: 'kitchen', name: 'Kitchen' }, id: 9, label: 'Cold kitchen' },
]
function response(agent: object | null = null) {
  return {
    data: {
      body: {
        branches: [{ id: 1, name: 'Main branch' }],
        registers: [{ id: 10, name: 'Main register' }],
        contents,
        branch_id: 1,
        agent,
      },
    },
  }
}
function mountDialog(props = {}) {
  return shallowMount(OrderPrint, {
    props: { modelValue: true, orderId: 42, ...props },
    global: {
      stubs: {
        VIcon: true,
        VBtn: true,
        VProgressCircular: true,
        VSelect: { name: 'VSelect', template: '<div />' },
        VDialog: { template: '<div><slot /></div>' },
        VCard: { template: '<div><slot /></div>' },
        VCardText: { template: '<div><slot /></div>' },
      },
    },
  })
}
beforeEach(() => {
  getPrintMeta.mockReset()
  initClient.mockReset()
  getPrintMeta.mockResolvedValue(response())
})

describe('Order print document selection', () => {
  it('preserves distinct kitchen destinations when switching documents with keyboard or pointer', async () => {
    const wrapper = mountDialog()
    await flushPromises()
    expect(wrapper.findComponent(PrintContent).props('content')).toEqual(contents[0])
    await wrapper.findAll('[role="radio"]')[0]!.trigger('keydown', { key: 'ArrowRight' })
    expect(wrapper.findComponent(PrintContent).props('content').id).toBe(7)
    await wrapper.findAll('[role="radio"]')[2]!.trigger('click')
    expect(wrapper.findComponent(PrintContent).props('content').id).toBe(9)
    expect(wrapper.findAll('[aria-checked="true"]')).toHaveLength(1)
  })
  it('keeps the POS register supplied by the caller and hides destination fields', async () => {
    const wrapper = mountDialog({ branchId: 1, registerId: 10 })
    await flushPromises()
    expect(getPrintMeta).toHaveBeenCalledTimes(1)
    expect(getPrintMeta).toHaveBeenCalledWith(42, 1, 10)
    expect(wrapper.findComponent(PrintContent).props('registerId')).toBe(10)
    expect(wrapper.find('#pos-print-register').exists()).toBe(false)
  })
  it('clears the register and agent availability when switching to a branch without a print agent', async () => {
    getPrintMeta.mockResolvedValueOnce(
      response({ base_url: 'https://print.example.test', api_key: 'test-key' }),
    )
    const wrapper = mountDialog()
    await flushPromises()
    expect(wrapper.findComponent(PrintContent).props('agentAvailable')).toBe(true)
    wrapper.findAllComponents({ name: 'VSelect' })[1]!.vm.$emit('update:modelValue', 10)
    await wrapper.vm.$nextTick()
    expect(wrapper.findComponent(PrintContent).props('registerId')).toBe(10)
    wrapper.findAllComponents({ name: 'VSelect' })[0]!.vm.$emit('update:modelValue', 2)
    await flushPromises()
    expect(getPrintMeta).toHaveBeenLastCalledWith(42, 2, undefined)
    expect(wrapper.findComponent(PrintContent).props('registerId')).toBeNull()
    expect(wrapper.findComponent(PrintContent).props('agentAvailable')).toBe(false)
  })
  it('disables document selection while a print action is running', async () => {
    const wrapper = mountDialog()
    await flushPromises()
    wrapper.findComponent(PrintContent).vm.$emit('busy', true)
    await wrapper.vm.$nextTick()
    expect(
      wrapper.findAll('[role="radio"]').every((item) => item.attributes('disabled') !== undefined),
    ).toBe(true)
  })
})
