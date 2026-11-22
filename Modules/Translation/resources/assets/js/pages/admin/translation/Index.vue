<script lang="ts" setup>
import { getApiErrorMessage } from '@/modules/core/api/errors'

import type {
  PaginationMeta,
  TableAction,
  TableFilterSchema,
} from '@/modules/core/contracts/Table.ts'
import type {
  ExportMode,
  ImportFileType,
  ImportMode,
  ImportPreview,
  TranslationEditingState,
  TranslationLanguage,
  TranslationRecord,
  TranslationRow,
  TranslationStatus,
} from '@/modules/translation/contracts/Translation.ts'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import TableHeader from '@/modules/core/components/Table/TableHeader.vue'
import TableHeaderActions from '@/modules/core/components/Table/TableHeaderActions.vue'
import TablePagination from '@/modules/core/components/Table/TablePagination.vue'
import { useAppStore } from '@/modules/core/stores/appStore.ts'
import { useLanguage } from '@/modules/translation/composables/language.ts'
import ExportDialog from '@/modules/translation/pages/admin/translation/Dialogs/ExportDialog.vue'
import ImportDialog from '@/modules/translation/pages/admin/translation/Dialogs/ImportDialog.vue'
import TranslationTable from '@/modules/translation/pages/admin/translation/Partials/TranslationTable.vue'

const appStore = useAppStore()
const { update, loadData, exportLocale, exportAll, previewImportTranslation, importTranslation } =
  useLanguage()
const { t } = useI18n()
const toast = useToast()
const { can } = useAuth()

const records = ref<TranslationRecord[]>([])
const loading = ref(true)
const failed = ref(false)
const saving = ref(false)
const exporting = ref(false)
const search = ref('')
const debouncedSearch = ref('')
const localeFilter = ref<string | null>(null)
const statusFilter = ref<TranslationStatus>('all')
const selected = ref<string[]>([])
const page = ref(1)
const perPage = ref(10)
const pagination = ref<PaginationMeta>({
  current_page: 1,
  from: 0,
  last_page: 1,
  per_page: 10,
  to: 0,
  total: 0,
})
const editing = ref<TranslationEditingState | null>(null)

const exportDialog = ref(false)
const exportTargetLocale = ref(appStore.supportedLanguages[0]?.id || '')
const exportMode = ref<ExportMode>('values')
const exportScope = ref<'single' | 'all'>('single')

const importDialog = ref(false)
const importLoading = ref(false)
const importApplying = ref(false)
const importFileType = ref<ImportFileType>('json')
const importLocale = ref(appStore.supportedLanguages[0]?.id || '')
const importMode = ref<ImportMode>('fill_missing')
const importFile = ref<File | File[] | null>(null)
const importPreview = ref<ImportPreview | null>(null)
const importResult = ref<ImportPreview | null>(null)

const supportedLanguages = computed<TranslationLanguage[]>(() => appStore.supportedLanguages)
const languageItems = computed(() =>
  supportedLanguages.value.map((lang) => ({
    title: lang.name,
    value: lang.id,
  })),
)
const statusItems = computed(() => [
  { title: t('translation::translations.status_options.all'), value: 'all' },
  { title: t('translation::translations.status_options.missing'), value: 'missing' },
  { title: t('translation::translations.status_options.filled'), value: 'filled' },
])
const importModeItems = computed<{ title: string; value: ImportMode }[]>(() => [
  {
    title: t('translation::translations.import_dialog.fill_missing_only'),
    value: 'fill_missing',
  },
  { title: t('translation::translations.import_dialog.overwrite_existing'), value: 'overwrite' },
])
const exportScopeItems = computed<{ title: string; value: 'single' | 'all' }[]>(() => [
  { title: t('translation::translations.export_dialog.single_language_json'), value: 'single' },
  { title: t('translation::translations.export_dialog.all_languages_zip'), value: 'all' },
])
const exportModeItems = computed<{ title: string; value: ExportMode }[]>(() => [
  { title: t('translation::translations.export_dialog.translated_values'), value: 'values' },
  { title: t('translation::translations.export_dialog.empty_all_keys'), value: 'empty_all' },
  {
    title: t('translation::translations.export_dialog.empty_missing_keys'),
    value: 'empty_missing',
  },
])

const displayedLanguages = computed(() => {
  const selectedLanguage = supportedLanguages.value.find((lang) => lang.id === localeFilter.value)
  return selectedLanguage ? [selectedLanguage] : supportedLanguages.value
})

const rows = computed<TranslationRow[]>(() =>
  records.value.map((item) => ({
    key: item.key,
    ...item.translations,
  })),
)

