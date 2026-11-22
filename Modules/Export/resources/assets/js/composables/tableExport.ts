import type {
  CreateTableExportPayload,
  TableExportListFilters,
  TableExportListResponseBody,
  TableExportItem,
} from '@/modules/export/contracts/export.ts'
import type { PaginationMeta } from '@/modules/core/contracts/Table.ts'
import { createExport, downloadExport, listExports } from '@/modules/export/api/export.api.ts'

function normalizeContextValue(value: unknown): unknown {
  if (Array.isArray(value)) {
    return value.map(normalizeContextValue)
  }

  if (value && typeof value === 'object') {
    return Object.keys(value as Record<string, unknown>)
      .sort()
      .reduce(
        (result, key) => {
          result[key] = normalizeContextValue((value as Record<string, unknown>)[key])
          return result
        },
        {} as Record<string, unknown>,
      )
  }

  return value
}

export function buildTableExportContextKey(
  routeName: string,
  routeParams: Record<string, unknown>,
  context: Record<string, unknown> = {},
) {
  return JSON.stringify(
    normalizeContextValue({
      route_name: routeName,
      route_params: routeParams,
      context,
    }),
  )
}

function emptyPagination(): PaginationMeta {
  return {
    current_page: 1,
    from: 0,
    last_page: 1,
    per_page: 10,
    to: 0,
    total: 0,
  }
}

function defaultFilters(): TableExportListFilters {
  return {
    format: null,
    is_downloaded: null,
  }
}

export function useTableExport() {
  const latestExport = ref<TableExportItem | null>(null)
  const exports = ref<TableExportItem[]>([])
  const pagination = ref<PaginationMeta>(emptyPagination())
  const page = ref(1)
  const perPage = ref(10)
  const filters = ref<TableExportListFilters>(defaultFilters())
  const drawerOpen = ref(false)
  const loading = ref(false)
  const queueing = ref(false)
  const downloadingById = ref<Record<number, boolean>>({})
  const activeResource = ref('')
  const activeContextKey = ref('')
  const lastLoadedSignature = ref('')
  const loadedOnce = ref(false)

  const isProcessing = computed(() =>
    ['pending', 'processing'].includes(latestExport.value?.status?.id || ''),
  )
  const hasProcessingOnCurrentPage = computed(() =>
    exports.value.some((item) => ['pending', 'processing'].includes(item.status?.id || '')),
  )
  const shouldPoll = computed(
    () => (drawerOpen.value && hasProcessingOnCurrentPage.value) || isProcessing.value,
  )

  function setContext(resource: string, contextKey: string) {
    const changed = activeResource.value !== resource || activeContextKey.value !== contextKey
    activeResource.value = resource
    activeContextKey.value = contextKey

    if (changed) {
      page.value = 1
    }
  }

  function clear() {
    activeResource.value = ''
    activeContextKey.value = ''
    latestExport.value = null
    exports.value = []
    pagination.value = emptyPagination()
    page.value = 1
    perPage.value = 10
    filters.value = defaultFilters()
    drawerOpen.value = false
    lastLoadedSignature.value = ''
    loadedOnce.value = false
  }

  function buildLoadSignature(
    resource: string,
    contextKey: string,
    pageValue: number,
    perPageValue: number,
    nextFilters: TableExportListFilters,
  ): string {
    return JSON.stringify({
      resource,
      contextKey,
      page: pageValue,
      perPage: perPageValue,
      filters: nextFilters,
    })
  }

  async function refreshExports(
    resource = activeResource.value,
    contextKey = activeContextKey.value,
    options?: {
      page?: number
      perPage?: number
      filters?: TableExportListFilters
      force?: boolean
    },
  ) {
    if (!resource || !contextKey) {
      clear()
      return null
    }

    setContext(resource, contextKey)

    const targetPage = options?.page ?? page.value
    const targetPerPage = options?.perPage ?? perPage.value
    const targetFilters = options?.filters ?? filters.value
    const signature = buildLoadSignature(
      resource,
      contextKey,
      targetPage,
      targetPerPage,
      targetFilters,
    )

    if (!options?.force && loadedOnce.value && lastLoadedSignature.value === signature) {
      return {
        data: exports.value,
        latest: latestExport.value,
        pagination: pagination.value,
      }
    }

    page.value = targetPage
    perPage.value = targetPerPage
    filters.value = { ...targetFilters }
    loading.value = true

    try {
      const response = await listExports(
        resource,
        contextKey,
        targetPage,
        targetPerPage,
        targetFilters,
      )
      const body = response.data.body as TableExportListResponseBody

      exports.value = body.data || []
      latestExport.value = body.latest || exports.value[0] || null
      pagination.value = body.pagination || emptyPagination()
      lastLoadedSignature.value = signature
      loadedOnce.value = true

      return body
    } finally {
      loading.value = false
    }
  }

  async function openDrawer(resource = activeResource.value, contextKey = activeContextKey.value) {
    drawerOpen.value = true
    return refreshExports(resource, contextKey)
  }

  function closeDrawer() {
    drawerOpen.value = false
  }

  async function queueExport(apiUri: string, payload: CreateTableExportPayload) {
    queueing.value = true
    try {
      const response = await createExport(apiUri, payload)
      latestExport.value = response.data.body
      page.value = 1
      return latestExport.value
    } finally {
      queueing.value = false
    }
  }

  async function triggerDownload(item: TableExportItem) {
    downloadingById.value = {
      ...downloadingById.value,
      [item.id]: true,
    }

    try {
      const response = await downloadExport(item.id)
      const blob = response.data
      const filename = item.file_name || `export-${item.id}.${item.format.id}`
      const url = window.URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.setAttribute('download', filename)
      document.body.append(link)
      link.click()
      link.remove()
      window.URL.revokeObjectURL(url)
    } finally {
      const updated = { ...downloadingById.value }
      delete updated[item.id]
      downloadingById.value = updated
    }
  }

  return {
    activeContextKey,
    activeResource,
    closeDrawer,
    clear,
    filters,
    downloadingById,
    drawerOpen,
    exports,
    hasProcessingOnCurrentPage,
    latestExport,
    loading,
    openDrawer,
    page,
    pagination,
    perPage,
    queueing,
    refreshExports,
    setContext,
    isProcessing,
    shouldPoll,
    queueExport,
    triggerDownload,
  }
}
