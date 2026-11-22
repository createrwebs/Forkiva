<script lang="ts" setup>
import type {
  PdfSettings,
  PdfSettingsMeta,
  PdfSettingsResponse,
} from '@/modules/setting/contracts/Settings.ts'
import PageLoader from '@/modules/core/components/PageLoader.vue'
import { useForm } from '@/modules/core/composables/form.ts'
import { useSetting } from '@/modules/setting/composables/setting.ts'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'

const { locale, t } = useI18n()
const toast = useToast()
const { getSettings, store, update } = useSetting()
const meta = ref<PdfSettingsMeta>({ drivers: [] })

const form = useForm<PdfSettings>({
  pdf_default_driver: 'dompdf',
  pdf_dompdf_remote_enabled: false,
  pdf_dompdf_chroot: null,
  pdf_browsershot_node_binary: null,
  pdf_browsershot_npm_binary: null,
  pdf_browsershot_chrome_path: null,
  pdf_browsershot_node_modules_path: null,
  pdf_browsershot_no_sandbox: false,
  pdf_chrome_binary: null,
  pdf_chrome_no_sandbox: false,
  pdf_chrome_startup_timeout: 30,
  pdf_chrome_timeout: 30000,
  pdf_chrome_operation_timeout: 5000,
  pdf_chrome_user_data_dir: null,
  pdf_cloudflare_account_id: null,
  pdf_gotenberg_url: 'http://localhost:3000',
  pdf_gotenberg_username: null,
  pdf_weasyprint_binary: 'weasyprint',
  pdf_weasyprint_timeout: 10,
  encryptable: {
    pdf_cloudflare_api_token: null,
    pdf_gotenberg_password: null,
  },
  pdf_secret_status: {
    cloudflare_api_token: false,
    gotenberg_password: false,
  },
  pdf_clear_secrets: {
    cloudflare_api_token: false,
    gotenberg_password: false,
  },
})

const driverItems = computed(() => meta.value.drivers.map(({ id, name }) => ({ id, name })))
const selectedDriver = computed(() =>
  meta.value.drivers.find(({ id }) => id === form.state.pdf_default_driver),
)
const verifiedState = ref('')
const needsVerification = computed(
  () => verifiedState.value !== '' && verifiedState.value !== settingsSnapshot(),
)

function settingsSnapshot(): string {
  return JSON.stringify(form.state)
}

onMounted(async () => {
  const response: PdfSettingsResponse | false = await getSettings('pdf')
  if (response !== false) {
    meta.value = response.meta
    Object.assign(form.state, response.settings)
    verifiedState.value = settingsSnapshot()
  }
})

function error(field: string) {
  return form.errors.value?.[field]
}

function errors(...fields: string[]): string[] {
  return fields.flatMap((field) => {
    const message = error(field)

    return message ? [message] : []
  })
}

watch(
  () => form.state.encryptable.pdf_cloudflare_api_token,
  (token) => {
    if (token) form.state.pdf_clear_secrets.cloudflare_api_token = false
  },
)

watch(
  () => form.state.pdf_clear_secrets.cloudflare_api_token,
  (clear) => {
    if (clear) form.state.encryptable.pdf_cloudflare_api_token = null
  },
)

watch(
  () => form.state.encryptable.pdf_gotenberg_password,
  (password) => {
    if (password) form.state.pdf_clear_secrets.gotenberg_password = false
  },
)

watch(
  () => form.state.pdf_clear_secrets.gotenberg_password,
  (clear) => {
    if (clear) form.state.encryptable.pdf_gotenberg_password = null
  },
)

function statusColor(driver: PdfSettingsMeta['drivers'][number]): string {
  if (needsVerification.value) return 'warning'
  if (driver.verification?.ready) return 'success'
  if (driver.verification) return 'error'

  return driver.available ? 'info' : 'warning'
}

function verificationLabel(driver: PdfSettingsMeta['drivers'][number]): string {
  if (needsVerification.value) return t('pdf::settings.verification_required')

  if (driver.verification?.ready) {
    return `${t('pdf::settings.verified')} · ${driver.verification.duration_ms} ms`
  }

  if (driver.verification) return t('pdf::settings.verification_failed')

  return driver.available ? t('pdf::settings.not_verified') : t('pdf::settings.dependency_missing')
}

function driverIcon(driver: PdfSettingsMeta['drivers'][number]): string {
  return (
    {
      dompdf: 'tabler-file-type-pdf',
      browsershot: 'tabler-camera',
      chrome: 'tabler-brand-chrome',
      cloudflare: 'tabler-cloud',
      gotenberg: 'tabler-server',
      weasyprint: 'tabler-file-code',
    }[driver.id] ?? 'tabler-file-type-pdf'
  )
}

