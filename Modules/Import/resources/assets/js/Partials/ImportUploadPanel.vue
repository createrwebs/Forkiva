<script setup lang="ts">
import type { DataImport } from '@/modules/import/contracts/import.ts'
import { useResizeObserver } from '@vueuse/core'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  busy: boolean
  history: DataImport[]
  historyLoading: boolean
  templateDownloading: boolean
  notice?: string
}>()
const emit = defineEmits<{
  upload: [file: File]
  resume: [item: DataImport]
  downloadTemplate: []
}>()
const { t } = useI18n()
const fileInput = ref<HTMLInputElement | null>(null)
const uploadSection = ref<HTMLElement | null>(null)
const uploadSectionHeight = ref(0)

useResizeObserver(uploadSection, () => {
  uploadSectionHeight.value = uploadSection.value?.offsetHeight ?? 0
})

function onFileChange(event: Event) {
  const selected = (event.target as HTMLInputElement).files?.[0]
  if (selected && !props.busy) emit('upload', selected)
  if (fileInput.value) fileInput.value.value = ''
}

function onDrop(event: DragEvent) {
  const selected = event.dataTransfer?.files?.[0]
  if (selected && !props.busy) emit('upload', selected)
}

function openFilePicker() {
  if (!props.busy) fileInput.value?.click()
}

function historyColor(status: DataImport['status']['id']): string {
  if (['completed', 'ready'].includes(status)) return 'success'
  if (['failed', 'validation_failed', 'partially_completed'].includes(status)) return 'error'
  if (status === 'cancelled') return 'secondary'
  return 'primary'
}

function historyAction(status: DataImport['status']['id']): string {
  if (['completed', 'partially_completed', 'failed', 'cancelled'].includes(status))
    return 'view_result'
  if (['queued', 'processing', 'staging', 'validating'].includes(status)) return 'view_progress'
  return 'resume'
}
</script>

