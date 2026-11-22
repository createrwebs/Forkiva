<script lang="ts" setup>
import { computed } from 'vue'
import ConfigurationStep from './components/ConfigurationStep.vue'
import InstallerField from './components/InstallerField.vue'
import InstallerSelect from './components/InstallerSelect.vue'
import InstallerIcon from './components/InstallerIcon.vue'
import ReadinessStep from './components/ReadinessStep.vue'
import WelcomeStep from './components/WelcomeStep.vue'
import SetupNavigation from './components/SetupNavigation.vue'
import { useInstaller } from './composables/useInstaller'
import type { InstallationState, Step } from './contracts/installer'

const props = defineProps<{ initialState?: InstallationState }>()

const {
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
} = useInstaller(props.initialState)
const steps: { id: Step; label: string; description: string }[] = [
  { id: 'welcome', label: 'Welcome', description: 'Start your setup' },
  { id: 'checks', label: 'System check', description: 'Check server readiness' },
  { id: 'configuration', label: 'Configuration', description: 'Set your business defaults' },
  { id: 'database', label: 'Database', description: 'Connect your database' },
  { id: 'admin', label: 'Administrator', description: 'Create your account' },
  { id: 'launch', label: 'Launch', description: 'Open your workspace' },
]
const stepIndex = computed(() => steps.findIndex((item) => item.id === step.value))
const titles = {
  welcome: 'Set up your restaurant',
  checks: 'A solid foundation.',
  configuration: 'Make it your own.',
  database: 'A home for your data.',
  admin: 'Make yourself at home.',
  launch: 'Ready when you are.',
}
const subtitles = {
  welcome: 'A few simple steps to get Forkiva ready for your team.',
  checks: 'Let’s make sure your server is ready for Forkiva.',
  configuration: 'Choose the defaults for your business, website, and secure sign-in.',
  database: 'Connect an empty database. We’ll take care of the structure.',
  admin: 'Create the account that will manage your business.',
  launch: 'One final check, then your workspace is open for business.',
}

function canVisit(id: Step) {
  if (busy.value || !loaded.value || state.value.finished) return false
  if (id === step.value || id === 'checks') return true
  if (state.value.database)
    return (
      id === 'database' ||
      (prepared.value && id === 'admin' && !state.value.admin) ||
      (state.value.admin && id === 'launch')
    )
  return (
    id === 'welcome' ||
    (id === 'configuration' && state.value.checks_passed) ||
    (id === 'database' && !!state.value.configuration)
  )
}
</script>

