<script setup lang="ts">
import type { DataImport, ImportResourceKey } from '@/modules/import/contracts/import.ts'
import { computed } from 'vue'
import ImportProgressSteps from '@/modules/import/Partials/ImportProgressSteps.vue'
import ImportUploadPanel from '@/modules/import/Partials/ImportUploadPanel.vue'
import ImportColumnMapping from '@/modules/import/Partials/ImportColumnMapping.vue'
import ImportSummary from '@/modules/import/Partials/ImportSummary.vue'
import ImportSheet from '@/modules/import/Partials/ImportSheet.vue'
import { useImportWizard } from '@/modules/import/composables/useImportWizard.ts'

interface Props {
  modelValue: boolean
  resource: ImportResourceKey
  context?: Record<string, number>
  initialImport?: DataImport
}

const props = defineProps<Props>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; completed: [] }>()
const open = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
})
const {
  t,
  step,
  busy,
  templateDownloading,
  historyLoading,
  openingImport,
  openingError,
  file,
  fields,
  current,
  history,
  mapping,
  missingRequiredFields,
  duplicateMapping,
  pollError,
  rows,
  rowsLoading,
  rowsError,
  pendingEdits,
  page,
  lastPage,
  mode,
  working,
  canCancel,
  canValidate,
  canRetry,
  canConfirm,
  modes,
  download,
  errorFileName,
  upload,
  resumeImport,
  saveMapping,
  changePage,
  changeMapping,
  loadRows,
  saveRow,
  validateFile,
  queueImport,
  retry,
  cancelImport,
  newImport,
} = useImportWizard(props, open, () => emit('completed'))

const editable = computed(
  () =>
    !busy.value &&
    !rowsLoading.value &&
    ['staged', 'validation_failed', 'ready'].includes(current.value?.status.id ?? ''),
)
const showValidationAction = computed(
  () =>
    canValidate.value &&
    (current.value?.status.id !== 'ready' || current.value.mode.id !== mode.value),
)

const locked = computed(() => (busy.value && !openingImport.value) || pendingEdits.value > 0)
const validated = computed(
  () =>
    current.value?.mode.id === mode.value &&
    ['ready', 'validation_failed'].includes(current.value?.status.id ?? ''),
)

function selectFile(selected: File) {
  file.value = selected
  void upload()
}

function backToReview() {
  mapping.value = { ...current.value!.mapping }
  step.value = 3
}
</script>

