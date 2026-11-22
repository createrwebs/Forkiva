import { computed, reactive, ref, watch } from 'vue'
import { watchDebounced } from '@vueuse/core'
import { getTableViewer } from '@/modules/seatingPlan/api/table.api.ts'

export function useTableViewerData(
  props: { modelValue: boolean; branchId: number | null },
  onError: () => void,
) {
  const loading = ref(false)
  const tables = ref<any[]>([])
  const meta = reactive({
    statuses: [] as { id: string; name: string }[],
    floors: [] as { id: string; name: string }[],
    zones: [] as { id: string; name: string }[],
  })
  const filters = reactive({
    search: '',
    floors: [] as string[],
    zones: [] as string[],
    statuses: [] as string[],
  })
  const queryKey = computed(() =>
    JSON.stringify([
      props.branchId,
      filters.search || '',
      filters.floors,
      filters.zones,
      filters.statuses,
    ]),
  )
  let requestedKey: string | undefined
  let requestId = 0

  async function refresh({ resetFilters = false, refreshing = false } = {}) {
    if (resetFilters) Object.assign(filters, { search: '', floors: [], zones: [], statuses: [] })
    const currentRequest = ++requestId
    requestedKey = queryKey.value
    const currentKey = requestedKey
    if (!refreshing) loading.value = true
    try {
      const response = await getTableViewer(props.branchId, {
        search: filters.search || '',
        floors: [...filters.floors],
        zones: [...filters.zones],
        statuses: [...filters.statuses],
      })
      if (currentRequest !== requestId || currentKey !== queryKey.value) return
      const data = response.data.body
      tables.value = data.tables || []
      meta.statuses = data.filters?.statuses || []
      meta.floors = data.filters?.floors || []
      meta.zones = data.filters?.zones || []
    } catch {
      if (currentRequest === requestId && currentKey === queryKey.value) onError()
    } finally {
      if (currentRequest === requestId) loading.value = false
    }
  }

  watch(
    () => [props.modelValue, props.branchId],
    () => {
      if (props.modelValue) void refresh({ resetFilters: true })
    },
    { immediate: true },
  )

  // Opening the panel already requests the reset filters; only changed queries need a second fetch.
  watchDebounced(
    queryKey,
    () => {
      if (props.modelValue && queryKey.value !== requestedKey) void refresh({ refreshing: true })
    },
    { debounce: 250, maxWait: 600 },
  )

  return { loading, tables, meta, filters, refresh }
}
