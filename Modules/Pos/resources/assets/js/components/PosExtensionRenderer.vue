<script lang="ts" setup>
import type { UseCart } from '@/modules/cart/composables/cart.ts'
import type { PosExtensionMount } from '@/modules/pos/contracts/PosExtension.ts'
import type { PosForm, PosMeta } from '@/modules/pos/contracts/posViewer.ts'
import { computed, defineAsyncComponent, useAttrs } from 'vue'
import { posExtensionRegistry } from '@/modules/pos/services/PosExtensionRegistry.ts'

const props = defineProps<{
  mount: PosExtensionMount
  cart?: UseCart
  form?: PosForm
  meta?: PosMeta
}>()
const attrs = useAttrs()

const components = computed(() => {
  return posExtensionRegistry.all(props.mount).map((item) => ({
    key: item.key,
    component: item.component || defineAsyncComponent(item.loader!),
  }))
})
</script>

<template>
  <template
    v-for="item in components"
    :key="item.key"
  >
    <component
      :is="item.component"
      :cart="cart"
      :form="form"
      :meta="meta"
      v-bind="attrs"
    />
  </template>
</template>