function driverAvailabilityLabel(driver: PdfSettingsMeta['drivers'][number]): string {
  if (driver.id === selectedDriver.value?.id) return t('pdf::settings.active')
  if (driver.verification?.ready) return t('pdf::settings.verified')

  return driver.available
    ? t('pdf::settings.dependency_ready')
    : t('pdf::settings.dependency_missing')
}

function formatVerifiedAt(value: string): string {
  const date = new Date(value)

  if (Number.isNaN(date.getTime())) return value

  return new Intl.DateTimeFormat(locale.value.replace('_', '-'), {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date)
}

async function submit() {
  const saved = await form.submit(() => update('pdf', form.state))
  if (saved === false) {
    const driverError = error('pdf_default_driver')
    if (driverError) toast.error(driverError)

    return
  }

  const response: PdfSettingsResponse | false = await getSettings('pdf')
  if (response !== false) {
    meta.value = response.meta
    Object.assign(form.state, response.settings)
    verifiedState.value = settingsSnapshot()
  }
}
</script>

<template>
  <PageLoader :loading="store.loading" />

  <VForm
    v-if="!store.loading"
    @submit.prevent="submit"
  >
    <div class="pdf-workspace">
      <aside class="engine-rail">
        <div class="engine-rail-heading">
          <h2 class="text-h5 font-weight-semibold mb-2">
            {{ t('pdf::settings.engine_title') }}
          </h2>
          <p class="text-body-2 text-medium-emphasis mb-0">
            {{ t('pdf::settings.engine_description') }}
          </p>
        </div>

        <VSelect
          v-model="form.state.pdf_default_driver"
          class="engine-mobile-select"
          :error-messages="error('pdf_default_driver')"
          item-title="name"
          item-value="id"
          :items="driverItems"
          :label="t('setting::attributes.settings.pdf_default_driver')"
        />

        <div class="engine-list">
          <button
            v-for="driver in meta.drivers"
            :key="driver.id"
            :aria-pressed="driver.id === selectedDriver?.id"
            class="engine-option"
            :class="{ 'engine-option--active': driver.id === selectedDriver?.id }"
            type="button"
            @click="form.state.pdf_default_driver = driver.id"
          >
            <span class="engine-option-icon">
              <VIcon
                :icon="driverIcon(driver)"
                size="22"
              />
            </span>
            <span class="engine-option-copy">
              <span class="engine-option-name">{{ driver.name }}</span>
              <span
                class="engine-option-state"
                :class="{
                  'text-primary': driver.id === selectedDriver?.id,
                  'text-error': !driver.available,
                }"
              >
                {{ driverAvailabilityLabel(driver) }}
              </span>
            </span>
            <VIcon
              v-if="driver.id === selectedDriver?.id"
              color="primary"
              icon="tabler-circle-check-filled"
              size="21"
            />
          </button>
        </div>
      </aside>

      <section
        v-if="selectedDriver"
        class="engine-configuration"
      >
        <header class="configuration-header">
          <div class="configuration-primary">
            <h2 class="configuration-title">{{ selectedDriver.name }}</h2>
            <VChip
              :color="statusColor(selectedDriver)"
              :prepend-icon="needsVerification ? 'tabler-alert-triangle' : 'tabler-shield-check'"
              size="small"
              variant="tonal"
            >
              {{ verificationLabel(selectedDriver) }}
            </VChip>
          </div>
          <div
            v-if="selectedDriver.verification?.checked_at && !needsVerification"
            class="verification-time"
          >
            <VIcon
              icon="tabler-clock-check"
              size="16"
            />
            <span>{{ t('pdf::settings.last_verified') }}</span>
            <time :datetime="selectedDriver.verification.checked_at">
              {{ formatVerifiedAt(selectedDriver.verification.checked_at) }}
            </time>
          </div>
        </header>

        <div class="verification-summary">
          <VIcon
            color="primary"
            icon="tabler-info-circle"
            size="20"
          />
          <p class="text-body-2 mb-0">{{ selectedDriver.requirements }}</p>
        </div>

        <VAlert
          v-if="selectedDriver.message"
          class="mt-4"
          color="warning"
          density="compact"
          icon="tabler-alert-triangle"
          variant="tonal"
        >
          {{ selectedDriver.message }}
        </VAlert>

        <VDivider class="configuration-divider" />

        <div class="configuration-section-heading">
          <h3 class="text-h6 font-weight-semibold mb-1">
            {{ t('pdf::settings.driver_settings') }}
          </h3>
          <p class="text-body-2 text-medium-emphasis mb-0">
            {{ t('pdf::settings.driver_settings_hint') }}
          </p>
        </div>

        <div class="driver-settings-fields">
          <VRow v-if="selectedDriver.id === 'dompdf'">
            <VCol cols="12">
              <VSwitch
                v-model="form.state.pdf_dompdf_remote_enabled"
                color="primary"
                hide-details
                inset
                :label="t('setting::attributes.settings.pdf_dompdf_remote_enabled')"
              />
            </VCol>
            <VCol cols="12">
              <VTextField
                v-model="form.state.pdf_dompdf_chroot"
                :error-messages="error('pdf_dompdf_chroot')"
                :label="t('setting::attributes.settings.pdf_dompdf_chroot')"
              />
            </VCol>
          </VRow>

          <VRow v-else-if="selectedDriver.id === 'browsershot'">
            <VCol
              v-for="field in [
                'node_binary',
                'npm_binary',
                'chrome_path',
                'node_modules_path',
              ] as const"
              :key="field"
              cols="12"
              md="6"
            >
              <VTextField
                v-model="form.state[`pdf_browsershot_${field}`]"
                :error-messages="error(`pdf_browsershot_${field}`)"
                :label="t(`setting::attributes.settings.pdf_browsershot_${field}`)"
              />
            </VCol>
            <VCol cols="12">
              <VSwitch
                v-model="form.state.pdf_browsershot_no_sandbox"
                color="primary"
                hide-details
                inset
                :label="t('setting::attributes.settings.pdf_browsershot_no_sandbox')"
              />
            </VCol>
          </VRow>

          <VRow v-else-if="selectedDriver.id === 'chrome'">
            <VCol cols="12">
              <VTextField
                v-model="form.state.pdf_chrome_binary"
                :error-messages="error('pdf_chrome_binary')"
                :label="t('setting::attributes.settings.pdf_chrome_binary')"
              />
            </VCol>
            <VCol
              v-for="field in ['startup_timeout', 'timeout', 'operation_timeout'] as const"
              :key="field"
              cols="12"
              md="4"
            >
              <VTextField
                v-model.number="form.state[`pdf_chrome_${field}`]"
                v-integer-en
                :error-messages="error(`pdf_chrome_${field}`)"
                :label="t(`setting::attributes.settings.pdf_chrome_${field}`)"
              />
            </VCol>
            <VCol cols="12">
              <VTextField
                v-model="form.state.pdf_chrome_user_data_dir"
                :error-messages="error('pdf_chrome_user_data_dir')"
                :label="t('setting::attributes.settings.pdf_chrome_user_data_dir')"
              />
            </VCol>
            <VCol cols="12">
              <VSwitch
                v-model="form.state.pdf_chrome_no_sandbox"
                color="primary"
                hide-details
                inset
                :label="t('setting::attributes.settings.pdf_chrome_no_sandbox')"
              />
            </VCol>
          </VRow>

          <VRow v-else-if="selectedDriver.id === 'cloudflare'">
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="form.state.pdf_cloudflare_account_id"
                :error-messages="error('pdf_cloudflare_account_id')"
                :label="t('setting::attributes.settings.pdf_cloudflare_account_id')"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="form.state.encryptable.pdf_cloudflare_api_token"
                :error-messages="
                  errors(
                    'encryptable.pdf_cloudflare_api_token',
                    'pdf_clear_secrets.cloudflare_api_token',
                  )
                "
                :hint="
                  form.state.pdf_secret_status.cloudflare_api_token
                    ? t('pdf::settings.secret_configured')
                    : undefined
                "
                :label="t('setting::attributes.settings.encryptable.pdf_cloudflare_api_token')"
                persistent-hint
                type="password"
              />
            </VCol>
            <VCol cols="12">
              <VCheckbox
                v-model="form.state.pdf_clear_secrets.cloudflare_api_token"
                :label="t('pdf::settings.clear_cloudflare_token')"
              />
            </VCol>
          </VRow>

          <VRow v-else-if="selectedDriver.id === 'gotenberg'">
            <VCol cols="12">
              <VTextField
                v-model="form.state.pdf_gotenberg_url"
                :error-messages="error('pdf_gotenberg_url')"
                :label="t('setting::attributes.settings.pdf_gotenberg_url')"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="form.state.pdf_gotenberg_username"
                :error-messages="error('pdf_gotenberg_username')"
                :label="t('setting::attributes.settings.pdf_gotenberg_username')"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <VTextField
                v-model="form.state.encryptable.pdf_gotenberg_password"
                :error-messages="
                  errors(
                    'encryptable.pdf_gotenberg_password',
                    'pdf_clear_secrets.gotenberg_password',
                  )
                "
                :hint="
                  form.state.pdf_secret_status.gotenberg_password
                    ? t('pdf::settings.secret_configured')
                    : undefined
                "
                :label="t('setting::attributes.settings.encryptable.pdf_gotenberg_password')"
                persistent-hint
                type="password"
              />
            </VCol>
            <VCol cols="12">
              <VCheckbox
                v-model="form.state.pdf_clear_secrets.gotenberg_password"
                :label="t('pdf::settings.clear_gotenberg_password')"
              />
            </VCol>
          </VRow>

          <VRow v-else-if="selectedDriver.id === 'weasyprint'">
            <VCol
              cols="12"
              md="8"
            >
              <VTextField
                v-model="form.state.pdf_weasyprint_binary"
                :error-messages="error('pdf_weasyprint_binary')"
                :label="t('setting::attributes.settings.pdf_weasyprint_binary')"
              />
            </VCol>
            <VCol
              cols="12"
              md="4"
            >
              <VTextField
                v-model.number="form.state.pdf_weasyprint_timeout"
                v-integer-en
                :error-messages="error('pdf_weasyprint_timeout')"
                :label="t('setting::attributes.settings.pdf_weasyprint_timeout')"
              />
            </VCol>
          </VRow>
        </div>

        <VAlert
          v-if="form.errors.value.general"
          class="mt-5"
          color="error"
          density="compact"
          variant="tonal"
        >
          {{ form.errors.value.general }}
        </VAlert>
      </section>
    </div>

    <div class="d-flex justify-end mt-4">
      <VBtn
        :disabled="form.loading.value || !selectedDriver"
        :loading="form.loading.value"
        type="submit"
      >
        <VIcon
          icon="tabler-settings-cog"
          start
        />
        {{ t('admin::admin.buttons.save') }}
      </VBtn>
    </div>
  </VForm>
