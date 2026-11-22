<script lang="ts" setup>
import type {
  PaginationMeta,
  TableAction,
  TableFilterSchema,
  TableHeader as DataTableHeader,
} from '@/modules/core/contracts/Table.ts'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { VDataTableServer } from 'vuetify/components'
import { http } from '@/modules/core/api/http.ts'
import TableActiveStatus from '@/modules/core/components/Table/Partials/TableActiveStatus.vue'
import TableAgent from '@/modules/core/components/Table/Partials/TableAgent.vue'
import TableEnum from '@/modules/core/components/Table/Partials/TableEnum.vue'
import TableMoney from '@/modules/core/components/Table/Partials/TableMoney.vue'
import TableStatus from '@/modules/core/components/Table/Partials/TableStatus.vue'
import TableThumbnail from '@/modules/core/components/Table/Partials/TableThumbnail.vue'
import TableUserInfo from '@/modules/core/components/Table/Partials/TableUserInfo.vue'
import TableHeader from '@/modules/core/components/Table/TableHeader.vue'
import TableHeaderActions from '@/modules/core/components/Table/TableHeaderActions.vue'
import TablePagination from '@/modules/core/components/Table/TablePagination.vue'
import TableRowActions from '@/modules/core/components/Table/TableRowActions.vue'
import { useTableAction } from '@/modules/core/composables/tableAction.ts'
import TableExportDrawer from '@/modules/export/components/TableExportDrawer.vue'
import {
  buildTableExportContextKey,
  useTableExport,
} from '@/modules/export/composables/tableExport.ts'
import { useReport } from '@/modules/report/composables/report.ts'

interface SortOption {
  key: string
  order: 'asc' | 'desc'
}

const props = withDefaults(
  defineProps<{
    headers?: DataTableHeader[]
    resource: string
    name: string
    module: string
    apiUri: string
    title?: string
    hasFilters?: boolean
    hasSearch?: boolean
    hasPagination?: boolean
    selectable?: boolean
    paddingTop?: number
    cellComponents?: Record<string, any>
    itemId?: string
    actions?: TableAction[]
    headerActions?: TableAction[]
    leftHeaderActions?: TableAction[]
    bulkActions?: TableAction[]
    defaultFilters?: Record<string, any>
    filterSchemas?: TableFilterSchema[]
    exceptFilters?: string[]
    onRowClick?: ((event: MouseEvent, value: any) => void) | null | undefined
    onLoaded?: ((body: Record<string, any>) => void) | null | undefined
    paramsTransformer?: ((params: Record<string, any>) => Record<string, any>) | null | undefined
    responseTransformer?: ((body: Record<string, any>) => Record<string, any>) | null | undefined
  }>(),
  {
    itemId: 'id',
    headers: () => [],
    hasFilters: true,
    actions: () => [],
    headerActions: () => [],
    leftHeaderActions: () => [],
    paddingTop: 4,
    hasSearch: true,
    hasPagination: true,
    selectable: false,
    defaultFilters: () => ({}) as Record<string, any>,
    filterSchemas: () => [],
  },
)

const selected = defineModel<number[] | string[]>('selected', { default: () => [] })
const { t } = useI18n()
const toast = useToast()
const route = useRoute()
const { downloadExportFile } = useReport()
const { hasPermission } = useTableAction(
  props.resource,
  props.name,
  props.module,
  props.itemId,
  () => {},
)

const loading = ref<boolean>(false)

function makePaginationMeta(overrides: Partial<PaginationMeta> = {}): PaginationMeta {
  return {
    current_page: 1,
    from: 0,
    last_page: 1,
    per_page: perPage.value || 10,
    to: 0,
    total: 0,
    ...overrides,
  }
}

