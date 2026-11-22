import { shallowMount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import PreparationTimeChip from '../PreparationTimeChip.vue'
import OrderProduct from './OrderProduct.vue'
import type { KitchenProduct } from '@/modules/pos/contracts/kitchen'

vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    t: (key: string, values?: Record<string, unknown>) =>
      key === 'pos::pos_viewer.kitchen_prep_time.estimate' ? `${values?.minutes} min` : key,
  }),
}))

vi.mock('vue-toastification', () => ({
  useToast: () => ({ error: vi.fn() }),
}))

vi.mock('@/modules/pos/composables/kitchenViewer.ts', () => ({
  useKitchenViewer: () => ({ moveToNextStatus: vi.fn() }),
}))

const stubs = {
  VBtn: { template: '<button><slot /></button>' },
  VChip: { template: '<span data-testid="prep-time"><slot /></span>' },
  VIcon: { template: '<i />' },
}

afterEach(() => {
  vi.useRealTimers()
})

function renderOptions(options: KitchenProduct['options']) {
  return shallowMount(OrderProduct, {
    props: {
      item: {
        id: 1,
        product: { id: 1, name: 'Soup' },
        quantity: 1,
        status: { id: 'pending', name: 'Pending' },
        next_status: null,
        options,
      },
      disabled: false,
      processing: false,
    },
    global: { stubs },
  })
}

describe('Kitchen product option values', () => {
  it.each([
    ['text', 'No salt'],
    ['textarea', 'No peanuts\nSauce on the side'],
    ['date', '2026-09-23'],
    ['time', '11:55 AM'],
  ])('shows the entered %s value despite an empty catalog selection', (name, value) => {
    const wrapper = renderOptions([{ id: 1, name, value, values: [{ id: 10, label: '' }] }])
    expect(wrapper.get('dt').text()).toBe(`${name}:`)
    expect(wrapper.get('dd').element.textContent).toBe(value)
    wrapper.unmount()
  })

  it.each([
    ['select', ['White']],
    ['radio', ['Hot']],
    ['multiple_select', ['Extra Cheese', 'Bacon']],
    ['checkbox', ['Olives', 'Peppers']],
  ])('shows every selected %s label in order', (name, labels) => {
    const wrapper = renderOptions([
      { id: 1, name, value: null, values: labels.map((label, id) => ({ id, label })) },
    ])
    expect(wrapper.get('dd').text()).toBe(labels.join(', '))
    wrapper.unmount()
  })

  it('preserves zero and renders instruction markup as text', () => {
    const wrapper = renderOptions([
      { id: 1, name: 'Candles', value: '0', values: [{ id: 10, label: '' }] },
      { id: 2, name: 'Note', value: '<b>No salt</b>', values: [] },
    ])
    expect(wrapper.findAll('dd').map((value) => value.text())).toEqual(['0', '<b>No salt</b>'])
    expect(wrapper.find('dd b').exists()).toBe(false)
    wrapper.unmount()
  })

  it('omits empty options and blank selection labels without losing provided values', () => {
    const wrapper = renderOptions([
      { id: 1, name: 'Empty note', value: '  ', values: [{ id: 10, label: '' }] },
      { id: 2, name: 'Empty selection', value: null, values: [] },
      {
        id: 3,
        name: 'Toppings',
        value: null,
        values: [
          { id: 11, label: '' },
          { id: 12, label: 'Olives' },
        ],
      },
    ])
    expect(wrapper.findAll('dt').map((label) => label.text())).toEqual(['Toppings:'])
    expect(wrapper.get('dd').text()).toBe('Olives')
    wrapper.unmount()
  })
})

describe('Kitchen order product preparation timer', () => {
  it('shows an overdue timer and advances it every second', async () => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-09-21T10:00:00.000Z'))

    const wrapper = shallowMount(PreparationTimeChip, {
      props: {
        active: true,
        minutes: 1,
        startedAt: '2026-09-21T09:58:50.000Z',
        expectedReadyAt: '2026-09-21T09:59:50.000Z',
      },
      global: { stubs },
    })

    expect(wrapper.get('[data-testid="prep-time"]').text()).toContain('+00:10')

    await vi.advanceTimersByTimeAsync(1_000)
    await nextTick()

    expect(wrapper.get('[data-testid="prep-time"]').text()).toContain('+00:11')
  })

  it('shows the configured estimate before preparation starts', () => {
    const wrapper = shallowMount(PreparationTimeChip, {
      props: {
        active: false,
        minutes: 12,
        startedAt: null,
      },
      global: { stubs },
    })

    expect(wrapper.get('[data-testid="prep-time"]').text()).toContain('12 min')
  })
})
