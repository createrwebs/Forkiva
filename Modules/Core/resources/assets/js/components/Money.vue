<script lang="ts" setup>
import type { ConvertedMoney, Money } from '@/modules/core/contracts/Money.ts'
import { useAuth } from '@/modules/auth/composables/auth.ts'

const props = defineProps<{
  money: ConvertedMoney | Money
  classes?: string[] | Record<string, unknown>
  prefix?: string
}>()
const { user } = useAuth()
const convertedMoney = computed(() => ('original' in props.money ? props.money : null))
const displayMoney = computed(() => convertedMoney.value?.original || props.money)
</script>

<template>
  <VTooltip
    v-if="convertedMoney"
    :disabled="
      user?.assigned_to_branch ||
      convertedMoney.converted.currency === convertedMoney.original.currency
    "
    :text="convertedMoney.converted.formatted"
  >
    <template #activator="{ props: tooltipProps }">
      <span
        :class="classes"
        v-bind="tooltipProps"
      >
        {{ prefix || '' }}{{ displayMoney.formatted }}
      </span>
    </template>
  </VTooltip>
  <span
    v-else
    :class="classes"
  >
    {{ prefix || '' }}{{ displayMoney?.formatted }}
  </span>
</template>
