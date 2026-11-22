<script lang="ts" setup>
import { computed, ref } from 'vue'
import { useDisplay } from 'vuetify'
import draggable from 'vuedraggable'
import type {
  WidgetCustomizerDefinition,
  WidgetCustomizerLabels,
  WidgetCustomizerLayoutItem,
} from '@/modules/core/contracts/WidgetCustomizer.ts'

const props = defineProps<{
  modelValue: boolean
  definitions: WidgetCustomizerDefinition[]
  layout: WidgetCustomizerLayoutItem[]
  labels: WidgetCustomizerLabels
  widgetTitle: (definition: WidgetCustomizerDefinition) => string
  categoryTitle: (definition: WidgetCustomizerDefinition) => string
  dragLabel: (title: string) => string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  'set-visible': [key: string, visible: boolean]
  minimize: [key: string]
  reorder: [keys: string[]]
  reset: []
}>()

type CustomizerRow = {
  definition: WidgetCustomizerDefinition
  layout: WidgetCustomizerLayoutItem
}

const { smAndDown, width } = useDisplay()
const search = ref<string | null>('')
const tab = ref<'visible' | 'hidden'>('visible')
const drawerOpen = computed({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value),
})
const definitionMap = computed(
  () => new Map(props.definitions.map((definition) => [definition.key, definition])),
)
const title = (row: CustomizerRow) => props.widgetTitle(row.definition)
const matchesSearch = (row: CustomizerRow) => {
  const needle = (search.value ?? '').trim().toLocaleLowerCase()
  return (
    !needle ||
    `${title(row)} ${props.categoryTitle(row.definition)}`.toLocaleLowerCase().includes(needle)
  )
}
const rows = computed<CustomizerRow[]>(() =>
  props.layout
    .filter((item) => definitionMap.value.has(item.key))
    .sort((left, right) => left.order - right.order)
    .map((item) => ({ definition: definitionMap.value.get(item.key)!, layout: item })),
)
const visibleRows = computed({
  get: () => rows.value.filter((row) => row.layout.visible && matchesSearch(row)),
  set: (value: CustomizerRow[]) =>
    emit(
      'reorder',
      value.map((row) => row.layout.key),
    ),
})
const hiddenRows = computed(() =>
  rows.value.filter((row) => !row.layout.visible && matchesSearch(row)),
)
const visibleCount = computed(() => rows.value.filter((row) => row.layout.visible).length)

function close() {
  drawerOpen.value = false
}
</script>

<template>
  <VNavigationDrawer
    v-model="drawerOpen"
    location="end"
    :scrim="true"
    temporary
    :width="smAndDown ? width : 430"
  >
    <div class="widget-customizer">
      <div class="widget-customizer__header">
        <div>
          <h2>{{ labels.title }}</h2>
          <p>{{ labels.subtitle }}</p>
        </div>
        <VBtn
          density="comfortable"
          icon="tabler-x"
          variant="text"
          @click="close"
        />
      </div>

      <div class="widget-customizer__tools">
        <VTextField
          v-model="search"
          clearable
          density="comfortable"
          hide-details
          :label="labels.search"
          prepend-inner-icon="tabler-search"
          variant="outlined"
        />
        <VTabs
          v-model="tab"
          color="primary"
          grow
        >
          <VTab value="visible">
            {{ labels.visible }}
            <VChip
              class="ms-2"
              size="x-small"
            >
              {{ visibleCount }}
            </VChip>
          </VTab>
          <VTab value="hidden">
            {{ labels.hidden }}
            <VChip
              class="ms-2"
              size="x-small"
            >
              {{ rows.length - visibleCount }}
            </VChip>
          </VTab>
        </VTabs>
      </div>

      <div class="widget-customizer__content">
        <p
          v-if="tab === 'visible'"
          class="widget-customizer__hint"
        >
          <VIcon
            icon="tabler-arrows-sort"
            size="16"
          />
          {{ labels.dragHint }}
        </p>

        <draggable
          v-if="tab === 'visible'"
          v-model="visibleRows"
          animation="180"
          class="widget-customizer__list"
          :disabled="Boolean(search)"
          ghost-class="widget-customizer__ghost"
          handle=".widget-customizer__drag"
          :item-key="(row: CustomizerRow) => row.definition.key"
        >
          <template #item="{ element: row }">
            <div class="widget-customizer__row">
              <button
                :aria-label="dragLabel(title(row))"
                class="widget-customizer__drag"
                type="button"
              >
                <VIcon
                  icon="tabler-grip-vertical"
                  size="18"
                />
              </button>
              <span
                class="widget-customizer__icon"
                :style="{
                  color: row.definition.color,
                  backgroundColor: `${row.definition.color}14`,
                }"
              >
                <VIcon
                  :icon="row.definition.icon"
                  size="20"
                />
              </span>
              <div class="widget-customizer__identity min-w-0">
                <strong class="text-truncate">{{ title(row) }}</strong>
                <span>{{ categoryTitle(row.definition) }}</span>
              </div>
              <VBtn
                :aria-label="row.layout.minimized ? labels.expand : labels.minimize"
                density="comfortable"
                :icon="row.layout.minimized ? 'tabler-square-chevron-down' : 'tabler-minus'"
                size="small"
                variant="text"
                @click="emit('minimize', row.layout.key)"
              />
              <VSwitch
                :aria-label="title(row)"
                color="primary"
                density="compact"
                hide-details
                :model-value="true"
                @update:model-value="emit('set-visible', row.layout.key, Boolean($event))"
              />
            </div>
          </template>
        </draggable>

        <div
          v-else
          class="widget-customizer__list"
        >
          <div
            v-for="row in hiddenRows"
            :key="row.layout.key"
            class="widget-customizer__row"
          >
            <span class="widget-customizer__drag" />
            <span
              class="widget-customizer__icon"
              :style="{
                color: row.definition.color,
                backgroundColor: `${row.definition.color}14`,
              }"
            >
              <VIcon
                :icon="row.definition.icon"
                size="20"
              />
            </span>
            <div class="widget-customizer__identity min-w-0">
              <strong class="text-truncate">{{ title(row) }}</strong>
              <span>{{ categoryTitle(row.definition) }}</span>
            </div>
            <span />
            <VSwitch
              :aria-label="title(row)"
              color="primary"
              density="compact"
              hide-details
              :model-value="false"
              @update:model-value="emit('set-visible', row.layout.key, Boolean($event))"
            />
          </div>
        </div>

        <div
          v-if="(tab === 'visible' ? visibleRows : hiddenRows).length === 0"
          class="widget-customizer__empty"
        >
          <VIcon
            icon="tabler-search-off"
            size="30"
          />
          {{ labels.empty }}
        </div>
      </div>

      <div class="widget-customizer__footer">
        <VBtn
          color="secondary"
          prepend-icon="tabler-restore"
          variant="text"
          @click="emit('reset')"
        >
          {{ labels.reset }}
        </VBtn>
        <VBtn
          color="primary"
          @click="close"
        >
          {{ labels.done }}
        </VBtn>
      </div>
    </div>
  </VNavigationDrawer>
