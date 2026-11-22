import { defineComponent, nextTick, ref } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { useSettlementCollections } from './useSettlementCollections'
import type { SettlementCandidate } from '../contracts/paymentSettlement'

const api = vi.hoisted(() => ({ settlementCandidates: vi.fn() }))
vi.mock('../api/paymentSettlement.api', () => api)
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))
const candidate = (id: number): SettlementCandidate => ({
  id,
  reference: `ORD-${id}`,
  transaction_id: null,
  collected_at: '2026-10-12',
  amount_decimal: '10.00',
  amount: { amount: 10, formatted: '$10.00', currency: 'USD', precision: 2 },
  selectable: true,
  source_hash: String(id),
})
function render() {
  const busy = ref(false)
  let state!: ReturnType<typeof useSettlementCollections>
  const wrapper = mount(
    defineComponent({
      setup() {
        state = useSettlementCollections(1, '2026-10-12', () => busy.value)
        return () => null
      },
    }),
  )
  return { wrapper, state, busy }
}
beforeEach(() => {
  vi.clearAllMocks()
  vi.useFakeTimers()
  api.settlementCandidates.mockImplementation(async ({ page }: { page: number }) => ({
    data: {
      body: { data: [candidate(page)], pagination: { current_page: page, last_page: 2, total: 2 } },
    },
  }))
})
afterEach(() => vi.useRealTimers())
describe('settlement collection selection', () => {
  it('searches automatically, preserves selection across pages and filters selected rows locally', async () => {
    const { state, wrapper } = render()
    await state.loadSources()
    state.selectPage()
    await state.loadSources(2)
    state.selectPage()
    expect([...state.selected.value.keys()]).toEqual([1, 2])
    state.search.value = 'ORD-2'
    await nextTick()
    expect(state.loading.value).toBe(true)
    expect(state.sources.value).toBeNull()
    expect(api.settlementCandidates).toHaveBeenCalledTimes(2)
    await vi.advanceTimersByTimeAsync(300)
    expect(api.settlementCandidates.mock.calls[2]![0]).toMatchObject({ page: 1, search: 'ORD-2' })
    expect(state.selected.value.size).toBe(2)
    state.selectedOnly.value = true
    expect(state.visibleSources.value.map((row) => row.id)).toEqual([2])
    state.search.value = ''
    await flushPromises()
    expect(state.visibleSources.value.map((row) => row.id)).toEqual([1, 2])
    state.clearSelection()
    expect(state.selectedOnly.value).toBe(false)
    expect(state.selected.value.size).toBe(0)
    wrapper.unmount()
  })
  it('collapses rapid typing into one request, submits immediately on Enter and cancels pending reads on unmount', async () => {
    const { state, wrapper } = render()
    await state.loadSources()
    state.search.value = 'O'
    await nextTick()
    await vi.advanceTimersByTimeAsync(150)
    state.search.value = 'ORD'
    await nextTick()
    await vi.advanceTimersByTimeAsync(150)
    expect(api.settlementCandidates).toHaveBeenCalledTimes(1)
    await state.applyFilters()
    expect(api.settlementCandidates).toHaveBeenCalledTimes(2)
    await vi.advanceTimersByTimeAsync(400)
    expect(api.settlementCandidates).toHaveBeenCalledTimes(2)
    state.search.value = 'ORD-1'
    await nextTick()
    wrapper.unmount()
    await vi.advanceTimersByTimeAsync(400)
    expect(api.settlementCandidates).toHaveBeenCalledTimes(2)
  })
  it('applies a new date range once and keeps selected collections', async () => {
    const { state, wrapper } = render()
    await state.loadSources()
    state.selectPage()
    state.from.value = '2026-10-01'
    state.to.value = '2026-10-05'
    await flushPromises()
    expect(api.settlementCandidates).toHaveBeenCalledTimes(2)
    expect(api.settlementCandidates.mock.calls[1]![0]).toMatchObject({
      from: '2026-10-01',
      to: '2026-10-05',
      page: 1,
    })
    expect(state.selected.value.size).toBe(1)
    wrapper.unmount()
  })
  it('toggles page selection and prevents mixing methods or editing locked selections', async () => {
    const { state, wrapper, busy } = render()
    await state.loadSources()
    state.selectPage()
    expect(state.pageSelected.value).toBe(true)
    state.selectPage()
    expect(state.selected.value.size).toBe(0)
    state.toggle(candidate(1), true)
    busy.value = true
    state.clearSelection()
    state.toggle(candidate(2), true)
    expect([...state.selected.value.keys()]).toEqual([1])
    busy.value = false
    state.method.value = 'bank_transfer'
    await flushPromises()
    expect(state.selected.value.size).toBe(0)
    expect(
      api.settlementCandidates.mock.calls[api.settlementCandidates.mock.calls.length - 1]![0]
        .method,
    ).toBe('bank_transfer')
    wrapper.unmount()
  })
  it('ignores late reads from a previous method and aborts on unmount', async () => {
    const { state, wrapper } = render()
    let resolve!: (value: unknown) => void
    api.settlementCandidates.mockReturnValueOnce(
      new Promise((done) => {
        resolve = done
      }),
    )
    const pending = state.loadSources()
    state.method.value = 'mobile_wallet'
    await flushPromises()
    resolve({ data: { body: { data: [candidate(99)] } } })
    await pending
    expect(state.sources.value?.data[0]?.id).toBe(1)
    expect(api.settlementCandidates.mock.calls[0]![1].aborted).toBe(true)
    wrapper.unmount()
    expect(api.settlementCandidates.mock.calls[1]![1].aborted).toBe(true)
  })
  it('limits selection to 500 valid sources and returns to the list after removing the last selection', () => {
    const { state, wrapper } = render()
    state.toggle({ ...candidate(999), selectable: false }, true)
    expect(state.selected.value.size).toBe(0)
    for (let id = 1; id <= 501; id++) state.toggle(candidate(id), true)
    expect(state.selected.value.size).toBe(500)
    state.clearSelection()
    state.toggle(candidate(1), true)
    state.selectedOnly.value = true
    state.toggle(candidate(1), false)
    expect(state.selectedOnly.value).toBe(false)
    wrapper.unmount()
  })
})
