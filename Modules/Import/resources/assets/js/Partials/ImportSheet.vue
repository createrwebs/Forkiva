<script setup lang="ts">
import type { DataImport, ImportField, ImportRow } from '@/modules/import/contracts/import.ts'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import ImportMenuCell from './ImportMenuCell.vue'

interface Props {
  current: DataImport
  fields: ImportField[]
  mapping: Record<string, number | null>
  rows: ImportRow[]
  loading: boolean
  error: string | null
  editable: boolean
  saving: boolean
  showOperations: boolean
  page: number
  lastPage: number
}

const props = defineProps<Props>()
const emit = defineEmits<{
  saveCell: [row: ImportRow, field: string]
  changePage: [page: number]
  retry: []
}>()
const { t } = useI18n()
const sheet = ref<HTMLElement | null>(null)
const activeError = ref<string | null>(null)
const activeCell = ref<string | null>(null)

const mappedColumns = computed(() => {
  const fields = new Map<number, ImportField>()
  for (const field of props.fields) {
    const column = props.mapping[field.key]
    if (column != null) fields.set(column, field)
  }
  return fields
})

function columnLetter(index: number): string {
  let number = index + 1
  let label = ''
  while (number > 0) {
    number--
    label = String.fromCharCode(65 + (number % 26)) + label
    number = Math.floor(number / 26)
  }
  return label
}

function errorsFor(row: ImportRow, column: number): string[] {
  const field = mappedColumns.value.get(column)
  return field ? (row.errors?.[field.key] ?? []) : []
}

function rowErrors(row: ImportRow): string[] {
  return Object.values(row.errors ?? {}).flat()
}

function valueFor(row: ImportRow, column: number): string {
  const field = mappedColumns.value.get(column)
  const value =
    field && Object.prototype.hasOwnProperty.call(row.data, field.key)
      ? row.data[field.key]
      : row.values[column]
  return value == null ? '' : String(value)
}

function optionsFor(
  row: ImportRow,
  column: number,
  scoped = true,
): Array<{ value: string; label: string }> | undefined {
  const field = mappedColumns.value.get(column)
  if (field?.options) {
    return scoped
      ? field.options.filter((option) =>
          Object.entries(option.matches ?? {}).every(([key, expected]) => {
            const actual = row.data[key]
            return actual == null || actual === '' || String(actual) === expected
          }),
        )
      : field.options
  }
  if (field?.type === 'boolean') {
    return [
      { value: '1', label: t('import::imports.yes') },
      { value: '0', label: t('import::imports.no') },
    ]
  }
  return undefined
}

function displayValue(row: ImportRow, column: number): string {
  const value = valueFor(row, column)
  const field = mappedColumns.value.get(column)
  const options = optionsFor(row, column, false)
  if (!options) return value
  if (field?.multiple) {
    return (value === '[]' ? '' : value)
      .split(/[|,]/)
      .map((part) => part.trim())
      .filter(Boolean)
      .map((part) => options.find((option) => option.value === part)?.label ?? part)
      .join(', ')
  }
  return options.find((option) => option.value === value)?.label ?? value
}

function resultFor(row: ImportRow): { label: string; tone: string } {
  if (['processed', 'skipped', 'failed'].includes(row.status.id)) {
    return {
      label: row.status.name,
      tone:
        row.status.id === 'failed'
          ? 'error'
          : row.status.id === 'processed'
            ? 'success'
            : 'secondary',
    }
  }
  if (props.showOperations && row.operation) {
    const tones: Record<string, string> = {
      create: 'success',
      update: 'info',
      skip: 'warning',
      invalid: 'error',
    }
    return { label: row.operation.name, tone: tones[row.operation.id] ?? 'secondary' }
  }
  return { label: t('import::imports.row_statuses.pending'), tone: 'secondary' }
}

function updateCell(row: ImportRow, column: number, event: Event) {
  setCellValue(row, column, (event.target as HTMLInputElement).value)
}

function setCellValue(row: ImportRow, column: number, value: string) {
  const field = mappedColumns.value.get(column)
  if (!field) return
  if (value === valueFor(row, column)) return

  row.data[field.key] = value
  activeError.value = null
  emit('saveCell', row, field.key)
}

function focusCell(row: ImportRow, column: number) {
  activeCell.value = `${columnLetter(column)}${row.row_number}`
  activeError.value = errorsFor(row, column).join(' · ') || rowErrors(row).join(' · ') || null
}

