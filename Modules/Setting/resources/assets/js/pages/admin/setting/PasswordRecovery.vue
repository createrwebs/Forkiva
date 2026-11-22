<script setup lang="ts">
import { computed, onBeforeMount, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import PageLoader from '@/modules/core/components/PageLoader.vue'
import { useForm } from '@/modules/core/composables/form.ts'
import { useSetting } from '@/modules/setting/composables/setting.ts'

const { t } = useI18n()
const { getSettings, store, update } = useSetting()
const providers = ref({ email: false, sms: false })
const loaded = ref(false)
const form = useForm({
  password_recovery_enabled: true,
  password_recovery_email_enabled: true,
  password_recovery_sms_enabled: true,
})
const channels = ['email', 'sms'] as const
const protections = ['lifetime', 'attempts', 'resend'] as const
const busy = computed(() => form.loading.value)

async function loadSettings() {
  loaded.value = false
  const response = await getSettings('password_recovery')
  if (response) {
    const { providers: available, ...settings } = response.settings
    providers.value = available
    Object.assign(form.state, settings)
    loaded.value = true
  }
}

onBeforeMount(loadSettings)
</script>

<template>
  <PageLoader :loading="store.loading" />
  <VAlert
    v-if="!store.loading && !loaded"
    color="error"
    variant="tonal"
  >
    {{ t('core::errors.failed_to_load_data') }}
  </VAlert>
  <VForm
    v-if="!store.loading && loaded"
    class="recovery-settings"
    @submit.prevent="form.submit(() => update('password_recovery', form.state))"
  >
    <header class="recovery-intro">
      <p>{{ t('setting::settings.recovery_subheading') }}</p>
    </header>

    <div class="recovery-master">
      <div>
        <label for="password-recovery-enabled">
          {{ t('setting::attributes.settings.password_recovery_enabled') }}
        </label>
        <p>{{ t('setting::settings.recovery_master_hint') }}</p>
      </div>
      <VSwitch
        id="password-recovery-enabled"
        v-model="form.state.password_recovery_enabled"
        :aria-label="t('setting::attributes.settings.password_recovery_enabled')"
        :disabled="busy"
        color="primary"
        hide-details
        inset
      />
    </div>
    <p
      v-if="form.errors.value.password_recovery_enabled"
      class="recovery-error"
      role="alert"
    >
      {{ form.errors.value.password_recovery_enabled }}
    </p>

    <section aria-labelledby="recovery-methods-title">
      <div class="recovery-section-heading">
        <h3 id="recovery-methods-title">{{ t('setting::settings.recovery_methods') }}</h3>
        <p>{{ t('setting::settings.recovery_methods_hint') }}</p>
      </div>
      <div class="recovery-methods">
        <article
          v-for="channel in channels"
          :key="channel"
          class="recovery-method"
        >
          <div class="recovery-method__main">
            <span
              class="recovery-method__icon"
              aria-hidden="true"
            >
              <VIcon
                :icon="channel === 'email' ? 'tabler-mail' : 'tabler-message-2'"
                size="22"
              />
            </span>
            <div class="recovery-method__copy">
              <label :for="`recovery-${channel}-enabled`">
                {{ t(`setting::settings.recovery_channel_${channel}`) }}
              </label>
              <p>{{ t(`setting::settings.recovery_channel_${channel}_hint`) }}</p>
            </div>
            <VSwitch
              :id="`recovery-${channel}-enabled`"
              v-model="form.state[`password_recovery_${channel}_enabled`]"
              :aria-label="t(`setting::attributes.settings.password_recovery_${channel}_enabled`)"
              :disabled="!form.state.password_recovery_enabled || busy"
              color="primary"
              hide-details
              inset
            />
          </div>
          <p
            v-if="form.errors.value[`password_recovery_${channel}_enabled`]"
            class="recovery-error"
            role="alert"
          >
            {{ form.errors.value[`password_recovery_${channel}_enabled`] }}
          </p>
          <div
            class="recovery-method__status"
            :class="{ 'recovery-method__status--ready': providers[channel] }"
          >
            <VIcon
              :icon="providers[channel] ? 'tabler-circle-check' : 'tabler-alert-circle'"
              size="18"
            />
            <span>
              {{
                t(
                  providers[channel]
                    ? 'setting::settings.recovery_provider_ready'
                    : 'setting::settings.recovery_provider_missing',
                )
              }}
            </span>
          </div>
        </article>
      </div>
      <div class="recovery-services">
        <RouterLink :to="{ name: 'admin.services.index' }">
          {{ t('setting::settings.recovery_manage_services') }}
          <VIcon
            icon="tabler-arrow-right"
            size="20"
          />
        </RouterLink>
        <p>{{ t('setting::settings.recovery_driver_hint') }}</p>
      </div>
    </section>

    <section
      class="recovery-protection"
      aria-labelledby="recovery-protection-title"
    >
      <h3 id="recovery-protection-title">{{ t('setting::settings.recovery_protection') }}</h3>
      <dl>
        <div
          v-for="protection in protections"
          :key="protection"
        >
          <dt>{{ t(`setting::settings.recovery_${protection}_label`) }}</dt>
          <dd>{{ t(`setting::settings.recovery_${protection}_value`) }}</dd>
        </div>
      </dl>
    </section>
    <p class="recovery-session-note">{{ t('setting::settings.recovery_session_note') }}</p>

    <footer class="recovery-actions">
      <p>{{ t('setting::settings.recovery_save_hint') }}</p>
      <VBtn
        :disabled="busy"
        :loading="busy"
        type="submit"
        color="primary"
      >
        {{ t('setting::settings.recovery_save') }}
      </VBtn>
    </footer>
  </VForm>
</template>

<style scoped lang="scss">
.recovery-settings {
  max-inline-size: 64rem;
  padding: 0;
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
}
.recovery-settings p {
  margin: 0;
  line-height: 1.6;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
}
.recovery-settings h2,
.recovery-settings h3 {
  color: inherit;
  letter-spacing: -0.02em;
}
.recovery-intro {
  margin-block-end: 1rem;
}
.recovery-intro p {
  font-size: 0.875rem;
}
.recovery-master {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  background: rgba(var(--v-theme-on-surface), 0.045);
  padding: 0.625rem 0.875rem;
  border-radius: 0.625rem;
  margin-block-end: 1rem;
}
.recovery-master label,
.recovery-method label {
  font-size: 0.875rem;
  font-weight: 700;
  cursor: pointer;
}
.recovery-master p {
  font-size: 0.875rem;
  margin-block-start: 0.25rem;
}
.recovery-settings :deep(.v-switch) {
  flex: 0 0 auto;
}
.recovery-settings :deep(.v-selection-control) {
  min-block-size: 2.25rem;
}
.recovery-section-heading {
  display: flex;
  align-items: baseline;
  gap: 0.75rem;
  margin-block-end: 0.5rem;
}
.recovery-section-heading h3,
.recovery-protection h3 {
  font-size: 0.875rem;
  font-weight: 700;
  margin-block-end: 0.25rem;
}
.recovery-section-heading p {
  font-size: 0.875rem;
}
.recovery-methods {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1rem;
}
.recovery-method {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 0.625rem;
  padding: 0.875rem;
  min-inline-size: 0;
}
.recovery-method__main {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
}
.recovery-method__icon {
  display: grid;
  place-items: center;
  flex: 0 0 auto;
  inline-size: 2rem;
  block-size: 2rem;
  border-radius: 0.5rem;
  background: rgba(var(--v-theme-primary), 0.09);
  color: rgb(var(--v-theme-primary));
}
.recovery-method__copy {
  flex: 1;
  min-inline-size: 0;
}
.recovery-method__copy p {
  font-size: 0.875rem;
  margin-block-start: 0.25rem;
}
.recovery-method__status {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-block-start: 0.5rem;
  padding-block-start: 0.5rem;
  border-block-start: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.875rem;
}
.recovery-method__status--ready :deep(.v-icon) {
  color: rgb(var(--v-theme-success));
}
.recovery-services {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 0.25rem 1rem;
  margin-block: 0.75rem 1rem;
}
.recovery-services a {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  font-weight: 650;
  color: rgb(var(--v-theme-primary));
  text-decoration: none;
}
.recovery-services a:focus-visible {
  outline: 2px solid currentColor;
  outline-offset: 4px;
}
.recovery-services p {
  margin-block-start: 0.35rem;
  font-size: 0.8rem;
}
.recovery-protection {
  border-radius: 0.625rem;
  background: rgba(var(--v-theme-on-surface), 0.045);
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 0.625rem 0.875rem;
}
.recovery-protection dl {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  flex: 1;
  margin: 0;
}
.recovery-protection dl > div {
  display: flex;
  flex-direction: column-reverse;
  justify-content: flex-end;
  text-align: center;
  gap: 0.125rem;
  padding-inline: 0.75rem;
}
.recovery-protection dl > div + div {
  border-inline-start: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
.recovery-protection dt {
  font-size: 0.8rem;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
}
.recovery-protection dd {
  margin: 0;
  font-size: 1rem;
  line-height: 1.4;
  font-weight: 750;
}
.recovery-settings .recovery-session-note {
  margin-block-start: 0.75rem;
  font-size: 0.8rem;
}
.recovery-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-block-start: 1rem;
  padding-block-start: 0.75rem;
  border-block-start: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
.recovery-actions p {
  font-size: 0.8rem;
}
.recovery-actions :deep(.v-btn) {
  min-block-size: 2.25rem;
  padding-inline: 1rem;
  text-transform: none;
}
.recovery-settings .recovery-error {
  color: rgb(var(--v-theme-error));
  font-size: 0.875rem;
  margin-block: 0.5rem;
}
@media (max-width: 900px) {
  .recovery-methods {
    grid-template-columns: 1fr;
  }
}
@media (max-width: 600px) {
  .recovery-settings {
    padding-inline: 0;
  }
  .recovery-master,
  .recovery-method {
    padding: 0.75rem;
  }
  .recovery-method__main {
    gap: 0.65rem;
  }
  .recovery-method__icon {
    inline-size: 2rem;
    block-size: 2rem;
  }
  .recovery-actions {
    align-items: stretch;
    flex-direction: column;
  }
  .recovery-protection {
    display: block;
    padding: 0.75rem;
  }
  .recovery-protection dl > div {
    padding-inline: 0.35rem;
  }
  .recovery-protection dd {
    font-size: 1rem;
  }
}
</style>
