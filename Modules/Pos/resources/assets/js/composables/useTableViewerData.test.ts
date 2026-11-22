import { effectScope, nextTick, reactive } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { getTableViewer } from '@/modules/seatingPlan/api/table.api.ts'
import { useTableViewerData } from './useTableViewerData'

vi.mock('@/modules/seatingPlan/api/table.api.ts', () => ({ getTableViewer: vi.fn() }))
const request = vi.mocked(getTableViewer)
const response = (tables: any[] = []) =>
  ({ data: { body: { tables, filters: {} } } }) as Awaited<ReturnType<typeof getTableViewer>>
let scope: ReturnType<typeof effectScope>
function setup(open = false) {
  const props = reactive({ modelValue: open, branchId: 1 as number | null })
  const error = vi.fn()
  scope = effectScope()
  const data = scope.run(() => useTableViewerData(props, error))!
  return { props, error, ...data }
}
async function settle() {
  await nextTick()
  await vi.advanceTimersByTimeAsync(700)
}
beforeEach(() => {
  vi.useFakeTimers()
  request.mockReset()
  request.mockResolvedValue(response())
})
afterEach(() => {
  scope.stop()
  vi.useRealTimers()
})

describe('POS table viewer requests', () => {
  it('loads once on opening, filtering, and reopening with reset filters', async () => {
    const { props, filters } = setup()
    props.modelValue = true
    await settle()
    expect(request).toHaveBeenCalledTimes(1)
    filters.statuses = ['occupied']
    await settle()
    expect(request).toHaveBeenCalledTimes(2)
    props.modelValue = false
    await settle()
    props.modelValue = true
    await settle()
    expect(request).toHaveBeenCalledTimes(3)
    expect(request.mock.calls[2]?.[1]?.statuses).toEqual([])
  })
  it('loads an initially open panel and allows deliberate refreshes of the same query', async () => {
    const { refresh } = setup(true)
    await settle()
    expect(request).toHaveBeenCalledTimes(1)
    await refresh({ refreshing: true })
    await settle()
    expect(request).toHaveBeenCalledTimes(2)
  })
  it('coalesces rapid filter edits and ignores reference-only changes', async () => {
    const { filters } = setup(true)
    await settle()
    filters.search = 'T'
    await nextTick()
    filters.search = 'T01'
    filters.statuses = ['occupied']
    await settle()
    expect(request).toHaveBeenCalledTimes(2)
    filters.statuses = ['occupied']
    await settle()
    expect(request).toHaveBeenCalledTimes(2)
  })
  it('does not let an old branch response replace the current tables', async () => {
    let finishOld!: (value: Awaited<ReturnType<typeof getTableViewer>>) => void
    request.mockImplementationOnce(
      () =>
        new Promise((resolve) => {
          finishOld = resolve
        }),
    )
    const { props, tables } = setup(true)
    props.branchId = 2
    request.mockResolvedValue(response([{ id: 22 }]))
    await settle()
    finishOld(response([{ id: 11 }]))
    await settle()
    expect(tables.value).toEqual([{ id: 22 }])
    expect(request).toHaveBeenCalledTimes(2)
  })
})
