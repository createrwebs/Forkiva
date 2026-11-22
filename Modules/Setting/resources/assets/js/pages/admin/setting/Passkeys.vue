<script lang="ts" setup>
import type {
  PasskeySettings,
  PasskeySettingsResponse,
} from '@/modules/setting/contracts/Settings.ts'
import { useI18n } from 'vue-i18n'
import PageLoader from '@/modules/core/components/PageLoader.vue'
import { useForm } from '@/modules/core/composables/form.ts'
import { useSetting } from '@/modules/setting/composables/setting.ts'

const { getSettings, store, update } = useSetting()
const { t } = useI18n()

const form = useForm<PasskeySettings>({
  passkeys_enabled: true,
  passkeys_relying_party_id: '',
  passkeys_allowed_origins: [],
  passkeys_timeout: 60000,
})

onBeforeMount(async () => {
  const response: PasskeySettingsResponse | false = await getSettings('passkeys')

  if (response !== false) {
    Object.assign(form.state, response.settings)
  }
})

function submit() {
  form.submit(() => update('passkeys', form.state))
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
          {{ t('setting::settings.passkeys_description') }}
        </VAlert>
      </VCol>

      <VCol cols="12">
        <VSwitch
          v-model="form.state.passkeys_enabled"
          :error="!!form.errors.value?.passkeys_enabled"
          :error-messages="form.errors.value?.passkeys_enabled"
          :label="t('setting::attributes.settings.passkeys_enabled')"
        />
      </VCol>

      <VCol cols="12">
        <VTextField
          v-model="form.state.passkeys_relying_party_id"
          :disabled="!form.state.passkeys_enabled"
          :error="!!form.errors.value?.passkeys_relying_party_id"
          :error-messages="form.errors.value?.passkeys_relying_party_id"
          :hint="t('setting::settings.passkeys_domain_hint')"
          :label="t('setting::attributes.settings.passkeys_relying_party_id')"
          persistent-hint
        />
      </VCol>

      <VCol cols="12">
        <VCombobox
          v-model="form.state.passkeys_allowed_origins"
          chips
          closable-chips
          :disabled="!form.state.passkeys_enabled"
          :error="!!form.errors.value?.passkeys_allowed_origins"
          :error-messages="form.errors.value?.passkeys_allowed_origins"
          :hint="t('setting::settings.passkeys_origins_hint')"
          :label="t('setting::attributes.settings.passkeys_allowed_origins')"
          multiple
          persistent-hint
        />
      </VCol>

      <VCol
        cols="12"
        md="6"
      >
        <VTextField
          v-model.number="form.state.passkeys_timeout"
          :disabled="!form.state.passkeys_enabled"
          :error="!!form.errors.value?.passkeys_timeout"
          :error-messages="form.errors.value?.passkeys_timeout"
          :hint="t('setting::settings.passkeys_timeout_hint')"
          :label="t('setting::attributes.settings.passkeys_timeout')"
          max="300000"
          min="15000"
          persistent-hint
          step="1000"
          type="number"
        />
      </VCol>
    </VRow>

    <VRow>
      <VCol cols="12">
        <VBtn
          :disabled="form.loading.value"
          :loading="form.loading.value"
          type="submit"
        >
          <VIcon
            icon="tabler-device-floppy"
            start
          />
          {{ t('admin::admin.buttons.save') }}
        </VBtn>
      </VCol>
    </VRow>
  </VForm>
</template>