<template>
  <div
    :class="{ 'is-welcome': step === 'welcome' && !state.finished }"
    class="installer-shell"
  >
    <header class="setup-topbar">
      <a
        aria-label="Forkiva installer"
        class="brand"
        href="/installer"
      >
        <img
          alt=""
          src="/web/logo.png"
        />
        <span>Forkiva</span>
      </a>
      <span>{{ state.finished ? 'Installation complete' : 'Installation' }}</span>
    </header>
    <div class="setup-workspace">
      <SetupNavigation
        :active="step"
        :active-index="stepIndex"
        :can-visit="canVisit"
        :finished="state.finished"
        :steps="steps"
        @navigate="navigate"
      />
      <main class="setup-main">
        <div class="step-context">
          <span>{{ state.finished ? 'Complete' : steps[stepIndex]?.label }}</span>
          <span
            v-if="busy && loadingMessage"
            class="step-loading"
            role="status"
          >
            <span
              class="spinner"
              aria-hidden="true"
            />
            {{ loadingMessage }}
          </span>
          <span v-else>
            {{
              state.finished ? 'Installation complete' : `Step ${stepIndex + 1} of ${steps.length}`
            }}
          </span>
        </div>
        <div
          class="setup-content"
          :aria-busy="busy"
        >
          <template v-if="!state.finished">
            <span
              v-if="step === 'welcome'"
              aria-hidden="true"
              class="welcome-symbol"
            >
              <InstallerIcon name="serve" />
            </span>
            <h1 tabindex="-1">{{ titles[step] }}</h1>
            <p class="subtitle">{{ subtitles[step] }}</p>
          </template>
          <div
            v-if="error"
            class="notice error-notice"
            role="alert"
          >
            <InstallerIcon name="alert" />
            <div>
              <strong>{{ error }}</strong>
              <p
                v-for="message in [
                  ...(fields.database ?? []),
                  ...(step === 'launch' ? (fields.cache_store ?? []) : []),
                ]"
                :key="message"
              >
                {{ message }}
              </p>
              <button
                v-if="!Object.keys(fields).length"
                :disabled="busy"
                class="text-button"
                @click="refresh"
              >
                Refresh status
              </button>
            </div>
          </div>
          <WelcomeStep v-if="step === 'welcome'" />

          <ReadinessStep
            v-if="step === 'checks'"
            :busy="busy && !!loadingMessage"
            :state="state"
          />

          <ConfigurationStep
            v-if="step === 'configuration'"
            :model-value="configuration"
            :busy="busy || !loaded"
            :locked="state.database"
            :fields="fields"
            :options="state.options"
            @save="saveConfiguration"
          />

          <section v-if="step === 'database'">
            <form
              v-if="!state.database"
              id="database-form"
              @submit.prevent="prepare"
            >
              <fieldset :disabled="busy || !loaded">
                <div class="form-heading">
                  <h2>Database connection</h2>
                  <span>MySQL 8.0.16+ / MariaDB 10.3+ / PostgreSQL 17+</span>
                </div>
                <div class="form-grid">
                  <InstallerSelect
                    v-model="database.db_connection"
                    :errors="fields.db_connection"
                    :disabled="busy || !loaded"
                    :options="[
                      { id: 'mysql', name: 'MySQL / MariaDB' },
                      { id: 'pgsql', name: 'PostgreSQL' },
                    ]"
                    class="full-width"
                    label="Database engine"
                    name="db_connection"
                  />
                  <InstallerField
                    v-model="database.db_host"
                    :errors="fields.db_host"
                    :maxlength="255"
                    autocomplete="off"
                    label="Host"
                    name="db_host"
                  />
                  <InstallerField
                    v-model="database.db_port"
                    :errors="fields.db_port"
                    :max="65535"
                    :min="1"
                    label="Port"
                    name="db_port"
                    type="number"
                  />
                  <InstallerField
                    v-model="database.db_name"
                    :errors="fields.db_name"
                    :maxlength="64"
                    autocomplete="off"
                    hint="An existing, empty database."
                    label="Database name"
                    name="db_name"
                    placeholder="forkiva"
                  />
                  <InstallerField
                    v-model="database.db_user"
                    :errors="fields.db_user"
                    :maxlength="255"
                    autocomplete="off"
                    label="Database username"
                    name="db_user"
                  />
                  <InstallerField
                    v-model="database.db_pass"
                    :errors="fields.db_pass"
                    :maxlength="255"
                    :required="false"
                    autocomplete="off"
                    class="full-width"
                    label="Database password"
                    name="db_pass"
                    type="password"
                  />
                </div>
                <label class="checkbox-row">
                  <input
                    v-model="database.with_demo"
                    type="checkbox"
                  />
                  <span>
                    <strong>Include sample data</strong>
                    <small>
                      Add demonstration records for exploring Forkiva. Leave off for a fresh
                      business workspace.
                    </small>
                  </span>
                </label>
                <p
                  v-for="message in fields.with_demo"
                  :key="message"
                  class="field-error"
                >
                  {{ message }}
                </p>
              </fieldset>
            </form>
            <div
              v-else
              class="preparation"
            >
              <div class="panel-heading">
                <h2>{{ prepared ? 'Database is ready' : 'Preparing your workspace' }}</h2>
                <span class="progress-label">{{ progress }}%</span>
              </div>
              <progress
                :max="state.tasks.length"
                :value="completeTasks"
                aria-label="Database preparation"
              />
              <p
                class="preparation-status"
                role="status"
              >
                <span
                  v-if="busy && loadingMessage"
                  class="spinner"
                />
                {{ prepared ? 'All database tasks completed.' : currentTask?.label }}
                <span>{{ completeTasks }} of {{ state.tasks.length }} tasks complete</span>
              </p>
              <div class="task-list">
                <div
                  v-for="task in state.tasks"
                  :key="task.id"
                  :class="task.status"
                  class="task-row"
                >
                  <span>{{ task.label }}</span>
                  <InstallerIcon
                    v-if="task.status === 'complete'"
                    name="check"
                  />
                  <span
                    v-else-if="task.status === 'running' && busy && loadingMessage"
                    class="spinner"
                  />
                  <span v-else>{{ task.status === 'running' ? 'Interrupted' : task.status }}</span>
                </div>
              </div>
            </div>
            <div class="notice">
              <InstallerIcon name="lock" />
              <p>
                {{
                  state.database
                    ? 'Progress is saved after each task. Keep this page open while preparation is running.'
                    : 'The connection is tested before credentials are saved. Existing databases are never overwritten.'
                }}
              </p>
            </div>
          </section>

          <section v-if="step === 'admin'">
            <form
              id="admin-form"
              @submit.prevent="saveAdmin"
            >
              <fieldset :disabled="busy || !loaded || state.admin">
                <div class="form-heading">
                  <h2>Administrator account</h2>
                  <span>Full workspace access</span>
                </div>
                <div class="form-grid">
                  <InstallerField
                    v-model="admin.name"
                    :errors="fields.name"
                    :maxlength="255"
                    autocomplete="name"
                    label="Full name"
                    name="name"
                  />
                  <InstallerField
                    v-model="admin.username"
                    :errors="fields.username"
                    :maxlength="255"
                    autocomplete="username"
                    hint="Letters, numbers, dots, hyphens and underscores."
                    label="Username"
                    name="username"
                  />
                  <InstallerField
                    v-model="admin.email"
                    :errors="fields.email"
                    :maxlength="50"
                    autocomplete="email"
                    class="full-width"
                    label="Email address"
                    name="email"
                    type="email"
                  />
                  <InstallerField
                    v-model="admin.password"
                    :errors="fields.password"
                    :maxlength="20"
                    autocomplete="new-password"
                    label="Password"
                    name="password"
                    type="password"
                  />
                  <InstallerField
                    v-model="admin.password_confirmation"
                    :errors="fields.password_confirmation"
                    :maxlength="20"
                    autocomplete="new-password"
                    label="Confirm password"
                    name="password_confirmation"
                    type="password"
                  />
                </div>
                <p class="password-guide">
                  Use 8–20 characters with uppercase and lowercase letters, a number and a symbol.
                </p>
              </fieldset>
            </form>
            <div class="notice">
              <InstallerIcon name="lock" />
              <p>
                This replaces the initial seeded administrator credentials. Your password is
                securely hashed and never returned to this page.
              </p>
            </div>
          </section>

          <section v-if="step === 'launch' && !state.finished">
            <div class="launch-summary">
              <div
                v-for="label in [
                  state.checks_passed ? 'Server checks passed' : 'Server checks need attention',
                  'Application configured',
                  'Database prepared',
                  'Administrator created',
                ]"
                :key="label"
              >
                <span class="success-icon"><InstallerIcon name="check" /></span>
                {{ label }}
              </div>
            </div>
            <form
              id="launch-form"
              @submit.prevent="finish"
            >
              <fieldset :disabled="busy || !loaded">
                <InstallerField
                  :model-value="appUrl"
                  readonly
                  :errors="fields.app_url"
                  :maxlength="255"
                  hint="The address your team will use, for example https://pos.example.com."
                  label="Public application URL"
                  name="app_url"
                  type="url"
                />
              </fieldset>
            </form>
            <div class="notice">
              <InstallerIcon name="lock" />
              <p>
                Launching verifies the storage link, enables production mode, turns off debugging
                and locks the installer.
              </p>
            </div>
          </section>
          <section
            v-if="state.finished"
            class="success-screen"
          >
            <span class="success-seal"><InstallerIcon name="check" /></span>
            <h1 tabindex="-1">Your workspace is ready.</h1>
            <p class="subtitle">Welcome to Forkiva. Let’s make your first service a great one.</p>
            <div class="launch-summary">
              <div>
                <InstallerIcon name="check" />
                Administrator secured
              </div>
              <div>
                <InstallerIcon name="check" />
                Production mode enabled
              </div>
              <div>
                <InstallerIcon name="check" />
                Installer locked
              </div>
            </div>
            <a
              class="button primary"
              href="/auth/login"
            >
              Open Forkiva
              <InstallerIcon name="arrow" />
            </a>
          </section>
        </div>

        <footer
          v-if="!state.finished"
          class="setup-actions"
        >
          <button
            v-if="step !== 'welcome' && !state.database"
            :disabled="busy || !loaded"
            class="text-button back-button"
            @click="
              navigate(
                step === 'checks' ? 'welcome' : step === 'database' ? 'configuration' : 'checks',
              )
            "
          >
            <InstallerIcon name="back" />
            Back
          </button>
          <span
            v-else
            class="footer-caption"
          >
            Your progress is saved as you go.
          </span>
          <div class="action-group">
            <template v-if="step === 'welcome'">
              <button
                :disabled="busy || !loaded"
                class="button primary"
                @click="checks"
              >
                Let’s get started
                <InstallerIcon name="arrow" />
              </button>
            </template>
            <template v-if="step === 'checks'">
              <button
                :disabled="busy || !loaded"
                class="button secondary"
                @click="checks"
              >
                Run checks again
              </button>
              <button
                :disabled="busy || !loaded || !state.checks_passed"
                class="button primary"
                @click="navigate(state.database ? 'database' : 'configuration')"
              >
                {{ state.database ? 'Continue to database' : 'Continue to configuration' }}
                <InstallerIcon name="arrow" />
              </button>
            </template>
            <button
              v-if="step === 'configuration'"
              :disabled="busy || !loaded"
              class="button primary"
              form="configuration-form"
            >
              Save & continue
              <InstallerIcon name="arrow" />
            </button>
            <template v-if="step === 'database'">
              <button
                v-if="!state.database"
                :disabled="busy || !loaded"
                class="button primary"
                form="database-form"
              >
                Verify & prepare database
                <InstallerIcon name="arrow" />
              </button>
              <button
                v-else
                :disabled="busy || !loaded"
                class="button primary"
                @click="prepared ? navigate(state.admin ? 'launch' : 'admin') : prepare()"
              >
                {{
                  busy
                    ? 'Preparing…'
                    : prepared
                      ? 'Continue'
                      : currentTask?.status === 'failed'
                        ? 'Retry preparation'
                        : 'Resume preparation'
                }}
                <InstallerIcon name="arrow" />
              </button>
            </template>
            <button
              v-if="step === 'admin'"
              :disabled="busy || !loaded"
              class="button primary"
              form="admin-form"
            >
              Save administrator
              <InstallerIcon name="arrow" />
            </button>
            <button
              v-if="step === 'launch'"
              :disabled="busy || !loaded"
              class="button primary"
              form="launch-form"
            >
              Launch Forkiva
              <InstallerIcon name="arrow" />
            </button>
          </div>
        </footer>
      </main>
    </div>
  </div>
</template>