const filters = computed<TableFilterSchema[]>(() => [
  {
    key: 'locale',
    label: t('translation::translations.language'),
    type: 'select',
    default: localeFilter.value ?? undefined,
    options: supportedLanguages.value.map((lang) => ({
      id: lang.id,
      name: lang.name,
    })),
    clearable: true,
  },
  {
    key: 'status',
    label: t('translation::translations.status'),
    type: 'select',
    default: statusFilter.value,
    options: statusItems.value.map((item) => ({ id: item.value, name: item.title })),
    clearable: false,
  },
])

const headerActions = computed<TableAction[]>(() => [
  {
    key: 'export',
    label: t('translation::translations.export'),
    icon: 'tabler-download',
    loading: exporting.value,
    permission: 'admin.translations.index',
    onClick: openExportDialog,
  },
  {
    key: 'import',
    label: t('translation::translations.import'),
    icon: 'tabler-upload',
    permission: 'admin.translations.edit',
    onClick: openImportDialog,
  },
])

const selectedImportFile = computed(() =>
  Array.isArray(importFile.value) ? importFile.value[0] : importFile.value,
)
const missingPlaceholders = computed(() => {
  if (!editing.value) return []
  return extractPlaceholders(editing.value.oldValue).filter(
    (token) => !extractPlaceholders(editing.value?.value || '').includes(token),
  )
})

async function loadTranslations() {
  loading.value = true
  failed.value = false

  const response = await loadData({
    page: page.value,
    per_page: perPage.value,
    search: debouncedSearch.value,
    locale: localeFilter.value,
    status: statusFilter.value,
  })

  if (response === false) {
    failed.value = true
    records.value = []
    loading.value = false
    return
  }

  records.value = response.data || []
  pagination.value = {
    ...(response.pagination || pagination.value),
    from: response.pagination?.from ?? 0,
    to: response.pagination?.to ?? 0,
  }
  page.value = pagination.value.current_page
  perPage.value = pagination.value.per_page
  loading.value = false
}

onMounted(loadTranslations)

debouncedWatch(
  search,
  (value) => {
    debouncedSearch.value = value.trim()
  },
  { debounce: 250, maxWait: 600 },
)

watch([debouncedSearch, localeFilter, statusFilter], () => {
  page.value = 1
  loadTranslations()
})

watch([page, perPage], loadTranslations)

function extractPlaceholders(value: string) {
  return Array.from(new Set(value.match(/:[A-Za-z_][A-Za-z0-9_]*/g) || []))
}

function switchEditMode(item: TranslationRow, lang: TranslationLanguage) {
  if (!can('admin.translations.edit') || saving.value) return

  editing.value = {
    key: item.key,
    lang: lang.id,
    oldValue: item[lang.id] ?? '',
    value: item[lang.id] ?? '',
  }
}

function cancelEdit() {
  editing.value = null
}

function updateEditingValue(value: string) {
  if (!editing.value) return
  editing.value.value = value
}

async function saveEdit() {
  if (editing.value === null || editing.value === undefined || saving.value) return

  const edit = editing.value
  if (edit.oldValue === edit.value) {
    editing.value = null
    return
  }

  if (missingPlaceholders.value.length > 0) {
    toast.warning(
      t('translation::translations.edit.missing_placeholders', {
        placeholders: missingPlaceholders.value.join(', '),
      }),
    )
  }

  saving.value = true
  const saved = await update(edit.key, edit.lang, edit.value)
  saving.value = false
  if (!saved) return

  const record = records.value.find((item) => item.key === edit.key)
  if (record) {
    record.translations[edit.lang] = edit.value
  }

  editing.value = null
}

async function runExportLocale(mode: ExportMode) {
  exporting.value = true
  try {
    await exportLocale(exportTargetLocale.value, mode)
  } catch (error: unknown) {
    toast.error(getApiErrorMessage(error) || t('translation::translations.export_dialog.failed'))
  } finally {
    exporting.value = false
  }
}

async function runExportAll(mode: ExportMode) {
  exporting.value = true
  try {
    await exportAll(mode)
  } catch (error: unknown) {
    toast.error(getApiErrorMessage(error) || t('translation::translations.export_dialog.failed'))
  } finally {
    exporting.value = false
  }
}

function resetImport() {
  importFileType.value = 'json'
  importLocale.value = supportedLanguages.value[0]?.id || ''
  importMode.value = 'fill_missing'
  importFile.value = null
  clearImportPreview()
}

function clearImportPreview() {
  importPreview.value = null
  importResult.value = null
}

function openExportDialog() {
  exportScope.value = 'single'
  exportMode.value = 'values'
  if (!exportTargetLocale.value) {
    exportTargetLocale.value = supportedLanguages.value[0]?.id || ''
  }
  exportDialog.value = true
}

