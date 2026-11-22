<script setup lang="ts">
import { computed, watch } from 'vue'
import InstallerField from './InstallerField.vue'
import InstallerSelect from './InstallerSelect.vue'
import type { Configuration, ConfigurationOptions, FieldErrors } from '../contracts/installer'
const props = defineProps<{
  busy: boolean
  locked: boolean
  fields: FieldErrors
  options?: ConfigurationOptions
}>()
const model = defineModel<Configuration>({ required: true })
const emit = defineEmits<{ save: [] }>()
const allowedOrigin = computed({
  get: () => model.value.passkeys_allowed_origins[0] ?? '',
  set: (value: string) => {
    model.value.passkeys_allowed_origins = value ? [value] : []
  },
})
function save() {
  model.value.passkeys_allowed_origins = allowedOrigin.value ? [allowedOrigin.value] : []
  emit('save')
}
let suggestedUrl = model.value.app_url
function errors(name: string) {
  return Object.entries(props.fields)
    .filter(([key]) => key === name || key.startsWith(`${name}.`))
    .flatMap(([, messages]) => messages)
}
watch(
  () => model.value.app_url,
  (value) => {
    if (props.locked) return
    try {
      const next = new URL(value)
      const old = new URL(suggestedUrl)
      if (model.value.passkeys_relying_party_id === old.hostname)
        model.value.passkeys_relying_party_id = next.hostname
      model.value.passkeys_allowed_origins = model.value.passkeys_allowed_origins.map((origin) =>
        origin === old.origin ? next.origin : origin,
      )
      suggestedUrl = value
    } catch {
      /* Keep edits while the URL is incomplete. */
    }
  },
)
</script>

<template>
  <form
    id="configuration-form"
    class="configuration-form"
    @submit.prevent="save"
  >
    <fieldset :disabled="busy || locked">
      <div class="form-heading">
        <h2>Your business</h2>
        <span>Application and regional defaults</span>
      </div>
      <div class="form-grid">
        <InstallerField
          v-model="model.app_name"
          label="Application name"
          name="app_name"
          :maxlength="100"
          :errors="errors('app_name')"
        />
        <InstallerField
          v-model="model.app_url"
          label="Public application URL"
          name="app_url"
          type="url"
          :maxlength="255"
          hint="Your website origin, without a trailing slash or path."
          :errors="errors('app_url')"
        />
        <InstallerSelect
          :disabled="busy || locked"
          v-model="model.supported_countries"
          label="Supported countries"
          name="supported_countries"
          multiple
          :options="options?.countries ?? []"
          :errors="errors('supported_countries')"
        />
        <InstallerSelect
          :disabled="busy || locked"
          v-model="model.default_country"
          label="Default country"
          name="default_country"
          :options="
            (options?.countries ?? []).filter((option) =>
              model.supported_countries.includes(option.id),
            )
          "
          :errors="errors('default_country')"
        />
        <InstallerSelect
          :disabled="busy || locked"
          v-model="model.supported_locales"
          label="Supported languages"
          name="supported_locales"
          multiple
          :options="options?.locales ?? []"
          :errors="errors('supported_locales')"
        />
        <InstallerSelect
          :disabled="busy || locked"
          v-model="model.default_locale"
          label="Default language"
          name="default_locale"
          :options="
            (options?.locales ?? []).filter((option) => model.supported_locales.includes(option.id))
          "
          :errors="errors('default_locale')"
        />
        <InstallerSelect
          :disabled="busy || locked"
          v-model="model.default_timezone"
          class="full-width"
          label="Timezone"
          name="default_timezone"
          :options="options?.timezones ?? []"
          :errors="errors('default_timezone')"
        />
        <InstallerSelect
          :disabled="busy || locked"
          v-model="model.supported_currencies"
          label="Supported currencies"
          name="supported_currencies"
          multiple
          :options="options?.currencies ?? []"
          :errors="errors('supported_currencies')"
        />
        <InstallerSelect
          :disabled="busy || locked"
          v-model="model.default_currency"
          label="Default currency"
          name="default_currency"
          :options="
            (options?.currencies ?? []).filter((option) =>
              model.supported_currencies.includes(option.id),
            )
          "
          :errors="errors('default_currency')"
        />
      </div>
      <p class="field-hint">
        Additional currencies start with a rate of 1. Set their exchange rates in Currency Settings
        before taking payments.
      </p>
      <div class="form-heading configuration-heading">
        <h2>Passkeys</h2>
        <span>Secure, passwordless sign-in</span>
      </div>
      <label class="checkbox-row">
        <input
          v-model="model.passkeys_enabled"
          name="passkeys_enabled"
          type="checkbox"
        />
        <span>
          <strong>Enable passkeys</strong>
          <small>Enabled by default. Use HTTPS for your public website.</small>
        </span>
      </label>
      <div
        v-if="model.passkeys_enabled"
        class="form-grid"
      >
        <InstallerField
          v-model="model.passkeys_relying_party_id"
          label="Relying-party ID"
          name="passkeys_relying_party_id"
          :maxlength="253"
          hint="Website hostname only, for example pos.example.com."
          :errors="errors('passkeys_relying_party_id')"
        />
        <InstallerField
          v-model="allowedOrigin"
          label="Allowed web origin"
          name="passkeys_allowed_origins"
          type="url"
          :maxlength="255"
          hint="Your application URL, without a trailing slash or path."
          :errors="errors('passkeys_allowed_origins')"
        />
      </div>
      <div class="form-heading configuration-heading">
        <h2>Application cache</h2>
        <span>Performance and authentication</span>
      </div>
      <label class="checkbox-row">
        <input
          v-model="model.cache_enabled"
          name="cache_enabled"
          type="checkbox"
        />
        <span>
          <strong>Enable application caching</strong>
          <small>
            Authentication challenges always use persistent storage, even when application caching
            is off.
          </small>
        </span>
      </label>
      <InstallerSelect
        :disabled="busy || locked"
        v-model="model.cache_store"
        label="Cache storage"
        name="cache_store"
        :options="[
          { id: 'file', name: 'File' },
          { id: 'database', name: 'Database' },
        ]"
        :errors="errors('cache_store')"
      />
      <p class="field-hint">
        Passkeys, two-factor authentication, password recovery, and other cache consumers share this
        store. Cart mutation locks always use files.
      </p>
    </fieldset>
  </form>
</template>
