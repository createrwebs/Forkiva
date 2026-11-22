<!-- The shared form is an intentional parent-owned form controller. -->
<!-- eslint-disable vue/no-mutating-props -->
<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  activeAction: 'status' | 'assign' | 'auto_assign' | null
  canAssignTables: boolean
  canAutoAssignTables: boolean
  form: Record<string, any>
  hasActiveAction: boolean
  item: Record<string, any>
  meta: Record<string, any>
}>()

defineEmits<{
  (event: 'assign' | 'auto-assign'): void
}>()

const { t } = useI18n()

const assignedTables = computed(() => props.item.tables || [])
const selectedIds = computed(() => new Set(props.form.state.table_ids || []))
const selectedTables = computed(() => {
  return (props.meta.tables || []).filter((table: Record<string, any>) =>
    selectedIds.value.has(table.id),
  )
})
const assignedCapacity = computed(() =>
  assignedTables.value.reduce((sum: number, assignment: Record<string, any>) => {
    return sum + Number(assignment.table?.capacity || 0)
  }, 0),
)
const selectedCapacity = computed(() =>
  selectedTables.value.reduce((sum: number, table: Record<string, any>) => {
    return sum + Number(table.capacity || 0)
  }, 0),
)
const hasAssignmentActions = computed(() => props.canAssignTables || props.canAutoAssignTables)
</script>

<template>
  <VCard>
    <VCardTitle class="d-flex align-center">
      <VIcon
        class="me-2"
        icon="tabler-armchair"
        size="20"
      />
      <span>{{ t('reservation::frontend.table_assignment') }}</span>
    </VCardTitle>

    <VCardText class="reservation-table-assignment">
      <div class="reservation-table-assignment__summary">
        <VChip
          v-if="item.table_assignment_status?.id"
          :color="item.table_assignment_status?.color || 'default'"
          size="small"
          variant="tonal"
        >
          {{ item.table_assignment_status?.name }}
        </VChip>

        <VChip
          v-if="item.table_assignment_mode?.id"
          :color="item.table_assignment_mode?.color || 'info'"
          size="small"
          variant="tonal"
        >
          {{ item.table_assignment_mode?.name }}
        </VChip>

        <VChip
          color="primary"
          size="small"
          variant="tonal"
        >
          {{ t('reservation::frontend.guest_target', { value: item.guest_count || 0 }) }}
        </VChip>

        <VChip
          v-if="assignedTables.length > 0"
          color="success"
          size="small"
          variant="tonal"
        >
          {{ t('reservation::frontend.selected_capacity', { value: assignedCapacity }) }}
        </VChip>
      </div>

      <div>
        <div class="text-body-2 font-weight-medium mb-2">
          {{ t('reservation::frontend.assigned_tables') }}
        </div>

        <div
          v-if="assignedTables.length > 0"
          class="reservation-table-assignment__grid"
        >
          <VSheet
            v-for="assignment in assignedTables"
            :key="assignment.id"
            border
            class="reservation-table-assignment__table"
            rounded="lg"
          >
            <div class="d-flex align-start justify-space-between ga-2">
              <div>
                <div class="reservation-table-assignment__table-name">
                  {{ assignment.table?.name || '-' }}
                </div>

                <div class="text-caption text-medium-emphasis">
                  {{
                    t('reservation::frontend.capacity_value', {
                      value: assignment.table?.capacity || 0,
                    })
                  }}
                </div>
              </div>

              <VChip
                v-if="assignment.table?.status?.id"
                :color="assignment.table?.status?.color || 'default'"
                size="x-small"
                variant="tonal"
              >
                {{ assignment.table?.status?.name }}
              </VChip>
            </div>

            <div class="text-caption text-medium-emphasis mt-2">
              {{ assignment.assigned_by?.name || '-' }} -
              {{ assignment.assigned_at || '-' }}
            </div>
          </VSheet>
        </div>

        <VAlert
          v-else
          type="info"
          variant="tonal"
        >
          {{ t('reservation::frontend.no_assigned_tables') }}
        </VAlert>
      </div>

      <template v-if="hasAssignmentActions">
        <VDivider />

        <div class="reservation-table-assignment__editor">
          <VSelect
            v-if="canAssignTables"
            v-model="form.state.table_ids"
            chips
            closable-chips
            :error="!!form.errors.value?.table_ids"
            :error-messages="form.errors.value?.table_ids"
            item-title="name"
            item-value="id"
            :items="meta.tables"
            :label="t('reservation::attributes.reservations.table_ids')"
            multiple
          />

          <div
            v-if="selectedTables.length > 0"
            class="reservation-table-assignment__grid"
          >
            <VSheet
              v-for="table in selectedTables"
              :key="table.id"
              border
              class="reservation-table-assignment__table reservation-table-assignment__table--selected"
              rounded="lg"
            >
              <div class="reservation-table-assignment__table-name">
                {{ table.name }}
              </div>

              <div class="text-caption text-medium-emphasis">
                {{
                  t('reservation::frontend.capacity_value', {
                    value: table.capacity || 0,
                  })
                }}
              </div>
            </VSheet>
          </div>

          <VChip
            v-if="selectedTables.length > 0"
            color="success"
            size="small"
            variant="tonal"
          >
            {{
              t('reservation::frontend.selected_capacity', {
                value: selectedCapacity,
              })
            }}
          </VChip>

          <div class="d-flex flex-wrap ga-2">
            <VBtn
              v-if="canAssignTables"
              color="primary"
              :disabled="hasActiveAction && activeAction !== 'assign'"
              :loading="activeAction === 'assign'"
              prepend-icon="tabler-device-floppy"
              variant="tonal"
              @click="$emit('assign')"
            >
              {{ t('admin::admin.buttons.save') }}
            </VBtn>

            <VBtn
              v-if="canAutoAssignTables"
              color="info"
              :disabled="hasActiveAction && activeAction !== 'auto_assign'"
              :loading="activeAction === 'auto_assign'"
              prepend-icon="tabler-wand"
              variant="tonal"
              @click="$emit('auto-assign')"
            >
              {{ t('reservation::frontend.auto_assign') }}
            </VBtn>
          </div>
        </div>
      </template>
    </VCardText>
  </VCard>
</template>

<style lang="scss" scoped>
.reservation-table-assignment {
  display: grid;
  gap: 18px;
}

.reservation-table-assignment__summary {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.reservation-table-assignment__grid {
  display: grid;
  gap: 10px;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.reservation-table-assignment__table {
  border-style: dashed;
  padding: 12px 14px;
}

.reservation-table-assignment__table--selected {
  background: rgba(var(--v-theme-primary), 0.04);
}

.reservation-table-assignment__table-name {
  color: rgb(var(--v-theme-on-surface));
  font-weight: 600;
  line-height: 1.4;
}

.reservation-table-assignment__editor {
  display: grid;
  gap: 12px;
}

@media (max-width: 599px) {
  .reservation-table-assignment__grid {
    grid-template-columns: 1fr;
  }
}
</style>
