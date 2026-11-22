import type { DataImport } from '@/modules/import/contracts/import.ts'
import { mount } from '@vue/test-utils'
import { createPinia, disposePinia, getActivePinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, reactive } from 'vue'
import { useImportRefresh } from '@/modules/import/composables/useImportRefresh.ts'
import { useImportProcessing } from './importProcessing.ts'

const mocks = vi.hoisted(() => ({
  advance: vi.fn(),
  show: vi.fn(),
  success: vi.fn(),
  error: vi.fn(),
  warning: vi.fn(),
  info: vi.fn(),
  auth: { getToken: 'first-session' },
}))
vi.mock('@/modules/import/api/import.api.ts', () => ({ importApi: mocks }))
vi.mock('vue-toastification', () => ({ useToast: () => mocks }))
vi.mock('@/modules/auth/stores/authStore.ts', () => ({ useAuthStore: () => mocks.auth }))
vi.mock('@/app/plugins/i18n.ts', () => ({ default: { global: { t: (key: string) => key } } }))

function item(id: number, status: DataImport['status']['id'] = 'processing'): DataImport {
  return {
    id,
    resource: 'menus',
    status: { id: status, name: status },
    mode: { id: 'create_only', name: 'Create only' },
    original_name: `menus-${id}.csv`,
    headers: ['name'],
    mapping: { name: 0 },
    context: {},
    total_rows: 1,
    processed_rows: 0,
    valid_rows: 1,
    invalid_rows: 0,
    new_rows: 1,
    update_rows: 0,
    skipped_rows: 0,
    imported_rows: status === 'completed' ? 1 : 0,
    failed_rows: 0,
    failure_reason: null,
    has_error_file: false,
    has_original_file: true,
    created_at: '',
    started_at: null,
    finished_at: null,
    requires_processing: ['processing', 'validating', 'queued'].includes(status),
  }
}
const response = (record: DataImport) => ({ data: { body: record } })