const pagination = ref<PaginationMeta>({
  current_page: 1,
  from: 1,
  last_page: 1,
  per_page: 10,
  to: 0,
  total: 0,
})
const items = ref<any[]>([])
const defaultFiltersResponse = ref<Record<string, any>>({})
const { headers } = toRefs(props)
const sortBy = ref<SortOption[]>([])
const page = ref(pagination.value.current_page)
const perPage = ref(pagination.value.per_page)
const filters = ref<TableFilterSchema[]>(props.filterSchemas.map((filter) => ({ ...filter })))
const filtersData = ref<Record<string, any>>({})
const withFilters = ref<boolean>(props.hasFilters && props.filterSchemas.length === 0)
const search = ref('')
const finalDefaultFilters = computed(() => ({
  ...props.defaultFilters,
  ...defaultFiltersResponse.value,
}))
const exportMethods = ref<Record<string, any>[]>([])
const exportApiUri = ref<string | null>(null)
const exportMode = ref<'queued' | 'download'>('queued')
const exportDownloading = ref(false)
const exportPermission = ref<string | null>(null)
const exportResource = ref<string | null>(null)
const exportContext = ref<Record<string, any>>({})
const exportFallbackKey = ref<string | null>(null)
const hasSearch = ref(props.hasSearch)
const {
  clear,
  downloadingById,
  drawerOpen,
  exports: exportItems,
  filters: exportHistoryFilters,
  hasProcessingOnCurrentPage,
  latestExport,
  loading: exportLoading,
  openDrawer,
  page: exportPage,
  pagination: exportPagination,
  perPage: exportPerPage,
  queueing: exportQueueing,
  isProcessing: exportProcessing,
  refreshExports,
  setContext,
  shouldPoll,
  queueExport,
  triggerDownload,
} = useTableExport()
let requestController: AbortController | null = null
let lastRequestKey = ''

const resolvedExportContext = computed(() => ({
  route_name: String(route.name || ''),
  route_params: { ...(route.params as Record<string, any>) },
  ...exportContext.value,
}))

const exportContextKey = computed(() =>
  buildTableExportContextKey(
    String(route.name || ''),
    route.params as Record<string, any>,
    exportContext.value,
  ),
)

const exportFilters = computed(() => ({
  search: search.value,
  ...finalDefaultFilters.value,
  ...filtersData.value,
}))

const resolvedExportPermission = computed(
  () =>
    exportPermission.value ||
    (exportFallbackKey.value
      ? `admin.reports.${exportFallbackKey.value}`
      : `admin.${props.resource}.export`),
)
const canAccessExports = computed(() =>
  hasPermission({ key: 'export', permission: resolvedExportPermission.value }),
)

const hasQueuedExportHistory = computed(
  () =>
    canAccessExports.value &&
    exportMode.value === 'queued' &&
    Boolean(exportApiUri.value && exportResource.value),
)
const hasExportHistories = computed(
  () => hasQueuedExportHistory.value && Boolean(latestExport.value),
)

const apiHeaderActions = computed<TableAction[]>(() => {
  const actions: TableAction[] = []

  if (canAccessExports.value && exportMethods.value.length > 0) {
    actions.push({
      key: 'export',
      label: t('admin::admin.buttons.export'),
      permission: resolvedExportPermission.value,
      options: exportMethods.value,
      icon: 'tabler-database-export',
      color: 'success',
      type: 'dropdown' as const,
      loading: exportQueueing.value || exportDownloading.value,
      onClick: async (option) => {
        if (!canAccessExports.value) {
          return
        }

        try {
          if (exportMode.value === 'download' && exportApiUri.value) {
            exportDownloading.value = true
            await downloadExportFile(
              exportFallbackKey.value || props.resource,
              option.id,
              exportFilters.value,
              { apiUri: exportApiUri.value, sorts: sortBy.value },
            )
            return
          }

          if (!exportApiUri.value) {
            return
          }

          await queueExport(exportApiUri.value, {
            format: option.id,
            filters: exportFilters.value,
            sorts: sortBy.value,
            context: resolvedExportContext.value,
            context_key: exportContextKey.value,
          })
          toast.success(t('export::exports.messages.queued'))
          if (canAccessExports.value) {
            await refreshExports(exportResource.value || '', exportContextKey.value, {
              page: 1,
              perPage: exportPerPage.value,
              filters: exportHistoryFilters.value,
              force: true,
            })
          }
        } catch {
          toast.error(t('export::exports.messages.failed_generic'))
        } finally {
          exportDownloading.value = false
        }
      },
    })
  }

  if (hasExportHistories.value) {
    actions.push({
      key: 'export-history',
      label: t('export::exports.histories'),
      permission: exportPermission.value || undefined,
      color: 'secondary',
      icon:
        hasProcessingOnCurrentPage.value || exportProcessing.value
          ? 'tabler-loader-2'
          : 'tabler-history',
      loading: exportLoading.value && drawerOpen.value,
      onClick: async () => {
        if (!canAccessExports.value) {
          return
        }

        try {
          await openDrawer(exportResource.value || '', exportContextKey.value)
        } catch {
          toast.error(t('core::errors.failed_to_load_data'))
        }
      },
    })
  }

  return actions
})

