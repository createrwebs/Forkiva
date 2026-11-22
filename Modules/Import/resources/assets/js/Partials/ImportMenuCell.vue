<script setup lang="ts">
import { format, isValid, parseISO } from 'date-fns'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  value: string
  type: 'choice' | 'date'
  options?: Array<{ value: string; label: string }>
  selectedLabel?: string
  multiple?: boolean
  clearable: boolean
  rowIndex: number
  column: number
  label: string
  fieldLabel: string
  invalid: boolean
}>()

const emit = defineEmits<{
  'update:value': [value: string]
  focus: []
  keydown: [event: KeyboardEvent]
}>()

const menu = ref(false)
const search = ref('')
const searchInput = ref<HTMLInputElement | null>(null)
const choicePanel = ref<HTMLElement | null>(null)
const { t } = useI18n()

const filteredOptions = computed(() => {
  const query = search.value.trim().toLocaleLowerCase()
  return query
    ? (props.options?.filter((option) =>
        `${option.label} ${option.value}`.toLocaleLowerCase().includes(query),
      ) ?? [])
    : (props.options ?? [])
})

const selectedValues = computed(() =>
  props.multiple
    ? (props.value === '[]' ? '' : props.value)
        .split(/[|,]/)
        .map((part) => part.trim())
        .filter(Boolean)
    : [props.value],
)

watch(menu, (open) => {
  if (!open) search.value = ''
})

function focusChoice() {
  if (props.type !== 'choice') return
  if ((props.options?.length ?? 0) > 8) {
    searchInput.value?.focus()
    const list = choicePanel.value?.querySelector<HTMLElement>('.import-choice-options')
    const selected = list?.querySelector<HTMLElement>('.import-choice-option-active')
    if (list && selected) {
      list.scrollTop =
        selected.offsetTop - list.offsetTop - (list.clientHeight - selected.clientHeight) / 2
    }
  } else {
    const selectedIndex =
      props.options?.findIndex((option) => selectedValues.value.includes(option.value)) ?? -1
    focusOption(Math.max(0, selectedIndex))
  }
}

const displayValue = computed(() => {
  if (props.type === 'choice') {
    if (props.multiple) {
      return selectedValues.value
        .map((value) => props.options?.find((option) => option.value === value)?.label ?? value)
        .join(', ')
    }
    return (
      props.selectedLabel ??
      props.options?.find((option) => option.value === props.value)?.label ??
      props.value
    )
  }
  return props.value
})

const calendarValue = computed(() => {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(props.value)) return null
  const date = parseISO(props.value)
  return isValid(date) && format(date, 'yyyy-MM-dd') === props.value ? props.value : null
})

function choose(value: string) {
  if (value === '') {
    menu.value = false
    const cleared = props.multiple ? '[]' : ''
    if (props.value !== cleared) emit('update:value', cleared)
    return
  }
  if (props.multiple && props.type === 'choice') {
    const available = new Set(props.options?.map((option) => option.value) ?? [])
    const validValues = selectedValues.value.filter((selected) => available.has(selected))
    const values = validValues.includes(value)
      ? validValues.filter((selected) => selected !== value)
      : [...validValues, value]
    emit('update:value', values.length ? values.join('|') : '[]')
    return
  }
  menu.value = false
  if (value !== props.value) emit('update:value', value)
}

function focusOption(index: number) {
  choicePanel.value?.querySelectorAll<HTMLButtonElement>('.import-choice-option')[index]?.focus()
}

function onSearchKeydown(event: KeyboardEvent) {
  if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
    event.preventDefault()
    event.stopPropagation()
    focusOption(event.key === 'ArrowDown' ? 0 : filteredOptions.value.length - 1)
  } else if (event.key === 'Enter') {
    event.preventDefault()
    event.stopPropagation()
    if (filteredOptions.value.length > 0) choose(filteredOptions.value[0]!.value)
  }
}

