<script lang="ts" setup>
import type {
  TwoFactorMethod,
  TwoFactorSetup,
  TwoFactorStatus,
} from '@/modules/auth/contracts/TwoFactor.ts'
import SecurityPanel from './SecurityPanel.vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import QRCodeVue3 from 'qrcode-vue3'
import * as twoFactorApi from '@/modules/auth/api/twoFactor.api.ts'

const props = defineProps<{ modelValue: boolean; embedded?: boolean }>()
const emit = defineEmits<{
  (event: 'update:modelValue', value: boolean): void
  (event: 'busy', value: boolean): void
}>()

const { t } = useI18n()
const toast = useToast()
const status = ref<TwoFactorStatus | null>(null)
const setupResult = ref<TwoFactorSetup | null>(null)
const selectedMethod = ref<TwoFactorMethod>('authenticator')
const currentPassword = ref('')
const code = ref('')
const recoveryCodes = ref<string[]>([])
const loading = ref(true)
const showSetup = ref(false)
const accountCodeSent = ref(false)

type TwoFactorField = 'current_password' | 'code' | 'method'
type TwoFactorAction =
  | 'begin-setup'
  | 'confirm-setup'
  | 'send-code'
  | 'resend-setup'
  | 'disable'
  | 'regenerate-recovery-codes'

const formErrors = ref<Partial<Record<TwoFactorField, string[]>>>({})
const activeAction = ref<TwoFactorAction | null>(null)
const submitting = computed(() => activeAction.value !== null)
watch(submitting, (value) => emit('busy', value), { immediate: true })
const requiresCurrentCode = computed(() => !!status.value?.confirmed_at && !!status.value?.method)
const {
  remaining: accountResendSeconds,
  start: startAccountResendTimer,
  stop: stopAccountResendTimer,
} = useCountdown(0)
const {
  remaining: setupResendSeconds,
  start: startSetupResendTimer,
  stop: stopSetupResendTimer,
} = useCountdown(0)
const accountSendLabel = computed(() =>
  accountResendSeconds.value > 0
    ? t('user::auth.two_factor_resend_short_in', { seconds: accountResendSeconds.value })
    : t(accountCodeSent.value ? 'user::auth.two_factor_resend' : 'user::auth.two_factor_send'),
)

const dialogModel = computed({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value),
})

const methodIcons: Record<TwoFactorMethod, string> = {
  authenticator: 'tabler-device-mobile',
  email: 'tabler-mail',
  sms: 'tabler-message-2',
}

const methodItems = computed(() =>
  (status.value?.methods || [])
    .filter((method) => method.available)
    .map((method) => ({
      title: t(`user::auth.two_factor_${method.id}`),
      value: method.id,
      icon: methodIcons[method.id],
      destination: method.destination,
    })),
)

const usesDeliveredCode = computed(() => ['email', 'sms'].includes(status.value?.method || ''))
const currentMethod = computed(() =>
  status.value?.methods.find((method) => method.id === status.value?.method),
)
const canSendCurrentCode = computed(
  () => Boolean(status.value?.enabled) && Boolean(currentMethod.value?.available),
)
const setupUsesDeliveredCode = computed(() =>
  ['email', 'sms'].includes(setupResult.value?.method || ''),
)
const currentDestination = computed(() => currentMethod.value?.destination)
const isRecoveryStep = computed(() => recoveryCodes.value.length > 0)
const isVerificationStep = computed(() => setupResult.value !== null)
const isSetupFlow = computed(
  () =>
    !status.value?.enabled || showSetup.value || isVerificationStep.value || isRecoveryStep.value,
)
const activeStep = computed(() => {
  if (isRecoveryStep.value) return 3
  if (isVerificationStep.value) return 2
  return 1
})

function errorMessage(error: any): string {
  const errors = error?.response?.data?.errors
  return (
    errors?.current_password?.[0] ||
    errors?.code?.[0] ||
    errors?.method?.[0] ||
    error?.response?.data?.message ||
    t('core::errors.an_unexpected_error_occurred')
  )
}

function captureFieldErrors(error: any, fallbackField?: TwoFactorField): boolean {
  const responseErrors = error?.response?.data?.errors
  const nextErrors: Partial<Record<TwoFactorField, string[]>> = {}

  for (const field of ['current_password', 'code', 'method'] as const) {
    const messages = responseErrors?.[field]
    if (Array.isArray(messages)) nextErrors[field] = messages.map(String)
    else if (messages) nextErrors[field] = [String(messages)]
  }

  const responseMessage = error?.response?.data?.message
  if (fallbackField && responseMessage && Object.keys(nextErrors).length === 0) {
    nextErrors[fallbackField] = [String(responseMessage)]
  }

  formErrors.value = nextErrors
  return Object.keys(nextErrors).length > 0
}

function handleSubmissionError(error: any, fallbackField?: TwoFactorField) {
  if (!captureFieldErrors(error, fallbackField)) toast.error(errorMessage(error))
}

function clearFieldError(field: TwoFactorField) {
  if (!formErrors.value[field]) return

  const nextErrors = { ...formErrors.value }
  delete nextErrors[field]
  formErrors.value = nextErrors
}

function clearFormErrors() {
  formErrors.value = {}
}

function resetTransientState() {
  stopAccountResendTimer()
  stopSetupResendTimer()
  accountCodeSent.value = false
  setupResult.value = null
  recoveryCodes.value = []
  showSetup.value = false
  currentPassword.value = ''
  code.value = ''
  clearFormErrors()
}

