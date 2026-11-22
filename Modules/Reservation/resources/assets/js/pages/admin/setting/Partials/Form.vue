<script lang="ts" setup>
import CustomIndex from './Form/Index.vue'

defineProps<{
  item?: Record<string, unknown> | null
  action: 'update' | 'create'
}>()
</script>

<template>
  <CustomIndex
    :action="action"
    :item="item"
  />
</template>
