<script lang="ts" setup>
import type { Passkey } from '@/modules/auth/contracts/Passkey.ts'
import type { TwoFactorStatus } from '@/modules/auth/contracts/TwoFactor.ts'
import SecurityPanel from './SecurityPanel.vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import {
  deletePasskey,
  getPasskeys,
  registerPasskey,
  supportsPasskeys,
} from '@/modules/auth/api/passkey.api.ts'
import { useConfirmDialog } from '@/modules/core/composables/confirmDialog.ts'
import { getStatus, sendAccountCode } from '@/modules/auth/api/twoFactor.api.ts'

const props = defineProps<{
  modelValue: boolean
  embedded?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (e: 'busy', value: boolean): void
}>()

const { t } = useI18n()
const toast = useToast()
const passkeys = ref<Passkey[]>([])
const name = ref('')
const currentPassword = ref('')
const code = ref('')
const securityStatus = ref<TwoFactorStatus | null>(null)
const fieldErrors = ref<Record<string, string[]>>({})
const formError = ref('')
const loading = ref(true)
const registering = ref(false)
const sending = ref(false)
const deletingId = ref<number | null>(null)
const accountCodeSent = ref(false)
const passkeySupported = supportsPasskeys()
const busy = computed(() => registering.value || sending.value || deletingId.value !== null)
watch(busy, (value) => emit('busy', value), { immediate: true })
const requiresCode = computed(
  () => !!securityStatus.value?.confirmed_at && !!securityStatus.value.method,
)
const canSendCode = computed(
  () =>
    securityStatus.value?.enabled &&
    ['email', 'sms'].includes(securityStatus.value.method || '') &&
    securityStatus.value.methods.some(
      (method) => method.id === securityStatus.value?.method && method.available,
    ),
)
const currentMethod = computed(() =>
  securityStatus.value?.methods.find((method) => method.id === securityStatus.value?.method),
)
const currentDestination = computed(() => currentMethod.value?.destination)
const { remaining: resendSeconds, start: startResendTimer, stop: stopResendTimer } = useCountdown(0)
const sendCodeLabel = computed(() =>
  resendSeconds.value > 0
    ? t('user::auth.two_factor_resend_short_in', { seconds: resendSeconds.value })
    : t(accountCodeSent.value ? 'user::auth.two_factor_resend' : 'user::auth.two_factor_send'),
)

const dialogModel = computed({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value),
})

function errorMessage(error: any): string {
  if (error?.name === 'NotAllowedError') {
    return t('user::auth.passkey_cancelled')
  }

  return (
    error?.response?.data?.errors?.credential?.[0] ||
    error?.response?.data?.message ||
    error?.message ||
    t('core::errors.an_unexpected_error_occurred')
  )
}

async function loadPasskeys() {
  loading.value = true
  securityStatus.value = null

  try {
    const [response, statusResponse] = await Promise.all([getPasskeys(), getStatus()])
    passkeys.value = response.data.body.passkeys
    securityStatus.value = statusResponse.data.body
    startResendTimer(statusResponse.data.body.resend_available_in)
  } catch (error: unknown) {
    toast.error(errorMessage(error))
  } finally {
    loading.value = false
  }
}

async function addPasskey() {
  if (
    busy.value ||
    loading.value ||
    !securityStatus.value ||
    !name.value.trim() ||
    !currentPassword.value ||
    (requiresCode.value && !code.value)
  ) {
    return
  }

  registering.value = true
  fieldErrors.value = {}
  formError.value = ''

  try {
    const response = await registerPasskey(
      name.value.trim(),
      currentPassword.value,
      requiresCode.value ? code.value : undefined,
    )
    toast.success(response.data.message)
    name.value = ''
    currentPassword.value = ''
    code.value = ''
    await loadPasskeys()
  } catch (error: unknown) {
    captureError(error)
  } finally {
    registering.value = false
  }
}

