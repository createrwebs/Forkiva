import { shallowMount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import FinanceDues from './FinanceDues.vue'

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))

function render(amount: number) {
  return shallowMount(FinanceDues, {
    props: {
      amount: { amount, formatted: `${amount.toFixed(3)} JOD`, currency: 'JOD', precision: 3 },
    },
    global: { stubs: { VIcon: true } },
  })
}

describe('unpaid order balance', () => {
  it('opens the drawer from the card and shows an outstanding balance', async () => {
    const wrapper = render(25.5)
    expect(wrapper.text()).toContain('25.500 JOD')
    expect(wrapper.text()).toContain('dailyfinance::daily_finance.dues.pending')
    expect(wrapper.attributes('aria-haspopup')).toBe('dialog')
    await wrapper.trigger('click')
    expect(wrapper.emitted('review')).toEqual([[]])
  })

  it('shows the settled state when there is no unpaid balance', () => {
    const wrapper = render(0)
    expect(wrapper.text()).toContain('dailyfinance::daily_finance.dues.settled')
    expect(wrapper.text()).not.toContain('dailyfinance::daily_finance.dues.pending')
    expect(wrapper.findComponent({ name: 'VIcon' }).exists()).toBe(true)
  })
})
