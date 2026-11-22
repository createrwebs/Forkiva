<script lang="ts" setup>
import type { Cart } from '@/modules/cart/composables/cart.ts'
import { computed } from 'vue'
import { summarizeCartTaxes } from '@/modules/cart/utils/taxSummary.ts'
import TaxSummaryRows from '@/modules/tax/components/TaxSummaryRows.vue'

const props = defineProps<{
  cart: Cart | null | undefined
}>()

const taxes = computed(() => summarizeCartTaxes(props.cart))
</script>

<template>
  <TaxSummaryRows :taxes="taxes" />
</template>
