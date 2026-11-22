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
          icon="tabler-calendar-check"
          size="20"
        />
        <span>{{ t('reservation::frontend.booking_policy') }}</span>
      </div>
    </VCardTitle>

    <VCardText>
      <VRow>
        <VCol cols="12">
          <VCheckbox
            v-model="form.state.auto_confirm_enabled"
            :label="t('reservation::attributes.reservation_settings.auto_confirm_enabled')"
          />
        </VCol>

        <VCol cols="12">
          <VCheckbox
            v-model="form.state.cancellation_allowed"
            :label="t('reservation::attributes.reservation_settings.cancellation_allowed')"
          />
        </VCol>

        <VCol
          v-if="form.state.cancellation_allowed"
          cols="12"
          md="6"
        >
          <VCheckbox
            v-model="form.state.require_cancellation_reason"
            :label="t('reservation::attributes.reservation_settings.require_cancellation_reason')"
          />
        </VCol>

        <VCol
          v-if="form.state.cancellation_allowed"
          cols="12"
          md="6"
        >
          <VTextField
            v-model="form.state.cancellation_deadline_minutes"
            v-integer-en
            :error="!!form.errors.value?.cancellation_deadline_minutes"
            :error-messages="form.errors.value?.cancellation_deadline_minutes"
            :label="t('reservation::attributes.reservation_settings.cancellation_deadline_minutes')"
          />
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>