async function loadStatus() {
  loading.value = true
  try {
    const response = await twoFactorApi.getStatus()
    status.value = response.data.body
    startAccountResendTimer(response.data.body.resend_available_in)
    const availableMethod = status.value?.methods.find((method) => method.available)
    if (availableMethod) selectedMethod.value = availableMethod.id
  } catch (error: unknown) {
    toast.error(errorMessage(error))
  } finally {
    loading.value = false
  }
}

async function beginSetup() {
  if (submitting.value) return
  clearFormErrors()
  activeAction.value = 'begin-setup'
  try {
    const response = await twoFactorApi.setup(
      selectedMethod.value,
      currentPassword.value,
      requiresCurrentCode.value ? code.value : undefined,
    )
    setupResult.value = response.data.body
    if (setupUsesDeliveredCode.value) startSetupResendTimer(response.data.body.resend_available_in)
    code.value = ''
  } catch (error: unknown) {
    handleSubmissionError(error, 'method')
  } finally {
    activeAction.value = null
  }
}

async function confirmSetup() {
  if (submitting.value) return
  clearFormErrors()
  activeAction.value = 'confirm-setup'
  try {
    const response = await twoFactorApi.confirm(code.value)
    status.value = response.data.body
    recoveryCodes.value = response.data.body.recovery_codes || []
    setupResult.value = null
    stopSetupResendTimer()
    showSetup.value = false
    currentPassword.value = ''
    code.value = ''
    toast.success(response.data.message)
  } catch (error: unknown) {
    handleSubmissionError(error, 'code')
  } finally {
    activeAction.value = null
  }
}

async function sendCode() {
  if (!canSendCurrentCode.value || submitting.value || accountResendSeconds.value > 0) return

  activeAction.value = 'send-code'
  try {
    const response = await twoFactorApi.sendAccountCode()
    accountCodeSent.value = true
    startAccountResendTimer(response.data.body.resend_available_in)
    toast.success(response.data.message)
  } catch (error: any) {
    const retryAfter = Number(error?.response?.headers?.['retry-after'])
    if (error?.response?.status === 429 && retryAfter > 0) {
      startAccountResendTimer(retryAfter)
      return
    }
    toast.error(errorMessage(error))
  } finally {
    activeAction.value = null
  }
}

async function resendSetupCode() {
  if (submitting.value || !setupUsesDeliveredCode.value || setupResendSeconds.value > 0) return

  activeAction.value = 'resend-setup'
  try {
    const response = await twoFactorApi.resendSetup()
    setupResult.value = response.data.body
    code.value = ''
    clearFieldError('code')
    startSetupResendTimer(response.data.body.resend_available_in)
    toast.success(response.data.message)
  } catch (error: any) {
    const retryAfter = Number(error?.response?.headers?.['retry-after'])
    if (error?.response?.status === 429 && retryAfter > 0) startSetupResendTimer(retryAfter)
    handleSubmissionError(error, 'code')
  } finally {
    activeAction.value = null
  }
}

async function disable() {
  if (submitting.value) return
  clearFormErrors()
  activeAction.value = 'disable'
  try {
    const response = await twoFactorApi.disable(code.value, currentPassword.value)
    toast.success(response.data.message)
    resetTransientState()
    await loadStatus()
  } catch (error: unknown) {
    handleSubmissionError(error, 'code')
  } finally {
    activeAction.value = null
  }
}

async function regenerateRecoveryCodes() {
  if (submitting.value) return
  clearFormErrors()
  activeAction.value = 'regenerate-recovery-codes'
  try {
    const response = await twoFactorApi.regenerateRecoveryCodes(code.value, currentPassword.value)
    recoveryCodes.value = response.data.body.recovery_codes
    code.value = ''
    currentPassword.value = ''
    toast.success(response.data.message)
    await loadStatus()
  } catch (error: unknown) {
    handleSubmissionError(error, 'code')
  } finally {
    activeAction.value = null
  }
}

function backToMethod() {
  stopSetupResendTimer()
  setupResult.value = null
  code.value = ''
  clearFormErrors()
}

function cancelSetup() {
  stopSetupResendTimer()
  showSetup.value = false
  setupResult.value = null
  currentPassword.value = ''
  code.value = ''
  clearFormErrors()
}

function openSetup() {
  showSetup.value = true
  clearFormErrors()
}

async function copyText(value: string) {
  await navigator.clipboard.writeText(value)
  toast.success(t('user::auth.two_factor_copied'))
}

function downloadRecoveryCodes() {
  const contents = [t('user::auth.two_factor_recovery_codes'), '', ...recoveryCodes.value].join(
    '\n',
  )
  const url = URL.createObjectURL(new Blob([contents], { type: 'text/plain;charset=utf-8' }))
  const link = document.createElement('a')
  link.href = url
  link.download = 'forkiva-recovery-codes.txt'
  link.click()
  URL.revokeObjectURL(url)
}

function finishSetup() {
  if (props.embedded) {
    resetTransientState()
    loadStatus()
  } else {
    dialogModel.value = false
  }
}

watch(currentPassword, () => clearFieldError('current_password'))
watch(code, () => clearFieldError('code'))
watch(selectedMethod, () => clearFieldError('method'))

watch(
  () => props.modelValue,
  (open) => {
    if (open) {
      resetTransientState()
      loadStatus()
    } else if (!submitting.value) {
      resetTransientState()
    }
  },
  { immediate: true },
)
</script>

