<script lang="ts" setup>
import type { UseCart } from '@/modules/cart/composables/cart.ts'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { useConfirmDialog } from '@/modules/core/composables/confirmDialog.ts'
import GiftItem from './GiftItem.vue'

const props = defineProps<{
  gifts: Record<string, any>[]
  customerId: number
  cart: UseCart
}>()
const emit = defineEmits(['applied'])

const { t } = useI18n()
const { applyGift } = props.cart

const toast = useToast()
const confirming = ref(false)
const processing = ref({
  id: 0,
  loading: false,
})

const applyGiftToCart = async (gift: Record<string, any>) => {
  if (confirming.value || processing.value.loading) return

  confirming.value = true
  try {
    const confirmed = await useConfirmDialog({
      title: t('pos::pos_viewer.apply_confirmation.title'),
      message: t('pos::pos_viewer.apply_confirmation.message', { points_cost: gift.points_cost }),
    })
    if (!confirmed) return

    processing.value = { id: gift.id, loading: true }
    const isSuccess = await applyGift(gift.id)
    if (isSuccess) {
      toast.success(t('cart::carts.gift_added_successfully'))
      emit('applied')
    }
  } catch {
    toast.error(t('core::errors.an_unexpected_error_occurred'))
  } finally {
    confirming.value = false
    processing.value = { id: 0, loading: false }
  }
}
</script>

<template>
  <div class="gifts-grid">
    <GiftItem
      v-for="gift in gifts"
      :key="gift.id"
      :gift="gift"
      :processing="processing"
      @apply="applyGiftToCart"
    />
  </div>
</template>

<style lang="scss" scoped>
.gifts-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}
@media (max-width: 520px) {
  .gifts-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
