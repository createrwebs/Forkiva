<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import DatePicker from '@/modules/core/components/Form/DatePicker.vue'
import { useGiftCardTransaction } from '@/modules/giftcard/composables/giftCardTransaction.ts'
import {
  giftCardActionGeneralError,
  useRetainedIdempotencyKey,
} from '@/modules/giftcard/utils/giftCardLifecycle.ts'

const props = defineProps<{
  modelValue: boolean
  transaction: Record<string, any> | null
}>()
const emit = defineEmits(['update:modelValue', 'saved'])
const { t } = useI18n()
const { reverseTransaction } = useGiftCardTransaction()
const loading = ref(false)
const errors = ref<Record<string, string[]>>({})
const generalError = ref('')
const idempotency = useRetainedIdempotencyKey()
const form = reactive({
  reason: '',
  economic_date: new Date().toISOString().slice(0, 10),
})

watch(
  () => props.modelValue,
  (open) => {
    if (open) {
      errors.value = {}
      generalError.value = ''
    }
  },
)

function close() {
  if (!loading.value) emit('update:modelValue', false)
}

async function submit() {
  if (!props.transaction) return
  loading.value = true
  errors.value = {}
  generalError.value = ''
  const response = await reverseTransaction(props.transaction.id, {
    ...form,
    idempotency_key: idempotency.key.value,
  })
  loading.value = false
  if (response.status >= 200 && response.status < 300) {
    idempotency.regenerateAfterSuccess()
    emit('saved', response.data)
    emit('update:modelValue', false)
  } else {
    errors.value = response.errors || {}
    generalError.value = giftCardActionGeneralError(
      errors.value,
      response.message,
      t('core::errors.an_unexpected_error_occurred'),
    )
  }
}
</script>

<template>
  <VDialog
    max-width="600"
    :model-value="modelValue"
    persistent
    @update:model-value="close"
  >
    <VCard>
      <VCardTitle>{{ t('giftcard::gift_card_transactions.actions.reverse') }}</VCardTitle>

      <VCardText>
        <VAlert
          v-if="generalError"
          class="mb-4"
          closable
          type="error"
          variant="tonal"
          @click:close="generalError = ''"
        >
          {{ generalError }}
        </VAlert>

        <VRow>
          <VCol cols="12">
            <DatePicker
              v-model="form.economic_date"
              :error-messages="errors.economic_date"
              :label="t('giftcard::gift_card_transactions.actions.economic_date')"
            />
          </VCol>

          <VCol cols="12">
            <VTextarea
              v-model="form.reason"
              :error-messages="errors.reason"
              :label="t('giftcard::gift_card_transactions.actions.reason')"
              rows="3"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VCardActions class="justify-end border-t pa-4">
        <VBtn
          :disabled="loading"
          variant="text"
          @click="close"
        >
          {{ t('admin::admin.buttons.cancel') }}
        </VBtn>

        <VBtn
          color="warning"
          :loading="loading"
          @click="submit"
        >
          {{ t('giftcard::gift_card_transactions.actions.reverse') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
