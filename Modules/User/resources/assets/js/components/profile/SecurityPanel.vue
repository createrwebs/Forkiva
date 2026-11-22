<script setup lang="ts">
defineProps<{ embedded?: boolean; persistent?: boolean }>()
const model = defineModel<boolean>()
</script>

<template>
  <div
    v-if="embedded"
    class="profile-security-panel"
  >
    <slot />
  </div>
  <VDialog
    v-else
    v-model="model"
    :persistent="persistent"
    max-width="920"
    scrollable
  >
    <slot />
  </VDialog>
</template>
