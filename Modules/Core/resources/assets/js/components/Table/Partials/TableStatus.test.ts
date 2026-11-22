import { shallowMount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import TableStatus from './TableStatus.vue'

function render(status: unknown) {
  return shallowMount(TableStatus, {
    props: { item: { status } },
    global: {
      stubs: {
        VChip: { props: ['color'], template: '<span :data-color="color"><slot /></span>' },
        VIcon: { props: ['icon'], template: '<i :data-icon="icon" />' },
      },
    },
  })
}

describe('TableStatus cash handover statuses', () => {
  it.each([
    ['pending', 'Awaiting receipt', 'warning', 'tabler-clock'],
    ['received', 'Received', 'success', 'tabler-circle-check'],
    ['cancelled', 'Cancelled', 'error', 'tabler-circle-x'],
  ])('renders %s using the shared status treatment', (id, name, color, icon) => {
    const wrapper = render({ id, name })

    expect(wrapper.get('[data-color]').attributes('data-color')).toBe(color)
    expect(wrapper.get('[data-icon]').attributes('data-icon')).toBe(icon)
    expect(wrapper.text()).toBe(name)
  })

  it('preserves explicit appearance supplied by other domains', () => {
    const wrapper = render({
      id: 'pending',
      name: 'Waiting',
      color: 'info',
      icon: 'tabler-hourglass',
    })

    expect(wrapper.get('[data-color]').attributes('data-color')).toBe('info')
    expect(wrapper.get('[data-icon]').attributes('data-icon')).toBe('tabler-hourglass')
  })
})
