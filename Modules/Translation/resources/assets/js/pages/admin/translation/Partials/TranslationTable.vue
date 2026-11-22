<script lang="ts" setup>
import type {
  TranslationEditingState,
  TranslationLanguage,
  TranslationRow,
} from '@/modules/translation/contracts/Translation.ts'
import { useI18n } from 'vue-i18n'

defineProps<{
  displayedLanguages: TranslationLanguage[]
  failed: boolean
  loading: boolean
  canEdit: boolean
  rows: TranslationRow[]
  saving: boolean
  editing: TranslationEditingState | null
  missingPlaceholders: string[]
}>()

const emit = defineEmits<{
  (e: 'retry' | 'cancel-edit' | 'save-edit'): void
  (e: 'start-edit', item: TranslationRow, lang: TranslationLanguage): void
  (e: 'update-editing-value', value: string): void
}>()

const { t } = useI18n()

const isMissing = (value: unknown) => value === null || value === undefined || value === ''
</script>

<template>
  <div class="translation-table-wrap">
    <VTable
      class="translation-table"
      density="comfortable"
      fixed-header
    >
      <thead>
        <tr>
          <th class="translation-key-column">
            {{ t('translation::translations.table.key') }}
          </th>
          <th
            v-for="lang in displayedLanguages"
            :key="lang.id"
          >
            {{ lang.name }}
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="loading || failed || rows.length === 0">
          <td
            class="text-center pa-8"
            :colspan="displayedLanguages.length + 1"
          >
            <div class="d-flex flex-column align-center justify-center ga-3">
              <template v-if="loading">
                <VProgressCircular
                  color="primary"
                  indeterminate
                />
                <div class="text-caption text-medium-emphasis">
                  {{ t('admin::admin.table.loading') }}
                </div>
              </template>
              <template v-else-if="failed">
                <VIcon
                  color="error"
                  icon="tabler-alert-circle"
                  size="32"
                />
                <div class="text-body-2">
                  {{ t('translation::translations.table.load_failed') }}
                </div>
                <VBtn
                  size="small"
                  variant="tonal"
                  @click="emit('retry')"
                >
                  {{ t('translation::translations.retry') }}
                </VBtn>
              </template>
              <template v-else>
                <VIcon
                  icon="tabler-search"
                  size="32"
                />
                <div class="text-body-2">
                  {{ t('admin::admin.table.no_matching_records_found') }}
                </div>
              </template>
            </div>
          </td>
        </tr>
        <tr
          v-for="item in rows"
          v-else
          :key="item.key"
        >
          <td class="translation-key-column font-weight-medium">{{ item.key }}</td>
          <td
            v-for="lang in displayedLanguages"
            :key="lang.id"
            class="translation-cell"
            :class="{ 'translation-cell--missing': isMissing(item[lang.id]) }"
          >
            <div
              v-if="editing?.key === item.key && editing?.lang === lang.id"
              class="translation-editor"
            >
              <VTextField
                autofocus
                density="compact"
                :disabled="saving"
                hide-details="auto"
                :model-value="editing.value"
                @keyup.enter="emit('save-edit')"
                @keyup.esc="emit('cancel-edit')"
                @update:model-value="emit('update-editing-value', $event)"
              >
                <template #append>
                  <VBtn
                    :aria-label="t('translation::translations.edit.cancel_aria')"
                    color="default"
                    icon="tabler-x"
                    size="small"
                    variant="text"
                    @click.stop="emit('cancel-edit')"
                  />
                  <VBtn
                    :aria-label="t('translation::translations.edit.save_aria')"
                    color="primary"
                    icon="tabler-check"
                    :loading="saving"
                    size="small"
                    variant="text"
                    @click.stop="emit('save-edit')"
                  />
                </template>
              </VTextField>
              <VAlert
                v-if="missingPlaceholders.length > 0"
                class="mt-2"
                density="compact"
                type="warning"
                variant="tonal"
              >
                {{
                  t('translation::translations.edit.missing_placeholders', {
                    placeholders: missingPlaceholders.join(', '),
                  })
                }}
              </VAlert>
            </div>
            <button
              v-else
              class="translation-cell-button"
              type="button"
              @click="emit('start-edit', item, lang)"
            >
              <span
                class="text-translation"
                :class="{
                  'translation-cell--empty': isMissing(item[lang.id]),
                  'text-error': isMissing(item[lang.id]),
                  'text-blue': !isMissing(item[lang.id]),
                }"
              >
                {{ item[lang.id] || t('translation::translations.table.empty') }}
              </span>
              <VIcon
                v-if="canEdit"
                class="translation-edit-icon"
                icon="tabler-pencil"
                size="16"
              />
            </button>
          </td>
        </tr>
      </tbody>
    </VTable>
  </div>
</template>

<style lang="scss" scoped>
.translation-table-wrap {
  overflow: auto;
  max-width: 100%;
}

.translation-table {
  min-width: 960px;
}

.translation-table th {
  white-space: nowrap;
}

.translation-key-column {
  position: sticky;
  inset-inline-start: 0;
  z-index: 2;
  min-width: 220px;
  max-width: 280px;
  background: rgb(var(--v-theme-surface));
  box-shadow: 1px 0 0 rgba(var(--v-border-color), var(--v-border-opacity));
}

thead .translation-key-column {
  z-index: 3;
}

.translation-cell {
  min-width: 260px;
  max-width: 360px;
  padding: 8px 12px;
  color: #333;
}

.translation-cell--missing {
  background: rgba(var(--v-theme-error), 0.04);
}

.translation-cell-button {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  min-height: 36px;
  padding: 0;
  border: 0;
  background: transparent;
  color: inherit;
  cursor: pointer;
  text-align: start;
}

.text-translation {
  flex: 1 1 auto;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.translation-cell--empty {
  font-style: italic;
}

.text-blue {
  color: #3498db;
}

.translation-edit-icon {
  flex: 0 0 auto;
  opacity: 0;
  transition: opacity 0.15s ease;
}

.translation-cell-button:hover .translation-edit-icon,
.translation-cell-button:focus-visible .translation-edit-icon {
  opacity: 1;
}

.translation-editor {
  min-width: 360px;
}
</style>
