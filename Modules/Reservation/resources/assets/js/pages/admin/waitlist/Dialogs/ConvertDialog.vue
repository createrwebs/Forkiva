<script lang="ts" setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import ConvertReservationForm from '../Partials/ConvertReservationForm.vue'

const props = defineProps<{
  item: Record<string, unknown>
}>()
const dialogModel = defineModel<boolean>({ required: true })

const emit = defineEmits<{
  saved: [reservation: Record<string, unknown>]
  submitting: [value: boolean]
}>()

const { t } = useI18n()
const formRef = ref<InstanceType<typeof ConvertReservationForm> | null>(null)
const submitting = ref(false)

function close() {
  if (submitting.value) {
    return
  }

  emit('submitting', false)
  dialogModel.value = false
}

function onSaved(reservation: Record<string, unknown>) {
  emit('saved', reservation)
  close()
}

async function submit() {
  if (submitting.value) {
    return
  }

  submitting.value = true
  emit('submitting', true)

  try {
    await formRef.value?.submit()
  } finally {
    submitting.value = false
    emit('submitting', false)
  }
}
</script>

<template>
  <VDialog
    v-model="dialogModel"
    max-width="640"
    persistent
    scrollable
  >
    <VForm @submit.prevent="submit">
      <VCard>
        <VCardTitle class="border-b pb-2 mb-4 d-flex align-center gap-1 font-weight-bold text-h6">
          <VIcon
            color="info"
            icon="tabler-calendar-plus"
            size="20"
          />
          {{ t('reservation::frontend.convert_to_reservation') }}
        </VCardTitle>

        <VCardText class="pt-2">
          <ConvertReservationForm
            ref="formRef"
            :item="props.item"
            @saved="onSaved"
          />
        </VCardText>

        <VCardActions>
          <VSpacer />

          <VBtn
            color="default"
            :disabled="submitting"
            @click="close"
          >
            {{ t('admin::admin.buttons.cancel') }}
          </VBtn>

          <VBtn
            color="info"
            :disabled="submitting"
            :loading="submitting"
            prepend-icon="tabler-calendar-plus"
            @click="submit"
          >
            {{ t('admin::admin.buttons.submit') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VForm>
  </VDialog>
</template>