<template>
  <SecurityPanel
    :embedded="embedded"
    v-model="dialogModel"
    max-width="920"
    :persistent="submitting"
    scrollable
  >
    <VCard class="two-factor-dialog">
      <header class="dialog-header">
        <div class="dialog-heading">
          <span class="dialog-heading__icon"><VIcon icon="tabler-shield-lock" /></span>
          <div>
            <h2>{{ t('user::auth.two_factor_authentication') }}</h2>
            <p>{{ t('user::auth.two_factor_subtitle') }}</p>
          </div>
        </div>
        <VBtn
          v-if="!embedded"
          :aria-label="t('admin::admin.buttons.close')"
          color="default"
          :disabled="submitting"
          icon="tabler-x"
          variant="text"
          @click="dialogModel = false"
        />
      </header>

      <div
        v-if="isSetupFlow && !loading"
        class="setup-progress"
        :aria-label="t('user::auth.two_factor_setup_progress')"
      >
        <div
          v-for="(step, index) in [
            t('user::auth.two_factor_step_method'),
            t('user::auth.two_factor_step_verify'),
            t('user::auth.two_factor_step_recovery'),
          ]"
          :key="step"
          class="setup-progress__item"
          :class="{
            'setup-progress__item--active': activeStep === index + 1,
            'setup-progress__item--complete': activeStep > index + 1,
          }"
        >
          <span class="setup-progress__number">
            <VIcon
              v-if="activeStep > index + 1"
              icon="tabler-check"
              size="15"
            />
            <template v-else>{{ index + 1 }}</template>
          </span>
          <span>{{ step }}</span>
        </div>
      </div>

      <VCardText class="dialog-body">
        <div
          v-if="loading"
          class="loading-state"
        >
          <VProgressCircular
            color="primary"
            indeterminate
          />
        </div>

        <template v-else-if="status">
          <section
            v-if="isRecoveryStep"
            class="recovery-state"
          >
            <div class="recovery-state__header">
              <span class="recovery-mark"><VIcon icon="tabler-shield-check" /></span>
              <div class="recovery-state__copy">
                <h3>{{ t('user::auth.two_factor_save_recovery_codes') }}</h3>
                <p>{{ t('user::auth.two_factor_recovery_codes_description') }}</p>
              </div>
              <div class="recovery-toolbar">
                <VBtn
                  color="primary"
                  prepend-icon="tabler-copy"
                  variant="tonal"
                  @click="copyText(recoveryCodes.join('\n'))"
                >
                  {{ t('user::auth.two_factor_copy_all') }}
                </VBtn>
                <VBtn
                  color="primary"
                  prepend-icon="tabler-download"
                  variant="outlined"
                  @click="downloadRecoveryCodes"
                >
                  {{ t('user::auth.two_factor_download') }}
                </VBtn>
              </div>
            </div>
            <ol class="recovery-code-list">
              <li
                v-for="(recoveryCode, index) in recoveryCodes"
                :key="recoveryCode"
              >
                <span class="recovery-code-number">{{ index + 1 }}</span>
                <code>{{ recoveryCode }}</code>
                <VBtn
                  :aria-label="`${t('user::auth.two_factor_copy')} ${index + 1}`"
                  color="primary"
                  icon="tabler-copy"
                  size="small"
                  variant="text"
                  @click="copyText(recoveryCode)"
                />
              </li>
            </ol>
            <div class="recovery-warning">
              <VIcon
                icon="tabler-alert-triangle"
                size="19"
              />
              <span>{{ t('user::auth.two_factor_recovery_codes_warning') }}</span>
            </div>
          </section>

          <section
            v-else-if="isVerificationStep && setupResult"
            class="verification-layout"
          >
            <div
              class="verification-visual"
              :class="{ 'verification-visual--qr': setupResult.otpauth_uri }"
              :aria-label="
                setupResult.otpauth_uri ? t('user::auth.two_factor_scan_qr_title') : undefined
              "
            >
              <template v-if="setupResult.otpauth_uri">
                <div class="instruction-block">
                  <span class="instruction-number">1</span>
                  <div>
                    <h3>{{ t('user::auth.two_factor_scan_qr_title') }}</h3>
                    <p>{{ t('user::auth.two_factor_authenticator_apps_hint') }}</p>
                  </div>
                </div>
                <div class="qr-frame">
                  <QRCodeVue3
                    :background-options="{ color: '#ffffff' }"
                    :corners-dot-options="{ type: 'square', color: '#111827' }"
                    :corners-square-options="{ type: 'square', color: '#111827' }"
                    :dots-options="{ type: 'square', color: '#111827' }"
                    :height="152"
                    imgclass="qr-frame__image"
                    :margin="0"
                    :qr-options="{ typeNumber: 0, mode: 'Byte', errorCorrectionLevel: 'M' }"
                    :value="setupResult.otpauth_uri"
                    :width="152"
                  />
                </div>
              </template>
              <template v-else>
                <span class="delivery-icon">
                  <VIcon
                    :icon="methodIcons[setupResult.method]"
                    size="34"
                  />
                </span>
                <h3>{{ t(`user::auth.two_factor_${setupResult.method}`) }}</h3>
                <p>{{ t('user::auth.two_factor_code_sent_to') }}</p>
                <strong>{{ setupResult.destination }}</strong>
              </template>
            </div>

            <div class="verification-form">
              <div class="instruction-block">
                <span class="instruction-number">
                  {{ setupUsesDeliveredCode ? 1 : 2 }}
                </span>
                <div>
                  <h3>{{ t('user::auth.two_factor_enter_code_title') }}</h3>
                  <p>
                    {{
                      t(
                        setupUsesDeliveredCode
                          ? 'user::auth.two_factor_delivered_code_hint'
                          : 'user::auth.two_factor_enter_code_hint',
                      )
                    }}
                  </p>
                </div>
              </div>
              <VTextField
                v-model="code"
                autocomplete="one-time-code"
                class="verification-code-field"
                :disabled="submitting"
                :error-messages="formErrors.code"
                inputmode="numeric"
                :label="t('user::auth.two_factor_six_digit_code')"
                maxlength="6"
                prepend-inner-icon="tabler-shield-check"
              />
              <div
                v-if="setupUsesDeliveredCode"
                class="management-resend setup-resend"
              >
                <p class="management-resend__copy">
                  <span>{{ t('user::auth.two_factor_resend_hint') }}</span>
                </p>
                <VBtn
                  :disabled="submitting || setupResendSeconds > 0"
                  :loading="activeAction === 'resend-setup'"
                  color="primary"
                  density="comfortable"
                  prepend-icon="tabler-refresh"
                  size="small"
                  variant="text"
                  @click="resendSetupCode"
                >
                  {{
                    setupResendSeconds > 0
                      ? t('user::auth.two_factor_resend_short_in', {
                          seconds: setupResendSeconds,
                        })
                      : t('user::auth.two_factor_resend')
                  }}
                </VBtn>
              </div>
              <div
                v-if="setupResult.secret"
                class="manual-key"
              >
                <span class="manual-key__label">
                  {{ t('user::auth.two_factor_manual_key') }}
                </span>
                <div class="manual-key__control">
                  <code>{{ setupResult.secret }}</code>
                  <VBtn
                    color="primary"
                    prepend-icon="tabler-copy"
                    variant="text"
                    @click="copyText(setupResult.secret)"
                  >
                    {{ t('user::auth.two_factor_copy') }}
                  </VBtn>
                </div>
                <small>{{ t('user::auth.two_factor_manual_key_hint') }}</small>
              </div>
            </div>
          </section>

          <section
            v-else-if="!status.enabled || showSetup"
            class="method-state"
          >
            <div class="section-heading">
              <h3>{{ t('user::auth.two_factor_choose_method') }}</h3>
              <p>{{ t('user::auth.two_factor_choose_method_hint') }}</p>
            </div>
            <div class="method-grid">
              <button
                v-for="method in methodItems"
                :key="method.value"
                class="method-option"
                :class="{
                  'method-option--selected': selectedMethod === method.value,
                }"
                :disabled="submitting"
                type="button"
                @click="selectedMethod = method.value"
              >
                <span class="method-option__icon">
                  <VIcon :icon="method.icon" />
                </span>
                <span class="method-option__content">
                  <strong>{{ method.title }}</strong>
                  <small>
                    {{ method.destination || t(`user::auth.two_factor_${method.value}_hint`) }}
                  </small>
                </span>
                <span class="method-option__radio"><span /></span>
              </button>
            </div>
            <div
              v-if="formErrors.method?.length"
              class="field-error"
              role="alert"
            >
              <VIcon
                icon="tabler-alert-circle"
                size="16"
              />
              <span>{{ formErrors.method[0] }}</span>
            </div>
            <div class="password-panel">
              <div>
                <h4>{{ t('user::auth.two_factor_confirm_identity') }}</h4>
                <p>{{ t('user::auth.two_factor_confirm_identity_hint') }}</p>
              </div>
              <div class="identity-fields">
                <VTextField
                  v-model="currentPassword"
                  autocomplete="current-password"
                  :disabled="submitting"
                  :error-messages="formErrors.current_password"
                  hide-details="auto"
                  :label="t('user::attributes.two_factor.current_password')"
                  prepend-inner-icon="tabler-lock"
                  type="password"
                />
                <template v-if="requiresCurrentCode">
                  <VTextField
                    v-model="code"
                    autocomplete="one-time-code"
                    :disabled="submitting"
                    :error-messages="formErrors.code"
                    :hint="t('user::auth.two_factor_existing_code_hint')"
                    :label="t('user::auth.two_factor_code')"
                    persistent-hint
                    prepend-inner-icon="tabler-shield-check"
                  />
                  <VBtn
                    v-if="usesDeliveredCode"
                    class="identity-send-action"
                    :disabled="!canSendCurrentCode || submitting || accountResendSeconds > 0"
                    :loading="activeAction === 'send-code'"
                    size="small"
                    variant="text"
                    @click="sendCode"
                  >
                    {{ accountSendLabel }}
                  </VBtn>
                  <p
                    v-if="usesDeliveredCode && !canSendCurrentCode"
                    class="delivery-unavailable"
                    role="status"
                  >
                    {{ t('user::auth.two_factor_send_unavailable') }}
                  </p>
                </template>
              </div>
            </div>
          </section>

          <section
            v-else
            class="management-state"
          >
            <div class="protected-summary state-heading">
              <span class="success-mark"><VIcon icon="tabler-shield-check" /></span>
              <div>
                <h3>{{ t('user::auth.two_factor_protected_title') }}</h3>
                <p>
                  {{
                    t('user::auth.two_factor_current_method', {
                      method: t(`user::auth.two_factor_${status.method}`),
                    })
                  }}
                </p>
              </div>
              <div class="recovery-count">
                <strong>{{ status.recovery_codes_remaining }}</strong>
                <span>{{ t('user::auth.two_factor_codes_left') }}</span>
              </div>
            </div>
            <div class="management-form">
              <div class="section-heading">
                <h3>{{ t('user::auth.two_factor_manage_title') }}</h3>
                <p>{{ t('user::auth.two_factor_manage_hint') }}</p>
              </div>
              <div class="management-form__fields">
                <VTextField
                  v-model="currentPassword"
                  autocomplete="current-password"
                  :disabled="submitting"
                  :error-messages="formErrors.current_password"
                  hide-details="auto"
                  :label="t('user::attributes.two_factor.current_password')"
                  prepend-inner-icon="tabler-lock"
                  type="password"
                />
                <div class="management-code-field">
                  <VTextField
                    v-model="code"
                    autocomplete="one-time-code"
                    :disabled="submitting"
                    :error-messages="formErrors.code"
                    hide-details="auto"
                    :label="t('user::auth.two_factor_code')"
                    prepend-inner-icon="tabler-shield-check"
                  />
                  <div
                    v-if="usesDeliveredCode"
                    class="management-resend"
                  >
                    <p class="management-resend__copy">
                      <span>
                        {{
                          t(
                            accountCodeSent
                              ? 'user::auth.two_factor_resend_hint'
                              : 'user::auth.two_factor_send_hint',
                          )
                        }}
                      </span>
                      <small v-if="currentDestination">
                        {{ currentDestination }}
                      </small>
                    </p>
                    <VBtn
                      :disabled="!canSendCurrentCode || submitting || accountResendSeconds > 0"
                      :loading="activeAction === 'send-code'"
                      color="primary"
                      density="comfortable"
                      :prepend-icon="accountCodeSent ? 'tabler-refresh' : 'tabler-send'"
                      size="small"
                      variant="text"
                      @click="sendCode"
                    >
                      {{ accountSendLabel }}
                    </VBtn>
                  </div>
                  <p
                    v-if="usesDeliveredCode && !canSendCurrentCode"
                    class="delivery-unavailable"
                    role="status"
                  >
                    {{ t('user::auth.two_factor_send_unavailable') }}
                  </p>
                </div>
              </div>
              <div class="management-actions">
                <VBtn
                  :disabled="submitting || !code || !currentPassword"
                  :loading="activeAction === 'regenerate-recovery-codes'"
                  color="primary"
                  prepend-icon="tabler-refresh"
                  variant="tonal"
                  @click="regenerateRecoveryCodes"
                >
                  {{ t('user::auth.two_factor_regenerate') }}
                </VBtn>
                <VBtn
                  :disabled="submitting"
                  color="primary"
                  prepend-icon="tabler-switch-2"
                  variant="outlined"
                  @click="openSetup"
                >
                  {{ t('user::auth.two_factor_change_method') }}
                </VBtn>
                <VBtn
                  class="management-actions__danger"
                  :disabled="submitting || !code || !currentPassword"
                  :loading="activeAction === 'disable'"
                  color="error"
                  prepend-icon="tabler-shield-off"
                  variant="tonal"
                  @click="disable"
                >
                  {{ t('user::auth.two_factor_disable') }}
                </VBtn>
              </div>
            </div>
          </section>
        </template>
      </VCardText>

      <footer
        v-if="!loading && status && isSetupFlow"
        class="dialog-actions"
      >
        <template v-if="isRecoveryStep">
          <VSpacer />
          <VBtn
            prepend-icon="tabler-shield-check"
            @click="finishSetup"
          >
            {{ t('user::auth.two_factor_done') }}
          </VBtn>
        </template>
        <template v-else-if="isVerificationStep">
          <VBtn
            :disabled="submitting"
            prepend-icon="tabler-arrow-left"
            variant="outlined"
            @click="backToMethod"
          >
            {{ t('user::auth.two_factor_back') }}
          </VBtn>
          <VSpacer />
          <VBtn
            :disabled="submitting || !code"
            :loading="activeAction === 'confirm-setup'"
            prepend-icon="tabler-shield-check"
            @click="confirmSetup"
          >
            {{ t('user::auth.two_factor_verify_enable') }}
          </VBtn>
        </template>
        <template v-else-if="!status.enabled || showSetup">
          <VBtn
            v-if="status.enabled"
            :disabled="submitting"
            variant="text"
            @click="cancelSetup"
          >
            {{ t('admin::admin.buttons.cancel') }}
          </VBtn>
          <VSpacer />
          <VBtn
            :disabled="
              submitting ||
              methodItems.length === 0 ||
              !currentPassword ||
              (requiresCurrentCode && !code)
            "
            :loading="activeAction === 'begin-setup'"
            append-icon="tabler-arrow-right"
            @click="beginSetup"
          >
            {{ t('user::auth.two_factor_continue') }}
          </VBtn>
        </template>
      </footer>
    </VCard>
  </SecurityPanel>
