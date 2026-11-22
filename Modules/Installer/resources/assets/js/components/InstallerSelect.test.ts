import { DOMWrapper, flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it } from 'vitest'
import InstallerSelect from './InstallerSelect.vue'

const options = [
  { id: 'JO', name: 'Jordan' },
  { id: 'US', name: 'United States' },
]
const wrappers: ReturnType<typeof mount>[] = []
function render(multiple = true) {
  const wrapper = mount(InstallerSelect, {
    attachTo: document.body,
    props: {
      label: 'Countries',
      name: 'countries',
      multiple,
      options,
      modelValue: multiple ? ['JO'] : 'JO',
      'onUpdate:modelValue': (value) => wrapper.setProps({ modelValue: value }),
    },
  })
  wrappers.push(wrapper)
  return wrapper
}
const dialog = () => new DOMWrapper(document.body).get('#countries-menu')
afterEach(() => {
  wrappers.splice(0).forEach((wrapper) => wrapper.unmount())
  document.body.innerHTML = ''
})

describe('installer dropdown', () => {
  it('filters choices, updates multiple selections, and closes with Escape', async () => {
    const wrapper = render()
    await wrapper.get('button[name=countries]').trigger('click')
    await dialog().get('input[type=search]').setValue('United')
    expect(dialog().findAll('input[type=checkbox]')).toHaveLength(1)
    await dialog().get('input[type=checkbox]').setValue(true)
    expect(wrapper.emitted('update:modelValue')?.slice(-1)[0]).toEqual([['JO', 'US']])
    expect(wrapper.get('button[name=countries]').text()).toContain('United States')
    await dialog().get('input[type=search]').trigger('keydown', { key: 'Escape' })
    expect(document.querySelector('#countries-menu')).toBeNull()
    expect(document.activeElement).toBe(wrapper.get('button[name=countries]').element)
  })

  it('uses the same controlled popup for single selections', async () => {
    const wrapper = render(false)
    expect(wrapper.find('select').exists()).toBe(false)
    await wrapper.get('button[name=countries]').trigger('click')
    await dialog().get('input[value=US]').setValue(true)
    expect(wrapper.emitted('update:modelValue')?.slice(-1)[0]).toEqual(['US'])
    expect(document.querySelector('#countries-menu')).toBeNull()
    expect(wrapper.get('button[name=countries]').text()).toContain('United States')
  })

  it('closes and disables the control during submission', async () => {
    const wrapper = render()
    await wrapper.get('button[name=countries]').trigger('click')
    await wrapper.setProps({ disabled: true })
    expect(document.querySelector('#countries-menu')).toBeNull()
    expect(wrapper.get('button[name=countries]').attributes('disabled')).toBeDefined()
  })

  it('does not reopen or focus a removed menu after rapid open/close clicks', async () => {
    const wrapper = render()
    const button = wrapper.get('button[name=countries]')
    for (let index = 0; index < 20; index++) {
      const opened = button.trigger('click')
      const closed = button.trigger('click')
      await Promise.all([opened, closed])
    }
    await flushPromises()
    expect(button.attributes('aria-expanded')).toBe('false')
    expect(document.querySelector('#countries-menu')).toBeNull()
  })

  it('keeps scrolling stable, dismisses outside focus, and cleans up on unmount', async () => {
    const wrapper = render()
    await wrapper.get('button[name=countries]').trigger('click')
    await dialog().get('.installer-select-options').trigger('scroll')
    expect(wrapper.get('button[name=countries]').attributes('aria-expanded')).toBe('true')
    document.dispatchEvent(new Event('scroll'))
    await flushPromises()
    expect(wrapper.get('button[name=countries]').attributes('aria-expanded')).toBe('true')
    document.body.dispatchEvent(new FocusEvent('focusin', { bubbles: true }))
    await flushPromises()
    expect(document.querySelector('#countries-menu')).toBeNull()
    await wrapper.get('button[name=countries]').trigger('click')
    wrapper.unmount()
    expect(document.querySelector('#countries-menu')).toBeNull()
  })
})
