<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import { useForm } from '@/modules/core/composables/form.ts'
import { generateUuid } from '@/modules/core/utils/uuid.ts'
import { useStockTransfer } from '@/modules/inventory/composables/stockTransfer.ts'

const props = defineProps<{
  modelValue: boolean
  transfer: Record<string, any>
}>()
const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (e: 'saved'): void
}>()

const { t } = useI18n()
const { receiveStockTransfer } = useStockTransfer()
const form = useForm({
  idempotency_key: generateUuid(),
  notes: null,
  items: props.transfer.items
    .filter((item: Record<string, any>) => Number(item.remaining_quantity) > 0)
    .map((item: Record<string, any>) => ({
      stock_transfer_item_id: item.id,
      quantity: null,
    })),
})
const dialogModel = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
})
const hasQuantity = computed(() =>
  form.state.items.some((item: Record<string, any>) => Number(item.quantity) > 0),
)

function transferItem(id: number): Record<string, any> {
  return props.transfer.items.find((item: Record<string, any>) => item.id === id)
}

async function submit(): Promise<void> {
  const saved = await form.submit(() =>
    receiveStockTransfer(props.transfer.id, {
      ...form.state,
      items: form.state.items.filter((item: Record<string, any>) => Number(item.quantity) > 0),
    }),
  )
  if (!saved) return
  emit('saved')
  emit('update:modelValue', false)
}
</script>

<template>
  <VDialog
    v-model="dialogModel"
    max-width="850"
    persistent
    scrollable
  >
    <VCard>
      <VCardTitle>{{ t('inventory::stock_transfers.actions.receive') }}</VCardTitle>

      <VCardText>
        <VAlert
          v-if="form.errors.value?.items || form.errors.value?.general"
          class="mb-4"
          color="error"
          variant="tonal"
        >
          {{ form.errors.value.items || form.errors.value.general }}
        </VAlert>

        <div
          v-for="(receiptItem, index) in form.state.items"
          :key="receiptItem.stock_transfer_item_id"
          class="receipt-line mb-3"
        >
          <div>
            <div class="font-weight-medium">
              {{ transferItem(receiptItem.stock_transfer_item_id)?.destination_ingredient?.name }}
            </div>

            <div class="text-caption text-medium-emphasis">
              {{ t('inventory::stock_transfers.show.remaining') }}:
              {{ transferItem(receiptItem.stock_transfer_item_id)?.remaining_quantity }}
              {{ transferItem(receiptItem.stock_transfer_item_id)?.unit?.symbol }}
            </div>
          </div>

          <VTextField
            v-model="receiptItem.quantity"
            v-decimal-en
            class="receipt-line__quantity"
            :error-messages="form.errors.value?.[`items.${index}.quantity`]"
            :label="t('inventory::attributes.stock_transfer_receipts.items.*.quantity')"
            :suffix="transferItem(receiptItem.stock_transfer_item_id)?.unit?.symbol"
          />
        </div>

        <VTextarea
          v-model="form.state.notes"
          :error-messages="form.errors.value?.notes"
          :label="t('inventory::attributes.stock_transfer_receipts.notes')"
          rows="3"
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
          color="primary"
          :disabled="!hasQuantity"
          :loading="form.loading.value"
          @click="submit"
        >
          {{ t('inventory::stock_transfers.actions.receive') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.receipt-line {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(220px, 280px);
  gap: 16px;
  align-items: center;
  padding: 12px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px;
}

.receipt-line__quantity {
  min-width: 0;
}

@media (max-width: 600px) {
  .receipt-line {
    grid-template-columns: 1fr;
  }
}
</style>