</template>

<style lang="scss" scoped>
.identity-send-action {
  align-self: flex-end;
}

.delivery-unavailable {
  margin: -8px 0 0;
  color: rgb(var(--v-theme-warning));
  font-size: 0.8125rem;
  line-height: 1.35;
  text-align: end;
}

.identity-fields {
  display: flex;
  flex-direction: column;
  gap: 16px;
  min-width: 0;
}

.two-factor-dialog {
  overflow: hidden;
  border: 1px solid rgba(var(--v-border-color), 0.7);
  border-radius: 16px !important;
  box-shadow: 0 24px 64px rgba(31, 45, 61, 0.18) !important;
}

.dialog-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  padding: 24px 28px 18px;
}

.dialog-heading {
  display: flex;
  align-items: center;
  gap: 14px;

  h2 {
    margin: 0;
    font-size: 1.35rem;
    font-weight: 700;
    line-height: 1.35;
  }

  p {
    margin: 3px 0 0;
    color: rgba(var(--v-theme-on-surface), 0.62);
    font-size: 0.925rem;
  }
}

.dialog-heading__icon {
  display: grid;
  inline-size: 44px;
  block-size: 44px;
  border: 1px solid rgba(var(--v-theme-primary), 0.28);
  border-radius: 12px;
  color: rgb(var(--v-theme-primary));
  place-items: center;

  .v-icon {
    font-size: 26px;
  }
}