function onOptionKeydown(event: KeyboardEvent, index: number) {
  if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
    event.preventDefault()
    event.stopPropagation()
    const direction = event.key === 'ArrowDown' ? 1 : -1
    const nextIndex =
      (index + direction + filteredOptions.value.length) % filteredOptions.value.length
    focusOption(nextIndex)
  } else if (event.key === 'Enter' || event.key === ' ') {
    event.stopPropagation()
  }
}

function chooseDate(value: unknown) {
  const date = value instanceof Date ? value : typeof value === 'string' ? parseISO(value) : null
  if (date && isValid(date)) choose(format(date, 'yyyy-MM-dd'))
}
</script>

<template>
  <VMenu
    v-model="menu"
    :close-on-content-click="false"
    location="bottom"
    :min-width="type === 'choice' ? 176 : undefined"
    :max-width="type === 'choice' ? 248 : undefined"
    @after-enter="focusChoice"
  >
    <template #activator="{ props: activatorProps }">
      <button
        v-bind="activatorProps"
        type="button"
        class="import-menu-trigger"
        :class="{ 'import-menu-trigger-open': menu && type === 'choice' }"
        :data-cell-row="rowIndex"
        :data-cell-column="column"
        :aria-label="label"
        :aria-invalid="invalid"
        @focus="emit('focus')"
        @keydown="emit('keydown', $event)"
      >
        <span class="import-menu-value">
          {{ displayValue || (type === 'date' ? 'YYYY-MM-DD' : '—') }}
        </span>
        <VIcon
          :icon="type === 'date' ? 'tabler-calendar' : 'tabler-chevron-down'"
          size="15"
        />
      </button>
    </template>

    <div v-if="type === 'date'">
      <VDatePicker
        :model-value="calendarValue"
        color="primary"
        show-adjacent-months
        @update:model-value="chooseDate"
      />
      <button
        v-if="clearable && value"
        type="button"
        class="import-menu-clear"
        @click="choose('')"
      >
        <VIcon
          icon="tabler-x"
          size="15"
        />
        {{ t('import::imports.clear_value') }}
      </button>
    </div>
    <div
      v-else
      ref="choicePanel"
      class="import-choice-panel"
    >
      <label
        v-if="(options?.length ?? 0) > 8"
        class="import-choice-search"
      >
        <VIcon
          icon="tabler-search"
          size="16"
        />
        <input
          ref="searchInput"
          v-model="search"
          :aria-label="t('import::imports.choice_search')"
          :placeholder="t('import::imports.choice_search')"
          type="search"
          @keydown="onSearchKeydown"
        />
      </label>
      <div
        class="import-choice-options"
        role="listbox"
        :aria-label="fieldLabel"
        :aria-multiselectable="multiple ? 'true' : undefined"
      >
        <button
          v-for="(option, index) in filteredOptions"
          :key="option.value"
          type="button"
          class="import-choice-option"
          :class="{ 'import-choice-option-active': selectedValues.includes(option.value) }"
          role="option"
          :aria-selected="selectedValues.includes(option.value)"
          :title="option.label"
          @click="choose(option.value)"
          @keydown="onOptionKeydown($event, index)"
        >
          <span class="import-choice-label">{{ option.label }}</span>
          <span
            v-if="selectedValues.includes(option.value)"
            class="import-choice-check"
          >
            <VIcon
              icon="tabler-check"
              size="12"
            />
          </span>
        </button>
        <div
          v-if="filteredOptions.length === 0"
          class="import-choice-empty"
        >
          {{ t('import::imports.choice_no_matches') }}
        </div>
      </div>
      <button
        v-if="clearable && (multiple ? value !== '[]' : value)"
        type="button"
        class="import-menu-clear import-choice-clear"
        @click="choose('')"
      >
        <VIcon
          icon="tabler-x"
          size="15"
        />
        {{ t(`import::imports.${multiple ? 'choice_clear_all' : 'choice_clear'}`) }}
      </button>
    </div>
  </VMenu>
