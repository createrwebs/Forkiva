import { shallowMount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import DashboardDragHandle from './DashboardDragHandle.vue'

vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    t: (_key: string, values: { widget: string }) => `Move ${values.widget}`,
  }),
}))

describe('DashboardDragHandle', () => {
  it.each([
    ['ArrowUp', 'up'],
    ['ArrowLeft', 'left'],
    ['ArrowDown', 'down'],
    ['ArrowRight', 'right'],
    ['Home', 'first'],
    ['End', 'last'],
  ] as const)('emits %s as %s for keyboard reordering', async (key, direction) => {
    const wrapper = shallowMount(DashboardDragHandle, {
      props: { title: 'Total sales' },
      global: { stubs: { VIcon: true } },
    })

    await wrapper.get('button').trigger('keydown', { key })

    expect(wrapper.emitted('move')).toContainEqual([direction])
    expect(wrapper.get('button').attributes('aria-label')).toBe('Move Total sales')
  })
})