<template>
  <main
    class="import-upload"
    :style="
      uploadSectionHeight
        ? { '--import-upload-section-height': `${uploadSectionHeight}px` }
        : undefined
    "
  >
    <section
      ref="uploadSection"
      class="import-upload-new"
    >
      <h3>{{ t('import::imports.upload_new') }}</h3>
      <p>{{ t('import::imports.upload_hint') }}</p>
      <div
        v-if="notice"
        class="import-upload-notice"
      >
        <VIcon
          icon="tabler-info-circle"
          size="18"
        />
        <span>{{ notice }}</span>
      </div>
      <div
        class="import-dropzone"
        :class="{ 'import-dropzone-busy': busy }"
        role="button"
        :tabindex="busy ? -1 : 0"
        :aria-label="t('import::imports.choose_file')"
        :aria-disabled="busy"
        @click="openFilePicker"
        @keydown.enter.prevent="openFilePicker"
        @keydown.space.prevent="openFilePicker"
        @dragover.prevent
        @drop.prevent="onDrop"
      >
        <span class="import-upload-icon">
          <VProgressCircular
            v-if="busy"
            indeterminate
            color="primary"
            size="26"
          />
          <VIcon
            v-else
            icon="tabler-upload"
            size="28"
          />
        </span>
        <strong>
          {{ busy ? t('import::imports.processing_file') : t('import::imports.drop_file') }}
        </strong>
        <span v-if="!busy">{{ t('import::imports.formats_hint') }}</span>
        <VBtn
          v-if="!busy"
          :disabled="busy"
          color="primary"
          size="default"
          variant="flat"
          @click.stop="openFilePicker"
        >
          {{ t('import::imports.choose_file') }}
        </VBtn>
        <input
          ref="fileInput"
          class="import-file-input"
          type="file"
          accept=".csv,.xlsx,.xls"
          :aria-label="t('import::imports.choose_file')"
          @click.stop
          @change="onFileChange"
        />
      </div>
      <div class="import-upload-options">
        <VIcon
          icon="tabler-file-spreadsheet"
          color="primary"
          size="24"
        />
        <div>
          <strong>{{ t('import::imports.get_started') }}</strong>
          <p>{{ t('import::imports.template_description') }}</p>
        </div>
        <VBtn
          :aria-label="t('import::imports.download_template')"
          :title="t('import::imports.download_template')"
          :loading="templateDownloading"
          :disabled="templateDownloading"
          color="secondary"
          icon="tabler-download"
          variant="outlined"
          size="small"
          rounded="lg"
          @click="emit('downloadTemplate')"
        />
      </div>
    </section>
    <section
      class="import-history"
      :aria-label="t('import::imports.history')"
    >
      <div class="import-history-heading">
        <h3>{{ t('import::imports.history') }}</h3>
        <VChip
          size="x-small"
          variant="tonal"
        >
          {{ history.length }}
        </VChip>
      </div>
      <p>{{ t('import::imports.history_hint') }}</p>
      <div
        v-if="historyLoading"
        class="import-history-loading"
        role="status"
        :aria-label="t('import::imports.loading_details')"
      >
        <VProgressCircular
          indeterminate
          color="primary"
          size="32"
        />
      </div>
      <div
        v-else-if="history.length"
        class="import-history-list"
      >
        <button
          v-for="item in history"
          :key="item.id"
          type="button"
          class="import-history-item"
          :disabled="busy"
          @click="emit('resume', item)"
        >
          <span class="import-history-icon">
            <VIcon
              icon="tabler-file-spreadsheet"
              size="20"
            />
          </span>
          <span class="import-history-content">
            <strong
              class="import-history-name"
              :title="item.original_name"
            >
              {{ item.original_name }}
            </strong>
            <span class="import-history-meta">
              <span>{{ item.created_at }}</span>
              <span v-if="item.total_rows">
                · {{ item.total_rows }} {{ t('import::imports.rows') }}
              </span>
            </span>
            <span class="import-history-bottom">
              <VChip
                :color="historyColor(item.status.id)"
                size="x-small"
                variant="tonal"
              >
                {{ item.status.name }}
              </VChip>
            </span>
          </span>
          <span class="import-history-action">
            <span>{{ t(`import::imports.${historyAction(item.status.id)}`) }}</span>
            <VIcon
              icon="tabler-chevron-right"
              size="16"
              class="import-direction-icon"
            />
          </span>
        </button>
      </div>
      <div
        v-else
        class="import-history-empty"
      >
        <VIcon
          icon="tabler-file-spreadsheet"
          size="32"
        />
        <strong>{{ t('import::imports.no_history') }}</strong>
        <span>{{ t('import::imports.no_history_hint') }}</span>
      </div>
    </section>
  </main>
</template>

<style lang="scss" scoped>
.import-upload {
  display: grid;
  grid-template-columns: minmax(0, 1.12fr) minmax(0, 1fr);
  align-items: start;
  flex: 1;
  min-height: 0;
  overflow: auto;
}

.import-upload-new,
.import-history {
  min-width: 0;
  padding: 28px;
}

.import-upload h3 {
  margin: 0 0 6px;
  font-size: 18px;
  font-weight: 700;
}

.import-upload p {
  margin: 0 0 22px;
  color: rgba(var(--v-theme-on-surface), 0.68);
  font-size: 13px;
  line-height: 1.7;
}

.import-upload-notice {
  display: flex;
  gap: 10px;
  margin: -8px 0 18px;
  padding: 12px;
  border-radius: 8px;
  background: rgba(var(--v-theme-primary), 0.07);
  color: rgb(var(--v-theme-on-surface));
  font-size: 12px;
  line-height: 1.5;
}

.import-upload-notice .v-icon {
  flex: none;
  color: rgb(var(--v-theme-primary));
}

.import-dropzone {
  display: flex;
  width: 100%;
  min-height: 252px;
  padding: 24px;
  text-align: center;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
  border: 1.5px dashed rgba(var(--v-theme-primary), 0.5);
  border-radius: 12px;
  background: rgba(var(--v-theme-primary), 0.015);
  color: rgb(var(--v-theme-primary));
  cursor: pointer;
  transition:
    background 0.15s,
    border-color 0.15s;
}

.import-dropzone:hover,
.import-dropzone:focus-within,
.import-dropzone:focus-visible {
  border-color: rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.04);
}

.import-upload-icon {
  display: grid;
  width: 56px;
  height: 56px;
  margin-bottom: 6px;
  place-items: center;
  border-radius: 14px;
  background: rgba(var(--v-theme-primary), 0.09);
}

