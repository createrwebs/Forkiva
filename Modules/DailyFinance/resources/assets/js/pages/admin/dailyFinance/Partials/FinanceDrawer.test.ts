import { shallowMount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import FinanceDrawer from './FinanceDrawer.vue'

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))

function render() {
  return shallowMount(FinanceDrawer, {
    props: { title: 'Sales' },
    global: {
      renderStubDefaultSlot: true,
      stubs: { VDialog: true, VCard: true, VBtn: true, VDivider: true, VCardText: true },
    },
  })
}

describe('finance drawer dismissal', () => {
  it('guards programmatic closes while persistent and keeps the same transition contract', async () => {
    const wrapper = render()
    await wrapper.setProps({ persistent: true })
    wrapper.vm.close()
    await wrapper.vm.$nextTick()
    expect(wrapper.findComponent({ name: 'VDialog' }).attributes('modelvalue')).toBe('true')
    await wrapper.setProps({ persistent: false })
    wrapper.vm.close()
    await wrapper.vm.$nextTick()
    expect(wrapper.findComponent({ name: 'VDialog' }).attributes('modelvalue')).toBe('false')
    expect(wrapper.emitted('close')).toBeUndefined()
    wrapper.findComponent({ name: 'VDialog' }).vm.$emit('afterLeave')
    expect(wrapper.emitted('close')).toEqual([[]])
    wrapper.unmount()
  })

  it('waits for the closing transition before unmounting and returns focus to its trigger', async () => {
    const activator = document.createElement('button')
    document.body.append(activator)
    activator.focus()
    const wrapper = render()
    activator.blur()
    wrapper.findComponent({ name: 'VBtn' }).vm.$emit('click')
    await wrapper.vm.$nextTick()
    expect(wrapper.findComponent({ name: 'VDialog' }).attributes('modelvalue')).toBe('false')
    expect(wrapper.emitted('close')).toBeUndefined()
    wrapper.findComponent({ name: 'VDialog' }).vm.$emit('afterLeave')
    expect(wrapper.emitted('close')).toEqual([[]])
    expect(document.activeElement).toBe(activator)
    wrapper.unmount()
    activator.remove()
  })

  it('uses the same delayed dismissal for Escape and backdrop closes', async () => {
    const wrapper = render()
    const dialog = wrapper.findComponent({ name: 'VDialog' })
    dialog.vm.$emit('update:modelValue', false)
    await wrapper.vm.$nextTick()
    expect(wrapper.emitted('close')).toBeUndefined()
    dialog.vm.$emit('afterLeave')
    expect(wrapper.emitted('close')).toEqual([[]])
  })
})
