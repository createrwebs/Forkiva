import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import PasswordRecoveryForm from './PasswordRecoveryForm.vue'
import * as api from '@/modules/auth/api/passwordRecovery.api.ts'
vi.mock('@/modules/auth/api/passwordRecovery.api.ts', () => ({
  identifyRecoveryAccount: vi.fn(),
  requestRecoveryCode: vi.fn(),
  verifyRecoveryCode: vi.fn(),
  resetPassword: vi.fn(),
}))
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))
vi.mock('vue-toastification', () => ({ useToast: () => ({ success: vi.fn(), error: vi.fn() }) }))
const field = defineComponent({
  props: ['modelValue', 'label', 'errorMessages', 'disabled'],
  emits: ['update:modelValue'],
  template:
    '<label>{{ label }}<input :value="modelValue" :disabled="disabled" @input="$emit(\'update:modelValue\', $event.target.value)"/><span>{{ errorMessages }}</span></label>',
})
const mounts: ReturnType<typeof mount>[] = []
function render() {
  const wrapper = mount(PasswordRecoveryForm, {
    global: {
      stubs: {
        VForm: { template: '<form><slot /></form>' },
        VTextField: field,
        VBtn: { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
        VIcon: true,
      },
    },
  })
  mounts.push(wrapper)
  return wrapper
}
const identity = { recovery_token: 'b'.repeat(64), channels: ['email'], expires_in: 600 }
const challenge = { challenge_token: 'a'.repeat(64), expires_in: 600, resend_available_in: 60 }
const grant = { reset_token: 'c'.repeat(64), expires_in: 500 }
beforeEach(() => {
  vi.clearAllMocks()
  vi.mocked(api.identifyRecoveryAccount).mockResolvedValue({ data: { body: identity } } as any)
  vi.mocked(api.requestRecoveryCode).mockResolvedValue({ data: { body: challenge } } as any)
  vi.mocked(api.verifyRecoveryCode).mockResolvedValue({ data: { body: grant } } as any)
  vi.mocked(api.resetPassword).mockResolvedValue({ data: { body: { success: true } } } as any)
})
afterEach(() => mounts.splice(0).forEach((w) => w.unmount()))
async function identify(wrapper: ReturnType<typeof mount>) {
  await wrapper.get('#recovery-identifier input').setValue('team-member')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
}
async function verify(wrapper: ReturnType<typeof mount>) {
  const inputs = wrapper.findAll('.recovery-digits input')
  for (let index = 0; index < 6; index++) await inputs[index]!.setValue(String(index + 1))
  await wrapper.get('form').trigger('submit')
  await flushPromises()
}
describe('identifier-first password recovery', () => {
  it.each(['email', 'sms'])(
    'asks for identity first and automatically sends through the only available method (%s)',
    async (channel) => {
      vi.mocked(api.identifyRecoveryAccount).mockResolvedValue({
        data: { body: { ...identity, channels: [channel] } },
      } as any)
      const wrapper = render()
      expect(wrapper.findAll('input')).toHaveLength(1)
      expect(wrapper.text()).toContain('user::attributes.auth.identifier')
      expect(wrapper.find('input[type="radio"]').exists()).toBe(false)
      await identify(wrapper)
      expect(api.identifyRecoveryAccount).toHaveBeenCalledWith('team-member')
      expect(api.requestRecoveryCode).toHaveBeenCalledWith(channel, identity.recovery_token)
      expect(wrapper.findAll('.recovery-digits input')).toHaveLength(6)
      expect(wrapper.find('#recovery-password').exists()).toBe(false)
      const resend = wrapper
        .findAll('button')
        .find((button) => button.text().includes('recovery_resend_in'))!
      expect(resend.attributes('disabled')).toBeDefined()
    },
  )
  it('offers only the account methods and waits for one selection before sending', async () => {
    vi.mocked(api.identifyRecoveryAccount).mockResolvedValue({
      data: { body: { ...identity, channels: ['email', 'sms'] } },
    } as any)
    const wrapper = render()
    await identify(wrapper)
    expect(api.requestRecoveryCode).not.toHaveBeenCalled()
    expect(wrapper.findAll('input[type="radio"]')).toHaveLength(2)
    expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeDefined()
    await wrapper.get('input[value="sms"]').setValue()
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(api.requestRecoveryCode).toHaveBeenCalledWith('sms', identity.recovery_token)
    expect(wrapper.find('input[type="radio"]').exists()).toBe(false)
  })
  it('keeps invalid codes on the code step without exposing password fields', async () => {
    vi.mocked(api.verifyRecoveryCode).mockRejectedValue({
      response: { status: 422, data: { errors: { code: ['Invalid code'] } } },
    })
    const wrapper = render()
    await identify(wrapper)
    await verify(wrapper)
    expect(wrapper.text()).toContain('Invalid code')
    expect(wrapper.find('#recovery-password').exists()).toBe(false)
    expect(api.resetPassword).not.toHaveBeenCalled()
  })
  it('verifies first, then sends only the reset grant and new password', async () => {
    const wrapper = render()
    await identify(wrapper)
    await verify(wrapper)
    expect(api.verifyRecoveryCode).toHaveBeenCalledWith(challenge.challenge_token, '123456')
    expect(wrapper.find('.recovery-digits').exists()).toBe(false)
    await wrapper.get('#recovery-password input').setValue('NewPassword123!')
    await wrapper.get('#recovery-confirmation input').setValue('NewPassword123!')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(api.resetPassword).toHaveBeenCalledWith({
      reset_token: grant.reset_token,
      password: 'NewPassword123!',
      password_confirmation: 'NewPassword123!',
    })
    expect(wrapper.findAll('input')).toHaveLength(0)
    await wrapper.get('button').trigger('click')
    expect(wrapper.emitted('complete')).toHaveLength(1)
  })
  it('handles unavailable contacts without sending a code', async () => {
    vi.mocked(api.identifyRecoveryAccount).mockResolvedValue({
      data: { body: { ...identity, channels: [] } },
    } as any)
    const wrapper = render()
    await identify(wrapper)
    expect(wrapper.text()).toContain('recovery_no_methods')
    expect(api.requestRecoveryCode).not.toHaveBeenCalled()
  })
  it('shows rate limiting and blocks repeated identity requests', async () => {
    vi.mocked(api.identifyRecoveryAccount).mockRejectedValue({
      response: { status: 429, headers: { 'retry-after': '60' }, data: { message: 'Please wait' } },
    })
    const wrapper = render()
    await identify(wrapper)
    expect(wrapper.text()).toContain('Please wait')
    expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeDefined()
  })
})
