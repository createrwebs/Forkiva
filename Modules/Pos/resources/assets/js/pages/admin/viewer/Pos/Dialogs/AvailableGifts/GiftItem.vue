<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  gift: Record<string, any>
  processing: Record<string, any>
}>()
const emit = defineEmits(['apply'])

const { t } = useI18n()
const giftImage = computed(() => {
  const icon = props.gift.reward?.icon
  if (icon?.type !== 'image' && !icon?.mime?.startsWith('image/')) return null
  return icon.preview_image_url || icon.url || null
})

const applyGift = () => {
  if (!props.processing.loading) emit('apply', props.gift, 1)
}
</script>

<template>
  <article class="gift-item">
    <div class="gift-item__header">
      <div class="gift-item__media">
        <VImg
          v-if="giftImage"
          :src="giftImage"
          :alt="gift.reward.name"
          cover
          width="40"
          height="40"
          class="gift-item__image"
        >
          <template #error>
            <VIcon
              icon="tabler-gift"
              size="22"
            />
          </template>
        </VImg>
        <VIcon
          v-else
          icon="tabler-gift"
          size="22"
        />
      </div>
      <div class="gift-item__content">
        <h3>{{ gift.reward.name }}</h3>
        <p>{{ gift.type.name }}</p>
      </div>
    </div>
    <div class="gift-item__footer">
      <VBtn
        block
        color="primary"
        :disabled="processing.loading && processing.id !== gift.id"
        :loading="processing.loading && processing.id === gift.id"
        variant="flat"
        rounded="lg"
        class="gift-item__action"
        @click="applyGift"
      >
        <VIcon
          icon="tabler-shopping-cart-plus"
          size="18"
          start
        />
        {{ t('pos::pos_viewer.apply_to_cart') }}
      </VBtn>
    </div>
  </article>
</template>

<style lang="scss" scoped>
.gift-item {
  display: flex;
  flex-direction: column;
  min-width: 0;
  min-height: 170px;
  gap: 16px;
  padding: 15px 16px;
  border: 1px solid rgba(var(--v-theme-primary), 0.2);
  border-radius: 14px;
  background: rgba(var(--v-theme-primary), 0.035);
  &__header {
    display: grid;
    grid-template-columns: 42px minmax(0, 1fr);
    align-items: start;
    gap: 10px;
  }
  &__media {
    display: grid;
    width: 42px;
    height: 42px;
    place-items: center;
    overflow: hidden;
    border: 1px solid rgba(var(--v-theme-primary), 0.16);
    border-radius: 12px;
    color: rgb(var(--v-theme-primary));
    background: rgba(var(--v-theme-primary), 0.08);
  }
  &__image {
    border-radius: 11px;
    overflow: hidden;
  }
  &__content {
    min-width: 0;
    h3 {
      font-size: 0.875rem;
      font-weight: 750;
      line-height: 1.45;
      overflow-wrap: anywhere;
      color: rgb(var(--v-theme-on-surface));
    }
    p {
      margin: 5px 0 0;
      font-size: 0.75rem;
      line-height: 1.4;
      color: rgba(var(--v-theme-on-surface), 0.78);
    }
  }
  &__footer {
    margin-top: auto;
    padding-top: 12px;
    border-top: 1px solid rgba(var(--v-theme-on-surface), 0.08);
  }
  &__action {
    min-height: 36px;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0;
    text-transform: none;
  }
}
</style>