function moveCell(event: KeyboardEvent, rowIndex: number, columnIndex: number) {
  if (event.target instanceof HTMLButtonElement && event.key === 'Enter') return
  if (event.key !== 'Enter' && event.key !== 'ArrowUp' && event.key !== 'ArrowDown') return

  const direction = event.key === 'ArrowUp' || (event.key === 'Enter' && event.shiftKey) ? -1 : 1
  const next = sheet.value?.querySelector<HTMLElement>(
    `[data-cell-row="${rowIndex + direction}"][data-cell-column="${columnIndex}"]`,
  )
  if (!next) return

  event.preventDefault()
  next.focus()
}
</script>

<template>
  <div class="import-sheet">
    <div
      v-if="loading && rows.length === 0"
      class="import-sheet-state"
    >
      <VProgressCircular
        indeterminate
        color="primary"
        size="28"
      />
      {{ t('import::imports.loading_rows') }}
    </div>
    <div
      v-else-if="error"
      class="import-sheet-state"
      role="alert"
    >
      <VIcon
        icon="tabler-alert-circle"
        color="error"
      />
      {{ error }}
      <VBtn
        color="primary"
        size="small"
        variant="text"
        @click="emit('retry')"
      >
        {{ t('import::imports.retry_preview') }}
      </VBtn>
    </div>
    <div
      v-else-if="rows.length === 0"
      class="import-sheet-state"
    >
      <VProgressCircular
        v-if="['staging', 'validating'].includes(current.status.id)"
        indeterminate
        color="primary"
        size="28"
      />
      {{
        ['staging', 'validating'].includes(current.status.id)
          ? current.status.name
          : t('import::imports.no_preview_rows')
      }}
    </div>
    <div
      v-else
      ref="sheet"
      class="import-sheet-scroll"
    >
      <table
        class="import-sheet-table"
        :style="{ width: `${current.headers.length * 172 + 152}px` }"
      >
        <thead>
          <tr class="import-sheet-letters">
            <th class="import-sheet-index" />
            <th class="import-sheet-result" />
            <th
              v-for="(_, column) in current.headers"
              :key="column"
            >
              {{ columnLetter(column) }}
            </th>
          </tr>
          <tr class="import-sheet-headings">
            <th class="import-sheet-index">#</th>
            <th class="import-sheet-result">{{ t('import::imports.result') }}</th>
            <th
              v-for="(header, column) in current.headers"
              :key="column"
              scope="col"
            >
              <span class="import-sheet-heading">{{ header || columnLetter(column) }}</span>
              <span
                v-if="mappedColumns.get(column)"
                class="import-sheet-field"
              >
                {{ mappedColumns.get(column)?.label }}
                <span
                  v-if="mappedColumns.get(column)?.required"
                  class="text-error"
                >
                  *
                </span>
              </span>
              <span
                v-else
                class="import-sheet-field"
              >
                {{ t('import::imports.unmapped') }}
              </span>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(row, rowIndex) in rows"
            :key="row.id"
            :class="{ 'import-sheet-invalid': rowErrors(row).length > 0 }"
          >
            <th
              class="import-sheet-index"
              scope="row"
              :title="rowErrors(row).join('\n')"
            >
              {{ row.row_number }}
            </th>
            <td
              class="import-sheet-result"
              :title="rowErrors(row).join('\n')"
            >
              <span
                class="import-result-badge"
                :class="`import-result-${resultFor(row).tone}`"
                :tabindex="rowErrors(row).length ? 0 : undefined"
                :aria-label="[resultFor(row).label, ...rowErrors(row)].join(': ')"
              >
                <VIcon
                  v-if="rowErrors(row).length"
                  icon="tabler-alert-circle"
                  size="13"
                />
                {{ resultFor(row).label }}
                <VTooltip
                  v-if="rowErrors(row).length"
                  activator="parent"
                  location="top"
                  max-width="360"
                >
                  {{ rowErrors(row).join(' · ') }}
                </VTooltip>
              </span>
            </td>
            <td
              v-for="(_, column) in current.headers"
              :key="column"
              :class="{
                'import-sheet-cell-error': errorsFor(row, column).length > 0,
                'import-sheet-cell-readonly': !editable || !mappedColumns.has(column),
              }"
              :title="errorsFor(row, column).join('\n')"
            >
              <ImportMenuCell
                v-if="
                  editable &&
                  (optionsFor(row, column) || mappedColumns.get(column)?.type === 'date')
                "
                :value="valueFor(row, column)"
                :options="optionsFor(row, column)"
                :selected-label="displayValue(row, column)"
                :multiple="mappedColumns.get(column)?.multiple"
                :clearable="!mappedColumns.get(column)?.required"
                :type="mappedColumns.get(column)?.type === 'date' ? 'date' : 'choice'"
                :row-index="rowIndex"
                :column="column"
                :label="`${row.row_number} ${current.headers[column]}`"
                :field-label="mappedColumns.get(column)?.label ?? current.headers[column] ?? ''"
                :invalid="errorsFor(row, column).length > 0"
                @update:value="setCellValue(row, column, $event)"
                @focus="focusCell(row, column)"
                @keydown="moveCell($event, rowIndex, column)"
              />
              <div
                v-else-if="editable && mappedColumns.has(column)"
                class="import-text-cell"
              >
                <input
                  :value="valueFor(row, column)"
                  type="text"
                  :inputmode="mappedColumns.get(column)?.type === 'integer' ? 'numeric' : 'text'"
                  :data-cell-row="rowIndex"
                  :data-cell-column="column"
                  :aria-label="`${row.row_number} ${current.headers[column]}`"
                  :aria-invalid="errorsFor(row, column).length > 0"
                  @change="updateCell(row, column, $event)"
                  @focus="focusCell(row, column)"
                  @keydown="moveCell($event, rowIndex, column)"
                />
                <button
                  v-if="!mappedColumns.get(column)?.required && valueFor(row, column)"
                  type="button"
                  class="import-text-clear"
                  :aria-label="`${t('import::imports.clear_value')}: ${mappedColumns.get(column)?.label}`"
                  @pointerdown.prevent
                  @click="setCellValue(row, column, '')"
                >
                  <VIcon
                    icon="tabler-x"
                    size="14"
                  />
                </button>
              </div>
              <span v-else>
                {{ displayValue(row, column) }}
              </span>
              <VTooltip
                v-if="errorsFor(row, column).length"
                activator="parent"
                location="top"
                max-width="360"
              >
                {{ errorsFor(row, column).join(' · ') }}
              </VTooltip>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="import-sheet-foot">
      <span
        class="import-sheet-cell-message"
        :class="{ 'text-error': activeError }"
        :title="activeError ?? undefined"
        role="status"
      >
        <strong
          v-if="activeCell"
          class="import-cell-address"
        >
          {{ activeCell }}
        </strong>
        <VIcon
          :icon="activeError ? 'tabler-alert-circle' : 'tabler-cursor-text'"
          size="17"
        />
        {{ activeError ?? t(`import::imports.${editable ? 'edit_hint' : 'readonly_hint'}`) }}
      </span>
      <div
        v-if="lastPage > 1"
        class="d-flex align-center ga-2"
      >
        <VBtn
          :aria-label="t('import::imports.previous_page')"
          :disabled="page <= 1 || loading || saving"
          icon="tabler-chevron-left"
          size="small"
          variant="text"
          @click="emit('changePage', page - 1)"
        />
        <span>{{ page }} / {{ lastPage }}</span>
        <VBtn
          :aria-label="t('import::imports.next_page')"
          :disabled="page >= lastPage || loading || saving"
          icon="tabler-chevron-right"
          size="small"
          variant="text"
          @click="emit('changePage', page + 1)"
        />
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.import-sheet {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-height: 140px;
  margin-inline: 24px;
  border: 1px solid rgba(var(--v-border-color), 0.16);
  border-radius: 8px;
  overflow: hidden;
  margin-bottom: 16px;
  background: rgb(var(--v-theme-surface));
}

