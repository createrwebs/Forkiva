import { afterEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import RecoveryCodeInput from './RecoveryCodeInput.vue'
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))
const mounts: ReturnType<typeof mount>[] = []
afterEach(() => mounts.splice(0).forEach((wrapper) => wrapper.unmount()))
function render() {
  const wrapper = mount(RecoveryCodeInput, { props: { modelValue: '' }, attachTo: document.body })
  mounts.push(wrapper)
  return wrapper
}
describe('six digit recovery code', () => {
  it('accepts pasted Arabic numerals and fills all six cells', async () => {
    const wrapper = render()
    await wrapper.get('input').trigger('paste', { clipboardData: { getData: () => '١٢٣٤٥٦' } })
    expect(
      wrapper.findAll('input').map((input) => (input.element as HTMLInputElement).value),
    ).toEqual(['1', '2', '3', '4', '5', '6'])
    expect(wrapper.emitted('update:modelValue')?.slice(-1)[0]).toEqual(['123456'])
  })
  it('distributes a complete autofilled code across six cells', async () => {
    const wrapper = render()
    await wrapper.get('input').setValue('123456')
    expect(
      wrapper.findAll('input').map((input) => (input.element as HTMLInputElement).value),
    ).toEqual(['1', '2', '3', '4', '5', '6'])
    expect(wrapper.emitted('update:modelValue')?.slice(-1)[0]).toEqual(['123456'])
  })
  it('advances focus, supports backspace navigation, and ignores letters', async () => {
    const wrapper = render()
    const inputs = wrapper.findAll('input')
    await inputs[0]!.setValue('1')
    expect(document.activeElement).toBe(inputs[1]!.element)
    await inputs[1]!.setValue('x')
    expect((inputs[1]!.element as HTMLInputElement).value).toBe('')
    await inputs[1]!.trigger('keydown', { key: 'Backspace' })
    expect(document.activeElement).toBe(inputs[0]!.element)
    expect(wrapper.emitted('update:modelValue')?.slice(-1)[0]).toEqual([''])
  })
})
