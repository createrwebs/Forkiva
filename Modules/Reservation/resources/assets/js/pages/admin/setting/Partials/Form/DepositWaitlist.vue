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
          icon="tabler-cash-banknote"
          size="20"
        />
        <span>{{ t('reservation::frontend.deposit_waitlist') }}</span>
      </div>
    </VCardTitle>

    <VCardText>
      <VRow>
        <VCol cols="12">
          <VCheckbox
            v-model="form.state.deposit_enabled"
            :label="t('reservation::attributes.reservation_settings.deposit_enabled')"
          />
        </VCol>

        <template v-if="form.state.deposit_enabled">
          <VCol
            cols="12"
            md="6"
          >
            <VCheckbox
              v-model="form.state.deposit_required_for_large_party"
              :label="
                t('reservation::attributes.reservation_settings.deposit_required_for_large_party')
              "
            />
          </VCol>

          <VCol
            cols="12"
            md="6"
          >
            <VCheckbox
              v-model="form.state.deposit_refundable"
              :label="t('reservation::attributes.reservation_settings.deposit_refundable')"
            />
          </VCol>

          <VCol
            cols="12"
            md="6"
          >
            <VTextField
              v-model="form.state.deposit_amount"
              v-decimal-en
              :error="!!form.errors.value?.deposit_amount"
              :error-messages="form.errors.value?.deposit_amount"
              :label="t('reservation::attributes.reservation_settings.deposit_amount')"
            />
          </VCol>

          <VCol
            cols="12"
            md="6"
          >
            <VTextField
              v-model="form.state.deposit_payment_deadline_minutes"
              v-integer-en
              :error="!!form.errors.value?.deposit_payment_deadline_minutes"
              :error-messages="form.errors.value?.deposit_payment_deadline_minutes"
              :label="
                t('reservation::attributes.reservation_settings.deposit_payment_deadline_minutes')
              "
            />
          </VCol>
        </template>

        <VCol cols="12">
          <VCheckbox
            v-model="form.state.waitlist_enabled"
            :label="t('reservation::attributes.reservation_settings.waitlist_enabled')"
          />
        </VCol>

        <VCol
          v-if="form.state.waitlist_enabled"
          cols="12"
          md="6"
        >
          <VTextField
            v-model="form.state.waitlist_expiry_minutes"
            v-integer-en
            :error="!!form.errors.value?.waitlist_expiry_minutes"
            :error-messages="form.errors.value?.waitlist_expiry_minutes"
            :label="t('reservation::attributes.reservation_settings.waitlist_expiry_minutes')"
          />
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>
