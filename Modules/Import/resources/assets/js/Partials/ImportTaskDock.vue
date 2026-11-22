<script setup lang="ts">
import type { DataImport } from '@/modules/import/contracts/import.ts'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import ImportWizard from '@/modules/import/Partials/ImportWizard.vue'
import { useImportProcessing } from '@/modules/import/stores/importProcessing.ts'

const { t } = useI18n()
const processing = useImportProcessing()
const collapsed = ref(false)
const selected = ref<DataImport | null>(null)
const detailsOpen = ref(false)
const tasks = computed(() => processing.backgroundTasks)
const context = computed(() =>
  Object.fromEntries(
    Object.entries(selected.value?.context ?? {}).filter(
      (entry): entry is [string, number] => typeof entry[1] === 'number',
    ),
  ),
)
const hasBrowserWork = computed(() =>
  tasks.value.some((item) => working(item) && item.requires_processing),
)
const activeCount = computed(() => tasks.value.filter(working).length)

function working(item: DataImport) {
  return (
    !processing.stopped.has(item.id) &&
    ['staging', 'validating', 'queued', 'processing'].includes(item.status.id)
  )
}

function progress(item: DataImport) {
  if (['ready', 'validation_failed', 'completed', 'partially_completed'].includes(item.status.id))
    return 100
  if (!item.total_rows) return 0
  const percentage = Math.round((item.processed_rows / item.total_rows) * 100)
  return Math.max(0, Math.min(working(item) ? 99 : 100, percentage))
}

function tone(item: DataImport) {
  if (
    processing.errors.get(item.id) ||
    ['failed', 'validation_failed', 'partially_completed'].includes(item.status.id)
  )
    return 'error'
  if (['completed', 'ready'].includes(item.status.id)) return 'success'
  return 'primary'
}

function status(item: DataImport) {
  if (processing.stopped.has(item.id)) return t('import::imports.request_failed')
  if (processing.errors.get(item.id)) return t('import::imports.task_reconnecting')
  if (
    item.status.id === 'processing' &&
    item.total_rows > 0 &&
    item.processed_rows >= item.total_rows
  )
    return t('import::imports.task_finalizing')
  return t(`import::imports.statuses.${item.status.id}`)
}

function openTask(item: DataImport) {
  selected.value = item
  detailsOpen.value = true
}

watch(
  () => processing.tasks.size,
  (size) => {
    if (size === 0) {
      detailsOpen.value = false
      selected.value = null
    }
  },
)
</script>

<template>
  <aside
    v-if="tasks.length"
    class="import-task-dock"
    :aria-label="t('import::imports.task_center')"
  >
    <button
      class="import-task-dock__header"
      type="button"
      :aria-expanded="!collapsed"
      aria-controls="import-task-list"
      @click="collapsed = !collapsed"
    >
      <VIcon
        icon="tabler-file-import"
        size="20"
      />
      <strong>{{ t('import::imports.task_center') }}</strong>
      <span class="import-task-dock__count">{{ tasks.length }}</span>
      <span
        v-if="collapsed && activeCount"
        class="import-task-dock__activity"
      />
      <VIcon
        :icon="collapsed ? 'tabler-chevron-up' : 'tabler-chevron-down'"
        size="18"
      />
    </button>
    <div
      v-show="!collapsed"
      id="import-task-list"
      class="import-task-dock__list"
    >
      <article
        v-for="item in tasks"
        :key="item.id"
        class="import-task"
        :class="`import-task--${tone(item)}`"
      >
        <div class="import-task__heading">
          <span class="import-task__file-icon">
            <VIcon
              icon="tabler-file-spreadsheet"
              size="20"
            />
          </span>
          <div class="import-task__identity">
            <strong :title="item.original_name">{{ item.original_name }}</strong>
            <span>{{ t(`import::imports.import_${item.resource}`) }}</span>
          </div>
          <button
            v-if="!working(item)"
            class="import-task__dismiss"
            type="button"
            :aria-label="t('import::imports.task_dismiss', { file: item.original_name })"
            @click="processing.dismiss(item.id)"
          >
            <VIcon
              icon="tabler-x"
              size="16"
            />
          </button>
        </div>
        <div class="import-task__status">
          <span role="status">
            <span class="import-task__dot" />
            {{ status(item) }}
          </span>
          <strong>{{ progress(item) }}%</strong>
        </div>
        <progress
          class="import-task__progress"
          :value="item.status.id === 'staging' && !item.total_rows ? undefined : progress(item)"
          max="100"
          :aria-label="t('import::imports.task_progress', { file: item.original_name })"
        />
        <div class="import-task__footer">
          <span
            v-if="item.invalid_rows || item.failed_rows"
            class="import-task__issues"
          >
            {{
              t('import::imports.rows_need_attention', {
                count: item.invalid_rows || item.failed_rows,
              })
            }}
          </span>
          <span v-else>
            {{
              t('import::imports.task_rows', {
                processed: item.processed_rows,
                total: item.total_rows,
              })
            }}
          </span>
          <button
            type="button"
            class="import-task__open"
            @click="openTask(item)"
          >
            {{ t(`import::imports.${working(item) ? 'task_view_progress' : 'task_view_details'}`) }}
            <VIcon
              icon="tabler-arrow-up-right"
              size="15"
            />
          </button>
        </div>
      </article>
    </div>
    <p
      v-if="!collapsed && hasBrowserWork"
      class="import-task-dock__hint"
    >
      {{ t('import::imports.task_tab_hint') }}
    </p>
  </aside>
  <ImportWizard
    v-if="selected && detailsOpen"
    :key="selected.id"
    v-model="detailsOpen"
    :resource="selected.resource"
    :context="context"
    :initial-import="selected"
  />