function captureError(error: any) {
  const errors = error?.response?.data?.errors || {}
  const next: Record<string, string[]> = {}
  for (const field of ['name', 'current_password', 'code']) {
    if (errors[field])
      next[field] = Array.isArray(errors[field]) ? errors[field] : [String(errors[field])]
  }
  fieldErrors.value = next
  formError.value = Object.keys(next).length ? '' : errorMessage(error)
}

async function sendCode() {
  if (busy.value || !canSendCode.value || resendSeconds.value > 0) return
  sending.value = true
  formError.value = ''
  try {
    const response = await sendAccountCode()
    accountCodeSent.value = true
    startResendTimer(response.data.body.resend_available_in)
    toast.success(response.data.message)
  } catch (error: any) {
    const retryAfter = Number(error?.response?.headers?.['retry-after'])
    if (error?.response?.status === 429 && retryAfter > 0) {
      startResendTimer(retryAfter)
      return
    }
    captureError(error)
  } finally {
    sending.value = false
  }
}

function resetConfirmation() {
  currentPassword.value = ''
  code.value = ''
  fieldErrors.value = {}
  formError.value = ''
  accountCodeSent.value = false
  stopResendTimer()
}

async function removePasskey(passkey: Passkey) {
  if (busy.value) return
  const confirmed = await useConfirmDialog({
    message: t('admin::admin.delete.confirmation_message'),
    confirmButtonText: t('user::auth.passkey_remove'),
  })

  if (!confirmed || busy.value) {
    return
  }

  deletingId.value = passkey.id

  try {
    const response = await deletePasskey(passkey.id)
    toast.success(response.data.message)
    passkeys.value = passkeys.value.filter((item) => item.id !== passkey.id)
  } catch (error: unknown) {
    toast.error(errorMessage(error))
  } finally {
    deletingId.value = null
  }
}

watch(
  () => props.modelValue,
  (isOpen) => {
    if (isOpen) {
      resetConfirmation()
      securityStatus.value = null
      loadPasskeys()
    } else if (!busy.value) {
      resetConfirmation()
    }
  },
  { immediate: true },
)
</script>

