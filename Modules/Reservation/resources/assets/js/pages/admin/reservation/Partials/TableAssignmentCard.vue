<!-- The shared form is an intentional parent-owned form controller. -->
<!-- eslint-disable vue/no-mutating-props -->
<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  form: Record<string, any>
  meta: Record<string, any>
  availability: Record<string, any> | null
  availabilityMessage: string
  suggested: Record<string, any>[]
  loadingBranchMeta: boolean
  checkingAvailability: boolean
  canViewAvailability: boolean
  canRunAvailability: boolean
  onCheckAvailability: () => void | Promise<void>
}>()

const { t } = useI18n()

const isManualTableAssignment = computed(
  () => props.form.state.table_assignment_mode === 'assign_now',
)
const selectedTables = computed(() => {
  const selectedIds = new Set(props.form.state.table_ids || [])

  return (props.meta.tables || []).filter((table: Record<string, any>) => selectedIds.has(table.id))
})
const selectedCapacity = computed(() =>
  selectedTables.value.reduce(
    (sum: number, table: Record<string, any>) => sum + Number(table.capacity || 0),
    0,
  ),
)
const guestCount = computed(() => Number(props.form.state.guest_count || 0))
const capacityGap = computed(() => Math.max(guestCount.value - selectedCapacity.value, 0))
const hasEnoughSelectedCapacity = computed(() => selectedCapacity.value >= guestCount.value)
const assignmentModes = computed(() =>
  (props.meta.table_assignment_modes || []).map((mode: Record<string, any>) => ({
    ...mode,
    icon:
      mode.id === 'assign_now'
        ? 'tabler-armchair-2'
        : mode.id === 'auto_assign'
          ? 'tabler-wand'
          : 'tabler-clock',
  })),
)
const normalizedSuggestions = computed(() => {
  return (props.suggested || [])
    .map((item: Record<string, any>, index: number) => {
      const tables = Array.isArray(item.tables) ? item.tables : item.name ? [item] : []

      const tableIds =
        Array.isArray(item.table_ids) && item.table_ids.length > 0
          ? item.table_ids
          : tables.map((table: Record<string, any>) => table.id).filter(Boolean)

      const capacity =
        item.capacity ??
        tables.reduce(
          (sum: number, table: Record<string, any>) => sum + Number(table.capacity || 0),
          0,
        )

      return {
        key: tableIds.join('-') || `suggestion-${index}`,
        tableIds,
        tables,
        capacity,
      }
    })
    .filter((item: Record<string, any>) => item.tables.length > 0)
})
const availabilityAlertColor = computed(() =>
  props.availability?.available ? 'success' : 'warning',
)
const availabilityAlertMessage = computed(() => {
  if (props.availability?.available) {
    return props.availabilityMessage || t('reservation::frontend.available')
  }

  return props.availability?.warnings?.[0] || props.availabilityMessage
})

function selectMode(modeId: string) {
  props.form.state.table_assignment_mode = modeId
}

function applySuggestedTables(tableIds: Array<string | number>) {
  props.form.state.table_assignment_mode = 'assign_now'
  props.form.state.table_ids = [...tableIds]
}