.import-dropzone strong {
  color: rgb(var(--v-theme-on-surface));
  font-size: 15px;
}

.import-dropzone > span:not(.import-upload-icon) {
  color: rgba(var(--v-theme-on-surface), 0.65);
  font-size: 12px;
}

.import-dropzone .v-btn {
  margin-top: 6px;
}

.import-dropzone-busy {
  cursor: wait;
  pointer-events: none;
}

.import-dropzone:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 2px;
}

.import-file-input {
  display: none;
}

.import-upload-options {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
  margin-top: 16px;
  border: 1.5px dashed rgba(var(--v-theme-primary), 0.5);
  border-radius: 10px;
  font-size: 13px;
}

.import-upload-options > div {
  flex: 1;
  min-width: 0;
}

.import-upload-options p {
  margin: 3px 0 0;
  font-size: 12px;
}

.import-upload-options .v-btn {
  flex-shrink: 0;
  border-color: rgba(var(--v-border-color), 0.18);
}

.import-history {
  display: flex;
  flex-direction: column;
  height: var(--import-upload-section-height, auto);
  min-height: 0;
  margin-block: 0 12px;
  margin-inline: 0 12px;
  padding: 24px;
  border-radius: 12px;
  background: rgba(var(--v-theme-on-surface), 0.035);
  overflow: hidden;
}

.import-history-heading {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 6px;
}

.import-history-heading h3 {
  margin: 0;
}

.import-history > p {
  margin-bottom: 12px;
}

.import-history-list {
  overflow: auto;
  min-height: 0;
  padding: 2px;
  scrollbar-width: thin;
}

.import-history-loading {
  display: flex;
  align-items: center;
  justify-content: center;
  flex: 1;
  min-height: 180px;
}

.import-history-item {
  display: flex;
  align-items: flex-start;
  width: 100%;
  gap: 12px;
  padding: 16px 0;
  border-block-end: 1px solid rgba(var(--v-border-color), 0.12);
  color: rgb(var(--v-theme-on-surface));
  cursor: pointer;
  font-size: 13px;
  text-align: start;
  transition: background 0.15s;
}

.import-history-item:last-child {
  border-block-end: 0;
}

.import-history-item:hover {
  background: rgba(var(--v-theme-primary), 0.04);
}

.import-history-item:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: -2px;
  border-radius: 6px;
}

.import-history-item:disabled {
  cursor: wait;
  opacity: 0.6;
}

.import-history-icon {
  display: grid;
  width: 36px;
  height: 40px;
  flex-shrink: 0;
  place-items: center;
  border-radius: 8px;
  background: rgba(var(--v-theme-on-surface), 0.055);
  color: rgba(var(--v-theme-on-surface), 0.7);
}

.import-history-content {
  flex: 1;
  min-width: 0;
}

.import-history-name {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-weight: 600;
}

.import-history-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
  margin: 4px 0 8px;
  color: rgba(var(--v-theme-on-surface), 0.65);
  font-size: 11px;
}

.import-history-bottom {
  display: flex;
}

.import-history-action {
  display: flex;
  align-items: center;
  align-self: center;
  gap: 3px;
  color: rgba(var(--v-theme-on-surface), 0.7);
  font-size: 11px;
  font-weight: 600;
}

.import-history-item:hover .import-history-action {
  color: rgb(var(--v-theme-primary));
}

.import-history-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  flex: 1;
  min-height: 180px;
  gap: 10px;
  text-align: center;
  color: rgba(var(--v-theme-on-surface), 0.65);
  font-size: 13px;
}

.import-direction-icon:dir(rtl) {
  transform: rotate(180deg);
}

@media (max-width: 850px) {
  .import-upload {
    display: block;
  }

  .import-history {
    height: auto;
    margin: 0 16px 16px;
    overflow: visible;
  }

  .import-history-list {
    max-height: 360px;
  }
}

@media (max-width: 600px) {
  .import-upload {
    display: block;
  }

  .import-upload-new,
  .import-history {
    padding: 20px 16px;
  }

  .import-history {
    margin: 0 12px 12px;
    border-inline-start: 0;
    border-block-start: 1px solid rgba(var(--v-border-color), 0.15);
  }

  .import-history-list {
    max-height: 360px;
  }
}
</style>
