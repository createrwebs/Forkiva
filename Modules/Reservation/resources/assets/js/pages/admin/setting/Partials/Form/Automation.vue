<!-- The shared form is an intentional parent-owned form controller. -->
<!-- eslint-disable vue/no-mutating-props -->
<script lang="ts" setup>
import { useI18n } from 'vue-i18n'

defineProps<{
  form: any
}>()

const { t } = useI18n()
</script>

<template>
  <VCard>
    <VCardTitle class="d-flex justify-space-between align-center mb-2">
      <div class="d-flex align-center">
        <VIcon
          class="me-2"
          icon="tabler-bolt"
          size="20"
        />
        <span>{{ t('reservation::frontend.automation') }}</span>
      </div>
    </VCardTitle>

    <VCardText>
      <VRow>
        <VCol
          cols="12"
          md="6"
        >
          <VCheckbox
            v-model="form.state.require_table_on_create"
            :label="t('reservation::attributes.reservation_settings.require_table_on_create')"
          />
        </VCol>

        <VCol
          cols="12"
          md="6"
        >
          <VCheckbox
            v-model="form.state.require_table_on_confirm"
            :label="t('reservation::attributes.reservation_settings.require_table_on_confirm')"
          />
        </VCol>

        <VCol
          cols="12"
          md="6"
        >
          <VCheckbox
            v-model="form.state.require_table_on_arrival"
            :label="t('reservation::attributes.reservation_settings.require_table_on_arrival')"
          />
        </VCol>

        <VCol
          cols="12"
          md="6"
        >
          <VCheckbox
            v-model="form.state.require_table_on_seating"
            :label="t('reservation::attributes.reservation_settings.require_table_on_seating')"
          />
        </VCol>

        <VCol
          cols="12"
          md="6"
        >
          <VCheckbox
            v-model="form.state.reminder_enabled"
            :label="t('reservation::attributes.reservation_settings.reminder_enabled')"
          />
        </VCol>

        <VCol
          v-if="form.state.reminder_enabled"
          cols="12"
          md="6"
        >
          <VTextField
            v-model="form.state.reminder_before_minutes"
            v-integer-en
            :error="!!form.errors.value?.reminder_before_minutes"
            :error-messages="form.errors.value?.reminder_before_minutes"
            :label="t('reservation::attributes.reservation_settings.reminder_before_minutes')"
          />
        </VCol>

        <VCol
          cols="12"
          md="6"
        >
          <VCheckbox
            v-model="form.state.auto_no_show_enabled"
            :label="t('reservation::attributes.reservation_settings.auto_no_show_enabled')"
          />
        </VCol>

        <VCol
          v-if="form.state.auto_no_show_enabled"
          cols="12"
          md="6"
        >
          <VTextField
            v-model="form.state.grace_period_minutes"
            v-integer-en
            :error="!!form.errors.value?.grace_period_minutes"
            :error-messages="form.errors.value?.grace_period_minutes"
            :label="t('reservation::attributes.reservation_settings.grace_period_minutes')"
          />
        </VCol>

        <VCol
          v-if="form.state.auto_no_show_enabled"
          cols="12"
          md="6"
        >
          <VTextField
            v-model="form.state.pending_expiry_minutes"
            v-integer-en
            :error="!!form.errors.value?.pending_expiry_minutes"
            :error-messages="form.errors.value?.pending_expiry_minutes"
            :label="t('reservation::attributes.reservation_settings.pending_expiry_minutes')"
          />
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>
