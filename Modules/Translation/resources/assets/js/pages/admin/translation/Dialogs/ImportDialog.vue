<script lang="ts" setup>
import type {
  ImportFileType,
  ImportMode,
  ImportPreview,
} from '@/modules/translation/contracts/Translation.ts'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  modelValue: boolean
  applying: boolean
  file: File | File[] | null
  importFileType: ImportFileType
  languageItems: { title: string; value: string }[]
  loading: boolean
  preview: ImportPreview | null
  result: ImportPreview | null
  importLocale: string
  importMode: ImportMode
  importModeItems: readonly { title: string; value: ImportMode }[]
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (e: 'update:file', value: File | File[] | null): void
  (e: 'update:importFileType', value: ImportFileType): void
  (e: 'update:importLocale', value: string): void
  (e: 'update:importMode', value: ImportMode): void
  (e: 'back-to-setup' | 'preview' | 'apply'): void
}>()

const selectedImportFile = computed(() => (Array.isArray(props.file) ? props.file[0] : props.file))
const isZipImport = computed(() => props.importFileType === 'zip')
const fileTypeItems = computed(
  () =>
    [
      {
        title: t('translation::translations.import_dialog.file_type_json'),
        value: 'json',
      },
      {
        title: t('translation::translations.import_dialog.file_type_zip'),
        value: 'zip',
      },
    ] as const,
)
const acceptedFileTypes = computed(() =>
  isZipImport.value ? '.zip,application/zip' : '.json,application/json',
)
const uploadHint = computed(() =>
  isZipImport.value
    ? t('translation::translations.import_dialog.upload_hint_zip')
    : t('translation::translations.import_dialog.upload_hint_json'),
)
const uploadFieldLabel = computed(() =>
  isZipImport.value
    ? t('translation::translations.import_dialog.file_zip')
    : t('translation::translations.import_dialog.file_json'),
)
const uploadPlaceholder = computed(() =>
  isZipImport.value
    ? t('translation::translations.import_dialog.file_placeholder_zip')
    : t('translation::translations.import_dialog.file_placeholder_json'),
)
const previewStats = computed(() => buildPreviewStats())
const selectedFileName = computed(() => selectedImportFile.value?.name || '—')
const zipLanguages = computed(() =>
  Object.keys(props.preview?.locales || {}).map((locale) => ({
    key: locale,
    title: languageTitle(locale),
  })),
)
const importModeLabel = computed(() =>
  props.importMode === 'overwrite'
    ? t('translation::translations.import_dialog.overwrite_existing')
    : t('translation::translations.import_dialog.fill_missing_only'),
)
const selectedLanguageTitle = computed(() => languageTitle(props.importLocale))
const currentStep = computed(() => (props.preview ? 2 : 1))
const wizardSteps = computed(() => [
  {
    key: 1,
    title: t('translation::translations.import_dialog.step_setup'),
  },
  {
    key: 2,
    title: t('translation::translations.import_dialog.step_review'),
  },
])
const previewHeadline = computed(() => {
  if (!props.preview) return ''

  return props.result
    ? t('translation::translations.import_dialog.preview_applied', {
        updated: props.result.updated ?? 0,
      })
    : t('translation::translations.import_dialog.preview_ready', { valid: props.preview.valid })
})
const { t } = useI18n()

function buildPreviewStats() {
  if (!props.preview) return []

  const stats = [
    {
      key: 'valid',
      value: props.preview.valid,
      label: t('translation::translations.import_dialog.stats.valid'),
      tone: 'primary',
    },
    {
      key: 'fill_missing',
      value: props.preview.would_fill_missing,
      label: t('translation::translations.import_dialog.stats.fill_missing'),
      tone: 'info',
    },
    {
      key: 'overwrite',
      value: props.preview.would_overwrite,
      label: t('translation::translations.import_dialog.stats.overwrite'),
      tone: 'warning',
    },
    {
      key: 'empty_skipped',
      value: props.preview.empty_skipped,
      label: t('translation::translations.import_dialog.stats.empty_skipped'),
      tone: 'secondary',
    },
    {
      key: 'unknown',
      value: props.preview.unknown,
      label: t('translation::translations.import_dialog.stats.unknown'),
      tone: 'error',
    },
    {
      key: 'placeholder_warnings',
      value: props.preview.placeholder_warning_count,
      label: t('translation::translations.import_dialog.stats.placeholder_warnings'),
      tone: 'warning',
    },
  ]

  if (props.result) {
    stats.push({
      key: 'updated',
      value: props.result.updated ?? 0,
      label: t('translation::translations.import_dialog.stats.updated'),
      tone: 'success',
    })
  }

  return stats
}

