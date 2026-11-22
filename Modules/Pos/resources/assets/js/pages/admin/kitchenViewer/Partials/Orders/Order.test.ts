import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { KitchenOrder } from '@/modules/pos/contracts/kitchen'
import Order from './Order.vue'
const { advance, toastError } = vi.hoisted(() => ({ advance: vi.fn(), toastError: vi.fn() }))
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))
vi.mock('vue-toastification', () => ({ useToast: () => ({ error: toastError }) }))
vi.mock('@/modules/pos/composables/kitchenViewer', () => ({
  useKitchenViewer: () => ({ moveToNextStatus: advance }),
}))
const order: KitchenOrder = {
  id: 20,
  order_number: '42',
  reference_no: 'K42',
  time: '10:00',
  date: '2026-09-21',
  created_at: '2026-09-21T10:00:00Z',
  status: { id: 'preparing', name: 'Preparing' },
  type: { id: 'dine_in', name: 'Dine in' },
  products: [
    {
      id: 1,
      quantity: 2,
      product: { id: 1, name: 'Soup' },
      status: { id: 'pending', name: 'Pending' },
      next_status: { id: 'preparing', name: 'Preparing' },
      options: [{ id: 1, name: 'Instructions', value: 'No nuts', values: [] }],
    },
    {
      id: 2,
      quantity: 1,
      product: { id: 2, name: 'Salad' },
      status: { id: 'ready', name: 'Ready' },
      next_status: { id: 'served', name: 'Served' },
      options: [],
    },
  ],
}
const create = (disabled = false) =>
  mount(Order, {
    props: {
      order,
      now: Date.parse('2026-09-21T10:10:00Z'),
      delayMinutes: 15,
      disabled,
    },
    global: {
      stubs: {
        VIcon: true,
        PreparationTimeChip: true,
        VBtn: {
          props: ['disabled', 'loading'],
          template: '<button :disabled="disabled || loading"><slot /></button>',
        },
      },
    },
  })
beforeEach(() => vi.resetAllMocks())

describe('Kitchen ticket actions', () => {
  it('shows free-text instructions and sends a single staged bulk request', async () => {
    let resolve!: (value: unknown) => void
    advance.mockReturnValue(
      new Promise((r) => {
        resolve = r
      }),
    )
    const wrapper = create()
    expect(wrapper.find('.kds-modifiers').text()).toContain('No nuts')
    await wrapper.get('.kds-order-action').trigger('click')
    await wrapper.get('.kds-order-action').trigger('click')
    expect(advance).toHaveBeenCalledTimes(1)
    expect(advance).toHaveBeenCalledWith(20, [1])
    expect(
      wrapper
        .findAll('.kds-item__action')
        .every((button) => button.attributes('disabled') !== undefined),
    ).toBe(true)
    resolve({ data: { body: { order: { ...order, id: 20 } } } })
    await flushPromises()
    expect(wrapper.emitted('order-updated')).toHaveLength(1)
    expect(wrapper.emitted('processing')).toEqual([[true], [false]])
    wrapper.unmount()
  })
  it('advances an individual item with only that item ID', async () => {
    advance.mockResolvedValue({ data: { body: { order } } })
    const wrapper = create()
    await wrapper.get('.kds-item__action').trigger('click')
    await flushPromises()
    expect(advance).toHaveBeenCalledWith(20, [1])
    wrapper.unmount()
  })
  it('keeps the ticket intact and requests resync after a failed action', async () => {
    advance.mockRejectedValue(new Error('Network failure'))
    const wrapper = create()
    await wrapper.get('.kds-order-action').trigger('click')
    await flushPromises()
    expect(wrapper.emitted('order-updated')).toBeUndefined()
    expect(wrapper.emitted('action-failed')).toHaveLength(1)
    expect(wrapper.find('.kds-action-error').exists()).toBe(true)
    expect(toastError).toHaveBeenCalledTimes(1)
    wrapper.unmount()
  })
  it('disables status actions while offline or data is stale', async () => {
    const wrapper = create(true)
    await wrapper.get('.kds-order-action').trigger('click')
    expect(advance).not.toHaveBeenCalled()
    expect(wrapper.get('.kds-item__action').attributes('disabled')).toBeDefined()
    wrapper.unmount()
  })
})
