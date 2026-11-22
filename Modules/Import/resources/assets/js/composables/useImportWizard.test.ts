import type { DataImport, ImportRow } from '@/modules/import/contracts/import.ts'
import { flushPromises, mount, shallowMount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, disposePinia, getActivePinia, setActivePinia } from 'pinia'
import { defineComponent, ref } from 'vue'
import ImportWizard from '../Partials/ImportWizard.vue'
import { useImportProcessing } from '@/modules/import/stores/importProcessing.ts'
import { useImportWizard } from './useImportWizard.ts'

const api = vi.hoisted(() => ({
  fields: vi.fn(),
  errors: vi.fn(),
  history: vi.fn(),
  template: vi.fn(),
  rows: vi.fn(),
  map: vi.fn(),
  upload: vi.fn(),
  show: vi.fn(),
  advance: vi.fn(),
  validate: vi.fn(),
  editRow: vi.fn(),
  queue: vi.fn(),
  retry: vi.fn(),
  cancel: vi.fn(),
}))

vi.mock('@/modules/import/api/import.api.ts', () => ({ importApi: api }))
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))
vi.mock('vue-toastification', () => ({
  useToast: () => ({ error: vi.fn(), success: vi.fn(), warning: vi.fn(), info: vi.fn() }),
}))
vi.mock('@/modules/auth/stores/authStore.ts', () => ({
  useAuthStore: () => ({ getToken: 'test-session' }),
}))
vi.mock('@/app/plugins/i18n.ts', () => ({ default: { global: { t: (key: string) => key } } }))

function importRecord(id: number): DataImport {
  return {
    id,
    resource: 'menus',
    status: { id: 'staged', name: 'staged' },
    mode: { id: 'create_only', name: 'create_only' },
    original_name: 'menus.csv',
    headers: ['Name'],
    mapping: { name: 0 },
    context: {},
    total_rows: 1,
    processed_rows: 0,
    valid_rows: 0,
    invalid_rows: 0,
    new_rows: 0,
    update_rows: 0,
    skipped_rows: 0,
    imported_rows: 0,
    failed_rows: 0,
    failure_reason: null,
    has_error_file: false,
    has_original_file: true,
    created_at: '2026-09-27T00:00:00Z',
    started_at: null,
    finished_at: null,
  }
}

