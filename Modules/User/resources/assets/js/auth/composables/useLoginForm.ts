import { getApiErrorMessage } from '@/modules/core/api/errors'
import { computed, onMounted, ref, watch } from 'vue'
import { useCountdown } from '@vueuse/core'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { getRecoveryOptions } from '@/modules/auth/api/passwordRecovery.api.ts'
import type { RecoveryChannel } from '@/modules/auth/contracts/PasswordRecovery.ts'
import { supportsPasskeys } from '@/modules/auth/api/passkey.api.ts'
import { resendLogin } from '@/modules/auth/api/twoFactor.api.ts'
import type { TwoFactorChallenge } from '@/modules/auth/contracts/TwoFactor.ts'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import { useForm } from '@/modules/core/composables/form.ts'
import { useAppStore } from '@/modules/core/stores/appStore.ts'

export function useLoginForm() {
  const auth = useAuth()
  const appStore = useAppStore()
  const { t } = useI18n()
  const router = useRouter()
  const route = useRoute()
  const toast = useToast()

  const form = useForm({
    identifier: '',
    password: '',
    code: '',
  })

  const recoveryChannels = ref<RecoveryChannel[]>([])
  const showRecovery = ref(false)
  onMounted(async () => {
    try {
      const response = await getRecoveryOptions()
      recoveryChannels.value = response.data.body.channels
    } catch {
      recoveryChannels.value = []
    }
  })
  function closeRecovery() {
    showRecovery.value = false
    form.state.password = ''
    form.resetErrors()
  }
  const isPasswordVisible = ref(false)
  const passkeyLoading = ref(false)
  const resendLoading = ref(false)
  const challenge = ref<TwoFactorChallenge | null>(null)
  const {
    remaining: resendSeconds,
    start: startResendTimer,
    stop: stopResendTimer,
  } = useCountdown(0)
  const passkeyAvailable = computed(() => appStore.settings.passkeys_enabled && supportsPasskeys())

  const disabled = computed(
    () =>
      form.loading.value ||
      passkeyLoading.value ||
      resendLoading.value ||
      (challenge.value ? !form.state.code : !form.state.identifier || !form.state.password),
  )
  const isAddAccountMode = computed(() => route.query?.add_account === '1')
  const canReturnDashboard = computed(() => isAddAccountMode.value && auth.store.isAuthenticated)

  async function submit() {
    if (form.loading.value || resendLoading.value || passkeyLoading.value) return
    await form.submit(async () => {
      if (challenge.value) {
        try {
          await auth.verifyTwoFactor(challenge.value.challenge_token, form.state.code)
        } catch (error: any) {
          const message = error?.response?.data?.message
          if (error?.response?.status === 422 && message && !error.response.data.errors?.code) {
            error.response.data.errors = {
              ...error.response.data.errors,
              code: [message],
            }
          }
          throw error
        }
        return
      }

      const result = await auth.login(form.state.identifier, form.state.password)
      if (result) {
        challenge.value = result
        form.state.code = ''
        if (result.can_resend) startResendTimer(result.resend_available_in)
      }
    })
  }

  watch(
    () => form.state.code,
    () => {
      if (form.errors.value.code) form.errors.value.code = ''
    },
  )

  async function resendCode() {
    if (!challenge.value || form.loading.value || resendLoading.value || resendSeconds.value > 0) {
      return
    }

    resendLoading.value = true
    try {
      const response = await resendLogin(challenge.value.challenge_token)
      challenge.value = response.data.body
      startResendTimer(response.data.body.resend_available_in)
      if (response.data.body.delivery_failed) toast.warning(response.data.message)
      else toast.success(response.data.message)
    } catch (error: unknown) {
      toast.error(getApiErrorMessage(error) || t('core::errors.an_unexpected_error_occurred'))
    } finally {
      resendLoading.value = false
    }
  }

  function restartLogin() {
    if (form.loading.value || resendLoading.value) return
    stopResendTimer()
    challenge.value = null
    form.state.code = ''
    form.resetErrors()
  }

  async function submitPasskey() {
    if (form.loading.value || passkeyLoading.value) return
    passkeyLoading.value = true

    try {
      await auth.loginWithPasskey()
    } catch (error: any) {
      const message =
        error?.name === 'NotAllowedError'
          ? t('user::auth.passkey_cancelled')
          : error?.response?.data?.errors?.credential?.[0] ||
            error?.response?.data?.message ||
            error?.message ||
            t('core::errors.an_unexpected_error_occurred')

      toast.error(message)
    } finally {
      passkeyLoading.value = false
    }
  }

  async function returnToDashboard() {
    await router.push(auth.getDefaultRoute())
  }

  return {
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
  }
}
