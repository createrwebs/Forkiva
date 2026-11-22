import { flushPromises, shallowMount } from '@vue/test-utils'
import * as Vue from 'vue'
import { debouncedWatch, pausableWatch, useIntervalFn } from '@vueuse/core'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import SmartDataTable from './SmartDataTable.vue'
import TableHeader from './TableHeader.vue'
import TablePagination from './TablePagination.vue'
import TableHeaderActions from './TableHeaderActions.vue'

const mocks = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  error: vi.fn(),
  success: vi.fn(),
  download: vi.fn(),
  hasPermission: vi.fn(),
}))
vi.mock('@/modules/core/api/http.ts', () => ({ http: { get: mocks.get, post: mocks.post } }))
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))
vi.mock('vue-toastification', () => ({
  useToast: () => ({ error: mocks.error, success: mocks.success }),
}))
vi.mock('vuetify/components', () => ({
  VDataTableServer: { name: 'VDataTableServer', template: '<div />' },
}))
vi.mock('@/modules/core/composables/tableAction.ts', () => ({
  useTableAction: () => ({ hasPermission: mocks.hasPermission }),
}))
vi.mock('@/modules/report/composables/report.ts', () => ({
  useReport: () => ({ downloadExportFile: mocks.download }),
}))

const Container = { template: '<div><slot /></div>' }
const TextField = {
  props: ['modelValue'],
  emits: ['update:modelValue'],
  template:
    '<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
}

function mountTable() {
  return shallowMount(SmartDataTable, {
    props: { apiUri: '/test-table', name: 'products', resource: 'products', module: 'product' },
    global: {
      stubs: {
        VCard: Container,
        VRow: Container,
        VCol: Container,
        VCardTitle: true,
        VBtn: { template: '<button><slot /></button>' },
        VSelect: true,
        VTextField: TextField,
        TableHeader: false,
        TablePagination: false,
      },
    },
  })
}

