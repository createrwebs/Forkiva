import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import PasswordRecovery from './PasswordRecovery.vue'

const mocks = vi.hoisted(() => ({ getSettings: vi.fn(), update: vi.fn() }))
vi.mock('@/modules/setting/composables/setting.ts', () => ({
  useSetting: () => ({ ...mocks, store: { loading: false } }),
}))
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))
vi.mock('vue-toastification', () => ({ useToast: () => ({ success: vi.fn(), error: vi.fn() }) }))
const switchControl = defineComponent({
  props: ['modelValue', 'disabled'],
  emits: ['update:modelValue'],
  template:
    '<input type="checkbox" :checked="modelValue" :disabled="disabled" @change="$emit(\'update:modelValue\', $event.target.checked)" />',
})
const mounts: ReturnType<typeof mount>[] = []
function render() {
  const wrapper = mount(PasswordRecovery, {
    global: {
      stubs: {
        PageLoader: true,
        VForm: { template: '<form><slot /></form>' },
        VSwitch: switchControl,
        VIcon: true,
        VBtn: { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
        VAlert: { template: '<div role="alert"><slot /></div>' },
        RouterLink: { template: '<a><slot /></a>' },
      },
    },
  })
  mounts.push(wrapper)
  return wrapper
}
beforeEach(() => {
  vi.clearAllMocks()
  mocks.getSettings.mockResolvedValue({
    settings: {
      password_recovery_enabled: true,
      password_recovery_email_enabled: true,
      password_recovery_sms_enabled: true,
      providers: { email: true, sms: true },
    },
  })
  mocks.update.mockResolvedValue({ data: {} })
})
afterEach(() => mounts.splice(0).forEach((wrapper) => wrapper.unmount()))

describe('password recovery settings', () => {
  it('locks channel switches when disabled and saves only editable settings', async () => {
    const wrapper = render()
    await flushPromises()
    await wrapper.get('#password-recovery-enabled').setValue(false)
    expect(wrapper.get('#recovery-email-enabled').attributes('disabled')).toBeDefined()
    expect(wrapper.get('#recovery-sms-enabled').attributes('disabled')).toBeDefined()
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(mocks.update).toHaveBeenCalledWith('password_recovery', {
      password_recovery_enabled: false,
      password_recovery_email_enabled: true,
      password_recovery_sms_enabled: true,
    })
  })
  it('shows ready services for both channels and allows selecting email only', async () => {
    const wrapper = render()
    await flushPromises()
    expect(wrapper.findAll('.recovery-method__status--ready')).toHaveLength(2)
    await wrapper.get('#recovery-sms-enabled').setValue(false)
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(mocks.update).toHaveBeenCalledWith('password_recovery', {
      password_recovery_enabled: true,
      password_recovery_email_enabled: true,
      password_recovery_sms_enabled: false,
    })
  })
  it('prevents saving empty defaults if loading fails', async () => {
    mocks.getSettings.mockResolvedValue(false)
    const wrapper = render()
    await flushPromises()
    expect(wrapper.find('form').exists()).toBe(false)
    expect(wrapper.get('[role="alert"]').text()).toContain('failed_to_load_data')
    expect(mocks.update).not.toHaveBeenCalled()
  })
})
