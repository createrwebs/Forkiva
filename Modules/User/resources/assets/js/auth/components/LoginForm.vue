<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import PasswordRecoveryForm from './PasswordRecoveryForm.vue'
import { useLoginForm } from '@/modules/auth/composables/useLoginForm.ts'

const { t } = useI18n()
const {
  form,
  recoveryChannels,
  showRecovery,
  closeRecovery,
  isPasswordVisible,
  passkeyLoading,
  resendLoading,
  challenge,
  resendSeconds,
  passkeyAvailable,
  disabled,
  canReturnDashboard,
  submit,
  resendCode,
  restartLogin,
  submitPasskey,
  returnToDashboard,
} = useLoginForm()
</script>

<template>
  <PasswordRecoveryForm
    v-if="showRecovery"
    @back="closeRecovery"
    @complete="closeRecovery"
  />
  <template v-else>
    <header class="login-heading">
      <h1>
        {{ challenge ? t('user::auth.two_factor_verify') : t('user::auth.login_welcome') }}
      </h1>
      <p>
        {{ t('user::auth.login_subtitle') }}
      </p>
    </header>

    <VForm
      class="login-form"
      @submit.prevent="submit"
    >
      <VTextField
        v-if="!challenge"
        id="login-identifier"
        v-model="form.state.identifier"
        :error="!!form.errors.value?.identifier"
        :error-messages="form.errors.value?.identifier"
        :label="t('user::attributes.auth.identifier')"
        autocomplete="username webauthn"
        autofocus
        hide-details="auto"
        type="text"
      />

      <VTextField
        v-if="!challenge"
        id="login-password"
        v-model="form.state.password"
        :append-inner-icon="isPasswordVisible ? 'tabler-eye' : 'tabler-eye-off'"
        :error="!!form.errors.value?.password"
        :error-messages="form.errors.value?.password"
        :label="t('user::attributes.auth.password')"
        :type="isPasswordVisible ? 'text' : 'password'"
        autocomplete="current-password"
        hide-details="auto"
        @click:append-inner="isPasswordVisible = !isPasswordVisible"
      />

      <VBtn
        v-if="!challenge && recoveryChannels.length"
        class="login-form__forgot"
        variant="text"
        size="small"
        :disabled="form.loading.value || passkeyLoading"
        @click="showRecovery = true"
      >
        {{ t('user::auth.forgot_password') }}
      </VBtn>

      <VAlert
        v-if="challenge"
        :color="challenge.delivery_failed ? 'warning' : 'info'"
        icon="tabler-shield-lock"
        variant="tonal"
      >
        {{
          challenge.delivery_failed
            ? t(
                challenge.can_resend
                  ? 'user::auth.two_factor_delivery_failed'
                  : 'user::auth.two_factor_send_unavailable',
              )
            : t('user::auth.two_factor_enter_code', {
                method: t(`user::auth.two_factor_${challenge.method}`),
              })
        }}
        <span v-if="challenge.destination">({{ challenge.destination }})</span>
      </VAlert>

      <VTextField
        v-if="challenge"
        id="login-two-factor-code"
        v-model="form.state.code"
        :disabled="form.loading.value || resendLoading"
        :error="!!form.errors.value?.code"
        :error-messages="form.errors.value?.code"
        :label="t('user::auth.two_factor_code')"
        autocomplete="one-time-code"
        autofocus
        hide-details="auto"
      />

      <VBtn
        :disabled="disabled"
        :loading="form.loading.value"
        block
        color="primary"
        :prepend-icon="challenge ? 'tabler-shield-check' : undefined"
        :append-icon="!challenge ? 'tabler-arrow-right' : undefined"
        type="submit"
      >
        {{ challenge ? t('user::auth.two_factor_verify') : t('user::auth.login_submit') }}
      </VBtn>

      <div
        v-if="challenge"
        class="login-form__challenge-actions"
      >
        <VBtn
          :disabled="form.loading.value || resendLoading"
          type="button"
          variant="text"
          @click="restartLogin"
        >
          {{ t('admin::admin.buttons.back') }}
        </VBtn>
        <VBtn
          v-if="challenge.can_resend"
          class="login-form__resend"
          :disabled="form.loading.value || resendLoading || resendSeconds > 0"
          :loading="resendLoading"
          color="primary"
          type="button"
          variant="text"
          @click="resendCode"
        >
          {{
            resendSeconds > 0
              ? t('user::auth.two_factor_resend_in', { seconds: resendSeconds })
              : t('user::auth.two_factor_resend')
          }}
        </VBtn>
      </div>

      <div
        v-if="passkeyAvailable && !challenge"
        class="login-form__divider"
      >
        <span>{{ t('user::auth.or') }}</span>
      </div>

      <VBtn
        v-if="passkeyAvailable && !challenge"
        class="login-form__passkey"
        :disabled="form.loading.value"
        :loading="passkeyLoading"
        block
        color="primary"
        prepend-icon="tabler-fingerprint"
        type="button"
        variant="outlined"
        @click="submitPasskey"
      >
        {{ t('user::auth.login_with_passkey') }}
      </VBtn>

      <VBtn
        v-if="canReturnDashboard"
        block
        color="secondary"
        variant="outlined"
        @click="returnToDashboard"
      >
        {{ t('user::auth.return_to_dashboard') }}
      </VBtn>
    </VForm>
  </template>
</template>

<style lang="scss" scoped>
.login-heading {
  text-align: center;
}
.login-heading h1 {
  margin: 0;
  color: #202b3c;
  font-size: 1.625rem;
  font-weight: 700;
  line-height: 1.3;
  letter-spacing: -0.025em;
}
.login-heading p {
  margin-block: 0.375rem 0;
  color: #68758a;
  font-size: 0.875rem;
  line-height: 1.55;
}
.login-form {
  display: grid;
  margin-block-start: 1.75rem;
  gap: 1rem;
}
.login-form :deep(.v-btn) {
  min-block-size: 2.5rem;
  border-radius: 0.5rem;
  font-size: 0.875rem;
  text-transform: none;
}
.login-form :deep(.v-btn--variant-elevated) {
  box-shadow: none;
  background: #ec7510 !important;
  color: #fff !important;
}
.login-form .login-form__passkey {
  color: #202b3c !important;
  border-color: #c4ccd8;
}
.login-form__forgot {
  justify-self: end;
  margin-block-start: -0.625rem;
  padding-inline: 0;
  color: #b95300 !important;
}
.login-form .login-form__forgot {
  min-block-size: 1.5rem;
  font-size: 0.8rem;
}
.login-form__challenge-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
}
.login-form__resend {
  margin-inline-start: auto;
}
.login-form__divider {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-block: -0.125rem;
  color: #68758a;
  font-size: 0.75rem;
}
.login-form__divider::before,
.login-form__divider::after {
  flex: 1;
  block-size: 1px;
  background: #e0e4eb;
  content: '';
}
</style>
