import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { computed, defineComponent, ref, watch } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { useCountdown } from '@vueuse/core'
import { AxiosHeaders } from 'axios'
import TwoFactor from '@/modules/user/components/profile/TwoFactor.vue'
import Passkeys from '@/modules/user/components/profile/Passkeys.vue'
import Login from './Login.vue'
import * as api from '@/modules/auth/api/twoFactor.api.ts'
import * as recoveryApi from '@/modules/auth/api/passwordRecovery.api.ts'
import * as passkeyApi from '@/modules/auth/api/passkey.api.ts'

const auth = vi.hoisted(() => ({
  login: vi.fn(),
  verifyTwoFactor: vi.fn(),
  store: { isAuthenticated: false },
}))
const toast = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn(), warning: vi.fn() }))
vi.mock('@/modules/auth/composables/auth.ts', () => ({ useAuth: () => auth }))
vi.mock('@/modules/core/stores/appStore.ts', () => ({
  useAppStore: () => ({ settings: {}, appName: 'Forkiva' }),
}))
vi.mock('@/modules/auth/api/passkey.api.ts', () => ({
  supportsPasskeys: () => true,
  getPasskeys: vi.fn(),
  registerPasskey: vi.fn(),
  deletePasskey: vi.fn(),
}))
vi.mock('@/modules/core/composables/confirmDialog.ts', () => ({ useConfirmDialog: vi.fn() }))
vi.mock('vue-router', () => ({
  useRoute: () => ({ query: {} }),
  useRouter: () => ({ push: vi.fn() }),
}))
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))
vi.mock('vue-toastification', () => ({ useToast: () => toast }))
vi.mock('@/modules/auth/api/twoFactor.api.ts', () => ({
  getStatus: vi.fn(),
  setup: vi.fn(),
  resendLogin: vi.fn(),
  sendAccountCode: vi.fn(),
  resendSetup: vi.fn(),
}))

const button = defineComponent({
  props: ['disabled', 'loading'],
  template: '<button :disabled="disabled || loading"><slot /></button>',
})
const field = defineComponent({
  props: ['modelValue', 'label', 'disabled', 'errorMessages', 'hint'],
  emits: ['update:modelValue'],
  template:
    '<label>{{ label }}<input :aria-label="$attrs[\'aria-label\'] || label" :value="modelValue" :disabled="disabled" @input="$emit(\'update:modelValue\', $event.target.value)" /><span>{{ errorMessages }}</span><small>{{ hint }}</small></label>',
})
const slot = { template: '<div><slot /></div>' }
vi.mock('@/modules/auth/api/passwordRecovery.api.ts', () => ({
  getRecoveryOptions: vi.fn().mockResolvedValue({ data: { body: { channels: [] } } }),
}))

const stubs = {
  VThemeProvider: slot,
  VForm: { template: '<form><slot /></form>' },
  VDialog: slot,
  VCard: slot,
  VCardText: slot,
  VCardTitle: slot,
  VCardActions: slot,
  VAlert: slot,
  VBtn: button,
  VTextField: field,
  VIcon: true,
  VDivider: true,
  VSpacer: true,
  VProgressCircular: true,
  QRCodeVue3: true,
  RouterLink: slot,
}
const status = {
  enabled: true,
  available: true,
  method: 'authenticator',
  confirmed_at: 'fixture-date',
  recovery_codes_remaining: 8,
  resend_available_in: 0,
  methods: [
    { id: 'authenticator', available: true },
    { id: 'email', available: false },
  ],
}
const response = (body: unknown) => ({
  data: { body },
  status: 200,
  statusText: 'OK',
  headers: {},
  config: { headers: new AxiosHeaders() },
})
const deliveredStatus = {
  ...status,
  method: 'sms',
  methods: [
    { id: 'authenticator', available: true },
    { id: 'sms', available: true, destination: '*******4123' },
    { id: 'email', available: true, destination: 'f***@example.test' },
  ],
}

