import { computed, onBeforeUnmount, ref, watch } from 'vue'
import debounce from 'lodash/debounce'
import { useI18n } from 'vue-i18n'
import { getApiErrorMessage } from '@/modules/core/api/errors'
import { settlementCandidates } from '../api/paymentSettlement.api'
import type {
  ElectronicMethod,
  SettlementCandidate,
  SettlementPage,
} from '../contracts/paymentSettlement'

/** Keep selections across pages and searches within one payment method. */
export function useSettlementCollections(branchId: number, date: string, busy: () => boolean) {
  const { t } = useI18n()
  const start = new Date(`${date}T00:00:00Z`)
  start.setUTCDate(start.getUTCDate() - 29)
  const method = ref<ElectronicMethod>('card')
  const from = ref(start.toISOString().slice(0, 10))
  const to = ref(date)
  const search = ref('')
  const sources = ref<SettlementPage<SettlementCandidate> | null>(null)
  const selected = ref(new Map<number, SettlementCandidate>())
  const loading = ref(false)
  const loadError = ref('')
  const selectedOnly = ref(false)
  const visibleSources = computed(() => {
    if (!selectedOnly.value) return sources.value?.data ?? []
    const query = search.value.trim().toLocaleLowerCase()

    return [...selected.value.values()].filter(
      (row) =>
        !query ||
        row.reference.toLocaleLowerCase().includes(query) ||
        row.transaction_id?.toLocaleLowerCase().includes(query),
    )
  })
  const pageSelected = computed(() => {
    const available = sources.value?.data.filter((row) => row.selectable) ?? []

    return available.length > 0 && available.every((row) => selected.value.has(row.id))
  })
  const pageIndeterminate = computed(
    () =>
      !pageSelected.value &&
      (sources.value?.data.some((row) => selected.value.has(row.id)) ?? false),
  )
  let filters = { from: from.value, to: to.value, search: '' }
  let revision = 0
  let controller: AbortController | null = null

  async function loadSources(page = 1) {
    const current = ++revision
    controller?.abort()
    controller = new AbortController()
    const signal = controller.signal
    loading.value = true
    loadError.value = ''
    sources.value = null
    try {
      const response = await settlementCandidates(
        { branch_id: branchId, method: method.value, ...filters, page },
        signal,
      )
      if (current === revision) sources.value = response.data.body
    } catch (failure: unknown) {
      if (current === revision && !signal.aborted)
        loadError.value =
          getApiErrorMessage(failure) || t('dailyfinance::payment_settlements.load_error')
    } finally {
      if (current === revision) loading.value = false
    }
  }

  const debouncedSearch = debounce(() => {
    void applyFilters()
  }, 300)

  function applyFilters() {
    debouncedSearch.cancel()
    filters = { from: from.value, to: to.value, search: search.value.trim() }

    return loadSources()
  }

  function toggle(row: SettlementCandidate, checked: boolean) {
    if (!row.selectable || busy()) return
    const next = new Map(selected.value)
    if (checked && next.size < 500) next.set(row.id, row)
    else if (!checked) next.delete(row.id)
    selected.value = next
    if (!next.size) selectedOnly.value = false
  }

  function selectPage() {
    const checked = !pageSelected.value
    for (const row of sources.value?.data ?? []) toggle(row, checked)
  }

  function clearSelection() {
    if (busy()) return
    selected.value = new Map()
    selectedOnly.value = false
  }

  watch([method, from, to], ([nextMethod], [previousMethod]) => {
    if (nextMethod !== previousMethod) clearSelection()
    selectedOnly.value = false
    void applyFilters()
  })
  watch(search, (value) => {
    // Hide and invalidate old results immediately, before the debounce sends the next request.
    revision++
    controller?.abort()
    sources.value = null
    loadError.value = ''
    loading.value = true
    if (!value.trim()) void applyFilters()
    else debouncedSearch()
  })
  onBeforeUnmount(() => {
    revision++
    controller?.abort()
    debouncedSearch.cancel()
  })

  return {
    method,
    from,
    to,
    search,
    sources,
    selected,
    selectedOnly,
    visibleSources,
    pageSelected,
    pageIndeterminate,
    loading,
    loadError,
    loadSources,
    applyFilters,
    toggle,
    selectPage,
    clearSelection,
  }
}