describe('useImportWizard', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })
  afterEach(() => {
    disposePinia(getActivePinia()!)
    vi.useRealTimers()
    vi.clearAllMocks()
  })

  it('keeps the template download busy until the file request finishes', async () => {
    let finishDownload: ((value: unknown) => void) | undefined
    api.template.mockReturnValue(
      new Promise((resolve) => {
        finishDownload = resolve
      }),
    )
    vi.stubGlobal('URL', { createObjectURL: () => 'blob:template', revokeObjectURL: vi.fn() })
    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {})
    let wizard!: ReturnType<typeof useImportWizard>
    const wrapper = mount(
      defineComponent({
        setup() {
          wizard = useImportWizard({ resource: 'menus' }, ref(false), () => {})
          return () => null
        },
      }),
    )

    try {
      const download = wizard.download('template')
      expect(wizard.templateDownloading.value).toBe(true)
      await wizard.download('template')
      expect(api.template).toHaveBeenCalledTimes(1)

      finishDownload?.({ data: new Blob(['template']) })
      await download
      expect(wizard.templateDownloading.value).toBe(false)
    } finally {
      wrapper.unmount()
      click.mockRestore()
      vi.unstubAllGlobals()
    }
  })

  it('shows history loading until recent imports arrive', async () => {
    api.fields.mockResolvedValue({ data: { body: [] } })
    let finishHistory: ((value: unknown) => void) | undefined
    api.history.mockReturnValue(
      new Promise((resolve) => {
        finishHistory = resolve
      }),
    )
    let wizard!: ReturnType<typeof useImportWizard>
    const wrapper = mount(
      defineComponent({
        setup() {
          wizard = useImportWizard({ resource: 'menus' }, ref(true), () => {})
          return () => null
        },
      }),
    )

    expect(wizard.historyLoading.value).toBe(true)
    finishHistory?.({ data: { body: { data: [importRecord(1)] } } })
    await flushPromises()
    expect(wizard.historyLoading.value).toBe(false)
    expect(wizard.history.value).toHaveLength(1)
    wrapper.unmount()
  })

  it('names error downloads after the uploaded workbook', async () => {
    vi.useFakeTimers()
    api.fields.mockResolvedValue({ data: { body: [] } })
    api.history.mockResolvedValue({ data: { body: { data: [] } } })
    api.errors.mockResolvedValue({ data: new Blob(['workbook']) })
    const createUrl = vi.fn(() => 'blob:errors')
    const revokeUrl = vi.fn()
    vi.stubGlobal('URL', { createObjectURL: createUrl, revokeObjectURL: revokeUrl })
    let downloadedName = ''
    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function (
      this: HTMLAnchorElement,
    ) {
      downloadedName = this.download
    })
    let wizard: ReturnType<typeof useImportWizard> | undefined
    const wrapper = mount(
      defineComponent({
        setup() {
          wizard = useImportWizard({ resource: 'menus' }, ref(false), () => {})
          return () => null
        },
      }),
    )
    try {
      wizard!.current.value = { ...importRecord(1), original_name: 'menus-template-filled.xlsx' }
      await wizard!.download('errors')
      expect(api.errors).toHaveBeenCalledWith(1)
      expect(downloadedName).toBe('validation-errors-menus-template-filled.xlsx')
      await vi.advanceTimersByTimeAsync(1000)
      expect(revokeUrl).toHaveBeenCalledWith('blob:errors')
    } finally {
      wrapper.unmount()
      click.mockRestore()
      vi.unstubAllGlobals()
    }
  })

  it('keeps the selected import preview when an earlier request completes late', async () => {
    api.fields.mockResolvedValue({ data: { body: [] } })
    api.history.mockResolvedValue({
      data: { body: { data: [], pagination: { current_page: 1, last_page: 1, total: 0 } } },
    })

    let resolveFirst: ((value: unknown) => void) | undefined
    const firstRows = new Promise((resolve) => {
      resolveFirst = resolve
    })
    const latestRow: ImportRow = {
      id: 20,
      row_number: 2,
      values: ['Latest'],
      data: { name: 'Latest' },
      errors: null,
      operation: null,
      status: { id: 'pending', name: 'pending' },
    }
    api.rows.mockReturnValueOnce(firstRows).mockResolvedValueOnce({
      data: {
        body: {
          data: [latestRow],
          pagination: { current_page: 1, last_page: 1, total: 1 },
        },
      },
    })

    let wizard: ReturnType<typeof useImportWizard> | undefined
    const component = defineComponent({
      setup() {
        wizard = useImportWizard({ resource: 'menus' }, ref(true), () => {})
        return () => null
      },
    })
    const wrapper = mount(component)
    await flushPromises()

    wizard?.selectImport(importRecord(1))
    wizard?.selectImport(importRecord(2))
    await flushPromises()
    resolveFirst?.({
      data: {
        body: { data: [], pagination: { current_page: 1, last_page: 1, total: 0 } },
      },
    })
    await flushPromises()

    expect(wizard?.current.value?.id).toBe(2)
    expect(wizard?.rows.value).toEqual([latestRow])
    wrapper.unmount()
  })

  it('blocks missing required mappings while allowing optional fields to stay unmapped', async () => {
    api.fields.mockResolvedValue({
      data: {
        body: [
          { key: 'name', label: 'Name', type: 'text', required: true },
          { key: 'description', label: 'Description', type: 'text', required: false },
        ],
      },
    })
    api.history.mockResolvedValue({ data: { body: { data: [] } } })
    api.map.mockResolvedValue({
      data: { body: { ...importRecord(1), status: { id: 'staging', name: 'staging' } } },
    })

    let wizard: ReturnType<typeof useImportWizard> | undefined
    const wrapper = mount(
      defineComponent({
        setup() {
          wizard = useImportWizard({ resource: 'menus' }, ref(true), () => {})
          return () => null
        },
      }),
    )
    await flushPromises()

    wizard?.selectImport({
      ...importRecord(1),
      status: { id: 'uploaded', name: 'uploaded' },
      mapping: {},
    })
    await wizard?.saveMapping()
    expect(api.map).not.toHaveBeenCalled()
    expect(wizard?.mappingAttempted.value).toBe(true)

    if (wizard) wizard.mapping.value.name = 0
    await wizard?.saveMapping()
    expect(api.map).toHaveBeenCalledWith(1, { name: 0 })
    wrapper.unmount()
  })

  it('shows preview loading and allows a failed row request to be retried', async () => {
    api.fields.mockResolvedValue({ data: { body: [] } })
    api.history.mockResolvedValue({ data: { body: { data: [] } } })
    const row: ImportRow = {
      id: 10,
      row_number: 2,
      values: ['Dinner'],
      data: { name: 'Dinner' },
      errors: { name: ['Name already exists.'] },
      operation: { id: 'invalid', name: 'invalid' },
      status: { id: 'invalid', name: 'invalid' },
    }
    api.rows.mockRejectedValueOnce(new Error('Unavailable')).mockResolvedValueOnce({
      data: {
        body: {
          data: [row],
          pagination: { current_page: 1, last_page: 1, total: 1 },
        },
      },
    })

    let wizard: ReturnType<typeof useImportWizard> | undefined
    const wrapper = mount(
      defineComponent({
        setup() {
          wizard = useImportWizard({ resource: 'menus' }, ref(true), () => {})
          return () => null
        },
      }),
    )
    await flushPromises()

    wizard?.selectImport({
      ...importRecord(1),
      status: { id: 'validation_failed', name: 'validation_failed' },
      invalid_rows: 1,
    })
    expect(wizard?.rowsLoading.value).toBe(true)
    await flushPromises()
    expect(wizard?.rowsLoading.value).toBe(false)
    expect(wizard?.rowsError.value).toBe('import::imports.request_failed')

    await wizard?.loadRows()
    expect(wizard?.rowsError.value).toBeNull()
    expect(wizard?.rows.value).toEqual([row])
    wrapper.unmount()
  })

  it('requires column review and validation even for recognized files', async () => {
    vi.useFakeTimers()
    api.fields.mockResolvedValue({
      data: { body: [{ key: 'name', label: 'Name', type: 'text', required: true }] },
    })
    api.history.mockResolvedValue({ data: { body: { data: [] } } })
    api.upload.mockResolvedValue({
      data: { body: { ...importRecord(1), status: { id: 'uploaded', name: 'uploaded' } } },
    })
    api.map.mockResolvedValue({
      data: { body: { ...importRecord(1), status: { id: 'staging', name: 'staging' } } },
    })
    api.show.mockResolvedValueOnce({ data: { body: importRecord(1) } }).mockResolvedValueOnce({
      data: {
        body: {
          ...importRecord(1),
          status: { id: 'ready', name: 'ready' },
          mode: { id: 'upsert', name: 'upsert' },
          valid_rows: 1,
        },
      },
    })
    api.validate.mockResolvedValue({
      data: {
        body: {
          ...importRecord(1),
          status: { id: 'validating', name: 'validating' },
          mode: { id: 'upsert', name: 'upsert' },
        },
      },
    })
    api.rows.mockResolvedValue({
      data: {
        body: {
          data: [
            {
              id: 10,
              row_number: 2,
              values: ['Example'],
              data: { name: 'Example' },
              errors: null,
              operation: { id: 'create', name: 'create' },
              status: { id: 'valid', name: 'valid' },
            },
          ],
          pagination: { current_page: 1, last_page: 1, total: 1 },
        },
      },
    })

    let wizard: ReturnType<typeof useImportWizard> | undefined
    const wrapper = mount(
      defineComponent({
        setup() {
          wizard = useImportWizard({ resource: 'menus' }, ref(true), () => {})
          return () => null
        },
      }),
    )
    await flushPromises()

    if (wizard) {
      wizard.mode.value = 'upsert'
      wizard.file.value = new File(['name\nExample'], 'menus.csv', { type: 'text/csv' })
    }
    await wizard?.upload()
    await flushPromises()
    expect(api.map).not.toHaveBeenCalled()
    expect(wizard?.step.value).toBe(2)
    await wizard?.saveMapping()
    expect(api.map).toHaveBeenCalledWith(1, { name: 0 })

    await vi.advanceTimersByTimeAsync(3000)
    expect(api.validate).not.toHaveBeenCalled()
    await wizard?.validateFile()
    expect(api.validate).toHaveBeenCalledWith(1, 'upsert')
    await vi.advanceTimersByTimeAsync(3000)
    expect(wizard?.current.value?.status.id).toBe('ready')
    expect(wizard?.rows.value).toHaveLength(1)
    expect(wizard?.canConfirm.value).toBe(true)
    wrapper.unmount()
  })

  it('reports missing preview rows even when pagination incorrectly says zero', async () => {
    api.fields.mockResolvedValue({ data: { body: [] } })
    api.history.mockResolvedValue({ data: { body: { data: [] } } })
    api.rows.mockResolvedValue({
      data: { body: { data: [], pagination: { current_page: 1, last_page: 1, total: 0 } } },
    })

    let wizard: ReturnType<typeof useImportWizard> | undefined
    const wrapper = mount(
      defineComponent({
        setup() {
          wizard = useImportWizard({ resource: 'menus' }, ref(true), () => {})
          return () => null
        },
      }),
    )
    await flushPromises()

    wizard?.selectImport({
      ...importRecord(1),
      total_rows: 12,
      status: { id: 'ready', name: 'ready' },
    })
    await flushPromises()
    expect(wizard?.rowsError.value).toBe('import::imports.preview_unavailable')
    expect(wizard?.canConfirm.value).toBe(false)
    wrapper.unmount()
  })

  it('blocks confirmation while a cell edit is being saved', async () => {
    api.fields.mockResolvedValue({ data: { body: [] } })
    api.history.mockResolvedValue({ data: { body: { data: [] } } })
    const row: ImportRow = {
      id: 10,
      row_number: 2,
      values: ['Before'],
      data: { name: 'Before' },
      errors: null,
      operation: { id: 'create', name: 'create' },
      status: { id: 'valid', name: 'valid' },
    }
    api.rows.mockResolvedValue({
      data: {
        body: { data: [row], pagination: { current_page: 1, last_page: 1, total: 1 } },
      },
    })
    let resolveEdit: ((value: unknown) => void) | undefined
    api.editRow.mockReturnValue(
      new Promise((resolve) => {
        resolveEdit = resolve
      }),
    )

    let wizard: ReturnType<typeof useImportWizard> | undefined
    const wrapper = mount(
      defineComponent({
        setup() {
          wizard = useImportWizard({ resource: 'menus' }, ref(true), () => {})
          return () => null
        },
      }),
    )
    await flushPromises()

    wizard?.selectImport({
      ...importRecord(1),
      status: { id: 'ready', name: 'ready' },
      valid_rows: 1,
    })
    await flushPromises()
    expect(wizard?.canConfirm.value).toBe(true)

    row.data.name = 'After'
    const saving = wizard?.saveRow(row, 'name')
    expect(wizard?.canConfirm.value).toBe(false)
    resolveEdit?.({ data: { body: { ...row, status: { id: 'pending', name: 'pending' } } } })
    await saving
    expect(wizard?.current.value?.status.id).toBe('staged')
    expect(wizard?.canConfirm.value).toBe(false)
    wrapper.unmount()
  })

  async function setupWizard() {
    api.fields.mockResolvedValue({
      data: { body: [{ key: 'name', label: 'Name', type: 'text', required: true }] },
    })
    api.history.mockResolvedValue({ data: { body: { data: [] } } })
    const row: ImportRow = {
      id: 10,
      row_number: 2,
      values: ['Dinner'],
      data: { name: 'Dinner' },
      errors: null,
      operation: { id: 'create', name: 'create' },
      status: { id: 'valid', name: 'valid' },
    }
    api.rows.mockResolvedValue({
      data: { body: { data: [row], pagination: { current_page: 1, last_page: 1, total: 1 } } },
    })
    const completed = vi.fn()
    const open = ref(true)
    let wizard!: ReturnType<typeof useImportWizard>
    const wrapper = mount(
      defineComponent({
        setup() {
          wizard = useImportWizard({ resource: 'menus' }, open, completed)
          return () => null
        },
      }),
    )
    await flushPromises()
    return { wizard, wrapper, completed, row, open }
  }

  it('loads rows when staging and validation finish during the HTTP request', async () => {
    const { wizard, wrapper } = await setupWizard()
    wizard.selectImport({ ...importRecord(1), status: { id: 'uploaded', name: 'uploaded' } })
    api.map.mockResolvedValue({ data: { body: importRecord(1) } })
    await wizard.saveMapping()
    expect(wizard.rows.value).toHaveLength(1)
    api.validate.mockResolvedValue({
      data: { body: { ...importRecord(1), status: { id: 'ready', name: 'ready' }, valid_rows: 1 } },
    })
    await wizard.validateFile()
    expect(wizard.canConfirm.value).toBe(true)
    expect(api.rows).toHaveBeenCalledTimes(2)
    wrapper.unmount()
  })

  it('shows immediate completion and refreshes the owning page once', async () => {
    const { wizard, wrapper, completed } = await setupWizard()
    wizard.selectImport({
      ...importRecord(1),
      status: { id: 'ready', name: 'ready' },
      valid_rows: 1,
    })
    await flushPromises()
    api.queue.mockResolvedValue({
      data: {
        body: {
          ...importRecord(1),
          status: { id: 'completed', name: 'completed' },
          imported_rows: 1,
        },
      },
    })
    await wizard.queueImport()
    expect(wizard.step.value).toBe(4)
    expect(completed).toHaveBeenCalledTimes(1)
    expect(useImportProcessing().completions.get(1)?.status.id).toBe('completed')
    expect(wizard.history.value[0]?.status.id).toBe('completed')
    expect(wizard.canConfirm.value).toBe(false)
    wrapper.unmount()
  })

  it('blocks duplicate mappings and confirmation of a changed mapping draft', async () => {
    const { wizard, wrapper } = await setupWizard()
    wizard.selectImport({
      ...importRecord(1),
      status: { id: 'ready', name: 'ready' },
      valid_rows: 1,
    })
    await flushPromises()
    wizard.changeMapping()
    expect(wizard.canConfirm.value).toBe(false)
    wizard.mapping.value = { name: 0, description: 0 }
    await wizard.saveMapping()
    expect(api.map).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('serializes fast edits so an older value cannot overwrite the latest value', async () => {
    const { wizard, wrapper, row } = await setupWizard()
    wizard.selectImport({
      ...importRecord(1),
      status: { id: 'ready', name: 'ready' },
      valid_rows: 1,
    })
    await flushPromises()
    let resolveFirst!: (value: unknown) => void
    api.editRow
      .mockReturnValueOnce(
        new Promise((resolve) => {
          resolveFirst = resolve
        }),
      )
      .mockResolvedValueOnce({
        data: { body: { ...row, status: { id: 'pending', name: 'pending' } } },
      })
    row.data.name = 'First'
    const first = wizard.saveRow(row, 'name')
    row.data.name = 'Second'
    const second = wizard.saveRow(row, 'name')
    await flushPromises()
    expect(api.editRow).toHaveBeenCalledTimes(1)
    expect(wizard.canValidate.value).toBe(false)
    resolveFirst({ data: { body: { ...row, status: { id: 'pending', name: 'pending' } } } })
    await Promise.all([first, second])
    expect(api.editRow.mock.calls.map((call) => call[2])).toEqual([
      { name: 'First' },
      { name: 'Second' },
    ])
    expect(wizard.pendingEdits.value).toBe(0)
    expect(wizard.current.value?.status.id).toBe('staged')
    wrapper.unmount()
  })

  it('ignores a delayed poll after restarting the same import', async () => {
    vi.useFakeTimers()
    const { wizard, wrapper } = await setupWizard()
    let resolvePoll!: (value: unknown) => void
    api.show.mockReturnValueOnce(
      new Promise((resolve) => {
        resolvePoll = resolve
      }),
    )
    wizard.selectImport({ ...importRecord(1), status: { id: 'processing', name: 'processing' } })
    await vi.advanceTimersByTimeAsync(3000)
    wizard.newImport()
    wizard.selectImport({ ...importRecord(1), status: { id: 'ready', name: 'ready' } })
    resolvePoll({
      data: { body: { ...importRecord(1), status: { id: 'processing', name: 'processing' } } },
    })
    await flushPromises()
    expect(wizard.current.value?.status.id).toBe('ready')
    wrapper.unmount()
  })

  it('continues browser batches after unmount without updating the removed dialog', async () => {
    vi.useFakeTimers()
    const { wizard, wrapper, completed } = await setupWizard()
    const pending: DataImport = {
      ...importRecord(1),
      status: { id: 'processing', name: 'processing' },
      requires_processing: true,
    }
    let resolveBatch!: (value: unknown) => void
    api.advance
      .mockReturnValueOnce(
        new Promise((resolve) => {
          resolveBatch = resolve
        }),
      )
      .mockResolvedValue({
        data: {
          body: {
            ...pending,
            status: { id: 'completed', name: 'completed' },
            requires_processing: false,
          },
        },
      })
    wizard.selectImport(pending)
    await vi.advanceTimersByTimeAsync(1000)
    expect(api.advance).toHaveBeenCalledTimes(1)
    wrapper.unmount()
    api.rows.mockClear()
    api.history.mockClear()
    resolveBatch({ data: { body: pending } })
    await flushPromises()
    await vi.advanceTimersByTimeAsync(10000)
    expect(api.advance).toHaveBeenCalledTimes(2)
    expect(api.rows).not.toHaveBeenCalled()
    expect(api.history).not.toHaveBeenCalled()
    expect(completed).not.toHaveBeenCalled()
  })

  it('keeps processing while closed and shows the latest result after reopening', async () => {
    vi.useFakeTimers()
    const { wizard, wrapper, open, completed } = await setupWizard()
    const pending: DataImport = {
      ...importRecord(1),
      status: { id: 'processing', name: 'processing' },
      requires_processing: true,
    }
    const finished: DataImport = {
      ...pending,
      status: { id: 'completed', name: 'completed' },
      requires_processing: false,
      imported_rows: 1,
    }
    let resolveBatch!: (value: unknown) => void
    api.advance
      .mockReturnValueOnce(
        new Promise((resolve) => {
          resolveBatch = resolve
        }),
      )
      .mockResolvedValue({ data: { body: finished } })
    wizard.selectImport(pending)
    await vi.advanceTimersByTimeAsync(1000)
    open.value = false
    await flushPromises()
    api.rows.mockClear()
    api.history.mockClear()
    resolveBatch({ data: { body: pending } })
    await flushPromises()
    await vi.advanceTimersByTimeAsync(10000)
    expect(api.advance).toHaveBeenCalledTimes(2)
    expect(api.rows).not.toHaveBeenCalled()
    expect(api.history).not.toHaveBeenCalled()
    expect(completed).not.toHaveBeenCalled()
    expect(wizard.current.value?.status.id).toBe('processing')
    open.value = true
    await flushPromises()
    expect(wizard.current.value?.status.id).toBe('completed')
    expect(completed).toHaveBeenCalledTimes(1)
    await vi.advanceTimersByTimeAsync(5000)
    expect(api.advance).toHaveBeenCalledTimes(2)
    wrapper.unmount()
  })

  it('finishes browser processing and emits completion once', async () => {
    vi.useFakeTimers()
    const { wizard, wrapper, completed } = await setupWizard()
    wizard.selectImport({
      ...importRecord(1),
      status: { id: 'processing', name: 'processing' },
      requires_processing: true,
    })
    api.advance.mockResolvedValue({
      data: {
        body: {
          ...importRecord(1),
          status: { id: 'completed', name: 'completed' },
          requires_processing: false,
          imported_rows: 1,
        },
      },
    })
    await vi.advanceTimersByTimeAsync(3000)
    expect(wizard.current.value?.status.id).toBe('completed')
    expect(completed).toHaveBeenCalledTimes(1)
    await vi.advanceTimersByTimeAsync(9000)
    expect(api.advance).toHaveBeenCalledTimes(1)
    wrapper.unmount()
  })

  it('opens the task requested by the global dock directly with fresh server data', async () => {
    const record = { ...importRecord(12), resource: 'categories' as const, context: { menu_id: 7 } }
    api.fields.mockResolvedValue({ data: { body: [] } })
    api.history.mockResolvedValue({ data: { body: { data: [record] } } })
    api.show.mockResolvedValue({ data: { body: record } })
    api.rows.mockResolvedValue({ data: { body: { data: [], pagination: { last_page: 1 } } } })
    let wizard!: ReturnType<typeof useImportWizard>
    const wrapper = mount(
      defineComponent({
        setup() {
          wizard = useImportWizard(
            { resource: 'categories', context: { menu_id: 7 }, initialImport: record },
            ref(true),
            () => {},
          )
          return () => null
        },
      }),
    )
    await flushPromises()
    expect(api.show).toHaveBeenCalledWith(12)
    expect(api.fields).toHaveBeenCalledWith('categories', { menu_id: 7 })
    expect(wizard.current.value?.id).toBe(12)
    expect(wizard.step.value).toBe(3)
    wrapper.unmount()
  })

  it('shows only details loading while opening a task, then its fresh result', async () => {
    const record = importRecord(12)
    let resolveFields!: (value: unknown) => void
    let resolveDetails!: (value: unknown) => void
    api.fields.mockReturnValueOnce(
      new Promise((resolve) => {
        resolveFields = resolve
      }),
    )
    api.history.mockResolvedValue({ data: { body: { data: [record] } } })
    api.show.mockReturnValueOnce(
      new Promise((resolve) => {
        resolveDetails = resolve
      }),
    )
    api.rows.mockResolvedValue({ data: { body: { data: [], pagination: { last_page: 1 } } } })
    const wrapper = shallowMount(ImportWizard, {
      props: { modelValue: true, resource: 'menus', initialImport: record },
      global: {
        renderStubDefaultSlot: true,
        stubs: {
          ImportProgressSteps: false,
          VDialog: true,
          VCard: true,
          VIcon: true,
          VBtn: true,
          VProgressCircular: true,
          VChip: true,
          VSelect: true,
          VMenu: true,
          VList: true,
          VListItem: true,
        },
        mocks: { $vuetify: { display: { xs: false } } },
      },
    })
    expect(wrapper.find('.import-details-loading').exists()).toBe(true)
    expect(wrapper.text()).toContain('menus.csv')
    expect(wrapper.find('.import-upload').exists()).toBe(false)
    expect(wrapper.find('.import-steps').exists()).toBe(false)
    resolveFields({ data: { body: [] } })
    await flushPromises()
    expect(wrapper.find('.import-details-loading').exists()).toBe(true)
    expect(wrapper.find('.import-upload').exists()).toBe(false)
    resolveDetails({
      data: { body: { ...record, status: { id: 'completed', name: 'Completed' } } },
    })
    await flushPromises()
    expect(wrapper.find('.import-details-loading').exists()).toBe(false)
    expect(wrapper.find('.import-upload').exists()).toBe(false)
    expect(wrapper.find('[aria-current="step"]').text()).toContain('import::imports.result')
    wrapper.unmount()
  })

  it('keeps failed details in a retryable state instead of showing upload or stale actions', async () => {
    const { wizard, wrapper } = await setupWizard()
    const record = importRecord(12)
    api.show.mockRejectedValueOnce(new Error('Offline'))
    await wizard.resumeImport(record)
    expect(wizard.openingImport.value?.id).toBe(12)
    expect(wizard.openingError.value).toBe('import::imports.request_failed')
    expect(wizard.canConfirm.value).toBe(false)
    api.show.mockResolvedValueOnce({ data: { body: record } })
    await wizard.resumeImport(record)
    expect(wizard.openingImport.value).toBeNull()
    expect(wizard.openingError.value).toBeNull()
    expect(wizard.current.value?.id).toBe(12)
    wrapper.unmount()
  })

  it('ignores a details response that arrives after the dialog closes', async () => {
    const { wizard, wrapper, open } = await setupWizard()
    let resolveDetails!: (value: unknown) => void
    api.show.mockReturnValueOnce(
      new Promise((resolve) => {
        resolveDetails = resolve
      }),
    )
    const opening = wizard.resumeImport(importRecord(12))
    open.value = false
    resolveDetails({ data: { body: importRecord(12) } })
    await opening
    expect(wizard.current.value).toBeNull()
    expect(api.rows).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('reopens pending history details without an older request clearing the new loading state', async () => {
    const { wizard, wrapper, open } = await setupWizard()
    let resolveFirst!: (value: unknown) => void
    let resolveSecond!: (value: unknown) => void
    api.show
      .mockReturnValueOnce(
        new Promise((resolve) => {
          resolveFirst = resolve
        }),
      )
      .mockReturnValueOnce(
        new Promise((resolve) => {
          resolveSecond = resolve
        }),
      )
    const first = wizard.resumeImport(importRecord(12))
    open.value = false
    open.value = true
    await flushPromises()
    expect(api.show).toHaveBeenCalledTimes(2)
    resolveFirst({ data: { body: importRecord(12) } })
    await first
    expect(wizard.current.value).toBeNull()
    expect(wizard.busy.value).toBe(true)
    resolveSecond({ data: { body: importRecord(12) } })
    await flushPromises()
    expect(wizard.current.value?.id).toBe(12)
    expect(wizard.openingImport.value).toBeNull()
    expect(wizard.busy.value).toBe(false)
    wrapper.unmount()
  })

  it('rejects unsupported uploads before sending them', async () => {
    const { wizard, wrapper } = await setupWizard()
    wizard.file.value = new File(['invalid'], 'data.pdf')
    await wizard.upload()
    expect(api.upload).not.toHaveBeenCalled()
    wrapper.unmount()
  })
})