.setup-progress {
  display: grid;
  padding: 4px 54px 20px;
  grid-template-columns: repeat(3, 1fr);
}

.setup-progress__item {
  position: relative;
  display: grid;
  color: rgba(var(--v-theme-on-surface), 0.55);
  font-size: 0.8rem;
  font-weight: 600;
  gap: 7px;
  justify-items: center;

  &:not(:last-child)::after {
    position: absolute;
    z-index: 0;
    block-size: 1px;
    background: rgba(var(--v-border-color), 0.7);
    content: '';
    inline-size: calc(100% - 44px);
    inset-block-start: 15px;
    inset-inline-start: calc(50% + 22px);
  }
}

.setup-progress__number {
  position: relative;
  z-index: 1;
  display: grid;
  inline-size: 30px;
  block-size: 30px;
  border: 1px solid rgba(var(--v-border-color), 0.95);
  border-radius: 50%;
  background: rgb(var(--v-theme-surface));
  place-items: center;
}

.setup-progress__item--active {
  color: rgb(var(--v-theme-primary));

  .setup-progress__number {
    border-color: rgb(var(--v-theme-primary));
    background: rgb(var(--v-theme-primary));
    color: rgb(var(--v-theme-on-primary));
  }
}

.setup-progress__item--complete .setup-progress__number {
  border-color: rgba(var(--v-theme-primary), 0.28);
  background: rgba(var(--v-theme-primary), 0.1);
  color: rgb(var(--v-theme-primary));
}

