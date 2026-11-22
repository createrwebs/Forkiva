<script lang="ts" setup>
defineProps<{ text: string }>()
</script>

<template>
  <VTooltip
    :text="text"
    max-width="340"
    location="top"
  >
    <template #activator="{ props }">
      <VBtn
        v-bind="props"
        :aria-label="text"
        icon="tabler-info-circle"
        variant="text"
        color="on-surface"
        size="x-small"
        class="finance-hint"
      />
    </template>
  </VTooltip>
</template>

<style lang="scss" scoped>
.finance-hint {
  opacity: 0.6;
}
</style>
