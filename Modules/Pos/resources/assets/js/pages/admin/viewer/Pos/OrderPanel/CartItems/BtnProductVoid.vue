<script lang="ts" setup>
import type { CartItem } from '@/modules/cart/composables/cart.ts'
import { computed, mergeProps, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = withDefaults(
  defineProps<{
    cartItem: CartItem
    label: string
    icon: string
    color: string
    action: 'cancel' | 'refund'
    loading?: boolean
    disabled?: boolean
  }>(),
  {
    loading: false,
    disabled: false,
  },
)

const emit = defineEmits(['on-submit'])

const { t } = useI18n()
const menu = ref(false)
const quantity = ref<number | string | null>(1)
const quantityNumber = computed(() => Number(quantity.value))

const disabledSubmit = computed(
  () =>
    props.disabled ||
    props.loading ||
    !Number.isInteger(quantityNumber.value) ||
    quantityNumber.value < 1 ||
    quantityNumber.value > props.cartItem.qty,
)

function submit() {
  if (!disabledSubmit.value) {
    emit('on-submit', quantityNumber.value)
    menu.value = false
  }
}
</script>

<template>
  <VMenu
    v-model="menu"
    :close-on-content-click="false"
    location="bottom end"
    max-width="calc(100vw - 32px)"
    offset="8"
  >
    <template #activator="{ props: propsMenu }">
      <VTooltip :text="label">
        <template #activator="{ props: tooltipProps }">
          <VBtn
            class="pos-cart-remove"
            :aria-label="label"
            :color="color"
            :disabled="disabled"
            :icon="icon"
            :loading="loading"
            v-bind="mergeProps(propsMenu, tooltipProps)"
            variant="tonal"
            @click.stop
          />
        </template>
      </VTooltip>
    </template>

    <VCard
      class="pos-product-void pa-4"
      :link="false"
      :ripple="false"
      rounded="lg"
      width="320"
      @click.stop
    >
      <div class="d-flex align-center ga-2 mb-1">
        <VIcon
          :color="color"
          :icon="icon"
          size="20"
        />
        <h3 class="text-subtitle-1 font-weight-bold">{{ label }}</h3>
      </div>
      <p class="text-body-2 text-medium-emphasis mb-4">{{ cartItem.item.name }}</p>
      <VTextField
        v-model="quantity"
        v-integer-en
        autofocus
        hide-details="auto"
        inputmode="numeric"
        :label="t('pos::pos_viewer.quantity')"
        @keydown.enter.prevent="submit"
      />
      <VBtn
        class="mt-4"
        block
        :color="color"
        :disabled="disabledSubmit"
        :loading="loading"
        :prepend-icon="icon"
        variant="tonal"
        @click.stop="submit"
      >
        {{ label }}
      </VBtn>
    </VCard>
  </VMenu>
</template>

<style scoped>
.pos-product-void {
  max-width: calc(100vw - 32px);
}
</style>
