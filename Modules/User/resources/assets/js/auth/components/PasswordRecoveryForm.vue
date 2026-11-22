<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useCountdown } from '@vueuse/core'
import { useI18n } from 'vue-i18n'
import {
  identifyRecoveryAccount,
  requestRecoveryCode,
  verifyRecoveryCode,
  resetPassword,
} from '@/modules/auth/api/passwordRecovery.api.ts'
import type {
  RecoveryChannel,
  RecoveryChallenge,
  RecoveryIdentity,
  RecoveryGrant,
} from '@/modules/auth/contracts/PasswordRecovery.ts'
import RecoveryCodeInput from './RecoveryCodeInput.vue'
import { useForm } from '@/modules/core/composables/form.ts'
const emit = defineEmits<{ back: []; complete: [] }>()
const { t } = useI18n()
const step = ref<'identify' | 'method' | 'code' | 'password' | 'success'>('identify')
const identity = ref<RecoveryIdentity | null>(null)
const channel = ref<RecoveryChannel | null>(null)
const challenge = ref<RecoveryChallenge | null>(null)
const grant = ref<RecoveryGrant | null>(null)
const form = useForm({ identifier: '', code: '', password: '', password_confirmation: '' })
const notice = ref('')
const { remaining, start, stop } = useCountdown(0)
const busy = computed(() => form.loading.value)
const passwordVisible = ref(false)
const confirmationVisible = ref(false)
const section = ref<HTMLElement | null>(null)
watch([step, busy], async () => {
  if (busy.value) return
  await nextTick()
  const target = section.value?.querySelector<HTMLElement>('input:not([disabled]), h1')
  const input = section.value?.querySelector<HTMLInputElement>('input:not([disabled])')
  ;(step.value === 'method' ? section.value?.querySelector('h1') : (input ?? target))?.focus()
})
const title = computed(() => t(`user::auth.recovery_step_${step.value}`))
async function deliver() {
  const response = await requestRecoveryCode(channel.value!, identity.value!.recovery_token)
  challenge.value = response.data.body
  form.state.code = ''
  start(response.data.body.resend_available_in)
  step.value = 'code'
}
async function run(action: () => Promise<void>) {
  if (busy.value) return
  notice.value = ''
  form.resetErrors()
  await form.submit(
    async () => {
      try {
        await action()
      } catch (error: any) {
        const errors = error?.response?.data?.errors ?? {}
        const visibleFields =
          step.value === 'identify'
            ? ['identifier']
            : step.value === 'code'
              ? ['code']
              : step.value === 'password'
                ? ['password', 'password_confirmation', 'code']
                : []
        notice.value = Object.keys(errors).some((key) => visibleFields.includes(key))
          ? ''
          : errors.identifier?.[0] ||
            error?.response?.data?.message ||
            t('core::errors.an_unexpected_error_occurred')
        if (error?.response?.status === 429)
          start(Number(error.response.headers?.['retry-after']) || 60)
        throw error
      }
    },
    { showErrorToast: false },
  )
}
async function submit() {
  await run(async () => {
    if (step.value === 'identify') {
      if (remaining.value > 0) return
      identity.value = (await identifyRecoveryAccount(form.state.identifier)).data.body
      channel.value = null
      if (!identity.value.channels.length) {
        notice.value = t('user::auth.recovery_no_methods')
        return
      }
      if (identity.value.channels.length === 1) {
        channel.value = identity.value.channels[0]!
        await deliver()
      } else step.value = 'method'
    } else if (step.value === 'method' && channel.value && remaining.value === 0) {
      await deliver()
    } else if (step.value === 'code' && challenge.value && /^\d{6}$/.test(form.state.code)) {
      grant.value = (
        await verifyRecoveryCode(challenge.value.challenge_token, form.state.code)
      ).data.body
      form.state.code = ''
      challenge.value = null
      stop()
      step.value = 'password'
    } else if (step.value === 'password' && grant.value) {
      await resetPassword({
        reset_token: grant.value.reset_token,
        password: form.state.password,
        password_confirmation: form.state.password_confirmation,
      })
      form.state.password = ''
      form.state.password_confirmation = ''
      grant.value = null
      identity.value = null
      step.value = 'success'
    }
  })
}
async function resend() {
  if (remaining.value > 0 || step.value !== 'code') return
  await run(deliver)
}
function restart() {
  identity.value = null
  challenge.value = null
  grant.value = null
  channel.value = null
  form.state.code = ''
  form.state.password = ''
  form.state.password_confirmation = ''
  form.resetErrors()
  notice.value = ''
  stop()
  step.value = 'identify'
}
const submitDisabled = computed(
  () =>
    busy.value ||
    (step.value === 'identify' && (!form.state.identifier.trim() || remaining.value > 0)) ||
    (step.value === 'method' && (!channel.value || remaining.value > 0)) ||
    (step.value === 'code' && !/^\d{6}$/.test(form.state.code)) ||
    (step.value === 'password' && (!form.state.password || !form.state.password_confirmation)),
)
</script>

