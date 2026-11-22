import { flushPromises, shallowMount } from '@vue/test-utils'
import { ref } from 'vue'
import { describe, expect, it, vi } from 'vitest'
import FinanceFilters from './FinanceFilters.vue'
import DatePicker from '@/modules/core/components/Form/DatePicker.vue'
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key, locale: ref('en') }) }))
function render() {
  return shallowMount(FinanceFilters, {
    props: {
      branchId: 1,
      branches: [{ id: 1, name: 'Main' }],
      branchLocked: false,
      date: '2026-10-11',
      today: '2026-10-11',
      loading: false,
    },
    global: {
      renderStubDefaultSlot: true,
      stubs: {
        VCard: true,
        VSelect: true,
        VMenu: true,
        VBtn: true,
        VAvatar: true,
        VIcon: true,
        VDivider: true,
        VCardText: true,
      },
    },
  })
}
describe('daily finance dashboard filters', () => {
  it('uses the server branch day for Today and Yesterday', async () => {
    const wrapper = render()
    const period = wrapper.findAllComponents({ name: 'VSelect' })[0]!
    period.vm.$emit('update:modelValue', 'yesterday')
    period.vm.$emit('update:modelValue', 'today')
    expect(wrapper.emitted('date')).toEqual([['2026-10-10'], [null]])
    await wrapper.setProps({ today: '2026-01-01', date: '2026-01-01' })
    period.vm.$emit('update:modelValue', 'yesterday')
    expect(wrapper.emitted('date')?.slice(-1)[0]).toEqual(['2025-12-31'])
  })
  it('applies a custom date only after confirmation and resets abandoned edits', async () => {
    const wrapper = render()
    wrapper.findAllComponents({ name: 'VSelect' })[0]!.vm.$emit('update:modelValue', 'custom')
    await flushPromises()
    wrapper.findComponent(DatePicker).vm.$emit('update:modelValue', '2026-10-05')
    expect(wrapper.emitted('date')).toBeUndefined()
    wrapper.findAllComponents({ name: 'VBtn' }).slice(-1)[0]!.vm.$emit('click')
    expect(wrapper.emitted('date')).toEqual([['2026-10-05']])
    await flushPromises()
    wrapper.findAllComponents({ name: 'VSelect' })[0]!.vm.$emit('update:modelValue', 'custom')
    await flushPromises()
    expect(wrapper.findComponent(DatePicker).props('modelValue')).toBe('2026-10-11')
  })
  it('keeps an assigned branch visible and disables its selector', async () => {
    const wrapper = render()
    await wrapper.setProps({ branchLocked: true })
    expect(wrapper.findAllComponents({ name: 'VSelect' })[1]!.attributes('disabled')).toBe('true')
  })
})
