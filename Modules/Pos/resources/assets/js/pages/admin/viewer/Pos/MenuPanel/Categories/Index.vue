<script lang="ts" setup>
import type { Category } from '@/modules/pos/contracts/posViewer.ts'
import { useI18n } from 'vue-i18n'

defineProps<{
  categories: Category[]
  activeCategories: Category[]
}>()

defineEmits(['change-root'])

const { t } = useI18n()
</script>

<template>
  <div class="category-list">
    <button
      type="button"
      class="category-item"
      :class="{ active: activeCategories.length === 0 }"
      @click="$emit('change-root')"
    >
      <VIcon
        icon="tabler-layout-grid"
        size="23"
      />
      <div class="category-name">
        {{ t('pos::pos_viewer.all_categories') }}
      </div>
    </button>
    <button
      type="button"
      v-for="category in categories"
      :key="category.id"
      class="category-item"
      :class="{
        active: activeCategories.length > 0 && activeCategories[0]?.id === category.id,
      }"
      @click="$emit('change-root', category)"
    >
      <VIcon
        icon="tabler-category"
        size="23"
      />
      <div class="category-name">
        {{ category.name }}
      </div>
    </button>
  </div>
</template>
