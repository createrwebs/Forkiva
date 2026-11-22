import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import ProfileInformation from './ProfileInformation.vue'
import UpdatePassword from './UpdatePassword.vue'

const account = vi.hoisted(() => ({ me: vi.fn(), updateProfile: vi.fn(), updatePassword: vi.fn() }))
const auth = vi.hoisted(() => ({ can: vi.fn(), store: { getUser: { id: 1 }, setUser: vi.fn() } }))
vi.mock('@/modules/user/composables/account.ts', () => ({ useAccount: () => account }))
vi.mock('@/modules/auth/composables/auth.ts', () => ({ useAuth: () => auth }))
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))
vi.mock('vue-toastification', () => ({ useToast: () => ({ success: vi.fn(), error: vi.fn() }) }))
const slot = { template: '<div><slot /></div>' }
const field = defineComponent({
  props: ['modelValue', 'label', 'readonly', 'disabled', 'errorMessages', 'type'],
  emits: ['update:modelValue'],
  template:
    '<label>{{ label }}<input :aria-label="label" :value="modelValue" :readonly="readonly" :disabled="disabled" :type="type" @input="$emit(\'update:modelValue\', $event.target.value)" /><span>{{ errorMessages }}</span></label>',
})
const stubs = {
  VRow: slot,
  VCol: slot,
  VAlert: slot,
  VProgressCircular: true,
  VIcon: true,
  VCheckbox: true,
  VTextField: field,
  VBtn: defineComponent({
    props: ['disabled', 'loading'],
    template: '<button :disabled="disabled || loading"><slot /></button>',
  }),
}
const profile = {
  name: 'Alex Morgan',
  email: 'alex@example.test',
  username: 'alex',
  gender: { name: 'Male' },
  branch: null,
}

describe('profile page forms', () => {
  let wrapper: ReturnType<typeof mount>
  beforeEach(() => {
    vi.clearAllMocks()
    auth.can.mockReturnValue(true)
    auth.store.getUser = { id: 1 }
    account.me.mockResolvedValue({ data: { body: profile } })
  })
  afterEach(() => wrapper?.unmount())

  it('updates only editable fields and synchronizes the active account', async () => {
    wrapper = mount(ProfileInformation, { global: { stubs } })
    await flushPromises()
    expect(wrapper.find('button').attributes('disabled')).toBeDefined()
    await wrapper.get('input[aria-label="user::attributes.users.name"]').setValue('Alex Updated')
    const user = { ...profile, id: 1, name: 'Alex Updated' }
    account.updateProfile.mockResolvedValue({ data: { body: { user }, message: 'Saved' } })
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(account.updateProfile).toHaveBeenCalledWith({
      name: 'Alex Updated',
      email: profile.email,
      username: profile.username,
    })
    expect(auth.store.setUser).toHaveBeenCalledWith(user)
    expect(wrapper.find('button').attributes('disabled')).toBeDefined()
    expect(wrapper.emitted('busy')).toEqual([[true], [false]])
  })

  it('keeps profiles read-only without edit permission, including programmatic submission', async () => {
    auth.can.mockReturnValue(false)
    wrapper = mount(ProfileInformation, { global: { stubs } })
    await flushPromises()
    expect(wrapper.get('input').attributes('readonly')).toBeDefined()
    expect(wrapper.text()).toContain('user::profiles.read_only')
    await wrapper.get('form').trigger('submit')
    expect(account.updateProfile).not.toHaveBeenCalled()
  })

  it('retries a failed profile load before allowing edits', async () => {
    account.me.mockRejectedValueOnce(new Error('Offline'))
    wrapper = mount(ProfileInformation, { global: { stubs } })
    await flushPromises()
    expect(wrapper.findAll('input')).toHaveLength(0)
    await wrapper.get('button').trigger('click')
    await flushPromises()
    expect(account.me).toHaveBeenCalledTimes(2)
    expect(wrapper.get('input').element.value).toBe('Alex Morgan')
  })

  it('does not overwrite another account when an earlier save finishes', async () => {
    wrapper = mount(ProfileInformation, { global: { stubs } })
    await flushPromises()
    await wrapper.get('input').setValue('Alex Updated')
    let finish!: (result: unknown) => void
    account.updateProfile.mockReturnValue(
      new Promise((resolve) => {
        finish = resolve
      }),
    )
    await wrapper.get('form').trigger('submit')
    auth.store.getUser = { id: 2 }
    finish({ data: { body: { user: { id: 1 } } } })
    await flushPromises()
    expect(auth.store.setUser).not.toHaveBeenCalled()
  })

  it('preserves password field errors and clears credentials only after success', async () => {
    wrapper = mount(UpdatePassword, { global: { stubs } })
    for (const input of wrapper.findAll('input')) await input.setValue('Test-only-password')
    account.updatePassword.mockRejectedValueOnce({
      response: { status: 422, data: { errors: { current_password: ['Incorrect password'] } } },
    })
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(wrapper.text()).toContain('Incorrect password')
    expect(wrapper.get('input').element.value).toBe('Test-only-password')
    account.updatePassword.mockResolvedValue({ data: { message: 'Updated' } })
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(wrapper.findAll('input').every((input) => input.element.value === '')).toBe(true)
    expect(account.updatePassword).toHaveBeenLastCalledWith({
      current_password: 'Test-only-password',
      password: 'Test-only-password',
      password_confirmation: 'Test-only-password',
      logout_from_other_devices: false,
    })
  })
})
