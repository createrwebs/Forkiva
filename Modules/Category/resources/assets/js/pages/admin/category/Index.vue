<script lang="ts" setup>
import type { TableExportListFilters } from '@/modules/export/contracts/export.ts'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import TableExportDrawer from '@/modules/export/components/TableExportDrawer.vue'
import ImportWizard from '@/modules/import/Partials/ImportWizard.vue'
import { useImportRefresh } from '@/modules/import/composables/useImportRefresh.ts'
import {
  buildTableExportContextKey,
  useTableExport,
} from '@/modules/export/composables/tableExport.ts'
import { useCategory } from '@/modules/category/composables/category.ts'
import From from './Partials/Form.vue'
import TreeView from './Partials/TreeView.vue'

const { getIndexData, show } = useCategory()
const { hasPermission } = useAuth()
const route = useRoute()
const toast = useToast()
const { t } = useI18n()

const loading = ref(true)
const refreshing = ref(false)
const isNotFound = ref(false)
const data = ref<Record<string, any> | null>(null)
const activeCategory = ref<Record<string, any> | null>(null)
const parentId = ref(null)
const action = ref<'update' | 'create'>('create')
const treeViewKey = ref(`tree-view`)
const formKey = ref(`form`)
const exportMethods = ref<Record<string, any>[]>([])
const importOpen = ref(false)
const exportApiUri = ref<string | null>(null)
const exportPermission = ref<string | null>(null)
const exportResource = ref<string | null>(null)
const {
  clear,
  downloadingById,
  drawerOpen,
  exports: exportItems,
  filters: exportHistoryFilters,
  latestExport,
  loading: exportLoading,
  openDrawer,
  page: exportPage,
  pagination: exportPagination,
  perPage: exportPerPage,
  queueing: exportQueueing,
  refreshExports,
  setContext,
  shouldPoll,
  queueExport,
  triggerDownload,
} = useTableExport()

const exportContext = computed(() => ({
  route_name: String(route.name || ''),
  route_params: { ...(route.params as Record<string, any>) },
  menu_id: data.value?.menu?.id ?? null,
}))

const exportContextKey = computed(() =>
  buildTableExportContextKey(String(route.name || ''), route.params as Record<string, any>, {
    menu_id: data.value?.menu?.id ?? null,
  }),
)

const exportFilters = computed(() => ({
  menu_id: data.value?.menu?.id ?? null,
}))

const hasCategoryExports = computed(() =>
  Boolean(exportApiUri.value && exportResource.value && latestExport.value),
)
const canExportCategories = computed(() =>
  exportPermission.value ? hasPermission(exportPermission.value) : false,
)
const canImportCategories = computed(() => hasPermission('admin.categories.import'))

useImportRefresh(
  'categories',
  () => loadData(true),
  () => ({ menu_id: Number(data.value?.menu?.id) || null }),
)

onMounted(() => {
  loadData()
})

async function loadData(isRefreshing = false) {
  if (isRefreshing) {
    refreshing.value = true
  } else {
    loading.value = true
  }
  const response = await getIndexData(
    data.value?.menu.id || (route.params as Record<string, any>).id,
  )
  if (response.status === 200) {
    data.value = response.data
    exportMethods.value = response.data.export_methods || []
    exportApiUri.value = response.data.export_api_uri || null
    exportPermission.value = response.data.export_permission || null
    exportResource.value = response.data.export_resource || null
    reloadTreeView()
    reloadForm()
  } else if (!isRefreshing && response.status === 404) {
    isNotFound.value = true
  }
  loading.value = false
  refreshing.value = false
}

async function onClickCategory(category: any) {
  if (Number(category.id) !== Number(activeCategory.value?.id)) {
    refreshing.value = true
    try {
      const response = await show(category.id, true)
      activeCategory.value = response.data.body
      action.value = 'update'
    } catch {
      toast.error(t('core::errors.an_unexpected_error_occurred'))
    }
    reloadForm()
    refreshing.value = false
  }
}

function addSubCategory() {
  if (activeCategory.value) {
    parentId.value = activeCategory.value?.id
    action.value = 'create'
    reloadForm()
  }
}
function addRootCategory() {
  activeCategory.value = null
  parentId.value = null
  action.value = 'create'
  reloadForm()
}

function onSuccess() {
  addRootCategory()
  loadData(true)
}

function reloadTreeView() {
  treeViewKey.value = `tree-view-${Date.now()}`
}
function reloadForm() {
  formKey.value = `form-${Date.now()}`
}

async function queueCategoryExport(format: string) {
  if (!exportApiUri.value) {
    return
  }

  try {
    await queueExport(exportApiUri.value, {
      format,
      filters: exportFilters.value,
      sorts: [],
      context: exportContext.value,
      context_key: exportContextKey.value,
    })
    toast.success(t('export::exports.messages.queued'))
    if (exportResource.value) {
      await refreshExports(exportResource.value, exportContextKey.value, {
        page: 1,
        perPage: exportPerPage.value,
        filters: exportHistoryFilters.value,
        force: true,
      })
    }
  } catch {
    toast.error(t('export::exports.messages.failed_generic'))
  }
}