.dialog-body {
  min-block-size: 320px;
  padding: 0 28px 26px !important;
}

.loading-state {
  display: grid;
  min-block-size: 320px;
  place-items: center;
}

.section-heading {
  margin-block-end: 18px;

  h3,
  p {
    margin: 0;
  }

  h3 {
    font-size: 1.05rem;
    font-weight: 700;
  }

  p {
    margin-block-start: 4px;
    color: rgba(var(--v-theme-on-surface), 0.62);
    font-size: 0.875rem;
  }
}

.method-grid {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}

.method-option {
  display: grid;
  min-block-size: 94px;
  padding: 16px;
  border: 1px solid rgba(var(--v-border-color), 0.85);
  border-radius: 12px;
  background: rgb(var(--v-theme-surface));
  color: inherit;
  cursor: pointer;
  gap: 12px;
  grid-template-columns: auto 1fr auto;
  text-align: start;
  transition:
    border-color 160ms ease,
    box-shadow 160ms ease,
    background-color 160ms ease;

  &:hover,
  &:focus-visible {
    border-color: rgba(var(--v-theme-primary), 0.6);
    outline: none;
  }
}

.method-option--selected {
  border-color: rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.045);
  box-shadow: 0 0 0 2px rgba(var(--v-theme-primary), 0.08);

  .method-option__radio {
    border-color: rgb(var(--v-theme-primary));

    span {
      background: rgb(var(--v-theme-primary));
    }
  }
}

.method-option__icon {
  display: grid;
  inline-size: 36px;
  block-size: 36px;
  border-radius: 10px;
  background: rgba(var(--v-theme-info), 0.1);
  color: rgb(var(--v-theme-info));
  place-items: center;
}

.method-option__content {
  display: grid;
  overflow: hidden;
  align-content: center;
  gap: 4px;

  strong {
    font-size: 0.9rem;
    font-weight: 650;
  }

  small {
    color: rgba(var(--v-theme-on-surface), 0.58);
    font-size: 0.75rem;
    line-height: 1.35;
  }
}

.method-option__radio {
  display: grid;
  inline-size: 18px;
  block-size: 18px;
  border: 1px solid rgba(var(--v-border-color), 1);
  border-radius: 50%;
  place-items: center;

  span {
    inline-size: 8px;
    block-size: 8px;
    border-radius: 50%;
  }
}

.field-error {
  display: flex;
  align-items: center;
  margin: 8px 4px 0;
  color: rgb(var(--v-theme-error));
  font-size: 0.75rem;
  gap: 6px;
}

.password-panel {
  display: grid;
  align-items: center;
  padding: 18px;
  margin-block-start: 18px;
  border-radius: 12px;
  background: rgba(var(--v-theme-info), 0.065);
  gap: 24px;
  grid-template-columns: minmax(0, 1fr) minmax(280px, 0.9fr);

  h4,
  p {
    margin: 0;
  }

  h4 {
    font-size: 0.925rem;
    font-weight: 700;
  }

  p {
    margin-block-start: 3px;
    color: rgba(var(--v-theme-on-surface), 0.6);
    font-size: 0.8rem;
  }
}

.verification-layout {
  display: grid;
  align-items: stretch;
  gap: 16px;
  grid-template-columns: minmax(220px, 0.72fr) minmax(0, 1.6fr);
}