<template>
  <VDialog
    v-model="open"
    :max-width="openingImport ? 1200 : step === 2 ? 960 : current ? 1200 : 1040"
    width="calc(100vw - 32px)"
    :fullscreen="$vuetify.display.xs"
    :persistent="locked"
    :aria-label="t(`import::imports.import_${resource}`)"
  >
    <VCard
      class="import-workspace"
      :class="{
        'import-workspace-compact': !current && !openingImport,
        'import-workspace-mapping': step === 2,
        'import-workspace-review': current && step >= 3,
      }"
      rounded="lg"
    >
      <header class="import-header">
        <div class="d-flex align-center ga-3 min-w-0">
          <VIcon
            icon="tabler-upload"
            color="primary"
            size="24"
          />
          <div class="min-w-0">
            <h2 class="import-title">{{ t(`import::imports.import_${resource}`) }}</h2>
          </div>
        </div>
        <VBtn
          :aria-label="t('import::imports.close')"
          :disabled="locked"
          color="secondary"
          icon="tabler-x"
          size="small"
          variant="text"
          @click="open = false"
        />
      </header>

      <ImportProgressSteps
        v-if="!openingImport"
        :step="step"
      />

      <main
        v-if="openingImport"
        class="import-details-loading d-flex flex-column align-center justify-center ga-4 pa-8"
        :aria-busy="busy || !openingError"
      >
        <VIcon
          v-if="openingError"
          icon="tabler-alert-circle"
          color="error"
          size="32"
        />
        <VProgressCircular
          v-else
          indeterminate
          color="primary"
          size="32"
        />
        <strong
          class="text-truncate"
          style="max-inline-size: 100%"
        >
          {{ openingImport.original_name }}
        </strong>
        <p :role="openingError ? 'alert' : 'status'">
          {{ openingError || t('import::imports.loading_details') }}
        </p>
        <VBtn
          v-if="openingError"
          variant="tonal"
          @click="resumeImport(openingImport)"
        >
          {{ t('import::imports.retry_preview') }}
        </VBtn>
      </main>
      <ImportUploadPanel
        v-else-if="!current"
        :busy="busy"
        :history="history"
        :history-loading="historyLoading"
        :template-downloading="templateDownloading"
        :notice="resource === 'users' ? t('user::users.import.password_setup') : undefined"
        @upload="selectFile"
        @resume="resumeImport"
        @download-template="download('template')"
      />

      <template v-else>
        <div class="import-toolbar">
          <div class="import-file-meta">
            <VIcon
              icon="tabler-file-spreadsheet"
              color="primary"
              size="22"
            />
            <div class="min-w-0">
              <strong>{{ current.original_name }}</strong>
              <span v-if="current.status.id !== 'uploaded'">
                {{ current.total_rows ?? 0 }} {{ t('import::imports.rows') }}
              </span>
            </div>
          </div>
          <div class="import-toolbar-actions">
            <div
              v-if="step === 3"
              class="import-mode-control"
            >
              <span>{{ t('import::imports.mode') }}</span>
              <VSelect
                v-model="mode"
                :items="modes"
                :aria-label="t('import::imports.mode')"
                :disabled="working || locked"
                class="import-mode"
                density="compact"
                hide-details
                variant="outlined"
              />
            </div>
            <VBtn
              v-if="step === 3 && editable"
              :disabled="locked"
              prepend-icon="tabler-arrows-shuffle"
              size="small"
              variant="outlined"
              @click="changeMapping"
            >
              {{ t('import::imports.change_mapping') }}
            </VBtn>
            <VMenu>
              <template #activator="{ props: menuProps }">
                <VBtn
                  v-bind="menuProps"
                  :aria-label="t('import::imports.more_actions')"
                  icon="tabler-dots"
                  size="small"
                  variant="text"
                />
              </template>
              <VList density="compact">
                <VListItem
                  prepend-icon="tabler-plus"
                  :disabled="locked"
                  :title="t('import::imports.new_import')"
                  @click="newImport"
                />
                <VListItem
                  :disabled="!current.has_original_file"
                  prepend-icon="tabler-file-download"
                  :title="t('import::imports.original_file')"
                  @click="download('original')"
                />
                <VListItem
                  v-if="current.has_error_file"
                  prepend-icon="tabler-file-alert"
                  :title="t('import::imports.error_file')"
                  @click="download('errors')"
                />
                <VListItem
                  :disabled="templateDownloading"
                  prepend-icon="tabler-download"
                  :title="t('import::imports.download_template')"
                  @click="download('template')"
                />
                <VListItem
                  v-if="canCancel"
                  :disabled="locked"
                  prepend-icon="tabler-ban"
                  :title="t('import::imports.cancel')"
                  @click="cancelImport"
                />
              </VList>
            </VMenu>
          </div>
        </div>

        <ImportColumnMapping
          v-if="step === 2"
          v-model="mapping"
          :current="current"
          :fields="fields"
          :busy="busy"
          :locked="locked"
          :missing-required-count="missingRequiredFields.length"
          :duplicate-mapping="duplicateMapping"
          @save="saveMapping"
          @back="current.status.id !== 'uploaded' ? backToReview() : newImport()"
        />

        <template v-else>
          <p
            v-if="step === 3"
            class="import-behavior-hint"
          >
            {{ t(`import::imports.mode_hints.${mode}`) }}
          </p>
          <ImportSummary
            :current="current"
            :validated="validated"
            :working="working"
            :result="step === 4"
            :poll-error="pollError"
          />
          <ImportSheet
            :current="current"
            :fields="fields"
            :mapping="current.mapping"
            :rows="rows"
            :loading="rowsLoading"
            :error="rowsError"
            :editable="editable"
            :saving="pendingEdits > 0"
            :show-operations="validated || step === 4"
            :page="page"
            :last-page="lastPage"
            @save-cell="saveRow"
            @change-page="changePage"
            @retry="loadRows"
          />
        </template>

        <footer
          v-if="step !== 2"
          class="import-footer"
        >
          <div class="import-footer-summary">
            <span v-if="pendingEdits">{{ t('import::imports.saving_edits') }}</span>
            <span
              v-if="current.invalid_rows"
              class="text-error"
            >
              {{ current.invalid_rows }} {{ t('import::imports.invalid_short') }}
            </span>
            <VBtn
              v-if="current.has_error_file"
              :title="errorFileName"
              :aria-label="`${t('import::imports.error_file')}: ${errorFileName}`"
              class="import-error-download"
              prepend-icon="tabler-download"
              size="small"
              variant="text"
              @click="download('errors')"
            >
              <span class="import-error-filename">{{ errorFileName }}</span>
            </VBtn>
          </div>
          <VBtn
            v-if="current.status.id === 'ready' && current.mode.id === mode"
            :disabled="!canConfirm"
            color="primary"
            :loading="busy"
            prepend-icon="tabler-check"
            variant="flat"
            @click="queueImport"
          >
            {{ t('import::imports.start_import') }}
          </VBtn>
          <VBtn
            v-else-if="step === 3 && !working"
            :disabled="!showValidationAction || !!rowsError"
            color="primary"
            :loading="busy"
            prepend-icon="tabler-refresh"
            variant="flat"
            @click="validateFile"
          >
            {{ t('import::imports.validate') }}
          </VBtn>
          <VBtn
            v-else-if="canRetry"
            color="primary"
            :loading="busy"
            variant="flat"
            @click="retry"
          >
            {{ t('import::imports.retry_import') }}
          </VBtn>
          <VBtn
            v-else-if="step === 4 && !working"
            color="primary"
            variant="flat"
            @click="open = false"
          >
            {{ t('import::imports.done') }}
          </VBtn>
          <VBtn
            v-else-if="working"
            variant="text"
            @click="open = false"
          >
            {{ t('import::imports.close_continue') }}
          </VBtn>
        </footer>
      </template>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.import-details-loading {
  min-block-size: 320px;
}

