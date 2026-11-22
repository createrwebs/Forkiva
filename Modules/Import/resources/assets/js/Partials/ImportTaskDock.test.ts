import type { DataImport } from '@/modules/import/contracts/import.ts'
import { mount } from '@vue/test-utils'
import { createPinia, disposePinia, getActivePinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick, reactive } from 'vue'
import { useImportProcessing } from '@/modules/import/stores/importProcessing.ts'
import ImportTaskDock from './ImportTaskDock.vue'

const mocks = vi.hoisted(() => ({ auth: { getToken: 'session' }, advance: vi.fn(), show: vi.fn() }))
vi.mock('@/modules/import/api/import.api.ts', () => ({ importApi: mocks }))
vi.mock('@/modules/auth/stores/authStore.ts', () => ({ useAuthStore: () => mocks.auth }))
vi.mock('vue-toastification', () => ({
  useToast: () => ({ error: vi.fn(), success: vi.fn(), warning: vi.fn(), info: vi.fn() }),
}))
vi.mock('@/app/plugins/i18n.ts', () => ({ default: { global: { t: (key: string) => key } } }))
vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    t: (key: string, params?: Record<string, unknown>) =>
      `${key}${params ? ' ' + JSON.stringify(params) : ''}`,
  }),
}))

function task(overrides: Partial<DataImport> = {}): DataImport {
  return {
    id: 1,
    resource: 'categories',
    status: { id: 'processing', name: 'Processing' },
    mode: { id: 'create_only', name: 'Create only' },
    original_name: 'categories.xlsx',
    context: { menu_id: 12, branch_id: null },
    headers: ['name'],
    mapping: { name: 0 },
    total_rows: 100,
    processed_rows: 37,
    valid_rows: 100,
    invalid_rows: 0,
    new_rows: 100,
    update_rows: 0,
    skipped_rows: 0,
    imported_rows: 37,
    failed_rows: 0,
    failure_reason: null,
    has_error_file: false,
    has_original_file: true,
    created_at: '',
    started_at: null,
    finished_at: null,
    requires_processing: true,
    ...overrides,
  }
}
function render() {
  return mount(ImportTaskDock, {
    attachTo: document.body,
    global: {
      stubs: {
        VIcon: true,
        ImportWizard: {
          name: 'ImportWizard',
          props: ['modelValue', 'resource', 'context', 'initialImport'],
          template: '<div data-testid="task-dialog" />',
        },
      },
    },
  })
}

describe('Import task dock', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    mocks.auth = reactive({ getToken: 'session' })
    setActivePinia(createPinia())
  })
  afterEach(() => {
    disposePinia(getActivePinia()!)
    vi.useRealTimers()
    vi.resetAllMocks()
  })

  it('appears when the matching dialog closes and shows filename and actual progress', async () => {
    const processing = useImportProcessing()
    const owner = Symbol('dialog')
    processing.track(task())
    processing.setDialog(owner, 1)
    const wrapper = render()
    expect(wrapper.find('aside').exists()).toBe(false)
    processing.setDialog(owner, null)
    await nextTick()
    expect(wrapper.text()).toContain('categories.xlsx')
    expect(wrapper.text()).toContain('37%')
    expect(wrapper.get('progress').attributes('value')).toBe('37')
    expect(wrapper.text()).toContain('"processed":37,"total":100')
    wrapper.unmount()
  })

  it('does not reopen the task dock after a completed import dialog closes', async () => {
    const processing = useImportProcessing()
    const owner = Symbol('dialog')
    processing.track(task())
    processing.setDialog(owner, 1)
    const wrapper = render()

    processing.track(
      task({
        status: { id: 'completed', name: 'Completed' },
        processed_rows: 100,
        imported_rows: 100,
        requires_processing: false,
      }),
    )
    processing.setDialog(owner, null)
    await nextTick()

    expect(wrapper.find('aside').exists()).toBe(false)
    expect(processing.tasks.has(1)).toBe(false)
    wrapper.unmount()
  })

  it('opens the exact category task with its menu context without changing pages', async () => {
    const processing = useImportProcessing()
    const record = task()
    processing.track(record)
    const wrapper = render()
    await wrapper.get('.import-task__open').trigger('click')
    const dialog = wrapper.findComponent({ name: 'ImportWizard' })
    expect(dialog.props('initialImport')).toEqual(record)
    expect(dialog.props('resource')).toBe('categories')
    expect(dialog.props('context')).toEqual({ menu_id: 12 })
    expect(dialog.props('modelValue')).toBe(true)
    wrapper.unmount()
  })

  it('can collapse without dismissing or stopping running work', async () => {
    const processing = useImportProcessing()
    processing.track(task())
    const wrapper = render()
    expect(wrapper.find('.import-task__dismiss').exists()).toBe(false)
    await wrapper.get('.import-task-dock__header').trigger('click')
    expect(wrapper.get('.import-task-dock__header').attributes('aria-expanded')).toBe('false')
    expect(wrapper.get('#import-task-list').isVisible()).toBe(false)
    processing.dismiss(1)
    expect(processing.tasks.has(1)).toBe(true)
    await wrapper.get('.import-task-dock__header').trigger('click')
    expect(wrapper.get('#import-task-list').isVisible()).toBe(true)
    wrapper.unmount()
  })

  it('does not show 100 percent while finalization is still running', () => {
    useImportProcessing().track(task({ processed_rows: 100 }))
    const wrapper = render()
    expect(wrapper.text()).toContain('99%')
    expect(wrapper.text()).toContain('import::imports.task_finalizing')
    wrapper.unmount()
  })

  it('keeps the result visible until explicitly dismissed', async () => {
    const processing = useImportProcessing()
    processing.track(task())
    processing.track(
      task({
        status: { id: 'completed', name: 'Completed' },
        requires_processing: false,
        processed_rows: 100,
      }),
    )
    const wrapper = render()
    expect(wrapper.text()).toContain('100%')
    expect(wrapper.get('article').classes()).toContain('import-task--success')
    await wrapper.get('.import-task__dismiss').trigger('click')
    expect(wrapper.find('aside').exists()).toBe(false)
    wrapper.unmount()
  })

  it('shows validation errors with a details action and reconnecting state honestly', async () => {
    const processing = useImportProcessing()
    processing.track(task())
    processing.errors.set(1, true)
    const wrapper = render()
    expect(wrapper.text()).toContain('import::imports.task_reconnecting')
    processing.errors.clear()
    processing.track(
      task({
        status: { id: 'validation_failed', name: 'Validation failed' },
        requires_processing: false,
        invalid_rows: 7,
      }),
    )
    await nextTick()
    expect(wrapper.get('article').classes()).toContain('import-task--error')
    expect(wrapper.text()).toContain('"count":7')
    expect(wrapper.get('.import-task__open').text()).toContain('task_view_details')
    wrapper.unmount()
  })

  it('removes task details as soon as the account changes', async () => {
    useImportProcessing().track(task())
    const wrapper = render()
    await wrapper.get('.import-task__open').trigger('click')
    mocks.auth.getToken = 'another-session'
    await nextTick()
    expect(wrapper.find('aside').exists()).toBe(false)
    expect(wrapper.find('[data-testid="task-dialog"]').exists()).toBe(false)
    wrapper.unmount()
  })
})
