<!-- The shared form is an intentional parent-owned form controller. -->
<!-- eslint-disable vue/no-mutating-props -->
<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  form: Record<string, any>
  loading: boolean
  statuses: Record<string, any>[]
}>()
const dialogModel = defineModel<boolean>({ required: true })

const emit = defineEmits<{
  (event: 'submit'): void
}>()

const { t } = useI18n()
const formFieldKeys = new Set(['status', 'reason', 'note'])
const alertError = computed(() => {
  const errors = props.form.errors.value ?? {}

  if (errors.general) {
    return String(errors.general)
  }

  const unmatchedError = Object.entries(errors).find(
    ([field, message]) => !formFieldKeys.has(field) && Boolean(message),
  )

  return unmatchedError ? String(unmatchedError[1]) : ''
})

function close() {
  if (props.loading) {
    return
  }

  dialogModel.value = false
}
</script>

<template>
  <VDialog
    v-model="dialogModel"
    max-width="560"
    persistent
    scrollable
  >
    <VForm @submit.prevent="emit('submit')">
      <VCard>
        <VCardTitle class="border-b pb-2 mb-4 d-flex align-center gap-1 font-weight-bold text-h6">
          <VIcon
            color="primary"
            icon="tabler-checkup-list"
            size="20"
          />
          {{ t('reservation::frontend.change_status') }}
        </VCardTitle>

        <VCardText class="pt-2">
          <VAlert
            v-if="alertError"
            class="mb-4"
            type="error"
            variant="tonal"
          >
            {{ alertError }}
          </VAlert>

          <VRow>
            <VCol cols="12">
              <VSelect
                v-model="form.state.status"
                :error="!!form.errors.value?.status"
                :error-messages="form.errors.value?.status"
                item-title="name"
                item-value="id"
                :items="statuses"
                :label="t('reservation::frontend.select_status')"
              />
            </VCol>

            <VCol cols="12">
              <VTextarea
                v-model="form.state.reason"
                :error="!!form.errors.value?.reason"
                :error-messages="form.errors.value?.reason"
                :label="t('reservation::attributes.reservation_status_logs.reason')"
                rows="3"
              />
            </VCol>

            <VCol cols="12">
              <VTextarea
                v-model="form.state.note"
                :error="!!form.errors.value?.note"
                :error-messages="form.errors.value?.note"
                :label="t('reservation::attributes.reservation_status_logs.note')"
                rows="2"
              />
            </VCol>
          </VRow>
        </VCardText>

        <VCardActions>
          <VSpacer />

          <VBtn
            color="default"
            :disabled="loading"
            @click="close"
          >
            {{ t('admin::admin.buttons.cancel') }}
          </VBtn>

          <VBtn
            color="primary"
            :disabled="loading || !form.state.status"
            :loading="loading"
            prepend-icon="tabler-device-floppy"
            type="submit"
          >
            {{ t('reservation::frontend.update_status') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VForm>
  </VDialog>
</template>