function languageTitle(locale: string): string {
  return props.languageItems.find((item) => item.value === locale)?.title || locale
}
</script>

<template>
  <VDialog
    max-width="760"
    :model-value="modelValue"
    :persistent="applying"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between">
        {{ t('translation::translations.import_dialog.title') }}
        <VBtn
          :disabled="applying"
          icon="tabler-x"
          variant="text"
          @click="emit('update:modelValue', false)"
        />
      </VCardTitle>
      <VCardText>
        <div class="wizard-steps mb-4">
          <div
            v-for="step in wizardSteps"
            :key="step.key"
            class="wizard-step"
            :class="{
              'wizard-step--active': currentStep === step.key,
              'wizard-step--done': currentStep > step.key,
            }"
          >
            <div class="wizard-step__badge">
              <VIcon
                v-if="currentStep > step.key"
                icon="tabler-check"
                size="16"
              />
              <span v-else>{{ step.key }}</span>
            </div>
            <span class="text-body-2">{{ step.title }}</span>
          </div>
        </div>

        <VAlert
          v-if="!preview"
          class="mb-6"
          type="info"
          variant="tonal"
        >
          <div class="text-subtitle-2 mb-1">
            {{ t('translation::translations.import_dialog.note_title') }}
          </div>
          <div class="text-body-2">
            {{ t('translation::translations.import_dialog.help') }}
          </div>
        </VAlert>

        <VRow v-if="!preview">
          <VCol
            cols="12"
            md="6"
          >
            <VSelect
              :items="fileTypeItems"
              :label="t('translation::translations.import_dialog.file_type')"
              :model-value="importFileType"
              @update:model-value="emit('update:importFileType', $event)"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <VSelect
              :items="importModeItems"
              :label="t('translation::translations.import_dialog.mode')"
              :model-value="importMode"
              @update:model-value="emit('update:importMode', $event)"
            />
          </VCol>
          <VCol
            v-if="!isZipImport"
            cols="12"
            md="6"
          >
            <VSelect
              :items="languageItems"
              :label="t('translation::translations.import_dialog.target_language')"
              :model-value="importLocale"
              @update:model-value="emit('update:importLocale', $event)"
            />
          </VCol>
          <VCol cols="12">
            <VSheet
              border
              class="import-upload-panel pa-4"
              rounded="lg"
            >
              <div class="d-flex align-center gap-3 mb-3">
                <VAvatar
                  color="primary"
                  size="40"
                  variant="tonal"
                >
                  <VIcon :icon="isZipImport ? 'tabler-file-zip' : 'tabler-file-code'" />
                </VAvatar>
                <div>
                  <div class="text-subtitle-2">
                    {{ t('translation::translations.import_dialog.upload_title') }}
                  </div>
                  <div class="text-body-2 text-medium-emphasis">
                    {{ uploadHint }}
                  </div>
                </div>
              </div>

              <VFileInput
                :accept="acceptedFileTypes"
                clearable
                density="comfortable"
                :label="uploadFieldLabel"
                :model-value="file"
                :placeholder="uploadPlaceholder"
                prepend-icon=""
                show-size
                variant="outlined"
                @update:model-value="emit('update:file', $event)"
              />
            </VSheet>
          </VCol>
        </VRow>

        <div
          v-if="preview"
          class="preview-panel"
        >
          <div class="preview-panel__header mb-4">
            <div class="text-subtitle-1 font-weight-medium mb-1">
              {{ previewHeadline }}
            </div>
            <div class="text-body-2 text-medium-emphasis">
              {{
                t('translation::translations.import_dialog.preview_description', {
                  total: preview.total,
                })
              }}
            </div>
          </div>

          <div class="d-flex flex-wrap gap-2 mb-4">
            <VChip
              size="small"
              variant="outlined"
            >
              {{
                isZipImport
                  ? t('translation::translations.import_dialog.file_type_zip')
                  : t('translation::translations.import_dialog.file_type_json')
              }}
            </VChip>
            <VChip
              size="small"
              variant="outlined"
            >
              {{ importModeLabel }}
            </VChip>
            <VChip
              size="small"
              variant="outlined"
            >
              {{
                preview.is_zip
                  ? t('translation::translations.import_dialog.details_multiple_languages')
                  : selectedLanguageTitle
              }}
            </VChip>
          </div>

          <VAlert
            v-if="preview.placeholder_warning_count > 0"
            class="mb-4"
            type="warning"
            variant="tonal"
          >
            {{ t('translation::translations.import_dialog.placeholder_warning') }}
          </VAlert>

          <VSheet
            class="preview-section"
            rounded="lg"
            variant="outlined"
          >
            <div class="preview-row">
              <span class="preview-row__label">
                {{ t('translation::translations.import_dialog.details_file') }}
              </span>
              <span class="preview-row__value text-truncate">
                {{ selectedFileName }}
              </span>
            </div>
            <div
              v-for="stat in previewStats"
              :key="stat.key"
              class="preview-row"
            >
              <span class="preview-row__label">{{ stat.label }}</span>
              <span class="preview-row__value">{{ stat.value }}</span>
            </div>
            <div
              v-if="preview.is_zip"
              class="preview-row preview-row--stacked"
            >
              <span class="preview-row__label">
                {{ t('translation::translations.import_dialog.zip_languages_title') }}
              </span>
              <div class="d-flex flex-wrap gap-2 justify-end">
                <VChip
                  v-for="locale in zipLanguages"
                  :key="locale.key"
                  size="small"
                  variant="outlined"
                >
                  {{ locale.title }}
                </VChip>
              </div>
            </div>
          </VSheet>
        </div>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          :disabled="applying"
          variant="text"
          @click="emit('update:modelValue', false)"
        >
          {{ t('translation::translations.close') }}
        </VBtn>
        <VBtn
          v-if="!preview"
          :disabled="!selectedImportFile"
          :loading="loading"
          variant="tonal"
          @click="emit('preview')"
        >
          {{ t('translation::translations.preview') }}
        </VBtn>
        <VBtn
          v-if="preview"
          :disabled="applying"
          variant="text"
          @click="emit('back-to-setup')"
        >
          {{ t('translation::translations.back') }}
        </VBtn>
        <VBtn
          v-if="preview"
          color="primary"
          :disabled="!preview || applying"
          :loading="applying"
          @click="emit('apply')"
        >
          {{ t('translation::translations.import_dialog.apply') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.import-upload-panel {
  border-style: dashed !important;
}

.wizard-steps {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.wizard-step {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.08);
  border-radius: 12px;
  color: rgba(var(--v-theme-on-surface), 0.68);
}

.wizard-step--active {
  border-color: rgba(var(--v-theme-primary), 0.3);
  background: rgba(var(--v-theme-primary), 0.05);
  color: rgb(var(--v-theme-on-surface));
}

.wizard-step--done {
  border-color: rgba(var(--v-theme-success), 0.24);
}

.wizard-step__badge {
  display: flex;
  align-items: center;
  justify-content: center;
  inline-size: 24px;
  block-size: 24px;
  border-radius: 999px;
  background: rgba(var(--v-theme-on-surface), 0.08);
  font-size: 12px;
  font-weight: 600;
}

.wizard-step--active .wizard-step__badge {
  background: rgba(var(--v-theme-primary), 0.14);
}

.wizard-step--done .wizard-step__badge {
  background: rgba(var(--v-theme-success), 0.14);
}

.import-preview-panel {
  padding-top: 8px;
}

.preview-review-surface {
  background: linear-gradient(
    180deg,
    rgba(var(--v-theme-surface), 1) 0%,
    rgba(var(--v-theme-surface-variant), 0.22) 100%
  );
  border: 1px solid rgba(var(--v-theme-on-surface), 0.08);
}

.preview-panel__header {
  max-width: 520px;
}

.preview-section {
  border-color: rgba(var(--v-theme-on-surface), 0.08);
}

.preview-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
  padding: 14px 16px;
}

.preview-row + .preview-row {
  border-top: 1px solid rgba(var(--v-theme-on-surface), 0.06);
}

.preview-row--stacked {
  align-items: center;
}

.preview-row__label {
  color: rgba(var(--v-theme-on-surface), 0.62);
  font-size: 0.875rem;
}

.preview-row__value {
  font-weight: 500;
  text-align: end;
  max-width: 320px;
}

@media (max-width: 959px) {
  .preview-row,
  .preview-row--stacked {
    flex-direction: column;
    align-items: flex-start;
  }

  .preview-row__value {
    max-width: none;
    text-align: start;
  }
}
</style>
