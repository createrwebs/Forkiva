<script lang="ts" setup>
import { computed, ref, toRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  disabled: boolean
  filters: Record<string, any>
  meta: Record<string, any>
}>()
const { t } = useI18n()
const menuOpen = ref(false)
const search = toRef(props.filters, 'search')
const floors = toRef(props.filters, 'floors')
const zones = toRef(props.filters, 'zones')
const statuses = toRef(props.filters, 'statuses')
const metaFloors = computed(() => props.meta?.floors ?? [])
const metaZones = computed(() => props.meta?.zones ?? [])
const metaStatuses = computed(() => props.meta?.statuses ?? [])
const activeCount = computed(
  () => (search.value ? 1 : 0) + floors.value.length + zones.value.length + statuses.value.length,
)
const zonesFiltered = computed(() =>
  floors.value.length === 0
    ? metaZones.value
    : metaZones.value.filter((zone: Record<string, any>) => floors.value.includes(zone.floor_id)),
)
const statusColors: Record<string, string> = {
  available: '#4caf50',
  occupied: '#f44336',
  reserved: '#d49b00',
  cleaning: '#2196f3',
  merged: '#d35400',
}

watch(
  () => [...floors.value],
  () => {
    zones.value = zones.value.filter((id: string | number) =>
      zonesFiltered.value.some((zone: Record<string, any>) => zone.id === id),
    )
  },
)
function clearFilters() {
  Object.assign(props.filters, { search: '', floors: [], zones: [], statuses: [] })
}
</script>

<template>
  <div class="pos-table-filters">
    <VSelect
      v-model="zones"
      :items="zonesFiltered"
      item-title="name"
      item-value="id"
      :placeholder="t('pos::pos_viewer.zones')"
      :aria-label="t('pos::pos_viewer.zones')"
      prepend-inner-icon="tabler-map-pin"
      multiple
      clearable
      hide-details
      density="compact"
      :disabled="disabled"
    >
      <template #selection="{ item, index }">
        <span
          v-if="index === 0"
          class="pos-zone-selection"
        >
          {{ item.title }}
        </span>
        <span
          v-if="index === 1"
          class="pos-zone-count"
        >
          +{{ zones.length - 1 }}
        </span>
      </template>
    </VSelect>
    <VMenu
      v-model="menuOpen"
      :close-on-content-click="false"
      location="bottom end"
      :offset="8"
    >
      <template #activator="{ props: menuProps }">
        <VBtn
          class="pos-table-filter-button"
          :disabled="disabled"
          v-bind="menuProps"
          variant="tonal"
          :aria-label="t('pos::pos_viewer.filters')"
        >
          <VIcon
            icon="tabler-adjustments-horizontal"
            size="20"
          />
          <span class="pos-filter-label">{{ t('pos::pos_viewer.filters') }}</span>
          <span
            v-if="activeCount"
            class="pos-filter-count"
          >
            {{ activeCount }}
          </span>
        </VBtn>
      </template>
      <VCard
        class="pos-details-dialog pos-table-filter-menu"
        width="340"
      >
        <div class="pos-filter-heading">
          <span class="pos-filter-title">
            <VIcon
              icon="tabler-adjustments-horizontal"
              size="20"
            />
            {{ t('pos::pos_viewer.filters') }}
          </span>
          <VBtn
            variant="text"
            size="small"
            :disabled="!activeCount || disabled"
            @click="clearFilters"
          >
            {{ t('admin::admin.buttons.clear') }}
          </VBtn>
          <VBtn
            icon="tabler-x"
            variant="text"
            size="small"
            :aria-label="t('admin::admin.buttons.close')"
            @click="menuOpen = false"
          />
        </div>
        <div class="pos-filter-content">
          <VTextField
            v-model="search"
            clearable
            hide-details
            density="compact"
            :placeholder="t('admin::admin.table.search_placeholder')"
            :aria-label="t('admin::admin.table.search_placeholder')"
            prepend-inner-icon="tabler-search"
            :disabled="disabled"
          />
          <section
            v-if="metaStatuses.length"
            class="pos-filter-section"
          >
            <h4>{{ t('pos::pos_viewer.table_statuses') }}</h4>
            <VChipGroup
              v-model="statuses"
              class="pos-status-options"
              color="primary"
              column
              multiple
              :disabled="disabled"
            >
              <VChip
                v-for="status in metaStatuses"
                :key="status.id"
                :value="status.id"
                variant="outlined"
                filter
              >
                <span
                  class="pos-status-dot"
                  :style="{ background: statusColors[status.id] || 'currentColor' }"
                />
                {{ status.name }}
              </VChip>
            </VChipGroup>
          </section>
          <section
            v-if="metaFloors.length"
            class="pos-filter-section"
          >
            <h4>
              <VIcon
                icon="tabler-building"
                size="16"
              />
              {{ t('pos::pos_viewer.floors') }}
            </h4>
            <VChipGroup
              v-model="floors"
              color="primary"
              column
              multiple
              :disabled="disabled"
            >
              <VChip
                v-for="floor in metaFloors"
                :key="floor.id"
                :value="floor.id"
                variant="outlined"
                filter
              >
                {{ floor.name }}
              </VChip>
            </VChipGroup>
          </section>
          <section
            v-if="zonesFiltered.length"
            class="pos-filter-section"
          >
            <h4>
              <VIcon
                icon="tabler-map-pin"
                size="16"
              />
              {{ t('pos::pos_viewer.zones') }}
            </h4>
            <VChipGroup
              v-model="zones"
              color="primary"
              column
              multiple
              :disabled="disabled"
            >
              <VChip
                v-for="zone in zonesFiltered"
                :key="zone.id"
                :value="zone.id"
                variant="outlined"
                filter
              >
                {{ zone.name }}
              </VChip>
            </VChipGroup>
          </section>
        </div>
      </VCard>
    </VMenu>
  </div>
