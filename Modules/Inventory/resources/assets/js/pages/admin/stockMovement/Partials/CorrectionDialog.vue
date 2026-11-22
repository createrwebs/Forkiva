<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { useStockMovement } from '@/modules/inventory/composables/stockMovement.ts'

const props = defineProps<{
  modelValue: boolean
  movement: Record<string, any>
}>()

const emit = defineEmits<{
  (event: 'update:modelValue', value: boolean): void
  (event: 'corrected'): void
}>()

const { t } = useI18n()
const toast = useToast()
const { correctStockMovement } = useStockMovement()
const loading = ref(false)
const quantity = ref<number | null>(Number(props.movement.quantity))
const reason = ref('')
const errors = ref<Record<string, string[]>>({})
const serverError = ref<string | null>(null)
const inputErrorKeys = ['quantity', 'reason']
const hasValidationErrors = computed(() => Object.keys(errors.value).length > 0)
const hasInputValidationErrors = computed(() =>
  inputErrorKeys.some((key) => !!errors.value[key]?.length),
)
const generalErrors = computed(() =>
  Object.entries(errors.value)
    .filter(([key]) => !inputErrorKeys.includes(key))
    .flatMap(([, messages]) => messages),
)

async function submit(): Promise<void> {
  loading.value = true
  errors.value = {}
  serverError.value = null

  try {
    const response = await correctStockMovement(props.movement.id, {
      quantity: Number(quantity.value),
      reason: reason.value,
    })
    toast.success(response.data.message)
    emit('corrected')
  } catch (error: any) {
    errors.value = error?.response?.data?.errors ?? {}
    const message =
      generalErrors.value[0] ??
      error?.response?.data?.message ??
      t('core::errors.an_unexpected_error_occurred')

    if (!hasValidationErrors.value) {
      serverError.value = message
      toast.error(message)

      return
    }

    if (!hasInputValidationErrors.value && message) {
      toast.error(message)
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <VDialog
    max-width="620"
    :model-value="modelValue"
    persistent
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard>
      <VCardTitle class="d-flex align-center gap-2">
        <VIcon icon="tabler-replace" />
        {{ t('inventory::stock_movements.correction.title') }}
      </VCardTitle>

      <VCardText>
        <VRow>
          <VCol cols="12">
            <VAlert
              color="warning"
              variant="tonal"
            >
              {{ t('inventory::stock_movements.correction.description') }}
            </VAlert>
          </VCol>

          <VCol
            v-if="serverError"
            cols="12"
          >
            <VAlert
              color="error"
              variant="tonal"
            >
              {{ serverError }}
            </VAlert>
          </VCol>

          <VCol
            cols="12"
            md="12"
          >
            <VTextField
              v-model="quantity"
              v-decimal-en
              :error="!!errors.quantity"
              :error-messages="errors.quantity"
              :label="t('inventory::stock_movements.correction.replacement_quantity')"
              :suffix="movement.ingredient?.symbol"
            />
          </VCol>

          <VCol cols="12">
            <VTextarea
              v-model="reason"
              :error="!!errors.reason"
              :error-messages="errors.reason"
              :label="t('inventory::stock_movements.correction.reason')"
              rows="3"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VCardActions class="justify-end">
        <VBtn
          :disabled="loading"
          variant="text"
          @click="emit('update:modelValue', false)"
        >
          {{ t('admin::admin.buttons.cancel') }}
        </VBtn>

        <VBtn
          color="warning"
          :loading="loading"
          prepend-icon="tabler-replace"
          @click="submit"
        >
          {{ t('inventory::stock_movements.actions.correct') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