<template>
  <SecurityPanel
    :embedded="embedded"
    v-model="dialogModel"
    :persistent="busy"
    max-width="920"
    scrollable
  >
    <VCard class="passkeys-dialog">
      <header class="dialog-header">
        <div class="dialog-heading">
          <span class="dialog-heading__icon"><VIcon icon="tabler-fingerprint" /></span>
          <div>
            <h2>{{ t('user::auth.passkeys') }}</h2>
            <p>{{ t('user::auth.passkey_description') }}</p>
          </div>
        </div>
        <VBtn
          v-if="!embedded"
          :aria-label="t('admin::admin.buttons.close')"
          :disabled="busy"
          color="default"
          icon="tabler-x"
          variant="text"
          @click="dialogModel = false"
        />
      </header>

      <VCardText class="dialog-body">
        <VAlert
          v-if="!passkeySupported"
          class="dialog-alert"
          color="warning"
          icon="tabler-alert-triangle"
          variant="tonal"
        >
          {{ t('user::auth.passkey_unsupported') }}
        </VAlert>

        <VAlert
          v-if="formError"
          class="dialog-alert"
          type="error"
          variant="tonal"
        >
          {{ formError }}
        </VAlert>

        <section class="passkey-form-panel">
          <div class="section-heading">
            <h3>{{ t('user::auth.passkey_add') }}</h3>
            <p>{{ t('user::auth.passkey_confirm_identity') }}</p>
          </div>
          <VTextField
            v-model="name"
            class="passkey-name-field"
            :disabled="!passkeySupported || busy || loading"
            :error-messages="fieldErrors.name"
            :label="t('user::auth.passkey_name')"
            prepend-inner-icon="tabler-key"
            hide-details="auto"
            @update:model-value="delete fieldErrors.name"
            @keyup.enter="addPasskey"
          />

          <div class="passkey-identity-fields">
            <VTextField
              v-model="currentPassword"
              :disabled="busy || loading || !passkeySupported"
              :error-messages="fieldErrors.current_password"
              :label="t('user::attributes.two_factor.current_password')"
              autocomplete="current-password"
              hide-details="auto"
              prepend-inner-icon="tabler-lock"
              type="password"
              @update:model-value="delete fieldErrors.current_password"
              @keyup.enter="addPasskey"
            />
            <div
              v-if="requiresCode"
              class="passkey-code-field"
            >
              <VTextField
                v-model="code"
                :disabled="busy || loading || !passkeySupported"
                :error-messages="fieldErrors.code"
                :label="t('user::auth.two_factor_code')"
                autocomplete="one-time-code"
                hide-details="auto"
                prepend-inner-icon="tabler-shield-check"
                @update:model-value="delete fieldErrors.code"
                @keyup.enter="addPasskey"
              />
              <div
                v-if="canSendCode"
                class="code-delivery-row"
              >
                <p>
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
                  :disabled="busy || resendSeconds > 0"
                  :loading="sending"
                  color="primary"
                  density="comfortable"
                  :prepend-icon="accountCodeSent ? 'tabler-refresh' : 'tabler-send'"
                  size="small"
                  variant="text"
                  @click="sendCode"
                >
                  {{ sendCodeLabel }}
                </VBtn>
              </div>
              <p
                v-else
                class="delivery-unavailable"
                role="status"
              >
                {{ t('user::auth.two_factor_send_unavailable') }}
              </p>
            </div>
          </div>

          <p
            v-if="requiresCode"
            class="factor-hint"
          >
            <VIcon
              icon="tabler-info-circle"
              size="17"
            />
            <span>{{ t('user::auth.passkey_factor_hint') }}</span>
          </p>

          <div class="passkey-create-actions">
            <VSpacer />
            <VBtn
              :disabled="
                !passkeySupported ||
                busy ||
                loading ||
                !securityStatus ||
                !name.trim() ||
                !currentPassword ||
                (requiresCode && !code)
              "
              :loading="registering"
              color="primary"
              prepend-icon="tabler-plus"
              @click="addPasskey"
            >
              {{ t('user::auth.passkey_add') }}
            </VBtn>
          </div>
        </section>

        <section class="passkey-list-panel">
          <div class="section-heading section-heading--compact">
            <h3>{{ t('user::auth.passkeys') }}</h3>
          </div>
          <div class="passkey-list">
            <div
              v-if="loading"
              class="passkey-state"
            >
              <VProgressCircular
                color="primary"
                indeterminate
              />
            </div>

            <div
              v-else-if="passkeys.length === 0"
              class="passkey-state"
            >
              {{ t('user::auth.passkey_empty') }}
            </div>

            <div
              v-for="passkey in passkeys"
              v-else
              :key="passkey.id"
              class="passkey-item"
            >
              <div class="passkey-item__icon">
                <VIcon icon="tabler-key" />
              </div>
              <div class="passkey-item__content">
                <strong>{{ passkey.name }}</strong>
                <span v-if="passkey.authenticator">
                  {{ passkey.authenticator }}
                </span>
                <span>
                  {{
                    passkey.last_used_at
                      ? t('user::auth.passkey_last_used', {
                          date: passkey.last_used_at,
                        })
                      : t('user::auth.passkey_never_used')
                  }}
                </span>
              </div>
              <VBtn
                :aria-label="t('user::auth.passkey_remove')"
                :disabled="busy"
                :loading="deletingId === passkey.id"
                color="error"
                icon="tabler-trash"
                size="small"
                variant="text"
                @click="removePasskey(passkey)"
              />
            </div>
          </div>
        </section>
      </VCardText>

      <footer
        v-if="!embedded"
        class="dialog-actions"
      >
        <VSpacer />
        <VBtn
          :disabled="busy"
          color="default"
          variant="text"
          @click="dialogModel = false"
        >
          {{ t('admin::admin.buttons.close') }}
        </VBtn>
      </footer>
    </VCard>
  </SecurityPanel>
