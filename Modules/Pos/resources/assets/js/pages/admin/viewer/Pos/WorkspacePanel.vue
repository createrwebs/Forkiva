<script lang="ts" setup>
import { storeToRefs } from 'pinia'
import { useAppStore } from '@/modules/core/stores/appStore.ts'

defineProps<{
  modelValue: boolean
  embedded?: boolean
}>()
defineEmits<{ (e: 'update:modelValue', value: boolean): void }>()

const { isRtl } = storeToRefs(useAppStore())
</script>

<template>
  <section
    v-if="embedded"
    v-show="modelValue"
    class="pos-embedded-panel"
  >
    <slot />
  </section>
  <VNavigationDrawer
    v-else
    class="pos-panel-drawer"
    :location="isRtl ? 'left' : 'right'"
    :model-value="modelValue"
    temporary
    :width="600"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <slot />
  </VNavigationDrawer>
</template>