</template>

<style scoped>
.import-menu-trigger {
  display: flex;
  width: 100%;
  height: 31px;
  align-items: center;
  justify-content: space-between;
  gap: 6px;
  padding: 0 8px;
  border: 0;
  background: transparent;
  color: inherit;
  font: inherit;
  text-align: start;
  cursor: pointer;
}

.import-menu-trigger:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: -2px;
  background: rgb(var(--v-theme-surface));
}

.import-menu-value {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.import-menu-trigger .v-icon {
  flex: none;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

.import-menu-trigger:hover,
.import-menu-trigger-open {
  background: rgba(var(--v-theme-primary), 0.05);
}

.import-menu-trigger-open .v-icon {
  color: rgb(var(--v-theme-primary));
  transform: rotate(180deg);
}

.import-choice-panel {
  overflow: hidden;
  padding: 5px;
  border: 1px solid rgba(var(--v-border-color), 0.2);
  border-radius: 9px;
  background: rgb(var(--v-theme-surface));
  box-shadow: 0 12px 30px rgba(22, 35, 48, 0.13);
  color: rgb(var(--v-theme-on-surface));
}

.import-choice-search {
  display: flex;
  height: 32px;
  align-items: center;
  gap: 7px;
  margin-bottom: 5px;
  padding: 0 9px;
  border: 1px solid rgba(var(--v-border-color), 0.18);
  border-radius: 6px;
  background: rgba(var(--v-theme-on-surface), 0.035);
  color: rgba(var(--v-theme-on-surface), 0.56);
}

.import-choice-search:focus-within {
  border-color: rgba(var(--v-theme-primary), 0.55);
  background: rgb(var(--v-theme-surface));
}

.import-choice-search input {
  width: 100%;
  height: 100%;
  min-width: 0;
  border: 0;
  outline: 0;
  background: transparent;
  color: rgb(var(--v-theme-on-surface));
  font: inherit;
  font-size: 12px;
}

.import-choice-options {
  max-height: 220px;
  overflow-y: auto;
}

.import-choice-option {
  display: flex;
  width: 100%;
  min-height: 35px;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 7px 9px;
  border: 0;
  border-radius: 6px;
  background: transparent;
  color: rgb(var(--v-theme-on-surface));
  font: inherit;
  font-size: 12px;
  text-align: start;
  cursor: pointer;
}

.import-choice-option:hover,
.import-choice-option:focus-visible {
  outline: 0;
  background: rgba(var(--v-theme-primary), 0.07);
}

.import-choice-option-active {
  background: rgba(var(--v-theme-primary), 0.08);
  color: rgb(var(--v-theme-primary));
  font-weight: 600;
}

.import-choice-option-active:hover,
.import-choice-option-active:focus-visible {
  background: rgba(var(--v-theme-primary), 0.12);
}

.import-choice-label {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.import-choice-check {
  display: flex;
  width: 17px;
  height: 17px;
  flex: none;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: rgb(var(--v-theme-primary));
  color: rgb(var(--v-theme-on-primary));
}

.import-choice-empty {
  padding: 14px 8px;
  color: rgba(var(--v-theme-on-surface), 0.55);
  font-size: 12px;
  text-align: center;
}

.import-menu-clear {
  display: flex;
  width: 100%;
  align-items: center;
  gap: 7px;
  padding: 8px 9px;
  border: 0;
  border-radius: 6px;
  background: transparent;
  color: rgba(var(--v-theme-on-surface), 0.7);
  font: inherit;
  font-size: 12px;
  text-align: start;
  cursor: pointer;
}

.import-menu-clear:hover,
.import-menu-clear:focus-visible {
  background: rgba(var(--v-theme-primary), 0.07);
  color: rgb(var(--v-theme-primary));
}
</style>