.verification-visual {
  display: flex;
  align-items: stretch;
  justify-content: flex-start;
  padding: 18px;
  border: 1px solid rgba(var(--v-border-color), 0.32);
  border-radius: 14px;
  background: rgba(var(--v-theme-on-surface), 0.02);
  flex-direction: column;
  text-align: start;

  p {
    max-inline-size: 220px;
    margin: 14px 0 0;
    color: rgba(var(--v-theme-on-surface), 0.66);
    font-size: 0.8rem;
    line-height: 1.45;
  }

  h3 {
    margin: 14px 0 0;
    font-size: 1rem;
  }
}

.verification-visual--qr {
  border-color: rgba(var(--v-theme-primary), 0.22);
  background: rgba(var(--v-theme-primary), 0.025);
}

.qr-frame {
  display: grid;
  align-self: center;
  margin-block-start: 18px;
  padding: 6px;
  border: 1px solid rgba(var(--v-theme-primary), 0.3);
  border-radius: 12px;
  background: #fff;
  box-shadow:
    0 8px 22px rgba(17, 24, 39, 0.1),
    0 0 0 3px rgba(var(--v-theme-primary), 0.05);
  place-items: center;

  :deep(.qr-frame__image) {
    display: block;
    border-radius: 4px;
  }
}

.delivery-icon,
.success-mark {
  display: grid;
  inline-size: 54px;
  block-size: 54px;
  border-radius: 50%;
  background: rgba(var(--v-theme-info), 0.12);
  color: rgb(var(--v-theme-info));
  place-items: center;
}

.verification-form {
  padding: 18px;
  border: 1px solid rgba(var(--v-border-color), 0.32);
  border-radius: 14px;
  background: rgba(var(--v-theme-on-surface), 0.012);
}

.instruction-block {
  display: grid;
  gap: 12px;
  grid-template-columns: auto 1fr;

  h3,
  p {
    margin: 0;
  }

  h3 {
    font-size: 1rem;
    font-weight: 700;
    line-height: 1.45;
  }

  p {
    margin-block-start: 4px;
    color: rgba(var(--v-theme-on-surface), 0.62);
    font-size: 0.8rem;
  }
}

.instruction-number {
  display: grid;
  inline-size: 28px;
  block-size: 28px;
  border-radius: 50%;
  background: rgba(var(--v-theme-primary), 0.1);
  color: rgb(var(--v-theme-primary));
  font-size: 0.75rem;
  font-weight: 700;
  place-items: center;
}

.verification-code-field {
  margin-block-start: 14px;

  :deep(.v-field) {
    border-radius: 10px;
    background: rgba(var(--v-theme-on-surface), 0.018);
  }
}

.manual-key__label {
  display: block;
  margin-block-end: 6px;
  color: rgba(var(--v-theme-on-surface), 0.7);
  font-size: 0.78rem;
  font-weight: 600;
}

.manual-key__control {
  display: flex;
  min-block-size: 48px;
  align-items: center;
  justify-content: space-between;
  padding-inline-start: 14px;
  border: 1px solid rgba(var(--v-theme-primary), 0.28);
  border-radius: 10px;
  background: rgba(var(--v-theme-primary), 0.035);
  gap: 12px;

  code {
    overflow: hidden;
    color: rgb(var(--v-theme-on-surface));
    font-size: 0.78rem;
    letter-spacing: 0.08em;
    text-overflow: ellipsis;
  }
}

.manual-key small {
  display: block;
  margin-block-start: 6px;
  color: rgba(var(--v-theme-on-surface), 0.55);
  font-size: 0.72rem;
}

.recovery-state {
  overflow: hidden;
  border: 1px solid rgba(var(--v-border-color), 0.42);
  border-radius: 14px;
  background: rgb(var(--v-theme-surface));
}

.recovery-state__header {
  display: grid;
  align-items: center;
  padding: 22px 24px;
  border-block-end: 1px solid rgba(var(--v-border-color), 0.28);
  background: rgba(var(--v-theme-info), 0.045);
  gap: 14px;
  grid-template-columns: auto minmax(0, 1fr) auto;
}

.recovery-mark {
  display: grid;
  inline-size: 46px;
  block-size: 46px;
  border: 1px solid rgba(var(--v-theme-primary), 0.2);
  border-radius: 13px;
  background: rgba(var(--v-theme-primary), 0.09);
  color: rgb(var(--v-theme-primary));
  place-items: center;

  .v-icon {
    font-size: 25px;
  }
}

.recovery-state__copy {
  h3,
  p {
    margin: 0;
  }

  h3 {
    font-size: 1.05rem;
    font-weight: 700;
  }

  p {
    max-inline-size: 600px;
    margin-block-start: 4px;
    color: rgba(var(--v-theme-on-surface), 0.62);
    font-size: 0.82rem;
    line-height: 1.45;
  }
}

.state-heading {
  display: flex;
  align-items: center;
  gap: 16px;

  h3,
  p {
    margin: 0;
  }

  h3 {
    font-size: 1.05rem;
    font-weight: 700;
  }

  p {
    margin-block-start: 4px;
    color: rgba(var(--v-theme-on-surface), 0.62);
    font-size: 0.83rem;
  }
}

.success-mark {
  flex: 0 0 auto;
  color: rgb(var(--v-theme-on-surface));
}

.recovery-toolbar {
  display: flex;
  gap: 8px;
}

