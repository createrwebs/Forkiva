import type { DataImport } from '@/modules/import/contracts/import.ts'
import { isAxiosError } from 'axios'
import { defineStore } from 'pinia'
import { computed, onScopeDispose, shallowReactive, watch } from 'vue'
import { useToast } from 'vue-toastification'
import i18n from '@/app/plugins/i18n.ts'
import { useAuthStore } from '@/modules/auth/stores/authStore.ts'
import { importApi } from '@/modules/import/api/import.api.ts'

interface PendingImport {
  item: DataImport
  failures: number
  nextAt: number
}

// Pinia owns this loop, so removing a dialog or changing routes cannot interrupt an import.
export const useImportProcessing = defineStore('import-processing', () => {
  const auth = useAuthStore()
  const toast = useToast()
  const records = shallowReactive(new Map<number, DataImport>())
  const tasks = shallowReactive(new Map<number, DataImport>())
  const completions = shallowReactive(new Map<number, DataImport>())
  const dialogs = shallowReactive(new Map<symbol, number>())
  const stopped = shallowReactive(new Set<number>())
  const backgroundTasks = computed(() =>
    [...tasks.values()].filter((item) => ![...dialogs.values()].includes(item.id)),
  )
  const errors = shallowReactive(new Map<number, boolean>())
  const pending = new Map<number, PendingImport>()
  let timer: ReturnType<typeof setTimeout> | undefined
  let running = false
  let session = 0

  function reset() {
    session++
    if (timer) clearTimeout(timer)
    timer = undefined
    pending.clear()
    records.clear()
    tasks.clear()
    completions.clear()
    dialogs.clear()
    stopped.clear()
    errors.clear()
  }

  function schedule() {
    if (timer || running || pending.size === 0) return
    const nextAt = Math.min(...Array.from(pending.values(), (task) => task.nextAt))
    // Serialize all imports in this tab, staying below the shared batch rate limit.
    timer = setTimeout(() => void advance(), Math.max(1000, nextAt - Date.now()))
  }

  function isWorking(item: DataImport) {
    return ['staging', 'validating', 'queued', 'processing'].includes(item.status.id)
  }

  function setDialog(owner: symbol, id: number | null) {
    const previousId = dialogs.get(owner)
    if (id === null) dialogs.delete(owner)
    else dialogs.set(owner, id)
    if (
      previousId !== undefined &&
      previousId !== id &&
      ![...dialogs.values()].includes(previousId)
    ) {
      const previousTask = tasks.get(previousId)
      if (previousTask?.status.id === 'completed') dismiss(previousId)
    }
  }

  function dismiss(id: number) {
    const item = tasks.get(id)
    if (!item || (isWorking(item) && !stopped.has(id))) return
    tasks.delete(id)
    records.delete(id)
    errors.delete(id)
    stopped.delete(id)
  }

  function recordCompletion(item: DataImport) {
    if (
      ['completed', 'partially_completed'].includes(item.status.id) &&
      item.imported_rows > 0 &&
      item.imported_rows > (completions.get(item.id)?.imported_rows ?? 0)
    ) {
      completions.set(item.id, item)
    }
  }

  function track(item: DataImport) {
    if (!auth.getToken) return
    if (!isWorking(item)) {
      if (tasks.has(item.id)) tasks.set(item.id, item)
      pending.delete(item.id)
      return
    }
    if (pending.has(item.id) || stopped.has(item.id)) return
    records.delete(item.id)
    tasks.set(item.id, item)
    pending.set(item.id, { item, failures: 0, nextAt: Date.now() })
    errors.delete(item.id)
    schedule()
  }

  function notify(item: DataImport) {
    const message = `${item.original_name}: ${item.status.name}`
    if (item.status.id === 'completed') toast.success(message)
    else if (item.status.id === 'failed') toast.error(message)
    else if (['validation_failed', 'partially_completed'].includes(item.status.id))
      toast.warning(message)
    else if (item.status.id === 'ready') toast.info(message)
  }

  async function advance() {
    timer = undefined
    if (running) return
    const task = [...pending.values()].sort((a, b) => a.nextAt - b.nextAt)[0]
    if (!task) return
    const id = task.item.id
    const requestSession = session
    running = true
    try {
      // A lost response can hide a committed batch or failure; read its state before retrying.
      const response =
        task.failures > 0 || !task.item.requires_processing
          ? await importApi.show(id)
          : await importApi.advance(id)
      if (requestSession !== session) return
      const item = response.data.body
      errors.delete(id)
      records.set(id, item)
      tasks.set(id, item)
      recordCompletion(item)
      if (isWorking(item)) {
        pending.set(id, {
          item,
          failures: 0,
          nextAt: Date.now() + (item.requires_processing ? 1000 : 3000),
        })
      } else {
        pending.delete(id)
        notify(item)
      }
    } catch (error) {
      if (requestSession !== session) return
      errors.set(id, true)
      const status = isAxiosError(error) ? error.response?.status : undefined
      if (status && [401, 403, 404].includes(status)) {
        pending.delete(id)
        stopped.add(id)
        toast.error(
          `${task.item.original_name}: ${i18n.global.t('import::imports.request_failed')}`,
        )
      } else {
        task.failures++
        task.nextAt = Date.now() + Math.min(30000, 5000 * task.failures)
      }
    } finally {
      running = false
      schedule()
    }
  }

  watch(() => auth.getToken, reset, { flush: 'sync' })
  onScopeDispose(reset)

  return {
    records,
    tasks,
    completions,
    backgroundTasks,
    errors,
    stopped,
    track,
    recordCompletion,
    setDialog,
    dismiss,
  }
})