function tableSuggestionNames(tables: Record<string, any>[]) {
  return tables.map((table) => table.name).join(', ')
}
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

    <VCardText class="table-assignment-card">
      <div class="table-assignment-summary">
        <VChip
          color="primary"
          size="small"
          variant="tonal"
        >
          {{
            t('reservation::frontend.guest_target', {
              value: form.state.guest_count || 0,
            })
          }}
        </VChip>

        <template v-if="isManualTableAssignment">
          <VChip
            color="info"
            size="small"
            variant="tonal"
          >
            {{
              t('reservation::frontend.selected_tables_count', {
                value: selectedTables.length,
              })
            }}
          </VChip>

          <VChip
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
        </template>
      </div>

      <div class="table-assignment-mode-grid">
        <VBtn
          v-for="mode in assignmentModes"
          :key="mode.id"
          class="table-assignment-mode-btn"
          :color="form.state.table_assignment_mode === mode.id ? 'primary' : 'default'"
          :variant="form.state.table_assignment_mode === mode.id ? 'flat' : 'tonal'"
          @click="selectMode(mode.id)"
        >
          <VIcon
            :icon="mode.icon"
            start
          />
          <span>{{ mode.name }}</span>
        </VBtn>
      </div>

      <div
        v-if="isManualTableAssignment"
        class="mt-4"
      >
        <VSelect
          v-model="form.state.table_ids"
          chips
          closable-chips
          :error="!!form.errors.value?.table_ids"
          :error-messages="form.errors.value?.table_ids"
          item-title="name"
          item-value="id"
          :items="meta.tables"
          :label="t('reservation::attributes.reservations.table_ids')"
          :loading="loadingBranchMeta"
          multiple
          :no-data-text="loadingBranchMeta ? t('reservation::frontend.loading_tables') : undefined"
        />

        <VAlert
          v-if="selectedTables.length > 0"
          class="mt-4"
          :type="hasEnoughSelectedCapacity ? 'success' : 'warning'"
          variant="tonal"
        >
          {{
            hasEnoughSelectedCapacity
              ? t('reservation::frontend.selected_capacity_ready')
              : t('reservation::frontend.selected_capacity_short', {
                  value: capacityGap,
                })
          }}
        </VAlert>

        <div
          v-if="selectedTables.length > 0"
          class="selected-table-grid"
        >
          <VSheet
            v-for="table in selectedTables"
            :key="table.id"
            border
            class="selected-table-item"
            rounded="lg"
          >
            <div class="selected-table-item__header">
              <div class="selected-table-item__title">
                {{ table.name }}
              </div>

              <VChip
                color="success"
                size="small"
                variant="tonal"
              >
                {{
                  t('reservation::frontend.capacity_value', {
                    value: table.capacity,
                  })
                }}
              </VChip>
            </div>
          </VSheet>
        </div>

        <VAlert
          v-else
          class="mt-4"
          type="info"
          variant="tonal"
        >
          {{ t('reservation::frontend.no_tables_selected') }}
        </VAlert>
      </div>

      <VAlert
        v-else-if="form.state.table_assignment_mode === 'auto_assign'"
        type="info"
        variant="tonal"
      >
        {{ t('reservation::frontend.auto_assign_summary') }}
      </VAlert>

      <VAlert
        v-else
        type="info"
        variant="tonal"
      >
        {{ t('reservation::frontend.assign_later_summary') }}
      </VAlert>

      <div
        v-if="canViewAvailability"
        class="table-assignment-actions"
      >
        <div class="table-assignment-actions__header">
          <div class="text-body-2 font-weight-medium">
            {{ t('reservation::frontend.check_availability') }}
          </div>

          <div class="text-body-2 text-medium-emphasis">
            {{ t('reservation::frontend.availability_helper') }}
          </div>
        </div>

        <VBtn
          block
          color="primary"
          :disabled="!canRunAvailability"
          :loading="checkingAvailability"
          prepend-icon="tabler-search"
          variant="tonal"
          @click="onCheckAvailability"
        >
          {{ t('reservation::frontend.check_availability') }}
        </VBtn>
      </div>

      <VAlert
        v-if="availability || availabilityMessage"
        class="mt-4"
        :type="availabilityAlertColor"
        variant="tonal"
      >
        {{ availabilityAlertMessage }}
      </VAlert>

      <div
        v-if="normalizedSuggestions.length > 0"
        class="table-suggestions"
      >
        <div class="table-suggestions__title">
          {{ t('reservation::frontend.suggested_tables') }}
        </div>

        <div class="table-suggestions__list">
          <VSheet
            v-for="suggestion in normalizedSuggestions"
            :key="suggestion.key"
            border
            class="table-suggestion-item"
            rounded="lg"
          >
            <div class="table-suggestion-item__header">
              <div class="table-suggestion-item__names">
                {{ tableSuggestionNames(suggestion.tables) }}
              </div>

              <div class="table-suggestion-item__meta">
                <VChip
                  color="success"
                  size="small"
                  variant="tonal"
                >
                  {{
                    t('reservation::frontend.capacity_value', {
                      value: suggestion.capacity,
                    })
                  }}
                </VChip>

                <VBtn
                  v-if="isManualTableAssignment"
                  color="info"
                  size="small"
                  variant="text"
                  @click="applySuggestedTables(suggestion.tableIds)"
                >
                  {{ t('reservation::frontend.use_selection') }}
                </VBtn>
              </div>
            </div>

            <div class="table-suggestion-item__chips">
              <VChip
                v-for="table in suggestion.tables"
                :key="table.id"
                size="small"
                variant="tonal"
              >
                {{ table.name }}
              </VChip>
            </div>
          </VSheet>
        </div>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped lang="scss">
.table-assignment-card {
  display: grid;
  gap: 16px;
}

.table-assignment-summary {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.table-assignment-mode-grid {
  display: grid;
  gap: 10px;
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.table-assignment-mode-btn {
  justify-content: flex-start;
  min-block-size: 44px;
  padding-inline: 14px;
  text-transform: none;
}

.table-assignment-actions {
  display: grid;
  gap: 12px;
}

.table-assignment-actions__header {
  display: grid;
  gap: 4px;
}

.selected-table-grid,
.table-suggestions__list {
  display: grid;
  gap: 10px;
  margin-top: 12px;
}

.selected-table-item,
.table-suggestion-item {
  padding: 12px 14px;
  border-style: dashed;
}

.selected-table-item__header,
.table-suggestion-item__header {
  align-items: center;
  display: flex;
  gap: 12px;
  justify-content: space-between;
}

.selected-table-item__title,
.table-suggestion-item__names {
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.95rem;
  font-weight: 600;
  line-height: 1.4;
}

.table-suggestions {
  display: grid;
  gap: 10px;
}

.table-suggestions__title {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 0.82rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0;
}

.table-suggestion-item__meta {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.table-suggestion-item__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 10px;
}

@media (max-width: 1279px) {
  .table-assignment-mode-grid {
    grid-template-columns: 1fr;
  }

  .selected-table-item__header,
  .table-suggestion-item__header {
    align-items: flex-start;
    flex-direction: column;
  }
}
</style>