async function loadData(options?: {
  page?: number
  sortBy?: SortOption[]
  perPage?: number | null
  search?: string | null
  filterValues?: Record<string, any>
  force?: boolean
}) {
  const normalizedOptions = {
    page: options?.page ?? page.value,
    sortBy: options?.sortBy ?? sortBy.value,
    perPage: options?.perPage ?? perPage.value,
    search: options?.search ?? search.value,
    filterValues: options?.filterValues ?? filtersData.value,
  }
  const defaultParams = {
    with_filters: withFilters.value ? 1 : 0,
    page: normalizedOptions.page,
    sorts: normalizedOptions.sortBy,
    per_page: normalizedOptions.perPage,
    filters: {
      search: normalizedOptions.search,
      ...finalDefaultFilters.value,
      ...normalizedOptions.filterValues,
    },
  }
  const requestParams = props.paramsTransformer?.(defaultParams) ?? defaultParams
  const requestKey = JSON.stringify(requestParams)

  if (!options?.force && loading.value && requestKey === lastRequestKey) {
    return
  }

  requestController?.abort()
  const controller = new AbortController()
  requestController = controller
  lastRequestKey = requestKey
  loading.value = true

  try {
    const response = await http.get(props.apiUri, {
      signal: controller.signal,
      params: requestParams,
    })
    const body = props.responseTransformer?.(response.data.body ?? {}) ?? response.data.body ?? {}
    const loadedItems = Array.isArray(body.data) ? body.data : []
    items.value = loadedItems
    defaultFiltersResponse.value = body.default_filters || {}
    exportMethods.value = body.export_methods || []
    exportApiUri.value = body.export_api_uri || null
    exportMode.value = body.export_mode === 'download' ? 'download' : 'queued'
    exportPermission.value = body.export_permission || null
    exportResource.value = body.export_resource || null
    exportContext.value = body.export_context || {}
    exportFallbackKey.value = body.key || null
    props.onLoaded?.({
      ...body,
      applied_filters: {
        ...finalDefaultFilters.value,
        ...normalizedOptions.filterValues,
      },
      selected_filters: {
        ...normalizedOptions.filterValues,
      },
    })

    const resolvedPagination = props.hasPagination
      ? body.pagination || {
          current_page: normalizedOptions.page,
          from: loadedItems.length > 0 ? 1 : 0,
          last_page: 1,
          per_page: normalizedOptions.perPage || loadedItems.length || perPage.value,
          to: loadedItems.length,
          total: loadedItems.length,
        }
      : {
          current_page: 1,
          from: loadedItems.length > 0 ? 1 : 0,
          last_page: 1,
          per_page: loadedItems.length || perPage.value,
          to: loadedItems.length,
          total: loadedItems.length,
        }

    pagination.value = makePaginationMeta(resolvedPagination)

    if (body.has_search) {
      hasSearch.value = true
    }

    if (headers.value.length === 0 && body.headers && body.headers.length > 0) {
      for (const header of body.headers) {
        headers.value.push(header)
      }
    }

    if (withFilters.value) {
      withFilters.value = false
      const filterList = body?.filters || []
      const filteredList =
        props.exceptFilters && props.exceptFilters.length > 0
          ? filterList.filter(
              (filter: Record<string, any>) => !props.exceptFilters?.includes(filter.key),
            )
          : filterList
      const defaultFilters = (finalDefaultFilters.value || {}) as Record<string, any>

      filters.value = filteredList.map((filter: Record<string, any>) => ({
        ...filter,
        default: defaultFilters[filter.key] ?? filter.default ?? null,
      }))
    }
  } catch (error: any) {
    if (
      error?.code === 'ERR_CANCELED' ||
      error?.name === 'CanceledError' ||
      error?.name === 'AbortError'
    ) {
      return
    }

    toast.error(t('core::errors.failed_to_load_data'))
  } finally {
    if (requestController === controller) {
      requestController = null
      loading.value = false
    }
  }
}

const { pause, resume } = pausableWatch([perPage, page, sortBy], ([newPerPage], [oldPerPage]) => {
  if (newPerPage === oldPerPage) {
    loadData({
      page: page.value,
      perPage: perPage.value,
      sortBy: sortBy.value,
      search: search.value,
      filterValues: filtersData.value,
    })
  } else {
    pause()
    page.value = 1
    requestAnimationFrame(() => {
      resume()
      loadData({
        page: page.value,
        perPage: perPage.value,
        sortBy: sortBy.value,
        search: search.value,
        filterValues: filtersData.value,
      })
    })
  }
})