describe('import background processing', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    mocks.auth = reactive({ getToken: 'first-session' })
    setActivePinia(createPinia())
  })
  afterEach(() => {
    disposePinia(getActivePinia()!)
    vi.useRealTimers()
    vi.resetAllMocks()
  })

  it('has one runner per import across dialog instances and notifies once on completion', async () => {
    const first = useImportProcessing()
    const second = useImportProcessing()
    mocks.advance
      .mockResolvedValueOnce(response(item(1)))
      .mockResolvedValue(response(item(1, 'completed')))
    first.track(item(1))
    second.track(item(1))
    await vi.advanceTimersByTimeAsync(10000)
    expect(mocks.advance).toHaveBeenCalledTimes(2)
    expect(second.records.get(1)?.status.id).toBe('completed')
    expect(second.completions.get(1)?.status.id).toBe('completed')
    expect(mocks.success).toHaveBeenCalledExactlyOnceWith('menus-1.csv: completed')
  })

  it('refreshes the matching page once when a background import finishes', async () => {
    const processor = useImportProcessing()
    const refreshMenus = vi.fn()
    const refreshProducts = vi.fn()
    const wrapper = mount(
      defineComponent({
        setup() {
          useImportRefresh('menus', refreshMenus)
          useImportRefresh('products', refreshProducts, () => ({ menu_id: 12 }))
          return () => null
        },
      }),
    )

    mocks.advance.mockResolvedValue(response(item(1, 'completed')))
    processor.track(item(1))
    await vi.advanceTimersByTimeAsync(1000)
    expect(refreshMenus).toHaveBeenCalledTimes(1)
    expect(refreshProducts).not.toHaveBeenCalled()

    processor.recordCompletion(item(1, 'completed'))
    processor.recordCompletion({ ...item(1, 'completed'), imported_rows: 2 })
    processor.recordCompletion({
      ...item(2, 'completed'),
      resource: 'products',
      context: { menu_id: 11 },
    })
    expect(refreshMenus).toHaveBeenCalledTimes(2)
    expect(refreshProducts).not.toHaveBeenCalled()

    processor.recordCompletion({
      ...item(3, 'partially_completed'),
      resource: 'products',
      context: { menu_id: 12 },
      imported_rows: 1,
    })
    expect(refreshProducts).toHaveBeenCalledTimes(1)
    wrapper.unmount()

    processor.recordCompletion(item(4, 'completed'))
    expect(refreshMenus).toHaveBeenCalledTimes(2)
  })

  it('serializes different imports, including when one request is slow', async () => {
    const processor = useImportProcessing()
    let resolveFirst!: (value: unknown) => void
    mocks.advance
      .mockReturnValueOnce(
        new Promise((resolve) => {
          resolveFirst = resolve
        }),
      )
      .mockResolvedValueOnce(response(item(2, 'completed')))
    processor.track(item(1))
    processor.track(item(2))
    await vi.advanceTimersByTimeAsync(10000)
    expect(mocks.advance).toHaveBeenCalledExactlyOnceWith(1)
    resolveFirst(response(item(1, 'completed')))
    await vi.advanceTimersByTimeAsync(1000)
    expect(mocks.advance.mock.calls).toEqual([[1], [2]])
  })

  it('monitors asynchronous jobs without advancing them and keeps their completed task', async () => {
    const processor = useImportProcessing()
    const queued = { ...item(1), requires_processing: false }
    mocks.show.mockResolvedValue(response(item(1, 'completed')))
    processor.track(queued)
    expect(processor.backgroundTasks).toEqual([queued])
    await vi.advanceTimersByTimeAsync(1000)
    expect(mocks.show).toHaveBeenCalledExactlyOnceWith(1)
    expect(mocks.advance).not.toHaveBeenCalled()
    expect(processor.backgroundTasks[0]?.status.id).toBe('completed')
    processor.dismiss(1)
    expect(processor.backgroundTasks).toEqual([])
  })

  it('checks persisted state after a lost response before sending another batch', async () => {
    const processor = useImportProcessing()
    mocks.advance
      .mockRejectedValueOnce(new Error('Connection lost'))
      .mockResolvedValue(response(item(1, 'completed')))
    mocks.show.mockResolvedValue(response(item(1)))
    processor.track(item(1))
    await vi.advanceTimersByTimeAsync(1000)
    expect(processor.errors.get(1)).toBe(true)
    await vi.advanceTimersByTimeAsync(4999)
    expect(mocks.advance).toHaveBeenCalledTimes(1)
    expect(mocks.show).not.toHaveBeenCalled()
    await vi.advanceTimersByTimeAsync(1)
    expect(mocks.show).toHaveBeenCalledExactlyOnceWith(1)
    expect(processor.errors.has(1)).toBe(false)
    await vi.advanceTimersByTimeAsync(1000)
    expect(mocks.advance).toHaveBeenCalledTimes(2)
  })

  it('reports server failures after recovering from a failed request and stops', async () => {
    const processor = useImportProcessing()
    mocks.advance.mockRejectedValue(new Error('Server error'))
    mocks.show.mockResolvedValue(response(item(1, 'failed')))
    processor.track(item(1))
    await vi.advanceTimersByTimeAsync(20000)
    expect(mocks.advance).toHaveBeenCalledTimes(1)
    expect(mocks.error).toHaveBeenCalledExactlyOnceWith('menus-1.csv: failed')
    expect(processor.records.get(1)?.status.id).toBe('failed')
  })

  it('stops when access is revoked instead of repeatedly retrying', async () => {
    const processor = useImportProcessing()
    mocks.advance.mockRejectedValue({ isAxiosError: true, response: { status: 403 } })
    processor.track(item(1))
    await vi.advanceTimersByTimeAsync(20000)
    expect(mocks.advance).toHaveBeenCalledTimes(1)
    expect(mocks.error).toHaveBeenCalledTimes(1)
  })

  it('discards old responses and pending work when the account changes', async () => {
    const processor = useImportProcessing()
    let resolveOld!: (value: unknown) => void
    mocks.advance.mockReturnValueOnce(
      new Promise((resolve) => {
        resolveOld = resolve
      }),
    )
    processor.track(item(1))
    processor.track(item(2))
    await vi.advanceTimersByTimeAsync(1000)
    mocks.auth.getToken = 'second-session'
    resolveOld(response(item(1, 'completed')))
    await vi.advanceTimersByTimeAsync(20000)
    expect(processor.records.size).toBe(0)
    expect(mocks.advance).toHaveBeenCalledTimes(1)
    expect(mocks.success).not.toHaveBeenCalled()
  })

  it('starts a new confirmed phase without reusing the previous validation result', async () => {
    const processor = useImportProcessing()
    mocks.advance
      .mockResolvedValueOnce(response(item(1, 'ready')))
      .mockResolvedValue(response(item(1, 'completed')))
    processor.track(item(1, 'validating'))
    await vi.advanceTimersByTimeAsync(1000)
    expect(processor.records.get(1)?.status.id).toBe('ready')
    processor.track(item(1, 'queued'))
    expect(processor.records.has(1)).toBe(false)
    await vi.advanceTimersByTimeAsync(1000)
    expect(processor.records.get(1)?.status.id).toBe('completed')
  })
})