</template>

<style lang="scss" scoped>
.widget-customizer {
  display: grid;
  grid-template-rows: auto auto minmax(0, 1fr) auto;
  block-size: 100%;
  background:
    linear-gradient(
      180deg,
      rgba(var(--v-theme-on-surface), 0.025),
      rgba(var(--v-theme-on-surface), 0.008)
    ),
    rgb(var(--v-theme-background));
}
.widget-customizer__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  padding: 23px 22px 18px;
  background: rgb(var(--v-theme-surface));
}
.widget-customizer__header h2 {
  font-size: 1.15rem;
  font-weight: 800;
  letter-spacing: -0.025em;
}
.widget-customizer__header p {
  margin-block-start: 4px;
  color: rgba(var(--v-theme-on-surface), 0.55);
  font-size: 0.76rem;
}
.widget-customizer__tools {
  display: grid;
  gap: 13px;
  padding: 0 22px 10px;
  border-block-end: 1px solid rgba(var(--v-border-color), 0.1);
  background: rgb(var(--v-theme-surface));
}
.widget-customizer__content {
  overflow-y: auto;
  padding: 17px 18px;
  color: rgb(var(--v-theme-on-surface));
}
.widget-customizer__hint {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-block-end: 10px;
  color: rgba(var(--v-theme-on-surface), 0.48);
  font-size: 0.71rem;
}
.widget-customizer__list {
  display: grid;
  gap: 8px;
}
.widget-customizer__row {
  display: grid;
  min-block-size: 68px;
  grid-template-columns: 20px 40px minmax(0, 1fr) 34px 38px;
  align-items: center;
  gap: 9px;
  padding: 9px 11px;
  border: 1px solid rgba(var(--v-border-color), 0.1);
  border-radius: 13px;
  background: rgb(var(--v-theme-surface));
  box-shadow: 0 5px 17px rgba(0, 0, 0, 0.055);
}
.widget-customizer__row:hover {
  border-color: rgba(var(--v-theme-primary), 0.22);
  box-shadow: 0 8px 22px rgba(0, 0, 0, 0.08);
}
.widget-customizer__drag {
  display: grid;
  border: 0;
  background: transparent;
  color: rgba(var(--v-theme-on-surface), 0.32);
  cursor: grab;
  place-items: center;
}
.widget-customizer__icon {
  display: grid;
  border-radius: 11px;
  block-size: 40px;
  inline-size: 40px;
  place-items: center;
}
.widget-customizer__identity {
  display: grid;
  gap: 3px;
}
.widget-customizer__identity strong {
  font-size: 0.8rem;
}
.widget-customizer__identity span {
  color: rgba(var(--v-theme-on-surface), 0.48);
  font-size: 0.68rem;
}
.widget-customizer__ghost {
  border-color: rgb(var(--v-theme-primary));
  opacity: 0.5;
}
.widget-customizer__empty {
  display: grid;
  min-block-size: 220px;
  align-content: center;
  gap: 10px;
  color: rgba(var(--v-theme-on-surface), 0.48);
  font-size: 0.8rem;
  justify-items: center;
}
.widget-customizer__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  padding: 14px 22px;
  border-block-start: 1px solid rgba(var(--v-border-color), 0.1);
  background: rgb(var(--v-theme-surface));
}
</style>
