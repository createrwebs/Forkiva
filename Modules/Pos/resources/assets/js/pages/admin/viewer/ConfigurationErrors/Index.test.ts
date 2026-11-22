import { shallowMount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { PosForm, PosMeta } from '@/modules/pos/contracts/posViewer.ts'
import Alert from './Alert.vue'
import ConfigurationErrors from './Index.vue'

const { can, push } = vi.hoisted(() => ({ can: vi.fn(() => true), push: vi.fn() }))

vi.mock('@/modules/auth/composables/auth.ts', () => ({ useAuth: () => ({ can }) }))
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))
vi.mock('vue-router', () => ({ useRouter: () => ({ push }) }))
vi.mock('@/modules/pos/pages/admin/session/Dialogs/OpenDialog.vue', () => ({
  default: { name: 'OpenDialog', template: '<div />' },
}))

function mountConfiguration() {
  return shallowMount(ConfigurationErrors, {
    props: {
      form: { branchId: 1, registerId: 10, sessionId: null } as PosForm,
      meta: {
        branches: [{ id: 1, name: 'Main branch' }],
        menus: [{ id: 2, name: 'Main menu' }],
        registers: [{ id: 10, name: 'Front register' }],
      } as unknown as PosMeta,
    },
    global: { stubs: { VContainer: { template: '<div><slot /></div>' } } },
  })
}

beforeEach(() => {
  can.mockReturnValue(true)
  push.mockReset()
})

describe('POS configuration recovery', () => {
  it('keeps the opened session in register metadata so switching back does not show a false alert', async () => {
    const wrapper = mountConfiguration()
    wrapper.findComponent(Alert).props('onClickAction')?.()
    await wrapper.vm.$nextTick()

    const dialog = wrapper.findComponent({ name: 'OpenDialog' })
    expect(dialog.exists()).toBe(true)
    dialog.vm.$emit('saved', 42)
    await wrapper.vm.$nextTick()

    expect(wrapper.props('form').sessionId).toBe(42)
    expect(wrapper.props('meta').registers[0].session).toEqual({ id: 42 })
    expect(wrapper.emitted('on-opened-session')).toHaveLength(1)
    expect(wrapper.findComponent({ name: 'OpenDialog' }).exists()).toBe(false)
    expect(wrapper.findComponent(Alert).exists()).toBe(false)
  })

  it('does not offer or open a session dialog without permission', async () => {
    can.mockReturnValue(false)
    const wrapper = mountConfiguration()
    expect(wrapper.findComponent(Alert).props('hasActionButton')).toBe(false)
    wrapper.findComponent(Alert).props('onClickAction')?.()
    await wrapper.vm.$nextTick()
    expect(wrapper.findComponent({ name: 'OpenDialog' }).exists()).toBe(false)
  })

  it('resolves missing configuration before offering to open a session', async () => {
    const wrapper = mountConfiguration()
    await wrapper.setProps({ meta: { ...wrapper.props('meta'), menus: [] } })
    const alert = wrapper.findComponent(Alert)
    expect(alert.props('title')).toContain('no_menus_found')
    await alert.props('onClickAction')?.()
    expect(push).toHaveBeenCalledWith({ name: 'admin.menus.create' })
    expect(wrapper.findComponent({ name: 'OpenDialog' }).exists()).toBe(false)
  })
})