</template>

<style scoped>
.pdf-workspace {
  display: grid;
  grid-template-columns: minmax(250px, 320px) minmax(0, 1fr);
  overflow: hidden;
  border-color: rgba(var(--v-border-color), var(--v-border-opacity));
  border-style: solid;
  border-width: 1px;
  border-radius: 12px;
  background: rgb(var(--v-theme-surface));
}

.engine-rail {
  padding: 28px 24px;
  border-inline-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.engine-rail-heading {
  margin-bottom: 24px;
}

.engine-mobile-select {
  display: none;
}

.engine-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.engine-option {
  display: flex;
  align-items: center;
  width: 100%;
  gap: 12px;
  padding: 13px 14px;
  border: 1px solid transparent;
  border-radius: 10px;
  background: transparent;
  color: rgb(var(--v-theme-on-surface));
  cursor: pointer;
  font: inherit;
  text-align: start;
  transition:
    border-color 150ms ease,
    background-color 150ms ease;
}

.engine-option:hover {
  background: rgba(var(--v-theme-on-surface), 0.035);
}

.engine-option--active {
  border-color: rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.055);
  box-shadow: inset 3px 0 0 rgb(var(--v-theme-primary));
}

:global([dir='rtl']) .engine-option--active {
  box-shadow: inset -3px 0 0 rgb(var(--v-theme-primary));
}