describe('table request scheduling', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.resetAllMocks()
    mocks.hasPermission.mockReturnValue(true)
    // These APIs are injected by the application's Vite auto-import plugin.
    for (const name of [
      'ref',
      'computed',
      'toRefs',
      'watch',
      'onMounted',
      'onBeforeMount',
      'onBeforeUnmount',
    ] as const) {
      vi.stubGlobal(name, Vue[name])
    }
    vi.stubGlobal('useRoute', () => ({ name: 'test.products', params: {} }))
    vi.stubGlobal('debouncedWatch', debouncedWatch)
    vi.stubGlobal('pausableWatch', pausableWatch)
    vi.stubGlobal('useIntervalFn', useIntervalFn)
    mocks.get.mockImplementation(async (_uri, { params }) => ({
      data: {
        body: {
          data: [],
          pagination: {
            current_page: params.page,
            last_page: 5,
            per_page: params.per_page,
            total: 50,
            from: 0,
            to: 0,
          },
        },
      },
    }))
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.unstubAllGlobals()
  })

  it('debounces the whole search flow once and clears the search correctly', async () => {
    const wrapper = mountTable()
    await flushPromises()
    mocks.get.mockClear()

    await wrapper.get('input').setValue('coffee')
    await vi.advanceTimersByTimeAsync(299)
    expect(mocks.get).not.toHaveBeenCalled()
    await vi.advanceTimersByTimeAsync(1)
    await flushPromises()
    expect(mocks.get).toHaveBeenCalledOnce()
    expect(mocks.get.mock.calls[0]![1].params.filters.search).toBe('coffee')

    wrapper.findComponent(TextField).vm.$emit('update:modelValue', null)
    await Vue.nextTick()
    await vi.advanceTimersByTimeAsync(300)
    expect(mocks.get).toHaveBeenCalledTimes(2)
    expect(mocks.get.mock.lastCall![1].params.filters.search).toBe('')
    wrapper.unmount()
  })

  it('downloads payroll reports through their format endpoint with the active filters and sorts', async () => {
    mocks.get.mockResolvedValue({
      data: {
        body: {
          key: 'run_summary',
          data: [],
          export_mode: 'download',
          export_api_uri: '/v1/payroll-reports/run_summary/export',
          export_methods: [{ id: 'pdf', name: 'PDF' }],
          export_permission: 'admin.payroll_reports.export',
        },
      },
    })
    const wrapper = mountTable()
    await flushPromises()
    wrapper.findComponent(TableHeader).vm.$emit('apply:filters', { branch_id: 7 })
    wrapper
      .findComponent({ name: 'VDataTableServer' })
      .vm.$emit('update:sortBy', [{ key: 'net_amount', order: 'desc' }])
    await flushPromises()
    const action = wrapper
      .findComponent(TableHeaderActions)
      .props('actions')
      .find((item: { key: string }) => item.key === 'export')!
    expect(action.permission).toBe('admin.payroll_reports.export')
    await action.onClick!({ id: 'pdf' })
    expect(mocks.download).toHaveBeenCalledExactlyOnceWith(
      'run_summary',
      'pdf',
      expect.objectContaining({ branch_id: 7 }),
      {
        apiUri: '/v1/payroll-reports/run_summary/export',
        sorts: [{ key: 'net_amount', order: 'desc' }],
      },
    )
    expect(mocks.error).not.toHaveBeenCalled()
    expect(mocks.post).not.toHaveBeenCalled()
    expect(mocks.success).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('keeps queued exports as the default for existing tables', async () => {
    mocks.get.mockResolvedValue({
      data: {
        body: {
          data: [],
          export_api_uri: '/v1/exports/products',
          export_methods: [{ id: 'xlsx', name: 'Excel' }],
        },
      },
    })
    mocks.post.mockResolvedValue({ data: { body: { id: 1, status: { id: 'queued' } } } })
    const wrapper = mountTable()
    await flushPromises()
    const action = wrapper
      .findComponent(TableHeaderActions)
      .props('actions')
      .find((item: { key: string }) => item.key === 'export')!
    await action.onClick!({ id: 'xlsx' })
    expect(mocks.post).toHaveBeenCalledExactlyOnceWith(
      '/v1/exports/products',
      expect.objectContaining({ format: 'xlsx' }),
    )
    expect(mocks.download).not.toHaveBeenCalled()
    expect(mocks.success).toHaveBeenCalledWith('export::exports.messages.queued')
    wrapper.unmount()
  })

  it('does not request export history without export permission', async () => {
    mocks.hasPermission.mockReturnValue(false)
    mocks.get.mockResolvedValue({
      data: {
        body: {
          data: [],
          export_api_uri: '/v1/exports/orders',
          export_methods: [{ id: 'xlsx', name: 'Excel' }],
          export_permission: 'admin.orders.export',
          export_resource: 'orders',
        },
      },
    })

    const wrapper = mountTable()
    await flushPromises()

    expect(mocks.get).toHaveBeenCalledOnce()
    expect(mocks.get).toHaveBeenCalledWith('/test-table', expect.any(Object))
    expect(
      wrapper
        .findComponent(TableHeaderActions)
        .props('actions')
        .some((item: { key: string }) => item.key === 'export'),
    ).toBe(false)
    wrapper.unmount()
  })

  it('queues legacy reports with their report context instead of downloading synchronously', async () => {
    mocks.get.mockResolvedValue({
      data: {
        body: {
          key: 'sales',
          data: [],
          export_mode: 'queued',
          export_api_uri: '/v1/exports/reports',
          export_methods: [{ id: 'json', name: 'JSON' }],
          export_permission: 'admin.reports.sales',
          export_resource: 'reports',
          export_context: { report: 'sales' },
        },
      },
    })
    mocks.post.mockResolvedValue({ data: { body: { id: 2, status: { id: 'pending' } } } })
    const wrapper = mountTable()
    await flushPromises()
    const action = wrapper
      .findComponent(TableHeaderActions)
      .props('actions')
      .find((item: { key: string }) => item.key === 'export')!
    await action.onClick!({ id: 'json' })
    expect(action.permission).toBe('admin.reports.sales')
    expect(mocks.post).toHaveBeenCalledExactlyOnceWith(
      '/v1/exports/reports',
      expect.objectContaining({
        format: 'json',
        context: expect.objectContaining({ report: 'sales' }),
      }),
    )
    expect(mocks.download).not.toHaveBeenCalled()
    expect(mocks.success).toHaveBeenCalledWith('export::exports.messages.queued')
    wrapper.unmount()
  })

  it('makes one request when filtering from a later page and keeps refresh on page one', async () => {
    const wrapper = mountTable()
    await flushPromises()
    wrapper.findComponent(TablePagination).vm.$emit('update:page', 3)
    await flushPromises()
    mocks.get.mockClear()

    wrapper.findComponent(TableHeader).vm.$emit('apply:filters', { status: 'active' })
    await flushPromises()
    expect(mocks.get).toHaveBeenCalledOnce()
    expect(mocks.get.mock.calls[0]![1].params).toMatchObject({
      page: 1,
      filters: { status: 'active' },
    })
    expect(wrapper.findComponent(TablePagination).props('pagination').current_page).toBe(1)

    wrapper.vm.reload()
    await flushPromises()
    expect(mocks.get).toHaveBeenCalledTimes(2)
    expect(mocks.get.mock.lastCall![1].params.page).toBe(1)
    wrapper.unmount()
  })

  it('fetches again when refreshed during an identical request', async () => {
    let resolveFirst!: (value: unknown) => void
    mocks.get.mockImplementationOnce(
      () =>
        new Promise((resolve) => {
          resolveFirst = resolve
        }),
    )
    const wrapper = mountTable()
    await Vue.nextTick()
    const firstSignal = mocks.get.mock.calls[0]![1].signal as AbortSignal

    wrapper.vm.refresh()
    await flushPromises()

    expect(mocks.get).toHaveBeenCalledTimes(2)
    expect(firstSignal.aborted).toBe(true)
    expect(mocks.get.mock.calls[1]![1].params).toEqual(mocks.get.mock.calls[0]![1].params)
    resolveFirst({ data: { body: { data: [] } } })
    await flushPromises()
    wrapper.unmount()
  })
})
