import { mount } from '@vue/test-utils'
import { ref } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import type * as ChartJsModule from 'chart.js'
import AnalyticsChart from './AnalyticsChart.vue'
import DashboardChart from '@/modules/dashboard/pages/admin/dashboard/Partials/DashboardChart.vue'

const mocks = vi.hoisted(() => ({ configs: [] as any[], destroy: vi.fn() }))
const theme = ref({ dark: false, colors: { primary: '#F57C00' } })
const locale = ref('en')
vi.mock('vuetify', () => ({ useTheme: () => ({ current: theme }) }))
vi.mock('vue-i18n', () => ({ useI18n: () => ({ locale }) }))
vi.mock('chart.js', async (importOriginal) => ({
  ...(await importOriginal<typeof ChartJsModule>()),
  Chart: class {
    static register = vi.fn()
    destroy = mocks.destroy
    constructor(_canvas: unknown, config: unknown) {
      mocks.configs.push(config)
    }
  },
}))
const config = () => mocks.configs[mocks.configs.length - 1]!
const props = {
  type: 'line' as const,
  labels: ['Jan', 'Feb'],
  datasets: [{ label: 'Revenue', data: [10, 20], color: 'primary', fill: true }],
}
beforeEach(() => {
  mocks.configs.length = 0
  mocks.destroy.mockClear()
  theme.value = { dark: false, colors: { primary: '#F57C00' } }
  locale.value = 'en'
})

describe('shared analytics charts', () => {
  it('renders through the main dashboard adapter and updates theme and data', async () => {
    const wrapper = mount(DashboardChart, { props: { ...props, showLegend: true } })
    expect(wrapper.find('canvas').exists()).toBe(true)
    expect(wrapper.text()).toContain('Revenue')
    expect(config().data.datasets[0].backgroundColor).toBe('rgba(245, 124, 0, 0.13)')
    theme.value = { dark: true, colors: { primary: '#60A5FA' } }
    await wrapper.setProps({ labels: ['March'], datasets: [{ ...props.datasets[0]!, data: [30] }] })
    expect(config().data.labels).toEqual(['March'])
    expect(config().data.datasets[0].borderColor).toBe('rgb(96, 165, 250)')
    expect(config().options.plugins.tooltip.backgroundColor).toBe('#171725')
    wrapper.unmount()
    expect(mocks.destroy).toHaveBeenCalled()
  })

  it('shows the count scale for count-only charts and preserves a separate scale for mixed units', async () => {
    const count = {
      label: 'Employees',
      data: [100, 200],
      color: '#043A63',
      yAxisID: 'count' as const,
    }
    const wrapper = mount(AnalyticsChart, {
      props: { ...props, datasets: [count], currency: 'JOD' },
    })
    expect(config().options.scales.count.display).toBe(true)
    expect(config().options.scales.money.display).toBe(false)
    expect(config().options.plugins.tooltip.callbacks.label({ raw: 100, datasetIndex: 0 })).toBe(
      'Employees: 100',
    )
    await wrapper.setProps({ datasets: [...props.datasets, count] })
    expect(config().options.scales.count.display).toBe(false)
    expect(config().options.scales.money.display).toBe(true)
    wrapper.unmount()
  })

  it('puts horizontal category labels on y and preserves full labels and monetary precision', () => {
    const label = 'A very long department name for testing'
    const wrapper = mount(AnalyticsChart, {
      props: {
        ...props,
        type: 'bar',
        horizontal: true,
        labels: [label],
        currency: 'JOD',
        precision: 3,
        rtl: true,
      },
    })
    expect(config().options.indexAxis).toBe('y')
    expect(config().options.scales.y.ticks.callback(0, 0)).toBe(`${label.slice(0, 23)}…`)
    expect(config().data.labels).toEqual([label])
    expect(config().options.scales.x.beginAtZero).toBe(true)
    expect(config().options.plugins.tooltip.rtl).toBe(true)
    expect(
      config().options.plugins.tooltip.callbacks.label({ raw: 1.234, datasetIndex: 0 }),
    ).toContain('1.234')
    wrapper.unmount()
  })

  it('supports rgba color arrays, doughnuts, long series and prop changes', async () => {
    const wrapper = mount(AnalyticsChart, {
      props: {
        ...props,
        type: 'doughnut',
        showLegend: true,
        datasets: [{ label: '', data: [1, 2], color: ['rgba(45, 156, 219, 0.6)', '#F57C00'] }],
      },
    })
    expect(config().options.cutout).toBe('72%')
    expect(config().options.scales).toBeUndefined()
    expect(config().data.datasets[0].backgroundColor).toEqual([
      'rgb(45, 156, 219)',
      'rgb(245, 124, 0)',
    ])
    expect(wrapper.text()).toContain('Jan')
    await wrapper.setProps({
      type: 'line',
      labels: Array(15).fill('Day'),
      datasets: [{ ...props.datasets[0]!, dashed: true }],
    })
    expect(config().data.datasets[0].pointRadius).toBe(0)
    expect(config().data.datasets[0].borderDash).toEqual([6, 5])
    wrapper.unmount()
  })
})
