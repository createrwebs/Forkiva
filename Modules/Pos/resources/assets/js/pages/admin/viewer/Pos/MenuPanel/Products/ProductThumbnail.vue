<script setup lang="ts">
defineProps<{ src?: string | null }>()
</script>

<template>
  <div
    class="pos-product-thumbnail"
    aria-hidden="true"
  >
    <VImg
      v-if="src"
      :src="src"
      alt=""
      class="pos-product-thumbnail-image"
    >
      <template #error>
        <div class="pos-product-thumbnail-fallback">
          <VIcon icon="tabler-tools-kitchen-2" />
        </div>
      </template>
    </VImg>
    <div
      v-else
      class="pos-product-thumbnail-fallback"
    >
      <VIcon icon="tabler-tools-kitchen-2" />
    </div>
  </div>
</template>
