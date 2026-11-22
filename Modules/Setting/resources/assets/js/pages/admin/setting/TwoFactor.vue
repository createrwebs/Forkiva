<script lang="ts" setup>
import type {
  TwoFactorSettings,
  TwoFactorSettingsResponse,
} from '@/modules/setting/contracts/Settings.ts'
import { useI18n } from 'vue-i18n'
import PageLoader from '@/modules/core/components/PageLoader.vue'
import { useForm } from '@/modules/core/composables/form.ts'
import { useSetting } from '@/modules/setting/composables/setting.ts'

const { getSettings, store, update } = useSetting()
const { t } = useI18n()

const form = useForm<TwoFactorSettings>({
  two_factor_enabled: false,
  two_factor_authenticator_enabled: true,
  two_factor_email_enabled: false,
  two_factor_sms_enabled: false,
})

onBeforeMount(async () => {
  const response: TwoFactorSettingsResponse | false = await getSettings('two_factor')

  if (response !== false) {
    Object.assign(form.state, response.settings)
  }
})

function submit() {
  form.submit(() => update('two_factor', form.state))
}
</script>

<template>
  <PageLoader :loading="store.loading" />

  <VForm
    v-if="!store.loading"
    @submit.prevent="submit"
  >
    <VRow>
      <VCol cols="12">
        <VAlert
          color="info"
          icon="tabler-info-circle"
          variant="tonal"
        >
          {{ t('setting::settings.two_factor_description') }}
        </VAlert>
      </VCol>

      <VCol cols="12">
        <VSwitch
          v-model="form.state.two_factor_enabled"
          :error="!!form.errors.value?.two_factor_enabled"
          :error-messages="form.errors.value?.two_factor_enabled"
          :label="t('setting::attributes.settings.two_factor_enabled')"
        />
      </VCol>

      <VCol
        cols="12"
        md="4"
      >
        <VSwitch
          v-model="form.state.two_factor_authenticator_enabled"
          :disabled="!form.state.two_factor_enabled"
          :label="t('setting::attributes.settings.two_factor_authenticator_enabled')"
        />
      </VCol>

      <VCol
        cols="12"
        md="4"
      >
        <VSwitch
          v-model="form.state.two_factor_email_enabled"
          :disabled="!form.state.two_factor_enabled"
          :hint="t('setting::settings.two_factor_email_hint')"
          :label="t('setting::attributes.settings.two_factor_email_enabled')"
          persistent-hint
        />
      </VCol>

      <VCol
        cols="12"
        md="4"
      >
        <VSwitch
          v-model="form.state.two_factor_sms_enabled"
          :disabled="!form.state.two_factor_enabled"
          :hint="t('setting::settings.two_factor_sms_hint')"
          :label="t('setting::attributes.settings.two_factor_sms_enabled')"
          persistent-hint
        />
      </VCol>
    </VRow>

    <VBtn
      :disabled="form.loading.value"
      :loading="form.loading.value"
      prepend-icon="tabler-device-floppy"
      type="submit"
    >
      {{ t('admin::admin.buttons.save') }}
    </VBtn>
  </VForm>
</template>
