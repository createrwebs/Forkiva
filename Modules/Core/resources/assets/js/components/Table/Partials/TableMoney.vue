<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/modules/auth/composables/auth.ts'

const props = defineProps<{ item: any; column: string; currency?: string }>()

const { user } = useAuth()
const { locale } = useI18n()
const money = computed(() => props.item?.[props.column])
const original = computed(() => money.value?.original ?? null)
const converted = computed(() => money.value?.converted ?? null)
const formatted = computed(() => {
  if (money.value && typeof money.value === 'object') return money.value.formatted ?? '—'
  if (money.value === null || money.value === undefined || money.value === '') return '—'

  const amount = Number(money.value)
  if (!Number.isFinite(amount)) return '—'

  const options: Intl.NumberFormatOptions = {
    minimumFractionDigits: 2,
    maximumFractionDigits: 6,
  }
  if (props.currency) {
    options.style = 'currency'
    options.currency = props.currency
  }

  return new Intl.NumberFormat(locale.value, options).format(amount)
})
</script>

<template>
  <VTooltip
    v-if="original"
    :disabled="user?.assigned_to_branch || !converted || converted.currency === original.currency"
    :text="converted?.formatted || ''"
  >
    <template #activator="{ props: activatorProps }">
      <span v-bind="activatorProps">{{ original.formatted }}</span>
    </template>
  </VTooltip>
  <span v-else>{{ formatted }}</span>
</template>