.import-workspace {
  display: flex;
  flex-direction: column;
  height: min(860px, calc(100dvh - 48px));
  max-height: min(860px, calc(100dvh - 48px));
  overflow: hidden;
  box-shadow: 0 24px 70px rgba(27, 38, 56, 0.19);
}

.import-workspace-compact {
  height: min(700px, calc(100dvh - 48px));
  max-height: min(700px, calc(100dvh - 48px));
}

.import-header {
  display: flex;
  min-height: 52px;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 8px 24px 4px;
}

.import-title {
  margin: 0;
  font-size: 18px;
  font-weight: 700;
  line-height: 1.4;
}

.import-workspace :deep(.v-btn) {
  letter-spacing: 0;
  text-transform: none;
}

.import-toolbar {
  display: flex;
  min-height: 52px;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 8px 24px;
  background: rgba(var(--v-theme-on-surface), 0.025);
  border-block-end: 1px solid rgba(var(--v-border-color), 0.15);
}

.import-file-meta,
.import-toolbar-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.import-file-meta {
  min-width: 0;
}

.import-file-meta strong,
.import-file-meta span {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.import-file-meta strong {
  font-size: 14px;
}

.import-file-meta span {
  color: rgba(var(--v-theme-on-surface), 0.6);
  font-size: 12px;
}

.import-toolbar-actions {
  flex-wrap: wrap;
  justify-content: flex-end;
}

.import-status {
  border-radius: 6px;
  padding: 5px 8px;
  background: rgba(var(--v-theme-primary), 0.09);
  color: rgb(var(--v-theme-primary));
  font-size: 12px;
  white-space: nowrap;
}

.import-status-error {
  background: rgba(var(--v-theme-error), 0.09);
  color: rgb(var(--v-theme-error));
}

.import-mode-control {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 12px;
  color: rgba(var(--v-theme-on-surface), 0.7);
}

.import-mode {
  width: 190px;
  min-width: 160px;
  flex: 0 1 190px;
}

.import-workspace-review .import-file-meta > div {
  display: flex;
  align-items: baseline;
  flex-wrap: wrap;
  gap: 4px 10px;
}

.import-workspace-review .import-file-meta strong {
  font-size: 13px;
  font-weight: 600;
}

.import-error-download {
  min-width: 0;
  max-width: 100%;
}

.import-error-download :deep(.v-btn__content) {
  min-width: 0;
}

.import-error-filename {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.import-workspace-mapping {
  height: min(800px, calc(100dvh - 48px));
  max-height: min(800px, calc(100dvh - 48px));
}

.import-workspace-mapping .import-toolbar {
  min-height: 44px;
  padding: 4px 12px;
  margin: 0 24px;
  border: 1px solid rgba(var(--v-border-color), 0.12);
  border-radius: 8px;
}

.import-workspace-mapping .import-file-meta > div {
  display: flex;
  align-items: baseline;
  flex-wrap: wrap;
  column-gap: 12px;
}

.import-workspace-mapping .import-file-meta strong {
  font-size: 13px;
  font-weight: 600;
}

.import-footer {
  display: flex;
  min-height: 66px;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 14px 28px;
  border-block-start: 1px solid rgba(var(--v-border-color), 0.15);
}

.import-footer-summary {
  display: flex;
  min-width: 0;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
  color: rgba(var(--v-theme-on-surface), 0.62);
  font-size: 12px;
}

.import-footer-summary strong {
  color: rgb(var(--v-theme-on-surface));
}

.import-behavior-hint {
  flex-shrink: 0;
  margin: 0;
  padding: 8px 24px;
  color: rgba(var(--v-theme-on-surface), 0.65);
  font-size: 12px;
}

.import-toolbar,
.import-header,
.import-footer {
  flex-shrink: 0;
}

@media (max-width: 900px) {
  .import-toolbar {
    align-items: flex-start;
    flex-direction: column;
  }

  .import-toolbar-actions {
    width: 100%;
    justify-content: flex-start;
  }

  .import-workspace-mapping .import-toolbar {
    flex-direction: row;
    align-items: center;
  }

  .import-workspace-mapping .import-toolbar-actions {
    width: auto;
  }
}

@media (max-width: 600px) {
  .import-header {
    min-height: 52px;
  }

  .import-title {
    font-size: 18px;
  }

  .import-behavior-hint {
    padding: 8px 16px;
  }

  .import-mode-control {
    flex: 1 1 100%;
    justify-content: space-between;
  }

  .import-mode {
    flex: auto;
    max-width: none;
  }

  .import-workspace {
    display: block;
    overflow-y: auto;
    height: 100dvh;
    max-height: 100dvh;
    border-radius: 0;
  }

  .import-header,
  .import-toolbar,
  .import-footer {
    padding-inline: 16px;
  }

  .import-footer {
    position: sticky;
    bottom: 0;
    z-index: 5;
    background: rgb(var(--v-theme-surface));
  }

  .import-footer {
    align-items: stretch;
    flex-direction: column;
  }

  .import-workspace-compact,
  .import-workspace-mapping,
  .import-workspace-review {
    display: flex;
    overflow: hidden;
  }

  .import-workspace-mapping .import-toolbar {
    margin-inline: 16px;
    flex-direction: row;
    align-items: center;
    gap: 8px;
  }

  .import-workspace-mapping .import-toolbar-actions {
    width: auto;
  }
}
</style>
