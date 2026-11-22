import { shallowMount } from '@vue/test-utils'
import * as Vue from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import WorkspaceRail from './WorkspaceRail.vue'

const mocks = vi.hoisted(() => ({
  buildSidebar: vi.fn(),
  hasPermission: vi.fn(),
}))

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))
vi.mock('@/modules/auth/composables/auth.ts', () => ({
  useAuth: () => ({ hasPermission: mocks.hasPermission }),
}))
vi.mock('@/modules/core/services/SidebarService.ts', () => ({
  sidebarRegistry: { build: mocks.buildSidebar },
}))
vi.mock('@/modules/core/stores/appStore.ts', () => ({
  useAppStore: () => ({
    appName: 'Forkiva',
    isRtl: false,
    logo: null,
    settings: { features: {} },
  }),
}))

function mountRail() {
  return shallowMount(WorkspaceRail, {
    props: { activeWorkspace: 'restaurant', isMenuCollapsed: false },
    global: {
      stubs: {
        RouterLink: { props: ['to'], template: '<a><slot /></a>' },
        VBtn: true,
        VIcon: true,
      },
    },
  })
}

describe('workspace rail access', () => {
  beforeEach(() => {
    vi.stubGlobal('computed', Vue.computed)
    mocks.hasPermission.mockReturnValue(false)
  })

  afterEach(() => {
    vi.unstubAllGlobals()
    vi.resetAllMocks()
  })

  it('shows a workspace whose destination does not require a permission', () => {
    mocks.buildSidebar.mockImplementation((_target, _attachChildren, workspace) =>
      workspace
        ? []
        : [
            {
              key: 'admin.dashboard',
              label: 'admin::sidebar.overview',
              to: { name: 'admin.dashboard' },
            },
          ],
    )

    const wrapper = mountRail()

    expect(wrapper.findAll('.workspace-link')).toHaveLength(1)
    expect(wrapper.find('.workspace-link').text()).toContain('admin::sidebar.restaurant_hub')
    expect(mocks.hasPermission).not.toHaveBeenCalled()
  })

  it('hides a workspace when its only destination requires a denied permission', () => {
    mocks.buildSidebar.mockImplementation((_target, _attachChildren, workspace) =>
      workspace
        ? []
        : [
            {
              key: 'admin.orders',
              label: 'order::orders.orders',
              permission: 'admin.orders.index',
              to: { name: 'admin.orders.index' },
            },
          ],
    )

    const wrapper = mountRail()

    expect(wrapper.findAll('.workspace-link')).toHaveLength(0)
    expect(mocks.hasPermission).toHaveBeenCalledWith('admin.orders.index')
  })
})