.import-sheet-scroll {
  flex: 1;
  min-height: 0;
  overflow: auto;
}

.import-sheet-table {
  border-collapse: separate;
  border-spacing: 0;
  min-width: 100%;
  color: rgb(var(--v-theme-on-surface));
  font-size: 12px;
  table-layout: fixed;
}

.import-sheet-table th,
.import-sheet-table td {
  border-inline-end: 1px solid rgba(var(--v-border-color), 0.16);
  border-block-end: 1px solid rgba(var(--v-border-color), 0.16);
  width: 172px;
  min-width: 172px;
  height: 32px;
  padding: 0;
  text-align: start;
  white-space: nowrap;
}

.import-sheet-table .import-sheet-index {
  position: sticky;
  z-index: 2;
  inset-inline-start: 0;
  width: 42px;
  min-width: 42px;
  text-align: center;
  background: rgb(var(--v-theme-surface));
  color: rgba(var(--v-theme-on-surface), 0.6);
  font-weight: 500;
}

.import-sheet-table thead th {
  position: sticky;
  z-index: 3;
  top: 0;
  background: rgb(var(--v-theme-surface));
  box-shadow: inset 0 0 0 999px rgba(var(--v-theme-on-surface), 0.035);
}

.import-sheet-table thead .import-sheet-index {
  z-index: 4;
  background: rgb(var(--v-theme-surface));
}