</template>

<style lang="scss" scoped>
.passkeys-dialog {
  overflow: hidden;
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
  flex: 0 0 auto;
  block-size: 44px;
  border: 1px solid rgba(var(--v-theme-primary), 0.28);
  border-radius: 12px;
  color: rgb(var(--v-theme-primary));
  inline-size: 44px;
  place-items: center;

  .v-icon {
    font-size: 26px;
  }
}

.dialog-body {
  display: grid;
  padding: 8px 28px 24px !important;
  gap: 18px;
}

.dialog-alert {
  margin: 0;
}

.passkey-form-panel,
.passkey-list-panel {
  padding: 24px;
  border-radius: 14px;
  background: rgba(var(--v-theme-on-surface), 0.035);
}

.section-heading {
  margin-block-end: 18px;

  h3 {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 700;
  }

  p {
    margin: 5px 0 0;
    color: rgba(var(--v-theme-on-surface), 0.58);
    font-size: 0.875rem;
  }
}

.section-heading--compact {
  margin-block-end: 12px;
}

.passkey-name-field {
  margin-block-end: 16px;
}

.passkey-identity-fields {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  align-items: start;
  gap: 16px;
}

.passkey-code-field {
  min-inline-size: 0;
}

.code-delivery-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-block-size: 38px;
  padding-block-start: 6px;
  gap: 12px;

  p {
    display: flex;
    align-items: center;
    min-inline-size: 0;
    margin: 0;
    color: rgba(var(--v-theme-on-surface), 0.55);
    font-size: 0.78rem;
    gap: 8px;
  }

  small {
    color: rgba(var(--v-theme-on-surface), 0.7);
    font-size: inherit;
    font-weight: 600;
  }
}

.delivery-unavailable {
  margin: 7px 0 0;
  color: rgb(var(--v-theme-warning));
  font-size: 0.78rem;
  text-align: end;
}

.factor-hint {
  display: flex;
  align-items: center;
  margin: 14px 0 0;
  color: rgba(var(--v-theme-on-surface), 0.58);
  font-size: 0.8rem;
  gap: 7px;
}

.passkey-create-actions {
  display: flex;
  align-items: center;
  margin-block-start: 18px;
}

.passkey-list {
  display: grid;
  gap: 10px;
}

.passkey-state {
  display: grid;
  min-block-size: 6rem;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  place-items: center;
}

.passkey-item {
  display: grid;
  align-items: center;
  padding: 14px;
  border-radius: 12px;
  background: rgb(var(--v-theme-surface));
  grid-template-columns: auto 1fr auto;
  gap: 12px;
}

.passkey-item__icon {
  display: grid;
  block-size: 2.5rem;
  border-radius: 0.65rem;
  background: rgba(var(--v-theme-primary), 0.1);
  color: rgb(var(--v-theme-primary));
  inline-size: 2.5rem;
  place-items: center;
}

.passkey-item__content {
  display: grid;
  min-inline-size: 0;
}

.passkey-item__content span {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.8rem;
}

.dialog-actions {
  display: flex;
  align-items: center;
  min-block-size: 72px;
  padding: 16px 28px;
}

@media (max-width: 700px) {
  .dialog-header {
    padding: 20px 20px 16px;
  }

  .dialog-body {
    padding: 6px 20px 20px !important;
  }

  .passkey-form-panel,
  .passkey-list-panel {
    padding: 18px;
  }

  .passkey-identity-fields {
    grid-template-columns: 1fr;
  }

  .dialog-actions {
    padding: 14px 20px;
  }
}

@media (max-width: 480px) {
  .dialog-heading p {
    display: none;
  }

  .code-delivery-row {
    align-items: flex-start;
    flex-direction: column;
  }

  .passkey-item {
    align-items: start;
  }
}
</style>