async function applyExport() {
  if (exportScope.value === 'all') {
    await runExportAll(exportMode.value)
    exportDialog.value = false
    return
  }

  if (!exportTargetLocale.value) {
    toast.error(t('translation::translations.export_dialog.select_language_error'))
    return
  }

  await runExportLocale(exportMode.value)
  exportDialog.value = false
}

function buildImportFormData() {
  const file = selectedImportFile.value
  if (!file) return null

  const data = new FormData()
  if (importFileType.value !== 'zip') {
    data.append('_locale', importLocale.value)
  }
  data.append('file', file)
  data.append('mode', importMode.value)
  return data
}

function openImportDialog() {
  resetImport()
  importDialog.value = true
}

async function previewImport() {
  const data = buildImportFormData()
  if (!data) return

  importLoading.value = true
  importResult.value = null
  try {
    importPreview.value = (await previewImportTranslation(data)).data.body
  } catch (error: unknown) {
    toast.error(
      getApiErrorMessage(error) || t('translation::translations.import_dialog.preview_failed'),
    )
  } finally {
    importLoading.value = false
  }
}

async function applyImport() {
  const data = buildImportFormData()
  if (!data || !importPreview.value) return

  importApplying.value = true
  try {
    const response = await importTranslation(data)
    importResult.value = response.data.body
    importPreview.value = importResult.value
    page.value = 1
    await loadTranslations()
    importDialog.value = false
    toast.success(response.data.message)
    resetImport()
  } catch (error: unknown) {
    toast.error(getApiErrorMessage(error) || t('translation::translations.import_dialog.failed'))
  } finally {
    importApplying.value = false
  }
}

function updateImportFile(value: File | File[] | null) {
  importFile.value = value
  clearImportPreview()
}

function updateImportFileType(value: ImportFileType) {
  importFileType.value = value
  importFile.value = null
  clearImportPreview()
}

function updateImportLocale(value: string) {
  importLocale.value = value
  clearImportPreview()
}

function updateImportMode(value: ImportMode) {
  importMode.value = value
  clearImportPreview()
}

function backToImportSetup() {
  clearImportPreview()
}

function applyFilters(values: Record<string, any>) {
  localeFilter.value = values.locale || null
  statusFilter.value = values.status || 'all'
}

function refresh() {
  loadTranslations()
}

function deselectAll() {
  selected.value = []
}
</script>

<template>
  <TableHeaderActions
    :actions="headerActions"
    item-id="key"
    module="translation"
    name="translations"
    :refresh="refresh"
    resource="translations"
  />

  <VCard>
    <TableHeader
      v-model="search"
      :deselect-all="deselectAll"
      :disabled="loading"
      :filters="filters"
      has-search
      item-id="key"
      :loading="loading"
      module="translation"
      name="translations"
      :refresh="refresh"
      resource="translations"
      :selected="selected"
      @apply:filters="applyFilters"
    />
    <VDivider />
    <TranslationTable
      :can-edit="can('admin.translations.edit')"
      :displayed-languages="displayedLanguages"
      :editing="editing"
      :failed="failed"
      :loading="loading"
      :missing-placeholders="missingPlaceholders"
      :rows="rows"
      :saving="saving"
      @cancel-edit="cancelEdit"
      @retry="loadTranslations"
      @save-edit="saveEdit"
      @start-edit="switchEditMode"
      @update-editing-value="updateEditingValue"
    />
    <VDivider />
    <TablePagination
      v-model:page="page"
      v-model:per-page="perPage"
      :disabled="loading"
      :pagination="pagination"
    />
  </VCard>

  <ImportDialog
    v-model="importDialog"
    :applying="importApplying"
    :file="importFile"
    :import-file-type="importFileType"
    :import-locale="importLocale"
    :import-mode="importMode"
    :import-mode-items="importModeItems"
    :language-items="languageItems"
    :loading="importLoading"
    :preview="importPreview"
    :result="importResult"
    @apply="applyImport"
    @back-to-setup="backToImportSetup"
    @preview="previewImport"
    @update:file="updateImportFile"
    @update:import-file-type="updateImportFileType"
    @update:import-locale="updateImportLocale"
    @update:import-mode="updateImportMode"
  />

  <ExportDialog
    v-model="exportDialog"
    :export-mode="exportMode"
    :export-mode-items="exportModeItems"
    :export-scope="exportScope"
    :export-scope-items="exportScopeItems"
    :export-target-locale="exportTargetLocale"
    :exporting="exporting"
    :language-items="languageItems"
    @apply="applyExport"
    @update:export-mode="exportMode = $event"
    @update:export-scope="exportScope = $event"
    @update:export-target-locale="exportTargetLocale = $event"
  />
</template>
