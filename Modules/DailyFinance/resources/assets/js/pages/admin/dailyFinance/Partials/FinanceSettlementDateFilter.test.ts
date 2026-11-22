import { ref } from 'vue'
import { shallowMount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import FinanceSettlementDateFilter from './FinanceSettlementDateFilter.vue'
import DatePicker from '@/modules/core/components/Form/DatePicker.vue'

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key, locale: ref('en-US') }) }))
function render() {
  return shallowMount(FinanceSettlementDateFilter, {
    props: { from: '2026-09-14', to: '2026-10-13', today: '2026-10-13', disabled: false },
    global: {
      renderStubDefaultSlot: true,
      stubs: Object.fromEntries(['VMenu', 'VCard', 'VBtn'].map((name) => [name, true])),
    },
  })
}
describe('settlement date filter', () => {
  it('uses shared date pickers and only applies a complete valid range', async () => {
    const wrapper = render()
    const pickers = wrapper.findAllComponents(DatePicker)
    expect(pickers).toHaveLength(2)
    const apply = wrapper
      .findAllComponents({ name: 'VBtn' })
      .find((button) => button.text().endsWith('.apply_dates'))!
    pickers[0]!.vm.$emit('update:modelValue', '')
    await wrapper.vm.$nextTick()
    expect(apply.attributes('disabled')).toBe('true')
    apply.vm.$emit('click')
    expect(wrapper.emitted('change')).toBeUndefined()
    pickers[0]!.vm.$emit('update:modelValue', '2026-10-14')
    await wrapper.vm.$nextTick()
    expect(apply.attributes('disabled')).toBe('true')
    pickers[0]!.vm.$emit('update:modelValue', '2026-10-01')
    await wrapper.vm.$nextTick()
    apply.vm.$emit('click')
    expect(wrapper.emitted('change')?.[0]).toEqual([{ from: '2026-10-01', to: '2026-10-13' }])
    wrapper.unmount()
  })
  it('applies quick periods relative to the branch day', async () => {
    const wrapper = render()
    wrapper
      .findAllComponents({ name: 'VBtn' })
      .find((button) => button.text().endsWith('.range_7'))!
      .vm.$emit('click')
    expect(wrapper.emitted('change')?.[0]).toEqual([{ from: '2026-10-07', to: '2026-10-13' }])
    await wrapper.setProps({ disabled: true })
    wrapper
      .findAllComponents({ name: 'VBtn' })
      .find((button) => button.text().endsWith('.range_1'))!
      .vm.$emit('click')
    expect(wrapper.emitted('change')).toHaveLength(1)
    wrapper.unmount()
  })
})