.engine-option-icon {
  display: grid;
  flex: 0 0 36px;
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: rgba(var(--v-theme-on-surface), 0.05);
  place-items: center;
}

.engine-option--active .engine-option-icon {
  background: rgba(var(--v-theme-primary), 0.12);
  color: rgb(var(--v-theme-primary));
}

.engine-option-copy {
  display: flex;
  flex: 1;
  min-width: 0;
  flex-direction: column;
}

.engine-option-name {
  overflow: hidden;
  font-size: 0.875rem;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.engine-option-state {
  margin-top: 2px;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.75rem;
}

.engine-configuration {
  min-width: 0;
  padding: 28px 32px;
}

.configuration-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.configuration-primary {
  display: flex;
  min-width: 0;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
}

.configuration-title {
  margin: 0;
  font-size: 1.375rem;
  font-weight: 600;
  line-height: 1.35;
}

.verification-time {
  display: inline-flex;
  flex: 0 0 auto;
  align-items: center;
  gap: 5px;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.75rem;
}

.verification-time time {
  color: rgb(var(--v-theme-on-surface));
  font-weight: 500;
}

.verification-summary {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  max-width: 760px;
  margin-top: 14px;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
}

.configuration-divider {
  margin-block: 20px;
}

.configuration-section-heading {
  margin-bottom: 20px;
}

.driver-settings-fields :deep(.v-row) {
  margin-top: 0;
}

@media (max-width: 959px) {
  .pdf-workspace {
    grid-template-columns: 1fr;
  }

  .engine-rail {
    padding: 24px;
    border-inline-end: 0;
    border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  }

  .engine-list {
    display: none;
  }

  .engine-mobile-select {
    display: block;
  }

  .engine-configuration {
    padding: 24px;
  }
}

@media (max-width: 600px) {
  .configuration-header {
    align-items: flex-start;
    flex-direction: column;
    gap: 10px;
  }

  .engine-rail,
  .engine-configuration {
    padding: 20px;
  }
}
</style>