.import-sheet-table .import-sheet-letters th {
  height: 26px;
  text-align: center;
  color: rgba(var(--v-theme-on-surface), 0.55);
  font-size: 11px;
}

.import-sheet-table .import-sheet-headings th {
  top: 26px;
  height: 44px;
  padding: 4px 8px;
}

.import-sheet-heading,
.import-sheet-field {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
}

.import-sheet-field {
  color: rgba(var(--v-theme-on-surface), 0.55);
  font-size: 11px;
  font-weight: 400;
}

.import-sheet-table input {
  width: 100%;
  height: 31px;
  padding: 0 8px;
  border-radius: 0;
  box-shadow: none;
  border: 0;
  outline: none;
  background: transparent;
  color: inherit;
  font: inherit;
}

.import-text-cell {
  display: flex;
  align-items: center;
}

.import-text-cell input {
  min-width: 0;
}

.import-text-clear {
  display: flex;
  width: 28px;
  height: 31px;
  flex: none;
  align-items: center;
  justify-content: center;
  border: 0;
  background: transparent;
  color: rgba(var(--v-theme-on-surface), 0.55);
  cursor: pointer;
}

.import-text-clear:hover,
.import-text-clear:focus-visible {
  color: rgb(var(--v-theme-primary));
}

.import-sheet-table td > span {
  display: block;
  overflow: hidden;
  padding: 5px 8px;
  text-overflow: ellipsis;
}

.import-sheet-table input:focus {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: -2px;
  background: rgb(var(--v-theme-surface));
}

.import-sheet-table tbody tr:focus-within .import-sheet-index {
  color: rgb(var(--v-theme-primary));
  box-shadow: inset 3px 0 rgb(var(--v-theme-primary));
}

.import-sheet-table td {
  position: relative;
}

.import-sheet-cell-error::after {
  content: '';
  position: absolute;
  inset-inline-end: 0;
  top: 0;
  width: 0;
  height: 0;
  border-top: 7px solid rgb(var(--v-theme-error));
  border-inline-start: 7px solid transparent;
  pointer-events: none;
}

.import-sheet-cell-readonly {
  background: rgba(var(--v-theme-on-surface), 0.025);
}

.import-cell-address {
  min-width: 36px;
  padding-inline-end: 8px;
  border-inline-end: 1px solid rgba(var(--v-border-color), 0.2);
  color: rgb(var(--v-theme-on-surface));
}

.import-sheet-table .import-sheet-cell-error,
.import-sheet-table .import-sheet-invalid .import-sheet-index {
  background: rgba(var(--v-theme-error), 0.1);
  color: rgb(var(--v-theme-error));
}

.import-sheet-state {
  display: flex;
  flex: 1;
  align-items: center;
  justify-content: center;
  gap: 12px;
  flex-wrap: wrap;
  padding: 20px;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

.import-sheet-foot {
  display: flex;
  min-height: 34px;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 4px 16px;
  border-block-start: 1px solid rgba(var(--v-border-color), 0.16);
  color: rgba(var(--v-theme-on-surface), 0.6);
  font-size: 12px;
}

.import-sheet-cell-message {
  display: flex;
  min-width: 0;
  align-items: center;
  gap: 6px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.import-sheet-table .import-sheet-result {
  position: sticky;
  inset-inline-start: 42px;
  z-index: 2;
  width: 110px;
  min-width: 110px;
  padding: 3px 8px;
  background: rgb(var(--v-theme-surface));
  font-size: 11px;
}

.import-sheet-table thead .import-sheet-result {
  z-index: 4;
}

.import-sheet-table .import-result-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px 7px;
  border-radius: 4px;
  max-width: 100%;
  font-size: 11px;
  font-weight: 600;
  line-height: 1.5;
  cursor: default;
}

@each $tone in error, success, info, warning, secondary {
  .import-result-#{$tone} {
    color: rgb(var(--v-theme-#{$tone}));
    background: rgba(var(--v-theme-#{$tone}), 0.12);
  }
}

@media (max-width: 600px) {
  .import-sheet {
    height: 300px;
    margin-inline: 16px;
  }

  .import-sheet-foot {
    flex-wrap: wrap;
  }
}
</style>