debouncedWatch(
  search,
  () => {
    if (page.value !== 1) {
      page.value = 1
      return
    }

    loadData({
      page: 1,
      perPage: perPage.value,
      sortBy: sortBy.value,
      search: search.value,
      filterValues: filtersData.value,
    })
  },
  { debounce: 300, maxWait: 800 },
)

function handleSortByUpdate(val: { key: string; order: 'asc' | 'desc' }[]) {
  sortBy.value = val.map((sort) => {
    const header = headers.value.find((h) => h.value === sort.key)
    return {
      key: header?.sortable_key || sort.key,
      order: sort.order,
    }
  })
}

onMounted(() => {
  loadData()
})

watch(
  [exportResource, exportContextKey, canAccessExports],
  async ([resource, contextKey, canAccess]) => {
    if (canAccess && resource && contextKey) {
      setContext(resource, contextKey)
      try {
        await refreshExports(resource, contextKey, {
          page: exportPage.value,
          perPage: exportPerPage.value,
          filters: exportHistoryFilters.value,
          force: true,
        })
      } catch {
        // Keep the table functional if export polling fails.
      }
    } else {
      clear()
    }
  },
  { immediate: true },
)

const { pause: pauseExportPolling, resume: resumeExportPolling } = useIntervalFn(
  async () => {
    if (canAccessExports.value && exportResource.value && exportContextKey.value) {
      await refreshExports(exportResource.value, exportContextKey.value, {
        page: exportPage.value,
        perPage: exportPerPage.value,
        filters: exportHistoryFilters.value,
        force: true,
      })
    }
  },
  5000,
  { immediate: false },
)

watch(
  shouldPoll,
  (value) => {
    if (value && canAccessExports.value && exportResource.value && exportContextKey.value) {
      resumeExportPolling()
    } else {
      pauseExportPolling()
    }
  },
  { immediate: true },
)

onBeforeUnmount(() => {
  requestController?.abort()
  pauseExportPolling()
})

const hasRowActions = computed(
  () =>
    props.actions?.length > 0 &&
    props.actions?.filter((action: TableAction) => hasPermission(action)).length > 0,
)
const hasBulkActions = computed(
  () =>
    props.bulkActions &&
    props.bulkActions?.length > 0 &&
    props.bulkActions?.filter((action: TableAction) => hasPermission(action)).length > 0,
)

onBeforeMount(() => {
  if (hasRowActions.value && !headers.value.some((header) => header.value === 'action')) {
    headers.value.push({ title: t('admin::admin.table.actions'), value: 'action' })
  }
})

function refresh() {
  deselectAll()
  loadData({ force: true })
}

function reload() {
  loadData({
    page: page.value,
    perPage: perPage.value,
    sortBy: sortBy.value,
    search: search.value,
    filterValues: filtersData.value,
  })
}

function applyFilters(values: Record<string, any>) {
  filtersData.value = values

  if (page.value !== 1) {
    page.value = 1
    return
  }

  loadData({
    page: 1,
    perPage: perPage.value,
    sortBy: sortBy.value,
    search: search.value,
    filterValues: filtersData.value,
  })
}

defineExpose({ refresh, reload, defaultFilters: finalDefaultFilters.value })

function deselectAll() {
  selected.value = []
}

function reloadFilters() {
  withFilters.value = true
}

async function handleExportPageUpdate(value: number) {
  if (!canAccessExports.value || !exportResource.value || !exportContextKey.value) {
    return
  }

  await refreshExports(exportResource.value, exportContextKey.value, {
    page: value,
    perPage: exportPerPage.value,
    filters: exportHistoryFilters.value,
    force: true,
  })
}

async function handleExportPerPageUpdate(value: number) {
  if (!canAccessExports.value || !exportResource.value || !exportContextKey.value) {
    return
  }

  await refreshExports(exportResource.value, exportContextKey.value, {
    page: 1,
    perPage: value,
    filters: exportHistoryFilters.value,
    force: true,
  })
}

async function handleExportFilterUpdate(value: {
  format: string | null
  is_downloaded: string | null
}) {
  if (!canAccessExports.value || !exportResource.value || !exportContextKey.value) {
    return
  }

  await refreshExports(exportResource.value, exportContextKey.value, {
    page: 1,
    perPage: exportPerPage.value,
    filters: value,
    force: true,
  })
}

