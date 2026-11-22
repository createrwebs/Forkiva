import { shallowMount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import FinanceMetric from './FinanceMetric.vue'

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))

function render(interactive: boolean) {
  return shallowMount(FinanceMetric, {
    props: {
      label: 'Sales',
      value: null,
      unavailable: 'Unavailable',
      hint: 'Orders placed today',
      icon: 'tabler-shopping-cart',
      color: 'primary',
      interactive,
    },
    global: { stubs: { VAvatar: true, VIcon: true } },
  })
}

describe('finance metric review', () => {
  it('opens from the card itself without a separate details action', async () => {
    const wrapper = render(true)
    const card = wrapper.find('button')
    expect(card.text()).toContain('Sales')
    expect(card.attributes('aria-haspopup')).toBe('dialog')
    expect(wrapper.find('button button').exists()).toBe(false)
    await card.trigger('click')
    expect(wrapper.emitted('review')).toEqual([[]])
  })

  it('keeps restricted and summary-only metrics noninteractive', async () => {
    const wrapper = render(false)
    expect(wrapper.find('button').exists()).toBe(false)
    await wrapper.find('.finance-metric__content').trigger('click')
    expect(wrapper.emitted('review')).toBeUndefined()
  })
})
