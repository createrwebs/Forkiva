<script lang="ts" setup>
import { useI18n } from 'vue-i18n'

defineProps<{
  merge: Record<string, any> | null
  currentTableId: number
}>()

const emit = defineEmits<{
  (e: 'reload', tableId: number): void
}>()

const { t } = useI18n()
</script>

<template>
  <div
    v-if="merge"
    class="pos-table-merge"
  >
    <div class="pos-table-merge-heading">
      <div class="d-flex align-center gap-1">
        <VIcon
          color="info"
          icon="tabler-arrow-merge"
          size="17"
        />
        {{ t('seatingplan::tables.merge_details') }}
      </div>
      <TableEnum
        column="type"
        :item="merge"
      />
    </div>
    <div class="pos-table-merge-members">
      <template
        v-for="member in merge.members"
        :key="member.id"
      >
        <VTooltip v-if="member.is_main">
          <template #activator="{ props }">
            <VChip
              :color="currentTableId === Number(member.table.id) ? 'primary' : 'default'"
              v-bind="props"
              @click="emit('reload', member.table.id)"
            >
              <VIcon icon="tabler-rosette-discount-check" />
              &nbsp;
              {{ member.table.name }}
            </VChip>
          </template>
          <span>{{ t(`seatingplan::tables.primary_table`) }}</span>
        </VTooltip>
        <VChip
          v-else
          :color="currentTableId === Number(member.table.id) ? 'primary' : 'default'"
          @click="emit('reload', member.table.id)"
        >
          {{ member.table.name }}
        </VChip>
      </template>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.pos-table-merge {
  margin-block-end: 0;
  padding-block-start: 18px;
  border-block-start: 1px solid var(--pos-border);
}
.pos-table-merge-heading {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 8px;
  margin-block-end: 12px;
  font-size: 14px;
  font-weight: 700;
  color: var(--pos-text);
}
.pos-table-merge-members {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}
</style>
