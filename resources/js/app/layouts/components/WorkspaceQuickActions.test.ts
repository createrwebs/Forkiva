import { shallowMount } from '@vue/test-utils'
import * as Vue from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import WorkspaceQuickActions from './WorkspaceQuickActions.vue'

const mocks = vi.hoisted(() => ({
  can: vi.fn(),
}))

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))
vi.mock('@/modules/auth/composables/auth.ts', () => ({
  useAuth: () => ({ can: mocks.can }),
}))

function mountActions() {
  return shallowMount(WorkspaceQuickActions, {
    global: {
      stubs: {
        RouterLink: {
          props: ['to'],
          template: '<a :data-route="to.name"><slot /></a>',
        },
        VIcon: true,
      },
    },
  })
}

describe('workspace quick actions permissions', () => {
  beforeEach(() => {
    vi.stubGlobal('computed', Vue.computed)
  })

  afterEach(() => {
    vi.unstubAllGlobals()
    vi.resetAllMocks()
  })

  it('shows only the launch actions the user can access', () => {
    mocks.can.mockImplementation((permission: string) => permission === 'admin.pos.index')

    const wrapper = mountActions()
    const actions = wrapper.findAll('.workspace-quick-action')

    expect(actions).toHaveLength(1)
    expect(actions[0]?.attributes('data-route')).toBe('admin.pos.index')
    expect(actions[0]?.text()).toContain('POS')
    expect(actions[0]?.attributes('aria-label')).toBe('pos::pos.pos_viewer')
    expect(mocks.can).toHaveBeenCalledWith('admin.pos.index')
    expect(mocks.can).toHaveBeenCalledWith('admin.pos.kitchen_viewer')
  })

  it('does not render the panel when both permissions are denied', () => {
    mocks.can.mockReturnValue(false)

    const wrapper = mountActions()

    expect(wrapper.find('.workspace-quick-actions').exists()).toBe(false)
  })
})