async function updateExportHistoryFilters(value: TableExportListFilters) {
  exportHistoryFilters.value = value

  if (!exportResource.value) {
    return
  }

  await refreshExports(exportResource.value, exportContextKey.value, {
    page: 1,
    perPage: exportPerPage.value,
    filters: value,
    force: true,
  })
}

function updateExportPage(value: number) {
  exportPage.value = value
}

function updateExportPerPage(value: number) {
  exportPerPage.value = value
}

watch(
  [exportResource, exportContextKey],
  async ([resource, contextKey]) => {
    if (resource && contextKey) {
      setContext(resource, contextKey)
      try {
        await refreshExports(resource, contextKey, {
          page: exportPage.value,
          perPage: exportPerPage.value,
          filters: exportHistoryFilters.value,
          force: true,
        })
      } catch {}
    } else {
      clear()
    }
  },
  { immediate: true },
)

const { pause: pauseExportPolling, resume: resumeExportPolling } = useIntervalFn(
  async () => {
    if (exportResource.value) {
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
    if (value && exportResource.value) {
      resumeExportPolling()
    } else {
      pauseExportPolling()
    }
  },
  { immediate: true },
)

watch(exportPage, async (value) => {
  if (!exportResource.value) {
    return
  }

  await refreshExports(exportResource.value, exportContextKey.value, {
    page: value,
    perPage: exportPerPage.value,
    filters: exportHistoryFilters.value,
    force: true,
  })
})

watch(exportPerPage, async (value) => {
  if (!exportResource.value) {
    return
  }

  await refreshExports(exportResource.value, exportContextKey.value, {
    page: 1,
    perPage: value,
    filters: exportHistoryFilters.value,
    force: true,
  })
})

onBeforeUnmount(() => {
  pauseExportPolling()
})
</script>

<template>
  <PageStateWrapper
    :loading="loading"
    :not-found="isNotFound"
  >
    <VRow v-if="data !== null && data !== undefined">
      <VCol cols="12">
        <div class="d-flex align-center justify-end flex-wrap ga-3 mb-2">
          <VBtn
            v-if="canImportCategories"
            prepend-icon="tabler-database-import"
            @click="importOpen = true"
          >
            {{ t('import::imports.title') }}
          </VBtn>
          <VMenu v-if="canExportCategories && exportMethods.length > 0">
            <template #activator="{ props: menuProps }">
              <VBtn
                :loading="exportQueueing"
                prepend-icon="tabler-database-export"
                v-bind="menuProps"
              >
                {{ t('admin::admin.buttons.export') }}
              </VBtn>
            </template>

            <VList
              density="comfortable"
              min-width="180"
            >
              <VListItem
                v-for="method in exportMethods"
                :key="method.id"
                @click="queueCategoryExport(method.id)"
              >
                <VListItemTitle>{{ method.name }}</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>

          <VBtn
            v-if="canExportCategories && hasCategoryExports"
            :loading="exportLoading && drawerOpen"
            color="secondary"
            prepend-icon="tabler-history"
            @click="openDrawer(exportResource || '', exportContextKey)"
          >
            {{ t('export::exports.histories') }}
          </VBtn>
        </div>
      </VCol>

      <VCol
        cols="12"
        md="4"
      >
        <TreeView
          :key="treeViewKey"
          :active-category="activeCategory"
          :categories="data.categories"
          :menu-id="data.menu.id"
          :refreshing="refreshing"
          @add-root-category="addRootCategory"
          @add-sub-category="addSubCategory"
          @click-category="onClickCategory"
        />
      </VCol>
      <VCol
        cols="12"
        md="8"
      >
        <From
          :key="formKey"
          :action="action"
          :item="parentId ? null : activeCategory"
          :menu-id="data.menu.id"
          :parent-id="parentId"
          @on-success="onSuccess"
        />
      </VCol>
    </VRow>

    <TableExportDrawer
      v-model="drawerOpen"
      :downloading-by-id="downloadingById"
      :export-methods="exportMethods"
      :exports="exportItems"
      :filters="exportHistoryFilters"
      :loading="exportLoading"
      :pagination="exportPagination"
      :per-page="exportPerPage"
      @download="triggerDownload"
      @update:filters="updateExportHistoryFilters"
      @update:page="updateExportPage"
      @update:per-page="updateExportPerPage"
    />
    <ImportWizard
      v-if="canImportCategories && data?.menu?.id && importOpen"
      v-model="importOpen"
      :context="{ menu_id: data.menu.id }"
      resource="categories"
    />
  </PageStateWrapper>
</template>