</template>

<style lang="scss" scoped>
.pos-zone-selection {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-inline-size: 150px;
}
.pos-zone-count {
  color: rgb(var(--v-theme-primary));
  font-size: 12px;
  margin-inline-start: 4px;
}
.pos-table-filter-button :deep(.v-btn__content) {
  gap: 6px;
}
.pos-filter-count {
  display: grid;
  place-items: center;
  min-inline-size: 20px;
  block-size: 20px;
  padding-inline: 4px;
  border-radius: 6px;
  background: rgb(var(--v-theme-primary));
  color: white;
  font-size: 11px;
}
.pos-table-filter-menu {
  max-inline-size: calc(100vw - 24px);
  border: 1px solid var(--pos-border);
  border-radius: 16px !important;
  box-shadow: 0 12px 40px rgb(20 35 60 / 14%) !important;
}
.pos-filter-heading {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 12px 12px 10px 16px;
  border-block-end: 1px solid var(--pos-border);
}
.pos-filter-title {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 1;
  color: var(--pos-text);
  font-size: 15px;
  font-weight: 700;
}
.pos-filter-title > .v-icon {
  color: rgb(var(--v-theme-primary));
}
.pos-filter-content {
  padding: 16px;
  max-block-size: min(520px, 65dvh);
  overflow-y: auto;
}
.pos-filter-section {
  margin-block-start: 18px;
}
.pos-filter-section h4 {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0 0 6px;
  font-size: 12px;
  font-weight: 600;
  color: var(--pos-muted);
}
.pos-filter-section :deep(.v-chip) {
  min-block-size: 38px;
  margin: 3px 6px 3px 0;
  border-color: var(--pos-border);
  border-radius: 8px;
  font-size: 13px;
}
.pos-filter-section :deep(.v-chip--selected) {
  border-color: rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.08);
}
.pos-status-dot {
  inline-size: 7px;
  block-size: 7px;
  border-radius: 50%;
  margin-inline-end: 7px;
}
@media (max-width: 380px) {
  .pos-filter-label {
    display: none;
  }
}
</style>