describe('two-factor security UI flows', () => {
  let wrapper: ReturnType<typeof mount>
  beforeEach(() => {
    vi.clearAllMocks()
    for (const [key, value] of Object.entries({ ref, computed, watch, useCountdown }))
      vi.stubGlobal(key, value)
    vi.mocked(api.getStatus).mockResolvedValue({
      data: { body: status },
      status: 200,
      statusText: 'OK',
      headers: {},
      config: { headers: new AxiosHeaders() },
    })
  })
  afterEach(() => {
    wrapper?.unmount()
    vi.useRealTimers()
    vi.unstubAllGlobals()
  })

  it('sends manually first, preserves resend state between steps, and resets on reopening', async () => {
    vi.useFakeTimers()
    vi.mocked(api.getStatus).mockResolvedValue(response(deliveredStatus))
    wrapper = mount(TwoFactor, { props: { modelValue: true }, global: { stubs } })
    await flushPromises()
    const action = () => wrapper.find('.management-resend button')
    expect(action().text()).toBe('user::auth.two_factor_send')
    expect(api.sendAccountCode).not.toHaveBeenCalled()
    expect(wrapper.text()).not.toContain('user::auth.two_factor_resend_hint')

    let reject!: (error: unknown) => void
    vi.mocked(api.sendAccountCode).mockImplementationOnce(
      () =>
        new Promise((_, fail) => {
          reject = fail
        }),
    )
    await action().trigger('click')
    expect(wrapper.findAll('button').every((b) => b.attributes('disabled') !== undefined)).toBe(
      true,
    )
    reject(new Error('Delivery failed'))
    await flushPromises()
    expect(action().text()).toBe('user::auth.two_factor_send')

    vi.mocked(api.sendAccountCode).mockResolvedValueOnce(response({ resend_available_in: 60 }))
    await action().trigger('click')
    await flushPromises()
    expect(action().text()).toBe('user::auth.two_factor_resend_short_in')
    expect(action().attributes('disabled')).toBeDefined()
    await vi.advanceTimersByTimeAsync(60_000)
    expect(action().text()).toBe('user::auth.two_factor_resend')
    expect(action().attributes('disabled')).toBeUndefined()

    await wrapper
      .findAll('button')
      .find((b) => b.text() === 'user::auth.two_factor_change_method')!
      .trigger('click')
    expect(wrapper.find('.identity-send-action').text()).toBe('user::auth.two_factor_resend')
    await wrapper.setProps({ modelValue: false })
    await wrapper.setProps({ modelValue: true })
    await flushPromises()
    expect(action().text()).toBe('user::auth.two_factor_send')
    expect(api.sendAccountCode).toHaveBeenCalledTimes(2)
  })

  it('shows the account resend timer immediately when login already started the cooldown', async () => {
    vi.useFakeTimers()
    vi.mocked(api.getStatus).mockResolvedValue(
      response({ ...deliveredStatus, resend_available_in: 42 }),
    )
    wrapper = mount(TwoFactor, { props: { modelValue: true }, global: { stubs } })
    await flushPromises()

    const action = wrapper.find('.management-resend button')
    expect(action.text()).toBe('user::auth.two_factor_resend_short_in')
    expect(action.attributes('disabled')).toBeDefined()
    await action.trigger('click')
    expect(api.sendAccountCode).not.toHaveBeenCalled()
  })

  it('turns a resend race into a timer without showing an error toast', async () => {
    vi.useFakeTimers()
    vi.mocked(api.getStatus).mockResolvedValue(response(deliveredStatus))
    vi.mocked(api.sendAccountCode).mockRejectedValue({
      response: {
        status: 429,
        headers: { 'retry-after': '37' },
        data: { message: 'Wait before requesting another code' },
      },
    })
    wrapper = mount(TwoFactor, { props: { modelValue: true }, global: { stubs } })
    await flushPromises()

    const action = wrapper.find('.management-resend button')
    await action.trigger('click')
    await flushPromises()

    expect(action.text()).toBe('user::auth.two_factor_resend_short_in')
    expect(action.attributes('disabled')).toBeDefined()
    expect(toast.error).not.toHaveBeenCalled()
  })

  it('disables code delivery when the enrolled SMS method is unavailable', async () => {
    vi.mocked(api.getStatus).mockResolvedValue(
      response({
        ...deliveredStatus,
        enabled: false,
        methods: deliveredStatus.methods.map((method) =>
          method.id === 'sms' ? { ...method, available: false } : method,
        ),
      }),
    )
    wrapper = mount(TwoFactor, { props: { modelValue: true }, global: { stubs } })
    await flushPromises()

    const send = wrapper.find('.identity-send-action')
    expect(send.exists()).toBe(true)
    expect(send.text()).toBe('user::auth.two_factor_send')
    expect(send.attributes('disabled')).toBeDefined()
    expect(wrapper.text()).toContain('user::auth.two_factor_send_unavailable')
    expect(
      wrapper.find('input[aria-label="user::auth.two_factor_code"]').attributes('disabled'),
    ).toBeUndefined()

    await send.trigger('click')
    expect(api.sendAccountCode).not.toHaveBeenCalled()
  })

  it.each(['sms', 'email'])(
    'shows setup resend with its own timer and instructions for %s',
    async (method) => {
      vi.useFakeTimers()
      vi.mocked(api.getStatus).mockResolvedValue(
        response({ ...deliveredStatus, enabled: false, method: null, confirmed_at: null }),
      )
      const setup = {
        method,
        destination: 'fixture-destination',
        expires_in: 600,
        resend_available_in: 60,
      }
      vi.mocked(api.setup).mockResolvedValueOnce(response(setup))
      wrapper = mount(TwoFactor, { props: { modelValue: true }, global: { stubs } })
      await flushPromises()
      await wrapper
        .findAll('.method-option')
        .find((button) => button.text().includes(`user::auth.two_factor_${method}`))!
        .trigger('click')
      await wrapper
        .find('input[aria-label="user::attributes.two_factor.current_password"]')
        .setValue('fixture-password')
      await wrapper
        .findAll('button')
        .find((b) => b.text() === 'user::auth.two_factor_continue')!
        .trigger('click')
      await flushPromises()
      const resend = () => wrapper.find('.setup-resend button')
      expect(resend().attributes('disabled')).toBeDefined()
      expect(resend().text()).toBe('user::auth.two_factor_resend_short_in')
      expect(wrapper.text()).toContain('user::auth.two_factor_delivered_code_hint')
      expect(wrapper.text()).not.toContain('user::auth.two_factor_scan_qr_title')
      expect(api.resendSetup).not.toHaveBeenCalled()
      await vi.advanceTimersByTimeAsync(60_000)
      expect(resend().text()).toBe('user::auth.two_factor_resend')

      let finish!: (value: ReturnType<typeof response>) => void
      vi.mocked(api.resendSetup).mockImplementationOnce(
        () =>
          new Promise((resolve) => {
            finish = resolve
          }),
      )
      await wrapper.find('input').setValue('111111')
      await resend().trigger('click')
      expect(wrapper.findAll('button').every((b) => b.attributes('disabled') !== undefined)).toBe(
        true,
      )
      expect(wrapper.find('input').attributes('disabled')).toBeDefined()
      finish(response({ ...setup, expires_in: 540 }))
      await flushPromises()
      expect((wrapper.find('input').element as HTMLInputElement).value).toBe('')
      expect(resend().attributes('disabled')).toBeDefined()
      expect(api.sendAccountCode).not.toHaveBeenCalled()
      expect(api.setup).toHaveBeenCalledTimes(1)
      expect(api.resendSetup).toHaveBeenCalledTimes(1)
    },
  )

  it('requires and sends the current factor before replacement, showing errors inline', async () => {
    wrapper = mount(TwoFactor, { props: { modelValue: true }, global: { stubs } })
    await flushPromises()
    await wrapper
      .findAll('button')
      .find((b) => b.text() === 'user::auth.two_factor_change_method')!
      .trigger('click')
    expect(wrapper.text()).toContain('user::auth.two_factor_existing_code_hint')
    expect(wrapper.findAll('.method-option')).toHaveLength(1)
    await wrapper
      .find('input[aria-label="user::attributes.two_factor.current_password"]')
      .setValue('fixture-password')
    const next = wrapper
      .findAll('button')
      .find((b) => b.text() === 'user::auth.two_factor_continue')!
    expect(next.attributes('disabled')).toBeDefined()
    await wrapper.find('input[aria-label="user::auth.two_factor_code"]').setValue('ABCDE-12345')
    vi.mocked(api.setup).mockRejectedValue({
      response: { data: { errors: { code: ['Invalid current code'] } } },
    })
    await next.trigger('click')
    await flushPromises()
    expect(api.setup).toHaveBeenCalledWith('authenticator', 'fixture-password', 'ABCDE-12345')
    expect(wrapper.text()).toContain('Invalid current code')
  })

  it('requires passkey reauthentication and shows backend errors under the matching field', async () => {
    vi.mocked(passkeyApi.getPasskeys).mockResolvedValue({
      data: { body: { passkeys: [] } },
      status: 200,
      statusText: 'OK',
      headers: {},
      config: { headers: new AxiosHeaders() },
    })
    wrapper = mount(Passkeys, { props: { modelValue: true }, global: { stubs } })
    await flushPromises()
    const add = wrapper.findAll('button').find((b) => b.text() === 'user::auth.passkey_add')!
    await wrapper.find('input[aria-label="user::auth.passkey_name"]').setValue('Fixture passkey')
    await wrapper
      .find('input[aria-label="user::attributes.two_factor.current_password"]')
      .setValue('fixture-password')
    expect(add.attributes('disabled')).toBeDefined()
    await wrapper.find('input[aria-label="user::auth.two_factor_code"]').setValue('WRONG-00000')
    let reject!: (error: unknown) => void
    vi.mocked(passkeyApi.registerPasskey).mockImplementation(
      () =>
        new Promise((_, fail) => {
          reject = fail
        }),
    )
    await add.trigger('click')
    expect(
      wrapper.findAll('input').every((input) => input.attributes('disabled') !== undefined),
    ).toBe(true)
    expect(
      wrapper.findAll('button').every((button) => button.attributes('disabled') !== undefined),
    ).toBe(true)
    reject({ response: { data: { errors: { code: ['Invalid current factor'] } } } })
    await flushPromises()
    expect(passkeyApi.registerPasskey).toHaveBeenCalledWith(
      'Fixture passkey',
      'fixture-password',
      'WRONG-00000',
    )
    expect(wrapper.text()).toContain('Invalid current factor')
    await wrapper.setProps({ modelValue: false })
    await wrapper.setProps({ modelValue: true })
    await flushPromises()
    expect(
      (
        wrapper.find('input[aria-label="user::attributes.two_factor.current_password"]')
          .element as HTMLInputElement
      ).value,
    ).toBe('')
    expect(
      (wrapper.find('input[aria-label="user::auth.two_factor_code"]').element as HTMLInputElement)
        .value,
    ).toBe('')
  })

  it('shows Send Code first and changes to the resend timer only after sending', async () => {
    vi.useFakeTimers()
    vi.mocked(api.getStatus).mockResolvedValue(response(deliveredStatus))
    vi.mocked(passkeyApi.getPasskeys).mockResolvedValue(
      response({
        passkeys: [],
      }),
    )
    vi.mocked(api.sendAccountCode).mockResolvedValue(response({ resend_available_in: 60 }))
    wrapper = mount(Passkeys, { props: { modelValue: true }, global: { stubs } })
    await flushPromises()

    const action = () => wrapper.find('.code-delivery-row button')
    expect(action().text()).toBe('user::auth.two_factor_send')
    expect(wrapper.text()).toContain('user::auth.two_factor_send_hint')

    await action().trigger('click')
    await flushPromises()
    expect(api.sendAccountCode).toHaveBeenCalledTimes(1)
    expect(action().text()).toBe('user::auth.two_factor_resend_short_in')
    expect(action().attributes('disabled')).toBeDefined()
    expect(wrapper.text()).toContain('user::auth.two_factor_resend_hint')

    await vi.advanceTimersByTimeAsync(60_000)
    expect(action().text()).toBe('user::auth.two_factor_resend')
    expect(action().attributes('disabled')).toBeUndefined()
  })

  it('preserves the identifier and clears the password when returning from password recovery', async () => {
    vi.mocked(recoveryApi.getRecoveryOptions).mockResolvedValueOnce(
      response({ channels: ['email', 'sms'] }),
    )
    wrapper = mount(Login, { global: { stubs } })
    await flushPromises()
    const identifier = () => wrapper.get('input[aria-label="user::attributes.auth.identifier"]')
    const password = () => wrapper.get('input[aria-label="user::attributes.auth.password"]')
    await identifier().setValue('fixture-user')
    await password().setValue('fixture-password')
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'user::auth.forgot_password')!
      .trigger('click')
    expect(wrapper.text()).toContain('user::auth.recovery_step_identify')
    expect(wrapper.find('input[aria-label="user::attributes.auth.password"]').exists()).toBe(false)
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'user::auth.recovery_back')!
      .trigger('click')
    expect((identifier().element as HTMLInputElement).value).toBe('fixture-user')
    expect((password().element as HTMLInputElement).value).toBe('')
    expect(wrapper.text()).toContain('user::auth.login_welcome')
  })

  it('keeps recovery verification available after delivery failure and locks other actions while verifying', async () => {
    auth.login.mockResolvedValue({
      two_factor_required: true,
      method: 'sms',
      challenge_token: 'fixture-challenge',
      delivery_failed: true,
      can_resend: true,
      resend_available_in: 0,
    })
    wrapper = mount(Login, { global: { stubs } })
    await wrapper
      .find('input[aria-label="user::attributes.auth.identifier"]')
      .setValue('fixture-user')
    await wrapper
      .find('input[aria-label="user::attributes.auth.password"]')
      .setValue('fixture-password')
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    expect(wrapper.text()).toContain('user::auth.two_factor_delivery_failed')
    await wrapper.find('input[aria-label="user::auth.two_factor_code"]').setValue('ABCDE-12345')
    let finish!: () => void
    auth.verifyTwoFactor.mockImplementation(
      () =>
        new Promise<void>((resolve) => {
          finish = resolve
        }),
    )
    await wrapper.find('form').trigger('submit')
    for (const key of ['admin::admin.buttons.back', 'user::auth.two_factor_resend']) {
      expect(
        wrapper
          .findAll('button')
          .find((b) => b.text() === key)!
          .attributes('disabled'),
      ).toBeDefined()
    }
    expect(auth.verifyTwoFactor).toHaveBeenCalledWith('fixture-challenge', 'ABCDE-12345')
    finish()
    await flushPromises()
  })

  it('shows a recovery-only login challenge when the enrolled SMS method is disabled', async () => {
    auth.login.mockResolvedValue({
      two_factor_required: true,
      method: 'sms',
      challenge_token: 'fixture-challenge',
      delivery_failed: true,
      can_resend: false,
      resend_available_in: 0,
    })
    wrapper = mount(Login, { global: { stubs } })
    await wrapper
      .find('input[aria-label="user::attributes.auth.identifier"]')
      .setValue('fixture-user')
    await wrapper
      .find('input[aria-label="user::attributes.auth.password"]')
      .setValue('fixture-password')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('user::auth.two_factor_send_unavailable')
    expect(wrapper.find('input[aria-label="user::auth.two_factor_code"]').exists()).toBe(true)
    expect(
      wrapper.findAll('button').some((button) => button.text() === 'user::auth.two_factor_resend'),
    ).toBe(false)
  })
})
