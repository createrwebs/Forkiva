import { shallowMount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import DashboardEmptyState from './DashboardEmptyState.vue'
import DashboardNoAccessState from './DashboardNoAccessState.vue'

vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    t: (key: string) =>
      (
        ({
          'dashboard::dashboards.actions.customize': 'Customize Dashboard',
          'dashboard::dashboards.empty.access_description':
            'Ask an administrator to assign dashboard insights to your role.',
          'dashboard::dashboards.empty.access_title': 'Dashboard access is unavailable',
          'dashboard::dashboards.empty.dashboard_description':
            'Open Customize dashboard to choose widgets.',
          'dashboard::dashboards.empty.dashboard_title': 'Your dashboard is empty',
        }) as Record<string, string>
      )[key] ?? key,
  }),
}))

const cardStub = {
  name: 'VCard',
  template: '<section><slot /></section>',
}

const buttonStub = {
  name: 'VBtn',
  emits: ['click'],
  template: '<button type="button" @click="$emit(\'click\')"><slot /></button>',
}

describe('Dashboard empty states', () => {
  it('renders the permission-aware state without an action', () => {
    const wrapper = shallowMount(DashboardNoAccessState, {
      global: {
        stubs: {
          VCard: cardStub,
          VIcon: true,
        },
      },
    })

    expect(wrapper.text()).toContain('Dashboard access is unavailable')
    expect(wrapper.find('.dashboard-no-access__preview').exists()).toBe(true)
    expect(wrapper.find('button').exists()).toBe(false)
  })

  it('emits customize from the user-selected empty state', async () => {
    const wrapper = shallowMount(DashboardEmptyState, {
      global: {
        stubs: {
          VBtn: buttonStub,
          VCard: cardStub,
          VIcon: true,
        },
      },
    })

    await wrapper.get('button').trigger('click')

    expect(wrapper.emitted('customize')).toHaveLength(1)
  })
})
