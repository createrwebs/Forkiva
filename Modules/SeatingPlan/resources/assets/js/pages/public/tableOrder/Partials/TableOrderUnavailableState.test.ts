import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import TableOrderUnavailableState from './TableOrderUnavailableState.vue'

vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    t: (key: string) =>
      ({
        'seatingplan::tables.table_order.ask_staff_help':
          'Please ask a staff member for help.',
        'seatingplan::tables.table_order.try_again': 'Try again',
      })[key] ?? key,
  }),
}))

function mountState(overrides: Record<string, unknown> = {}) {
  return mount(TableOrderUnavailableState, {
    props: {
      message: 'This table is not available for QR ordering right now.',
      title: 'Ordering is paused',
      ...overrides,
    },
    global: {
      stubs: {
        VBtn: { template: '<button><slot /></button>' },
        VIcon: true,
      },
    },
  })
}

describe('table order unavailable state', () => {
  it('keeps retry as the primary action and emits a retry request', async () => {
    const wrapper = mountState()

    expect(wrapper.find('h1').text()).toBe('Ordering is paused')
    expect(wrapper.find('.unavailable-message').text()).toContain('not available')
    expect(wrapper.find('.unavailable-identity').exists()).toBe(false)

    await wrapper.find('.unavailable-action').trigger('click')
    expect(wrapper.emitted('retry')).toHaveLength(1)
  })
})