async function handleExportDownload(item: any) {
  if (!canAccessExports.value) {
    return
  }

  await triggerDownload(item)
  toast.success(t('export::exports.messages.download_started'))

  if (canAccessExports.value && exportResource.value && exportContextKey.value) {
    await refreshExports(exportResource.value, exportContextKey.value, {
      page: exportPage.value,
      perPage: exportPerPage.value,
      filters: exportHistoryFilters.value,
      force: true,
    })
  }
}

const defaultCellComponents = [
  { key: 'is_active', component: TableActiveStatus },
  { key: 'agent', component: TableAgent },
  { key: 'user', component: TableUserInfo },
  { key: 'status', component: TableStatus },
  { key: 'type', component: TableEnum },
  { key: 'total', component: TableMoney },
  { key: 'amount', component: TableMoney },
  { key: 'thumbnail', component: TableThumbnail },
  { key: 'icon', component: TableThumbnail },
]
</script>

<template>
  <TableHeaderActions
    :actions="[...headerActions, ...apiHeaderActions]"
    :item-id="itemId"
    :left-actions="leftHeaderActions"
    :module="module"
    :name="name"
    :refresh="refresh"
    :resource="resource"
  />

  <VCard>
    <TableHeader
      v-model="search"
      :bulk-actions="bulkActions"
      :deselect-all="deselectAll"
      :disabled="loading"
      :filters="filters"
      :has-search="hasSearch"
      :item-id="itemId"
      :loading="loading"
      :module="module"
      :name="name"
      :padding-top="paddingTop"
      :refresh="refresh"
      :resource="resource"
      :selected="selected"
      :title="title"
      @apply:filters="applyFilters"
      @reload:filters="reloadFilters"
    />

    <VDataTableServer
      v-model="selected"
      :headers="headers.filter((_header) => !_header.hidden)"
      :item-value="itemId"
      :items="items"
      :items-length="pagination.total"
      :items-per-page="pagination.per_page"
      :loading="loading"
      :page="page"
      :show-select="selectable || hasBulkActions"
      class="custom-table"
      hide-default-footer
      @click:row="onRowClick"
      @update:sort-by="handleSortByUpdate"
    >
      <template #no-data>
        <div class="d-flex flex-column align-center justify-center py-6">
          <div class="text-caption text-medium-emphasis">
            {{
              t(
                search
                  ? 'admin::admin.table.no_matching_records_found'
                  : 'admin::admin.table.no_data_available',
              )
            }}
          </div>
        </div>
      </template>

      <template
        v-for="(slotConfig, key) in cellComponents"
        :key="key"
        #[`item.${key}`]="{ item }"
      >
        <component
          :is="slotConfig.component"
          :item="item"
          v-bind="slotConfig.props || {}"
        />
      </template>

      <template
        v-for="slotConfig in defaultCellComponents"
        :key="slotConfig.key"
        #[`item.${slotConfig.key}`]="{ item }"
      >
        <component
          :is="slotConfig.component"
          :column="slotConfig.key"
          :item="item"
        />
      </template>

      <template
        v-if="actions?.length > 0"
        #item.action="{ item }"
      >
        <TableRowActions
          :actions="actions"
          :item="item"
          :item-id="itemId"
          :module="module"
          :name="name"
          :refresh="refresh"
          :resource="resource"
        />
      </template>
    </VDataTableServer>

    <TablePagination
      v-if="hasPagination"
      v-model:page="page"
      :disabled="loading"
      :pagination="pagination"
      :per-page="perPage"
      @update:per-page="perPage = $event"
    />
  </VCard>

  <TableExportDrawer
    v-if="hasQueuedExportHistory"
    v-model="drawerOpen"
    :downloading-by-id="downloadingById"
    :export-methods="exportMethods"
    :exports="exportItems"
    :filters="exportHistoryFilters"
    :loading="exportLoading"
    :pagination="exportPagination"
    :per-page="exportPerPage"
    @download="handleExportDownload"
    @update:filters="handleExportFilterUpdate"
    @update:page="handleExportPageUpdate"
    @update:per-page="handleExportPerPageUpdate"
  />
</template>

<style lang="scss" scoped>
::v-deep(.v-data-table thead) {
  background-color: rgb(var(--v-table-head-bg));
  color: rgb(var(--v-on-table-head-bg));
}

::v-deep(.v-data-table thead th) {
  border-top: thin solid rgba(var(--v-border-color), var(--v-border-opacity));
}

::v-deep(.v-data-table) {
  border-bottom: thin solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-right: 0;
}
</style>
