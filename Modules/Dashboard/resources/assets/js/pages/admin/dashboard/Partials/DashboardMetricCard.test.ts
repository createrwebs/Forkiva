import { shallowMount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import DashboardMetricCard from './DashboardMetricCard.vue'

vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    t: (key: string) =>
      (
        ({
          'dashboard::dashboards.actions.hide': 'Hide widget',
          'dashboard::dashboards.actions.minimize': 'Minimize widget',
          'dashboard::dashboards.comparison.live': 'Live now',
          'dashboard::dashboards.comparison.previous_period': 'vs previous period',
        }) as Record<string, string>
      )[key] ?? key,
  }),
}))

const cardStub = {
  name: 'VCard',
  template: '<section><slot /></section>',
}

const expandTransitionStub = {
  name: 'VExpandTransition',
  template: '<div><slot /></div>',
}

const iconStub = {
  name: 'VIcon',
  props: ['icon'],
  template: '<i :data-icon="icon" />',
}

function mountMetric(inverseTrend = false) {
  return shallowMount(DashboardMetricCard, {
    props: {
      color: '#E11D48',
      data: {
        value: 50593.788,
        comparison: { percent: 544.7, direction: 1 as const },
      },
      description: 'Unpaid order balance.',
      editable: false,
      icon: 'tabler-currency-dollar',
      inverseTrend,
      loading: false,
      minimized: false,
      title: 'Outstanding balance',
    },
    global: {
      stubs: {
        DashboardDragHandle: true,
        VBtn: true,
        VCard: cardStub,
        VExpandTransition: expandTransitionStub,
        VIcon: iconStub,
        VSkeletonLoader: true,
        VSpacer: true,
      },
    },
  })
}

describe('DashboardMetricCard', () => {
  it('uses KPI direction semantics when styling the period comparison', () => {
    const improving = mountMetric()
    const worsening = mountMetric(true)

    expect(improving.find('.dashboard-metric__comparison--positive').exists()).toBe(true)
    expect(worsening.find('.dashboard-metric__comparison--negative').exists()).toBe(true)
    expect(worsening.find('[data-icon="tabler-trending-up"]').exists()).toBe(true)
    expect(worsening.text()).toContain('544.7%')
    expect(worsening.text()).toContain('vs previous period')
  })

  it('gives live KPIs a distinct status treatment', async () => {
    const wrapper = mountMetric()
    await wrapper.setProps({ data: { value: 433, live: true } })

    expect(wrapper.find('.dashboard-metric__comparison--live').exists()).toBe(true)
    expect(wrapper.text()).toContain('Live now')
  })

  it('shows layout controls only while editing is enabled', async () => {
    const wrapper = mountMetric()
    expect(wrapper.findComponent({ name: 'DashboardDragHandle' }).exists()).toBe(false)
    expect(wrapper.findAllComponents({ name: 'VBtn' })).toHaveLength(0)

    await wrapper.setProps({ editable: true })
    expect(wrapper.findComponent({ name: 'DashboardDragHandle' }).exists()).toBe(true)
    expect(wrapper.findAllComponents({ name: 'VBtn' })).toHaveLength(2)
  })

  it('shows an unavailable state without presenting a false zero', async () => {
    const wrapper = mountMetric()
    await wrapper.setProps({
      data: null,
      unavailableMessage: 'Control-account mapping required for this scope.',
    })

    expect(wrapper.find('.dashboard-metric__value--unavailable').text()).toBe('—')
    expect(wrapper.find('[data-icon="tabler-settings-exclamation"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Control-account mapping required for this scope.')
    expect(wrapper.text()).not.toContain('0')
  })
})
