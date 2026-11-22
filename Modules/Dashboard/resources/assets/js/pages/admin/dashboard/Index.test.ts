import { ref } from 'vue'
import type { GridItemHTMLElement, GridStackOptions } from 'gridstack'
import { flushPromises, shallowMount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import WidgetCustomizer from '@/modules/core/components/Dashboard/WidgetCustomizer.vue'
import DashboardIndex from './Index.vue'
import DashboardNoAccessState from './Partials/DashboardNoAccessState.vue'
import DashboardWidgetRenderer from './Partials/DashboardWidgetRenderer.vue'
import { dashboardWidgets } from '@/modules/dashboard/config/dashboard.widgets.ts'
import { normalizeDashboardLayout } from '@/modules/dashboard/composables/dashboardLayout.ts'

const mocks = vi.hoisted(() => ({
  can: vi.fn<(permission: string) => boolean>(),
  dashboard: vi.fn(),
  dashboardFilters: vi.fn(),
  toastError: vi.fn(),
  gridItems: [] as GridItemHTMLElement[],
  gridColumn: 12,
  gridOptions: null as GridStackOptions | null,
  gridEvents: new Map<string, (event: Event, element: GridItemHTMLElement) => void>(),
  float: vi.fn(),
  enableMove: vi.fn(),
  savePreference: vi.fn(),
  preferenceValues: { dashboardLayout: null, dashboardFilters: null } as {
    dashboardLayout: unknown[] | null
    dashboardFilters: Record<string, unknown> | null
  },
  preferenceServerLoaded: false,
}))

vi.mock('@/modules/auth/stores/authStore.ts', () => ({
  useAuthStore: () => ({
    getUser: { id: 17 },
    can: mocks.can,
  }),
}))

vi.mock('@/modules/dashboard/composables/dashboard.ts', () => ({
  useDashboard: () => ({
    dashboard: mocks.dashboard,
    dashboardFilters: mocks.dashboardFilters,
  }),
}))

vi.mock('@/modules/userPreference/stores/userPreferenceStore.ts', () => ({
  useUserPreferenceStore: () => ({
    get values() {
      return mocks.preferenceValues
    },
    get serverLoaded() {
      return mocks.preferenceServerLoaded
    },
    save: mocks.savePreference,
  }),
}))

vi.mock('vue-toastification', () => ({
  useToast: () => ({ error: mocks.toastError }),
}))

vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    locale: ref('en'),
    t: (key: string) =>
      (
        ({
          'dashboard::dashboards.actions.customize': 'Customize Dashboard',
          'dashboard::dashboards.empty.access_description':
            'Ask an administrator to assign dashboard insights to your role.',
          'dashboard::dashboards.empty.access_title': 'Dashboard access is unavailable',
          'dashboard::dashboards.errors.load': 'The dashboard could not be loaded.',
          'dashboard::dashboards.filters.refresh': 'Refresh',
          'dashboard::dashboards.page.subtitle': 'Your restaurant at a glance',
          'dashboard::dashboards.page.title': 'Dashboard',
        }) as Record<string, string>
      )[key] ?? key,
  }),
}))

vi.mock('gridstack', () => ({
  GridStackEngine: class {},
  GridStack: {
    init: (options: GridStackOptions) => {
      mocks.gridOptions = options
      return {
        destroy: vi.fn(),
        enableMove: mocks.enableMove,
        float: mocks.float,
        getColumn: () => mocks.gridColumn,
        getGridItems: () => mocks.gridItems,
        offAll: vi.fn(),
        on(name: string, callback: (event: Event, element: GridItemHTMLElement) => void) {
          mocks.gridEvents.set(name, callback)
          return this
        },
        update: vi.fn(),
      }
    },
  },
}))

const cardStub = {
  name: 'VCard',
  template: '<section><slot /></section>',
}

const buttonStub = {
  name: 'VBtn',
  template: '<button type="button"><slot /></button>',
}

