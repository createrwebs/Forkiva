<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import { useForm } from '@/modules/core/composables/form.ts'
import { useStockTransfer } from '@/modules/inventory/composables/stockTransfer.ts'

const props = defineProps<{
  modelValue: boolean
  transferId: number
  action: 'close' | 'cancel' | 'void'
}>()
const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (e: 'saved'): void
}>()

const { t } = useI18n()
const { closeStockTransfer, cancelStockTransfer, voidStockTransfer } = useStockTransfer()
const form = useForm({ reason: '' })
const dialogModel = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
})
const color = computed(() => (props.action === 'close' ? 'warning' : 'error'))

async function submit(): Promise<void> {
  const callback = {
    close: closeStockTransfer,
    cancel: cancelStockTransfer,
    void: voidStockTransfer,
  }[props.action]
  const saved = await form.submit(() => callback(props.transferId, form.state.reason))
  if (!saved) return
  emit('saved')
  emit('update:modelValue', false)
}
</script>

<template>
  <VDialog
    v-model="dialogModel"
    max-width="560"
    persistent
  >
    <VCard>
      <VCardTitle>{{ t(`inventory::stock_transfers.actions.${action}`) }}</VCardTitle>

      <VCardText>
        <VAlert
          class="mb-4"
          :color="color"
          variant="tonal"
        >
          {{ t(`inventory::stock_transfers.confirm.${action}`) }}
        </VAlert>

        <VTextarea
          v-model="form.state.reason"
          autofocus
          :error-messages="form.errors.value?.reason"
          :label="t('inventory::attributes.stock_transfers.reason')"
          rows="4"
        />
      </VCardText>

      <VCardActions>
        <VSpacer />

        <VBtn
          :disabled="form.loading.value"
          variant="text"
          @click="dialogModel = false"
        >
          {{ t('admin::admin.buttons.cancel') }}
        </VBtn>

        <VBtn
          :color="color"
          :disabled="!form.state.reason.trim()"
          :loading="form.loading.value"
          @click="submit"
        >
          {{ t(`inventory::stock_transfers.actions.${action}`) }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