<template>
  <section
    ref="section"
    aria-labelledby="recovery-title"
  >
    <header class="recovery-heading">
      <h1
        id="recovery-title"
        tabindex="-1"
      >
        {{ title }}
      </h1>
      <p v-if="step === 'identify'">{{ t('user::auth.recovery_identifier_hint') }}</p>
      <p v-else-if="step === 'method'">{{ t('user::auth.recovery_description') }}</p>
      <p
        v-else-if="step === 'code'"
        role="status"
      >
        {{ t('user::auth.recovery_sent') }}
      </p>
    </header>
    <div
      v-if="step === 'success'"
      class="recovery-form"
      role="status"
    >
      <p class="recovery-note">{{ t('user::auth.recovery_success') }}</p>
      <VBtn
        block
        color="primary"
        @click="emit('complete')"
      >
        {{ t('user::auth.recovery_back') }}
      </VBtn>
    </div>
    <VForm
      v-else
      class="recovery-form"
      @submit.prevent="submit"
    >
      <p
        v-if="notice"
        class="recovery-notice"
        role="alert"
      >
        {{ notice }}
      </p>
      <VTextField
        v-if="step === 'identify'"
        id="recovery-identifier"
        v-model="form.state.identifier"
        :label="t('user::attributes.auth.identifier')"
        autocomplete="username"
        hide-details="auto"
        :error-messages="form.errors.value.identifier"
        :disabled="busy"
        autofocus
        dir="auto"
      />
      <fieldset
        v-if="step === 'method'"
        class="recovery-methods"
        :disabled="busy"
      >
        <legend class="sr-only">{{ t('user::auth.recovery_description') }}</legend>
        <label
          v-for="option in identity?.channels"
          :key="option"
          class="recovery-method"
          :class="{ 'is-selected': channel === option }"
        >
          <span class="recovery-method__icon">
            <VIcon
              :icon="option === 'email' ? 'tabler-mail' : 'tabler-message-2'"
              size="22"
            />
          </span>
          <span class="recovery-method__title">{{ t(`user::auth.recovery_${option}`) }}</span>
          <span class="recovery-method__description">
            {{ t(`user::auth.recovery_${option}_hint`) }}
          </span>
          <input
            v-model="channel"
            type="radio"
            name="recovery-method"
            :aria-label="t(`user::auth.recovery_${option}`)"
            :value="option"
          />
        </label>
      </fieldset>
      <RecoveryCodeInput
        v-if="step === 'code'"
        v-model="form.state.code"
        :disabled="busy"
        :error="form.errors.value.code"
      />
      <template v-if="step === 'password'">
        <VTextField
          id="recovery-password"
          v-model="form.state.password"
          :label="t('user::auth.recovery_new_password')"
          :type="passwordVisible ? 'text' : 'password'"
          autocomplete="new-password"
          hide-details="auto"
          :append-inner-icon="passwordVisible ? 'tabler-eye' : 'tabler-eye-off'"
          :error-messages="form.errors.value.password"
          :disabled="busy"
          autofocus
          @click:append-inner="passwordVisible = !passwordVisible"
        />
        <VTextField
          id="recovery-confirmation"
          v-model="form.state.password_confirmation"
          :label="t('user::auth.recovery_confirm_password')"
          :type="confirmationVisible ? 'text' : 'password'"
          autocomplete="new-password"
          hide-details="auto"
          :append-inner-icon="confirmationVisible ? 'tabler-eye' : 'tabler-eye-off'"
          :error-messages="form.errors.value.password_confirmation"
          :disabled="busy"
          @click:append-inner="confirmationVisible = !confirmationVisible"
        />
        <p class="recovery-note">{{ t('user::auth.recovery_password_hint') }}</p>
        <p
          v-if="form.errors.value.code"
          class="recovery-notice"
          role="alert"
        >
          {{ form.errors.value.code }}
        </p>
      </template>
      <VBtn
        block
        type="submit"
        color="primary"
        :loading="busy"
        :disabled="submitDisabled"
      >
        {{
          t(
            step === 'method'
              ? 'user::auth.recovery_send'
              : step === 'code'
                ? 'user::auth.recovery_verify'
                : step === 'password'
                  ? 'user::auth.recovery_reset'
                  : 'user::auth.recovery_continue',
          )
        }}
      </VBtn>
      <p
        v-if="step === 'code'"
        class="recovery-note"
      >
        {{ t('user::auth.recovery_expiry') }}
      </p>
      <VBtn
        v-if="step === 'code'"
        variant="text"
        :disabled="busy || remaining > 0"
        @click="resend"
      >
        {{
          remaining > 0
            ? t('user::auth.recovery_resend_in', { seconds: remaining })
            : t('user::auth.recovery_resend')
        }}
      </VBtn>
      <p
        v-if="remaining > 0 && (step === 'identify' || step === 'method')"
        class="recovery-note"
      >
        {{ t('user::auth.recovery_resend_in', { seconds: remaining }) }}
      </p>
      <div
        class="recovery-actions"
        :class="{ 'recovery-actions--method': step === 'method' }"
      >
        <VBtn
          v-if="step !== 'identify'"
          variant="text"
          :disabled="busy"
          @click="restart"
        >
          {{ t('user::auth.recovery_change') }}
        </VBtn>
        <VBtn
          variant="text"
          :disabled="busy"
          prepend-icon="tabler-arrow-left"
          @click="emit('back')"
        >
          {{ t('user::auth.recovery_back') }}
        </VBtn>
      </div>
    </VForm>
  </section>