function mountDashboard() {
  return shallowMount(DashboardIndex, {
    global: {
      stubs: {
        VBtn: buttonStub,
        VCard: cardStub,
        VCardText: cardStub,
        VDivider: true,
        VIcon: true,
        VMenu: true,
        VSelect: true,
        VSpacer: true,
      },
    },
  })
}

describe('Admin dashboard permissions', () => {
  beforeEach(() => {
    window.localStorage.clear()
    mocks.can.mockReset()
    mocks.can.mockReturnValue(false)
    mocks.dashboard.mockReset()
    mocks.dashboardFilters.mockReset()
    mocks.toastError.mockReset()
    mocks.gridItems = []
    mocks.gridColumn = 12
    mocks.gridOptions = null
    mocks.gridEvents.clear()
    mocks.float.mockReset()
    mocks.enableMove.mockReset()
    mocks.savePreference.mockReset()
    mocks.savePreference.mockResolvedValue(true)
    mocks.preferenceValues = { dashboardLayout: null, dashboardFilters: null }
    mocks.preferenceServerLoaded = false
  })

  it('does not call dashboard APIs or expose actions when no widget is authorized', async () => {
    const wrapper = mountDashboard()
    await flushPromises()

    expect(mocks.dashboardFilters).not.toHaveBeenCalled()
    expect(mocks.dashboard).not.toHaveBeenCalled()
    expect(wrapper.findComponent(DashboardNoAccessState).exists()).toBe(true)
    expect(wrapper.text()).not.toContain('Times use the application timezone')
    expect(wrapper.find('.admin-dashboard__filters').exists()).toBe(false)
    expect(wrapper.find('.admin-dashboard__header-actions').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('Refresh')
    expect(wrapper.text()).not.toContain('Customize Dashboard')
  })

  it('shows filter request failures as a toast without an inline error panel', async () => {
    mocks.can.mockImplementation((permission) => permission === 'admin.dashboards.total_sales')
    mocks.dashboardFilters.mockRejectedValueOnce(new Error('Request failed'))

    const wrapper = mountDashboard()
    await flushPromises()

    expect(mocks.dashboardFilters).toHaveBeenCalledOnce()
    expect(mocks.dashboard).not.toHaveBeenCalled()
    expect(mocks.toastError).toHaveBeenCalledWith('The dashboard could not be loaded.')
    expect(wrapper.find('.admin-dashboard__header-actions').exists()).toBe(true)
    expect(wrapper.find('.admin-dashboard__filters').exists()).toBe(true)
    expect(wrapper.text()).not.toContain('The dashboard could not be loaded.')
  })

  it('restores saved date filters when the dashboard is remounted', async () => {
    const savedFilters = { branch_id: null, from: '2026-08-01', to: '2026-08-31' }
    window.localStorage.setItem('forkiva.dashboard.filters.v1.17', JSON.stringify(savedFilters))
    mocks.can.mockImplementation((permission) => permission === 'admin.dashboards.total_sales')
    mocks.dashboardFilters.mockResolvedValue({
      data: {
        body: {
          filters: [],
          presets: [],
          defaults: savedFilters,
          meta: { branch_locked: false, max_range_days: 367, timezone: 'UTC' },
        },
      },
    })
    mocks.dashboard.mockResolvedValue({ data: { body: { meta: {}, widgets: {} } } })

    const wrapper = mountDashboard()
    await flushPromises()

    expect(mocks.dashboardFilters).toHaveBeenCalledWith(savedFilters, expect.any(AbortSignal))
    expect(mocks.dashboard).toHaveBeenCalledWith(
      expect.objectContaining({ from: savedFilters.from, to: savedFilters.to }),
      expect.any(AbortSignal),
    )
    wrapper.unmount()
  })

  it('uses account preferences ahead of this browser’s old layout and filters', async () => {
    const localLayout = normalizeDashboardLayout(null, dashboardWidgets)
    const remoteLayout = localLayout.map((item) =>
      item.key === 'total_sales' ? { ...item, visible: false } : item,
    )
    const localFilters = { branch_id: null, from: '2026-08-01', to: '2026-08-31' }
    const remoteFilters = { branch_id: null, from: '2026-09-01', to: '2026-09-27' }
    window.localStorage.setItem('forkiva.dashboard.layout.v5.17', JSON.stringify(localLayout))
    window.localStorage.setItem('forkiva.dashboard.filters.v1.17', JSON.stringify(localFilters))
    mocks.preferenceValues = { dashboardLayout: remoteLayout, dashboardFilters: remoteFilters }
    mocks.preferenceServerLoaded = true
    mocks.can.mockImplementation((permission) =>
      ['admin.dashboards.total_sales', 'admin.dashboards.total_orders'].includes(permission),
    )
    mocks.dashboardFilters.mockRejectedValueOnce(new Error('Request failed'))

    const wrapper = mountDashboard()
    await flushPromises()

    expect(wrapper.find('[gs-id="total_sales"]').exists()).toBe(false)
    expect(wrapper.find('[gs-id="total_orders"]').exists()).toBe(true)
    expect(mocks.dashboardFilters).toHaveBeenCalledWith(remoteFilters, expect.any(AbortSignal))
    wrapper.unmount()
  })

  it('migrates this browser’s saved dashboard settings to account preferences', async () => {
    const savedFilters = { branch_id: null, from: '2026-08-01', to: '2026-08-31' }
    window.localStorage.setItem('forkiva.dashboard.filters.v1.17', JSON.stringify(savedFilters))
    mocks.preferenceServerLoaded = true
    mocks.can.mockImplementation((permission) => permission === 'admin.dashboards.total_sales')
    mocks.dashboardFilters.mockResolvedValueOnce({
      data: { body: { filters: [], presets: [], defaults: savedFilters, meta: {} } },
    })
    mocks.dashboard.mockResolvedValueOnce({ data: { body: { meta: {}, widgets: {} } } })

    const wrapper = mountDashboard()
    await flushPromises()

    expect(mocks.savePreference).toHaveBeenCalledWith(
      'dashboardLayout',
      expect.arrayContaining([expect.objectContaining({ key: 'total_sales' })]),
    )
    expect(mocks.savePreference).toHaveBeenCalledWith('dashboardFilters', savedFilters)
    wrapper.unmount()
  })

  it('marks KPI cards as non-resizable', async () => {
    mocks.can.mockImplementation((permission) => permission === 'admin.dashboards.total_sales')
    mocks.dashboardFilters.mockRejectedValueOnce(new Error('Request failed'))

    const wrapper = mountDashboard()
    await flushPromises()

    const metric = wrapper.find('[data-kind="metric"]')

    expect(metric.exists()).toBe(true)
    expect(metric.attributes('gs-no-resize')).toBe('true')
  })

  it('starts dragging only from the visible handle', async () => {
    mocks.can.mockImplementation((permission) => permission === 'admin.dashboards.total_sales')
    mocks.dashboardFilters.mockRejectedValueOnce(new Error('Request failed'))
    const wrapper = mountDashboard()
    await flushPromises()

    expect(mocks.gridOptions?.handle).toBe('.dashboard-widget__drag')
    expect(mocks.gridOptions?.draggable?.handle).toBe('.dashboard-widget__drag')
    expect(mocks.gridOptions?.float).toBe(true)
    expect(mocks.gridOptions?.engineClass).toBeDefined()
    expect(mocks.gridOptions?.disableDrag).toBe(true)
    expect(wrapper.findComponent(DashboardWidgetRenderer).props('editable')).toBe(false)
    await wrapper.get('.admin-dashboard__edit-toggle').trigger('click')
    await flushPromises()
    expect(mocks.enableMove).toHaveBeenCalledWith(true)
    expect(wrapper.findComponent(DashboardWidgetRenderer).props('editable')).toBe(true)
    await wrapper.get('.admin-dashboard__edit-toggle').trigger('click')
    expect(mocks.enableMove).toHaveBeenCalledWith(false)
    wrapper.unmount()
  })

  it('does not report a saved layout when a drag returns to its starting position', async () => {
    mocks.can.mockImplementation((permission) => permission === 'admin.dashboards.total_sales')
    mocks.dashboardFilters.mockRejectedValueOnce(new Error('Request failed'))
    const wrapper = mountDashboard()
    await flushPromises()
    await wrapper.get('.admin-dashboard__edit-toggle').trigger('click')
    const element = wrapper.get('[gs-id="total_sales"]').element as GridItemHTMLElement
    element.gridstackNode = { x: 0, y: 0, w: 3, h: 2 }
    mocks.gridItems = [element]
    const saved = window.localStorage.getItem('forkiva.dashboard.layout.v5.17')

    mocks.gridEvents.get('dragstart')!(new Event('dragstart'), element)
    mocks.gridEvents.get('dragstop')!(new Event('dragstop'), element)
    await flushPromises()

    expect(window.localStorage.getItem('forkiva.dashboard.layout.v5.17')).toBe(saved)
    expect(wrapper.find('.admin-dashboard__reorder-status').exists()).toBe(false)
    wrapper.unmount()
  })

  it('saves the settled drop position after GridStack finishes its dragstop cycle', async () => {
    mocks.can.mockImplementation((permission) => permission === 'admin.dashboards.total_sales')
    mocks.dashboardFilters.mockRejectedValueOnce(new Error('Request failed'))
    const wrapper = mountDashboard()
    await flushPromises()
    await wrapper.get('.admin-dashboard__edit-toggle').trigger('click')
    const element = wrapper.get('[gs-id="total_sales"]').element as GridItemHTMLElement
    element.gridstackNode = { x: 0, y: 0, w: 3, h: 2 }
    mocks.gridItems = [element]
    const grid = wrapper.get('.grid-stack').element
    // GridStack owns these classes; Vue must preserve them across drag state updates.
    grid.classList.add('grid-stack-rtl', 'grid-stack-animate', 'gs-12')

    const floatCallsBeforeDrag = mocks.float.mock.calls.length
    mocks.gridEvents.get('dragstart')!(new Event('dragstart'), element)
    await flushPromises()
    expect(mocks.float).toHaveBeenCalledTimes(floatCallsBeforeDrag)
    expect(grid.getAttribute('data-sorting')).toBe('true')
    expect(grid.classList.contains('grid-stack-rtl')).toBe(true)
    mocks.gridEvents.get('dragstop')!(new Event('dragstop'), element)
    element.gridstackNode.x = 6
    element.gridstackNode.y = 2
    await flushPromises()
    expect(mocks.float).toHaveBeenCalledTimes(floatCallsBeforeDrag + 2)
    expect(mocks.float.mock.calls.slice(-2)).toEqual([[false], [true]])

    const saved = JSON.parse(window.localStorage.getItem('forkiva.dashboard.layout.v5.17')!)
    expect(saved.find((item: { key: string }) => item.key === 'total_sales')).toMatchObject({
      x: 6,
      y: 2,
    })
    expect(mocks.savePreference).toHaveBeenCalledWith(
      'dashboardLayout',
      expect.arrayContaining([expect.objectContaining({ key: 'total_sales', x: 6, y: 2 })]),
    )
    expect(grid.hasAttribute('data-sorting')).toBe(false)
    expect([...grid.classList]).toEqual(
      expect.arrayContaining(['grid-stack-rtl', 'grid-stack-animate', 'gs-12']),
    )
    wrapper.unmount()
  })

  it('closes the gap after hiding a card and retains the order after remounting', async () => {
    mocks.can.mockImplementation((permission) =>
      ['admin.dashboards.total_sales', 'admin.dashboards.total_orders'].includes(permission),
    )
    mocks.dashboardFilters.mockRejectedValue(new Error('Request failed'))
    const wrapper = mountDashboard()
    await flushPromises()

    wrapper.get('[gs-id="total_sales"]').findComponent(DashboardWidgetRenderer).vm.$emit('hide')
    await flushPromises()

    expect(wrapper.find('[gs-id="total_sales"]').exists()).toBe(false)
    expect(mocks.savePreference).toHaveBeenCalledWith(
      'dashboardLayout',
      expect.arrayContaining([expect.objectContaining({ key: 'total_sales', visible: false })]),
    )
    expect(wrapper.get('[gs-id="total_orders"]').attributes('gs-x')).toBe('0')
    expect(wrapper.get('[gs-id="total_orders"]').attributes('gs-y')).toBe('0')
    wrapper.unmount()

    const restored = mountDashboard()
    await flushPromises()
    expect(restored.find('[gs-id="total_sales"]').exists()).toBe(false)
    expect(restored.get('[gs-id="total_orders"]').attributes('gs-x')).toBe('0')
    expect(restored.get('[gs-id="total_orders"]').attributes('gs-y')).toBe('0')
    restored.unmount()
  })

  it.each([1, 6])(
    'saves a drag in a %i-column layout using desktop card sizes',
    async (columns) => {
      mocks.gridColumn = columns
      mocks.can.mockImplementation((permission) =>
        ['admin.dashboards.total_sales', 'admin.dashboards.total_orders'].includes(permission),
      )
      mocks.dashboardFilters.mockRejectedValue(new Error('Request failed'))
      const wrapper = mountDashboard()
      await flushPromises()
      await wrapper.get('.admin-dashboard__edit-toggle').trigger('click')
      const sales = wrapper.get('[gs-id="total_sales"]').element as GridItemHTMLElement
      const orders = wrapper.get('[gs-id="total_orders"]').element as GridItemHTMLElement
      sales.gridstackNode = { x: 0, y: 0, w: 1, h: 2 }
      orders.gridstackNode = { x: 0, y: 2, w: 1, h: 2 }
      sales.setAttribute('gs-y', '2')
      sales.setAttribute('gs-w', '1')
      mocks.gridItems = [sales, orders]

      mocks.gridEvents.get('dragstart')!(new Event('dragstart'), sales)
      sales.gridstackNode.y = 2
      orders.gridstackNode.y = 0
      mocks.gridEvents.get('dragstop')!(new Event('dragstop'), sales)
      await flushPromises()

      const saved = JSON.parse(window.localStorage.getItem('forkiva.dashboard.layout.v5.17')!)
      expect(
        saved.filter((item: { key: string }) => ['total_orders', 'total_sales'].includes(item.key)),
      ).toMatchObject([
        { key: 'total_orders', order: 0, x: 0, y: 0, w: 3, h: 2 },
        { key: 'total_sales', order: 1, x: 3, y: 0, w: 3, h: 2 },
      ])
      expect(sales.getAttribute('gs-y')).toBe('0')
      expect(sales.getAttribute('gs-w')).toBe('3')
      wrapper.unmount()
    },
  )

  it('places newly shown hidden cards across a row and preserves their layout after reload', async () => {
    mocks.can.mockReturnValue(true)
    mocks.dashboardFilters.mockRejectedValue(new Error('Request failed'))
    mocks.dashboard.mockResolvedValue({ data: { body: { meta: {}, widgets: {} } } })
    const wrapper = mountDashboard()
    await flushPromises()
    const keys = ['total_users', 'total_menus', 'total_products', 'total_categories']

    for (const key of keys) {
      wrapper.findComponent(WidgetCustomizer).vm.$emit('set-visible', key, true)
      await flushPromises()
    }

    for (const [index, key] of keys.entries()) {
      const card = wrapper.get(`[gs-id="${key}"]`)
      expect(card.attributes('gs-x')).toBe(String(index * 3))
      expect(card.attributes('gs-y')).toBe('2')
    }
    expect(
      Number(wrapper.get('[gs-id="sales_analytics"]').attributes('gs-y')),
    ).toBeGreaterThanOrEqual(4)
    wrapper.unmount()

    const restored = mountDashboard()
    await flushPromises()
    for (const [index, key] of keys.entries()) {
      const card = restored.get(`[gs-id="${key}"]`)
      expect(card.attributes('gs-x')).toBe(String(index * 3))
      expect(card.attributes('gs-y')).toBe('2')
    }
    restored.unmount()
  })
})
