<script lang="ts" setup>
import { useI18n } from 'vue-i18n'

defineProps<{
  disabled: boolean
  quantity: number
  total: string
}>()

defineEmits<{
  (event: 'open'): void
}>()

const { t } = useI18n()
</script>

<template>
  <div class="cart-bar">
    <VBtn
      block
      class="cart-bar__button"
      color="primary"
      :disabled="disabled"
      size="large"
      variant="flat"
      @click="$emit('open')"
    >
      <VIcon
        icon="tabler-shopping-bag"
        start
      />
      <span class="cart-bar__label">
        {{ t('seatingplan::tables.table_order.view_order') }}
        <small>· {{ t('seatingplan::tables.table_order.items_count', quantity) }}</small>
      </span>
      <span class="cart-bar__total">{{ total }}</span>
    </VBtn>
  </div>
</template>