</template>

<style lang="scss" scoped>
.import-task-dock {
  position: fixed;
  z-index: 1900;
  inset-block-end: 20px;
  inset-inline-end: 24px;
  inline-size: min(380px, calc(100vw - 24px));
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 14px;
  background: rgb(var(--v-theme-surface));
  color: rgb(var(--v-theme-on-surface));
  box-shadow: 0 8px 32px rgba(15, 23, 42, 0.14);
  overflow: hidden;

  button {
    font: inherit;
    cursor: pointer;
  }

  button:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: -2px;
    border-radius: 6px;
  }
}

.import-task-dock__header {
  display: flex;
  align-items: center;
  gap: 9px;
  inline-size: 100%;
  padding: 13px 16px;
  text-align: start;
  background: rgba(var(--v-theme-on-surface), 0.035);
  font-size: 13px !important;

  strong {
    flex: 1;
  }
}

.import-task-dock__count {
  display: grid;
  place-items: center;
  min-inline-size: 22px;
  block-size: 22px;
  padding-inline: 5px;
  border-radius: 6px;
  background: rgba(var(--v-theme-on-surface), 0.06);
  font-size: 11px;
  font-weight: 600;
}

.import-task-dock__activity,
.import-task__dot {
  inline-size: 6px;
  block-size: 6px;
  border-radius: 50%;
  background: rgb(var(--v-theme-primary));
  flex-shrink: 0;
}

.import-task-dock__list {
  max-block-size: min(420px, 55dvh);
  overflow-y: auto;
}

.import-task {
  --task-color: var(--v-theme-primary);
  padding: 16px;
  border-block-start: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.import-task--success {
  --task-color: var(--v-theme-success);
}
.import-task--error {
  --task-color: var(--v-theme-error);
}

.import-task__heading,
.import-task__status,
.import-task__footer {
  display: flex;
  align-items: center;
  gap: 10px;
}

.import-task__file-icon {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  inline-size: 36px;
  block-size: 38px;
  border-radius: 9px;
  color: rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.08);
}

.import-task__identity {
  display: grid;
  flex: 1;
  min-inline-size: 0;
  gap: 3px;

  strong {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 13px;
    font-weight: 600;
  }

  span {
    font-size: 11px;
    opacity: 0.65;
  }
}

.import-task__dismiss {
  display: grid;
  place-items: center;
  inline-size: 28px;
  block-size: 28px;
  opacity: 0.65;
}

.import-task__status {
  justify-content: space-between;
  margin-block: 14px 8px;
  font-size: 12px;

  span[role='status'] {
    display: flex;
    align-items: center;
    gap: 7px;
  }
  strong {
    color: rgb(var(--task-color));
    font-variant-numeric: tabular-nums;
  }
}

.import-task__dot {
  background: rgb(var(--task-color));
}

.import-task__progress {
  display: block;
  appearance: none;
  inline-size: 100%;
  block-size: 5px;
  border: 0;
  border-radius: 6px;
  overflow: hidden;
  background: rgba(var(--v-theme-on-surface), 0.07);
  accent-color: rgb(var(--task-color));

  &::-webkit-progress-bar {
    background: rgba(var(--v-theme-on-surface), 0.07);
  }
  &::-webkit-progress-value {
    background: rgb(var(--task-color));
    border-radius: 6px;
    transition: width 0.25s;
  }
  &::-moz-progress-bar {
    background: rgb(var(--task-color));
    border-radius: 6px;
  }
}

.import-task__footer {
  justify-content: space-between;
  margin-block-start: 11px;
  font-size: 11px;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

.import-task__issues {
  color: rgb(var(--v-theme-error));
}

.import-task__open {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  color: rgb(var(--v-theme-primary));
  font-weight: 600 !important;
  white-space: nowrap;
  padding-block: 4px;
}

.import-task-dock__hint {
  margin: 0;
  padding: 10px 16px;
  border-block-start: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  font-size: 11px;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

@media (max-width: 600px) {
  .import-task-dock {
    inset-inline-end: 12px;
    inset-block-end: max(12px, env(safe-area-inset-bottom));
  }
}
</style>
