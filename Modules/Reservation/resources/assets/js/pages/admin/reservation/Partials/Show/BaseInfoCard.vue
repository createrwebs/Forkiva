<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import BlockInfo from '@/modules/core/components/BlockInfo.vue'
import Money from '@/modules/core/components/Money.vue'
import TableEnum from '@/modules/core/components/Table/Partials/TableEnum.vue'
import TableStatus from '@/modules/core/components/Table/Partials/TableStatus.vue'

export type InfoCardField = {
  column?: string
  cols?: number
  item?: Record<string, unknown>
  label: string
  type?: 'boolean' | 'money' | 'tableEnum' | 'tableStatus'
  value?: any
}

defineProps<{
  columns?: number
  fields: InfoCardField[]
  icon: string
  item: Record<string, unknown>
  title: string
}>()

const { t } = useI18n()

function displayValue(value: unknown) {
  return [null, undefined, ''].includes(value as any) ? '-' : value
}

function booleanValue(value: unknown) {
  return value ? t('admin::admin.table.yes') : t('admin::admin.table.no')
}
</script>

<template>
  <VCard>
    <VCardTitle class="d-flex align-center">
      <VIcon
        class="me-2"
        :icon="icon"
        size="18"
      />
      <span>{{ title }}</span>
    </VCardTitle>

    <VCardText>
      <VRow>
        <VCol
          v-for="field in fields"
          :key="`${title}-${field.label}`"
          cols="12"
          :md="field.cols || columns || 6"
        >
          <BlockInfo :title="field.label">
            <TableEnum
              v-if="field.type === 'tableEnum'"
              :column="field.column || 'type'"
              :item="field.item || item"
            />

            <TableStatus
              v-else-if="field.type === 'tableStatus'"
              :column="field.column || 'status'"
              :item="field.item || item"
            />

            <Money
              v-else-if="field.type === 'money' && field.value"
              :money="field.value"
            />

            <template v-else-if="field.type === 'boolean'">
              {{ booleanValue(field.value) }}
            </template>

            <template v-else>
              {{ displayValue(field.value) }}
            </template>
          </BlockInfo>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>
