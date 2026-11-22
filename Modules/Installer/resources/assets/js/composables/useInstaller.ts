import { computed, nextTick, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import { InstallerError, installerRequest } from '../api/installer'
import type { Configuration, FieldErrors, InstallationState, Step } from '../contracts/installer'

export function useInstaller(initialState?: InstallationState) {
  const lifetime = new AbortController()
  const request = (name: string, data?: object) => installerRequest(name, data, lifetime.signal)
  const state = ref<InstallationState>(
    initialState ?? {
      checks_passed: false,
      database: false,
      tasks: [],
      admin: false,
      finished: false,
    },
  )
  const step = ref<Step>('welcome')
  const busy = ref(false)
  const loadingMessage = ref('')
  const loaded = ref(false)
  const error = ref('')
  const fields = ref<FieldErrors>({})
  const database = reactive({
    db_connection: 'mysql',
    db_host: '127.0.0.1',
    db_port: '3306',
    db_name: '',
    db_user: '',
    db_pass: '',
    with_demo: false,
  })
  watch(
    () => database.db_connection,
    (driver, previous) => {
      if (database.db_port === (previous === 'pgsql' ? '5432' : '3306')) {
        database.db_port = driver === 'pgsql' ? '5432' : '3306'
      }
    },
  )
  const admin = reactive({
    name: '',
    username: '',
    email: '',
    password: '',
    password_confirmation: '',
  })
  const configuration = reactive<Configuration>({
    app_name: 'Forkiva',
    app_url: window.location.origin,
    supported_countries: ['TH'],
    default_country: 'TH',
    supported_locales: ['th', 'en'],
    default_locale: 'th',
    default_timezone: 'Asia/Bangkok',
    supported_currencies: ['THB'],
    default_currency: 'THB',
    passkeys_enabled: true,
    passkeys_relying_party_id: window.location.hostname,
    passkeys_allowed_origins: [window.location.origin],
    cache_enabled: true,
    cache_store: 'file',
  })
  const appUrl = computed(() => configuration.app_url)
  const completeTasks = computed(
    () => state.value.tasks.filter((task) => task.status === 'complete').length,
  )
  const prepared = computed(
    () => state.value.tasks.length > 0 && completeTasks.value === state.value.tasks.length,
  )
  const progress = computed(() =>
    state.value.tasks.length
      ? Math.round((completeTasks.value / state.value.tasks.length) * 100)
      : 0,
  )
  const currentTask = computed(() => state.value.tasks.find((task) => task.status !== 'complete'))

  async function action(message: string, callback: () => Promise<void>) {
    if (busy.value) return
    busy.value = true
    loadingMessage.value = message
    error.value = ''
    fields.value = {}
    try {
      await callback()
    } catch (cause) {
      if (lifetime.signal.aborted) return
      error.value =
        cause instanceof InstallerError
          ? cause.message
          : 'Connection interrupted. Refresh the status to see what completed before retrying.'
      if (cause instanceof InstallerError) fields.value = cause.fields
    } finally {
      busy.value = false
      loadingMessage.value = ''
    }
  }

  function resumeStep() {
    step.value =
      state.value.finished || state.value.admin
        ? 'launch'
        : prepared.value
          ? 'admin'
          : state.value.database || state.value.configuration
            ? 'database'
            : state.value.checks_passed
              ? 'configuration'
              : state.value.checks
                ? 'checks'
                : 'welcome'
  }

  if (initialState?.configuration) Object.assign(configuration, initialState.configuration)
  resumeStep()

  function waitForStatus() {
    return new Promise<void>((resolve, reject) => {
      const abort = () => {
        window.clearTimeout(timer)
        reject(new DOMException('Installer closed', 'AbortError'))
      }
      const timer = window.setTimeout(() => {
        lifetime.signal.removeEventListener('abort', abort)
        resolve()
      }, 2000)
      lifetime.signal.addEventListener('abort', abort, { once: true })
      if (lifetime.signal.aborted) abort()
    })
  }

  function canReconcile(cause: unknown) {
    return (
      cause instanceof TypeError ||
      (cause instanceof InstallerError &&
        [0, 409, 500, 502, 503, 504].includes(cause.status) &&
        !Object.keys(cause.fields).length)
    )
  }

  async function readUntilIdle() {
    let activeTaskId = state.value.processing ? currentTask.value?.id : undefined
    let failures = 0
    while (!lifetime.signal.aborted) {
      try {
        state.value = await request('status')
        failures = 0
        if (!state.value.processing) return activeTaskId
        activeTaskId ??= currentTask.value?.id
      } catch (cause) {
        if (!canReconcile(cause) || ++failures >= 3) throw cause
      }
      await waitForStatus()
    }
    throw new DOMException('Installer closed', 'AbortError')
  }

  async function refresh() {
    await action('', async () => {
      const activeTaskId = await readUntilIdle()
      if (state.value.configuration) Object.assign(configuration, state.value.configuration)
      loaded.value = true
      resumeStep()
      if (
        state.value.database &&
        !state.value.admin &&
        !prepared.value &&
        !state.value.error &&
        activeTaskId &&
        state.value.tasks.find((task) => task.id === activeTaskId)?.status === 'complete'
      ) {
        // Continue the run observed on reload without releasing the busy state between tasks.
        loadingMessage.value = 'Preparing your database…'
        await runTasks()
      } else if (state.value.error) {
        error.value = state.value.error
      }
    })
  }

  async function checks() {
    step.value = 'checks'
    await action('Checking your server…', async () => {
      state.value = await request('checks', {})
    })
  }

  async function saveConfiguration() {
    await action('Saving configuration…', async () => {
      state.value = await request('configuration', configuration)
      step.value = 'database'
    })
  }

  async function prepare() {
    await action('Preparing your database…', async () => {
      if (!state.value.database) {
        state.value = await request('database', {
          ...database,
          db_port: Number(database.db_port),
        })
        database.db_pass = ''
      }
      await runTasks()
    })
  }

  async function runTasks() {
    while (!prepared.value && !lifetime.signal.aborted) {
      const taskId = currentTask.value?.id
      try {
        state.value = await request('process', {})
        if (state.value.processing) {
          await readUntilIdle()
          if (
            !state.value.error &&
            state.value.tasks.find((task) => task.id === taskId)?.status !== 'complete'
          ) {
            throw new InstallerError(
              'Preparation was interrupted. Your completed tasks are saved. Resume preparation to continue.',
              {},
              422,
            )
          }
        }
      } catch (cause) {
        if (!canReconcile(cause)) throw cause
        loadingMessage.value = 'Checking saved progress…'
        await readUntilIdle()
        if (
          !state.value.error &&
          state.value.tasks.find((task) => task.id === taskId)?.status !== 'complete'
        ) {
          throw new InstallerError(
            'Preparation was interrupted. Your completed tasks are saved. Resume preparation to continue.',
          )
        }
        loadingMessage.value = 'Preparing your database…'
      }
      if (state.value.error) {
        error.value = state.value.error
        return
      }
    }
    step.value = 'admin'
  }

  async function saveAdmin() {
    await action('Saving administrator…', async () => {
      state.value = await request('admin', admin)
      admin.password = ''
      admin.password_confirmation = ''
      step.value = 'launch'
    })
  }

  async function finish() {
    await action('Finishing setup…', async () => {
      state.value = await request('finish', { app_url: appUrl.value })
    })
  }

  function navigate(target: Step) {
    if (busy.value || !loaded.value || state.value.finished) return
    if (target === 'checks') {
      void checks()
      return
    }
    step.value = target
    fields.value = {}
    error.value = ''
  }

  watch(step, async () => {
    await nextTick()
    document.querySelector<HTMLElement>('h1')?.focus()
  })
  onMounted(() => {
    void refresh()
  })
  onUnmounted(() => lifetime.abort())

  return {
    state,
    step,
    busy,
    loadingMessage,
    loaded,
    error,
    fields,
    database,
    configuration,
    saveConfiguration,
    admin,
    appUrl,
    prepared,
    progress,
    completeTasks,
    currentTask,
    refresh,
    checks,
    prepare,
    saveAdmin,
    finish,
    navigate,
  }
}
