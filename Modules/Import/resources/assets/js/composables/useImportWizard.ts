import type { Ref } from 'vue'
import type {
  DataImport,
  ImportField,
  ImportMode,
  ImportResourceKey,
  ImportRow,
} from '@/modules/import/contracts/import.ts'
import { isAxiosError } from 'axios'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { importApi } from '@/modules/import/api/import.api.ts'
import { useImportProcessing } from '@/modules/import/stores/importProcessing.ts'

interface ImportWizardContext {
  resource: ImportResourceKey
  context?: Record<string, number>
  initialImport?: DataImport
}

export function useImportWizard(
  props: Readonly<ImportWizardContext>,
  open: Ref<boolean>,
  onCompleted: () => void,
) {
  const { t } = useI18n()
  const toast = useToast()
  const processing = useImportProcessing()
  const dialogOwner = Symbol('import-dialog')
  const step = ref(1)
  const busy = ref(false)
  const templateDownloading = ref(false)
  const historyLoading = ref(false)
  const openingImport = ref<DataImport | null>(props.initialImport ?? null)
  const openingError = ref<string | null>(null)
  const file = ref<File | null>(null)
  const fields = ref<ImportField[]>([])
  const current = ref<DataImport | null>(null)
  const history = ref<DataImport[]>([])
  const mapping = ref<Record<string, number | null>>({})
  const mappingAttempted = ref(false)
  const rows = ref<ImportRow[]>([])
  const rowsLoading = ref(false)
  const rowsError = ref<string | null>(null)
  const pendingEdits = ref(0)
  const page = ref(1)
  const lastPage = ref(1)
  const mode = ref<ImportMode>('create_only')
  let timer: ReturnType<typeof setInterval> | undefined
  let rowsRequest = 0
  let editFailed = false
  let polling = false
  let disposed = false
  let pollingSession = 0
  let detailsRequest = 0
  let historyRequest = 0
  let selection = 0
  let editQueue = Promise.resolve()
  const pollError = ref<string | null>(null)
  const errorFileName = computed(() =>
    current.value
      ? `validation-errors-${current.value.original_name.replace(/\.[^.]+$/, '')}.xlsx`
      : '',
  )

  const missingRequiredFields = computed(() =>
    fields.value.filter((field) => field.required && mapping.value[field.key] == null),
  )
  const duplicateMapping = computed(() => {
    const columns = Object.values(mapping.value).filter((column) => column != null)
    return new Set(columns).size !== columns.length
  })
  const working = computed(() =>
    ['staging', 'validating', 'queued', 'processing'].includes(current.value?.status.id ?? ''),
  )
  const canCancel = computed(() =>
    ['uploaded', 'staged', 'validation_failed', 'ready', 'queued'].includes(
      current.value?.status.id ?? '',
    ),
  )
  const canValidate = computed(
    () =>
      ['staged', 'validation_failed', 'ready'].includes(current.value?.status.id ?? '') &&
      current.value!.total_rows > 0 &&
      pendingEdits.value === 0 &&
      !rowsLoading.value &&
      !rowsError.value &&
      !busy.value,
  )
  const canRetry = computed(() =>
    ['failed', 'partially_completed'].includes(current.value?.status.id ?? ''),
  )
  const canConfirm = computed(
    () =>
      step.value === 3 &&
      current.value?.status.id === 'ready' &&
      current.value.mode.id === mode.value &&
      current.value.invalid_rows === 0 &&
      rows.value.length > 0 &&
      !rowsError.value &&
      !rowsLoading.value &&
      pendingEdits.value === 0 &&
      !busy.value,
  )
  const modes = computed<Array<{ title: string; value: ImportMode }>>(() => [
    { title: t('import::imports.modes.create_only'), value: 'create_only' },
    { title: t('import::imports.modes.update_only'), value: 'update_only' },
    { title: t('import::imports.modes.upsert'), value: 'upsert' },
    { title: t('import::imports.modes.skip_duplicates'), value: 'skip_duplicates' },
    { title: t('import::imports.modes.replace_existing'), value: 'replace_existing' },
  ])

  function stopPolling() {
    pollingSession++
    if (timer) clearInterval(timer)
    timer = undefined
  }

  function errorMessage(error: unknown): string {
    if (isAxiosError<{ message?: string }>(error)) {
      return error.response?.data?.message ?? t('import::imports.request_failed')
    }
    return t('import::imports.request_failed')
  }

  function newImport() {
    selection++
    rowsRequest++
    pollError.value = null
    openingImport.value = null
    openingError.value = null
    current.value = null
    file.value = null
    mapping.value = {}
    mappingAttempted.value = false
    rows.value = []
    pendingEdits.value = 0
    editFailed = false
    rowsError.value = null
    rowsLoading.value = false
    page.value = 1
    lastPage.value = 1
    step.value = 1
    mode.value = 'create_only'
  }

  function rememberImport(item: DataImport) {
    history.value = [item, ...history.value.filter((entry) => entry.id !== item.id)].slice(0, 15)
  }

  function selectImport(item: DataImport, preserveMode = false) {
    processing.track(item)
    rememberImport(item)
    selection++
    rowsRequest++
    pollError.value = null
    current.value = item
    mapping.value = { ...item.mapping }
    mappingAttempted.value = false
    if (!preserveMode) mode.value = item.mode.id
    rows.value = []
    pendingEdits.value = 0
    editFailed = false
    rowsError.value = null
    rowsLoading.value = false
    page.value = 1
    lastPage.value = 1
    step.value = item.status.id === 'uploaded' ? 2 : resultStage(item) ? 4 : 3
    if (item.status.id !== 'uploaded') void loadRows()
  }

  function resultStage(item: DataImport) {
    return [
      'queued',
      'processing',
      'completed',
      'partially_completed',
      'failed',
      'cancelled',
    ].includes(item.status.id)
  }

  async function receiveImport(item: DataImport) {
    processing.track(item)
    if (disposed || !open.value) return
    rememberImport(item)
    const previous = current.value?.status.id
    current.value = item
    pollError.value = null
    step.value = item.status.id === 'uploaded' ? 2 : resultStage(item) ? 4 : 3
    if (!working.value) await loadRows()
    if (
      previous !== item.status.id &&
      ['completed', 'partially_completed'].includes(item.status.id)
    ) {
      processing.recordCompletion(item)
      onCompleted()
    }
  }

  async function resumeImport(item: DataImport) {
    if (busy.value || pendingEdits.value) return
    busy.value = true
    openingImport.value = item
    openingError.value = null
    const details = ++detailsRequest
    const request = selection
    const session = pollingSession
    const isActive = () =>
      details === detailsRequest &&
      request === selection &&
      session === pollingSession &&
      open.value &&
      !disposed
    try {
      const response = await importApi.show(item.id)
      if (!isActive()) return

      selectImport(response.data.body)
      openingImport.value = null
    } catch (error) {
      if (isActive()) openingError.value = errorMessage(error)
    } finally {
      if (details === detailsRequest) busy.value = false
    }
  }

  async function refreshHistory() {
    const resource = props.resource
    const menuId = props.context?.menu_id
    const request = ++historyRequest
    historyLoading.value = true
    try {
      const response = await importApi.history(resource, props.context ?? {})
      if (!open.value || props.resource !== resource || props.context?.menu_id !== menuId) return

      history.value = response.data.body.data
    } finally {
      if (request === historyRequest) historyLoading.value = false
    }
  }

  async function loadRows() {
    const item = current.value
    if (!item || item.total_rows === 0) {
      rows.value = []
      rowsLoading.value = false
      return
    }

    const requestedPage = page.value
    const request = ++rowsRequest
    rowsLoading.value = true
    rowsError.value = null
    try {
      const response = await importApi.rows(item.id, requestedPage)
      if (current.value?.id !== item.id || page.value !== requestedPage || request !== rowsRequest)
        return

      rows.value = response.data.body.data
      lastPage.value = response.data.body.pagination.last_page
      if (rows.value.length === 0 && item.total_rows > 0) {
        rowsError.value = t('import::imports.preview_unavailable')
      }
    } catch (error) {
      if (current.value?.id !== item.id || page.value !== requestedPage || request !== rowsRequest)
        return

      rows.value = []
      rowsError.value = errorMessage(error)
    } finally {
      if (
        current.value?.id === item.id &&
        page.value === requestedPage &&
        request === rowsRequest
      ) {
        rowsLoading.value = false
      }
    }
  }

  async function changePage(next: number) {
    if (pendingEdits.value || busy.value || rowsLoading.value || next < 1 || next > lastPage.value)
      return

    page.value = next
    await loadRows()
  }

  function changeMapping() {
    if (
      !current.value ||
      busy.value ||
      pendingEdits.value ||
      working.value ||
      !['staged', 'ready', 'validation_failed'].includes(current.value.status.id)
    )
      return

    step.value = 2
  }

  async function download(kind: 'template' | 'errors' | 'original') {
    const item = current.value
    if (kind !== 'template' && !item) return
    if (kind === 'template' && templateDownloading.value) return

    if (kind === 'template') templateDownloading.value = true
    try {
      let blob: Blob
      let name: string
      if (kind === 'template') {
        blob = (await importApi.template(props.resource)).data
        name = `${props.resource}-template.xlsx`
      } else if (kind === 'errors' && item) {
        name = errorFileName.value
        blob = (await importApi.errors(item.id)).data
      } else if (item) {
        blob = (await importApi.download(item.id)).data
        name = item.original_name
      } else {
        return
      }

      const url = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = name
      link.click()
      setTimeout(() => {
        if (typeof URL.revokeObjectURL === 'function') {
          URL.revokeObjectURL(url)
        }
      }, 1000)
    } catch (error) {
      toast.error(errorMessage(error))
    } finally {
      if (kind === 'template') templateDownloading.value = false
    }
  }

  async function upload() {
    if (!file.value || busy.value) return
    if (!/\.(csv|xlsx|xls)$/i.test(file.value.name) || file.value.size > 50 * 1024 * 1024) {
      toast.error(t('import::imports.invalid_file'))
      file.value = null
      return
    }

    const resource = props.resource
    const menuId = props.context?.menu_id
    busy.value = true
    try {
      const response = await importApi.upload(resource, file.value, props.context ?? {})
      if (!open.value || props.resource !== resource || props.context?.menu_id !== menuId) return

      if (fields.value.length === 0) {
        fields.value = (await importApi.fields(resource, props.context ?? {})).data.body
      }
      if (!open.value || props.resource !== resource || props.context?.menu_id !== menuId) return

      selectImport(response.data.body, true)
    } catch (error) {
      toast.error(errorMessage(error))
    } finally {
      busy.value = false
    }
  }

  async function saveMapping() {
    const item = current.value
    if (!item || busy.value || pendingEdits.value) return
    mappingAttempted.value = true
    if (missingRequiredFields.value.length || duplicateMapping.value) return

    const selected: Record<string, number> = {}
    for (const [field, column] of Object.entries(mapping.value)) {
      if (typeof column === 'number') selected[field] = column
    }

    busy.value = true
    try {
      const response = await importApi.map(item.id, selected)
      if (current.value?.id !== item.id) return

      rowsRequest++
      rows.value = []
      rowsError.value = null
      rowsLoading.value = false
      page.value = 1
      await receiveImport(response.data.body)
    } catch (error) {
      step.value = 2
      toast.error(errorMessage(error))
    } finally {
      busy.value = false
    }
  }

  async function saveRow(row: ImportRow, field: string) {
    const item = current.value
    if (
      !item ||
      busy.value ||
      working.value ||
      !['staged', 'ready', 'validation_failed'].includes(item.status.id)
    )
      return

    const request = selection
    const value = row.data[field]
    const previousEdit = editQueue
    let releaseEdit!: () => void
    editQueue = new Promise<void>((resolve) => {
      releaseEdit = resolve
    })
    pendingEdits.value++
    await previousEdit
    try {
      if (request !== selection) return
      const response = await importApi.editRow(item.id, row.id, {
        [field]: value == null ? null : String(value),
      })
      if (current.value?.id !== item.id || request !== selection) return

      row.errors = response.data.body.errors
      row.operation = response.data.body.operation
      row.status = response.data.body.status
      current.value.status = { id: 'staged', name: t('import::imports.statuses.staged') }
      current.value.invalid_rows = 0
      current.value.valid_rows = 0
      current.value.new_rows = 0
      current.value.update_rows = 0
      current.value.skipped_rows = 0
      current.value.has_error_file = false
      step.value = 3
    } catch (error) {
      if (current.value?.id === item.id && request === selection) {
        editFailed = true
        toast.error(errorMessage(error))
      }
    } finally {
      releaseEdit()
      if (current.value?.id === item.id && request === selection) {
        pendingEdits.value--
        if (pendingEdits.value === 0 && editFailed) {
          editFailed = false
          await loadRows()
        }
      }
    }
  }

  async function validateFile() {
    const item = current.value
    if (!item || !canValidate.value) return

    busy.value = true
    try {
      const response = await importApi.validate(item.id, mode.value)
      if (current.value?.id !== item.id) return

      await receiveImport(response.data.body)
    } catch (error) {
      toast.error(errorMessage(error))
    } finally {
      busy.value = false
    }
  }

  async function queueImport() {
    const item = current.value
    if (!item || !canConfirm.value) return

    busy.value = true
    try {
      const response = await importApi.queue(item.id)
      if (current.value?.id !== item.id) return

      await receiveImport(response.data.body)
    } catch (error) {
      toast.error(errorMessage(error))
    } finally {
      busy.value = false
    }
  }

  async function retry() {
    const item = current.value
    if (!item || !canRetry.value || busy.value) return

    busy.value = true
    try {
      const response = await importApi.retry(item.id)
      if (current.value?.id !== item.id) return

      await receiveImport(response.data.body)
    } catch (error) {
      toast.error(errorMessage(error))
    } finally {
      busy.value = false
    }
  }

  async function cancelImport() {
    const item = current.value
    if (!item || !canCancel.value || busy.value || pendingEdits.value) return

    busy.value = true
    try {
      const response = await importApi.cancel(item.id)
      if (current.value?.id !== item.id) return

      await receiveImport(response.data.body)
    } catch (error) {
      toast.error(errorMessage(error))
    } finally {
      busy.value = false
    }
  }

  async function poll() {
    const item = current.value
    if (!item || !working.value || polling || busy.value || !open.value || disposed) return

    if (processing.tasks.has(item.id)) {
      const latest = processing.records.get(item.id)
      if (latest && latest !== item) await receiveImport(latest)
      else processing.track(item)
      return
    }

    polling = true
    const session = pollingSession
    const request = selection
    try {
      const response = await importApi.show(item.id)
      if (
        session !== pollingSession ||
        disposed ||
        !open.value ||
        current.value?.id !== item.id ||
        request !== selection ||
        busy.value ||
        current.value.status.id !== item.status.id
      )
        return

      await receiveImport(response.data.body)
      if (session !== pollingSession || request !== selection || disposed || !open.value) return
      if (item.status.id !== response.data.body.status.id) await refreshHistory()
    } catch {
      if (session === pollingSession && request === selection && !disposed && open.value) {
        pollError.value = t('import::imports.connection_lost')
      }
    } finally {
      polling = false
    }
  }

  watch(
    () => (current.value && working.value ? processing.records.get(current.value.id) : undefined),
    (item) => {
      if (item && open.value && !disposed && !busy.value) void receiveImport(item)
    },
  )
  watch(
    () => current.value && processing.errors.get(current.value.id),
    (failed) => {
      if (open.value && !disposed) {
        pollError.value = failed ? t('import::imports.connection_lost') : null
      }
    },
  )

  watch(
    () => [open.value, openingImport.value?.id ?? current.value?.id] as const,
    ([visible, id]) => processing.setDialog(dialogOwner, visible && id != null ? id : null),
    { immediate: true, flush: 'sync' },
  )

  watch(
    () => [open.value, props.resource, props.context?.menu_id, props.initialImport?.id] as const,
    async ([visible], _previous, onCleanup) => {
      stopPolling()
      if (!visible) {
        if (openingImport.value) {
          detailsRequest++
          busy.value = false
        }
        return
      }

      const requestedImport = props.initialImport ?? openingImport.value
      if (requestedImport) {
        openingImport.value = requestedImport
        openingError.value = null
      }

      let stale = false
      const request = ++historyRequest
      historyLoading.value = true
      onCleanup(() => {
        stale = true
      })

      try {
        const [fieldResponse, historyResponse] = await Promise.all([
          importApi.fields(props.resource, props.context ?? {}),
          importApi.history(props.resource, props.context ?? {}),
        ])
        if (stale) return

        fields.value = fieldResponse.data.body
        if (request === historyRequest) history.value = historyResponse.data.body.data
      } catch (error) {
        if (!stale && request === historyRequest) toast.error(errorMessage(error))
      } finally {
        if (!stale && request === historyRequest) historyLoading.value = false
      }

      if (!stale && requestedImport) await resumeImport(requestedImport)
      if (!stale) {
        void poll()
        timer = setInterval(() => void poll(), 3000)
      }
    },
    { immediate: true, flush: 'sync' },
  )
  watch(() => [props.resource, props.context?.menu_id], newImport)
  onBeforeUnmount(() => {
    disposed = true
    processing.setDialog(dialogOwner, null)
    stopPolling()
  })

  return {
    t,
    step,
    busy,
    templateDownloading,
    historyLoading,
    openingImport,
    openingError,
    file,
    fields,
    current,
    history,
    mapping,
    mappingAttempted,
    rows,
    rowsLoading,
    rowsError,
    pendingEdits,
    page,
    lastPage,
    mode,
    missingRequiredFields,
    duplicateMapping,
    pollError,
    working,
    canCancel,
    canValidate,
    canRetry,
    canConfirm,
    modes,
    download,
    errorFileName,
    upload,
    selectImport,
    resumeImport,
    saveMapping,
    changePage,
    changeMapping,
    loadRows,
    saveRow,
    validateFile,
    queueImport,
    retry,
    cancelImport,
    newImport,
  }
}