</template>

<style scoped lang="scss">
.recovery-heading {
  text-align: center;
}
.recovery-heading h1 {
  color: #202b3c;
  font-size: 1.625rem;
  line-height: 1.25;
  font-weight: 700;
  letter-spacing: -0.035em;
}
.recovery-heading p,
.recovery-note {
  color: #68758a;
  font-size: 0.875rem;
  line-height: 1.6;
}
.recovery-heading p {
  margin-block: 0.5rem 0;
}
.recovery-form {
  display: grid;
  gap: 0.875rem;
  margin-block-start: 1.5rem;
}
.recovery-note {
  margin: 0;
}
.recovery-form :deep(.v-btn) {
  min-block-size: 2.5rem;
  white-space: normal;
  text-transform: none;
  font-size: 0.875rem;
}
.recovery-form :deep(.v-btn--variant-text) {
  color: #b95300 !important;
  min-block-size: 2rem;
}
.recovery-form :deep(.v-btn--variant-elevated) {
  background: #ec7510 !important;
  color: #fff !important;
  box-shadow: none;
}
.recovery-methods {
  min-inline-size: 0;
  border: 0;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem;
}
.recovery-method {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  padding: 1rem;
  border: 1px solid #dce2ea;
  border-radius: 0.75rem;
  background: #fafbfc;
  color: #202b3c;
  cursor: pointer;
  transition:
    border-color 150ms,
    background-color 150ms;
}
.recovery-method__icon {
  display: grid;
  place-items: center;
  inline-size: 2.5rem;
  block-size: 2.5rem;
  margin-block-end: 1rem;
  border: 1px solid #e5e9ef;
  border-radius: 0.625rem;
  background: #fff;
  color: #526176;
}
.recovery-method__title {
  font-size: 0.9375rem;
  font-weight: 700;
  line-height: 1.5;
}
.recovery-method__description {
  margin-block-start: 0.25rem;
  font-size: 0.75rem;
  line-height: 1.5;
  color: #68758a;
}
.recovery-method input {
  position: absolute;
  inset-block-start: 1rem;
  inset-inline-end: 1rem;
  margin: 0;
  accent-color: #d96b0c;
  inline-size: 1rem;
  block-size: 1rem;
}
.recovery-method:hover {
  border-color: #a9b4c4;
}
.recovery-method.is-selected {
  border-color: #e2761c;
  background: #fff6ed;
  box-shadow: inset 0 0 0 1px #e2761c;
}
.recovery-method.is-selected .recovery-method__icon {
  border-color: #f9ddc5;
  background: #ffead8;
  color: #b95300;
}
.recovery-method:has(input:focus-visible) {
  outline: 2px solid #d96b0c;
  outline-offset: 3px;
}
.recovery-methods:disabled .recovery-method {
  cursor: wait;
  opacity: 0.65;
}
.recovery-actions {
  display: grid;
  gap: 0.5rem;
}
.recovery-actions--method {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 0.25rem;
  padding-block-start: 0.25rem;
}
.recovery-actions--method :deep(.v-btn) {
  padding-inline: 0.25rem;
  font-size: 0.75rem;
}
@media (prefers-reduced-motion: reduce) {
  .recovery-method {
    transition: none;
  }
}
.recovery-notice {
  margin: 0;
  color: #9f341a;
  font-size: 0.8rem;
  line-height: 1.6;
}
.sr-only {
  position: absolute;
  inline-size: 1px;
  block-size: 1px;
  overflow: hidden;
  clip-path: inset(50%);
  white-space: nowrap;
}
</style>