.recovery-code-list {
  display: grid;
  padding: 22px 24px;
  margin: 0;
  gap: 10px;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  list-style: none;

  li {
    display: grid;
    min-block-size: 54px;
    align-items: center;
    padding: 6px 8px 6px 10px;
    border: 1px dashed rgba(var(--v-border-color), 0.48);
    border-radius: 10px;
    background: rgba(var(--v-theme-on-surface), 0.018);
    gap: 10px;
    grid-template-columns: auto minmax(0, 1fr) auto;
  }

  code {
    overflow: hidden;
    color: rgb(var(--v-theme-on-surface));
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.82rem;
    font-weight: 650;
    letter-spacing: 0.09em;
    text-overflow: ellipsis;
  }
}

.recovery-code-number {
  display: grid;
  inline-size: 26px;
  block-size: 26px;
  border-radius: 8px;
  background: rgba(var(--v-theme-primary), 0.09);
  color: rgb(var(--v-theme-primary));
  font-size: 0.72rem;
  font-weight: 700;
  place-items: center;
}

.recovery-warning {
  display: flex;
  align-items: center;
  padding: 12px 14px;
  margin: 0 24px 22px;
  border: 1px solid rgba(var(--v-theme-warning), 0.28);
  border-radius: 9px;
  background: rgba(var(--v-theme-warning), 0.075);
  color: rgba(var(--v-theme-on-surface), 0.75);
  font-size: 0.8rem;
  gap: 10px;

  .v-icon {
    color: rgb(var(--v-theme-warning));
  }
}

.protected-summary {
  padding: 20px;
  border: 1px solid rgba(var(--v-theme-success), 0.2);
  border-radius: 13px;
  background: rgba(var(--v-theme-success), 0.055);
}

.management-state {
  display: grid;
  gap: 18px;
}

.recovery-count {
  display: grid;
  min-inline-size: 86px;
  margin-inline-start: auto;
  text-align: end;

  strong {
    color: rgb(var(--v-theme-success));
    font-size: 1.35rem;
  }

  span {
    color: rgba(var(--v-theme-on-surface), 0.6);
    font-size: 0.72rem;
  }
}

.management-form {
  padding: 20px;
  border: 1px solid rgba(var(--v-border-color), 0.38);
  border-radius: 13px;
  background: rgba(var(--v-theme-on-surface), 0.012);

  > .section-heading {
    margin-block-end: 18px;
  }
}

.management-form__fields {
  display: grid;
  align-items: start;
  gap: 16px;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.management-code-field {
  display: grid;
  min-inline-size: 0;
  gap: 6px;
}

.management-resend {
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-block-size: 34px;
  padding-inline: 4px 0;
  gap: 8px;

  .v-btn {
    flex: 0 0 auto;
  }
}

.management-resend__copy {
  display: flex;
  min-inline-size: 0;
  align-items: center;
  margin: 0;
  color: rgba(var(--v-theme-on-surface), 0.58);
  font-size: 0.72rem;
  line-height: 1.35;
  gap: 6px;

  small {
    overflow: hidden;
    color: rgba(var(--v-theme-on-surface), 0.78);
    font-size: inherit;
    font-weight: 600;
    text-overflow: ellipsis;
    white-space: nowrap;

    &::before {
      margin-inline-end: 6px;
      color: rgba(var(--v-theme-on-surface), 0.35);
      content: '•';
    }
  }
}

.management-actions {
  display: flex;
  align-items: center;
  padding-block-start: 18px;
  margin-block-start: 10px;
  border-block-start: 1px solid rgba(var(--v-border-color), 0.3);
  flex-wrap: wrap;
  gap: 10px;
}

.management-actions__danger {
  margin-inline-start: auto;
}

.dialog-actions {
  display: flex;
  align-items: center;
  min-block-size: 72px;
  padding: 14px 28px;
  border-block-start: 1px solid rgba(var(--v-border-color), 0.65);
  gap: 10px;
}

@media (max-width: 760px) {
  .dialog-header {
    padding: 20px 18px 14px;
  }

  .dialog-heading {
    align-items: flex-start;

    h2 {
      font-size: 1.1rem;
    }

    p {
      font-size: 0.8rem;
    }
  }

  .dialog-heading__icon {
    inline-size: 38px;
    block-size: 38px;
  }

  .setup-progress {
    padding: 4px 24px 18px;
  }

  .dialog-body {
    padding: 0 18px 20px !important;
  }

  .method-grid,
  .verification-layout,
  .password-panel,
  .management-form__fields {
    grid-template-columns: 1fr;
  }

  .verification-visual {
    min-block-size: auto;
    padding: 16px;
  }

  .verification-form {
    padding: 16px;
  }

  .recovery-code-list {
    padding: 18px;
    grid-template-columns: 1fr;
  }

  .recovery-state {
    border-radius: 12px;
  }

  .recovery-state__header {
    align-items: start;
    padding: 18px;
    grid-template-columns: auto minmax(0, 1fr);
  }

  .recovery-toolbar {
    grid-column: 1 / -1;
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .recovery-warning {
    margin: 0 18px 18px;
  }

  .state-heading {
    align-items: flex-start;
  }

  .recovery-toolbar,
  .management-actions {
    display: grid;
  }

  .management-form {
    padding: 18px;
  }

  .management-resend {
    flex-wrap: wrap;

    .v-btn {
      margin-inline-start: auto;
    }
  }

  .management-actions__danger {
    margin-inline-start: 0;
  }

  .management-actions .v-btn {
    inline-size: 100%;
  }

  .recovery-count {
    min-inline-size: auto;
  }

  .dialog-actions {
    padding: 12px 18px;
  }
}

@media (max-width: 460px) {
  .recovery-toolbar {
    grid-template-columns: 1fr;
  }
}
</style>
