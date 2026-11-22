<script setup lang="ts">
import type { DataImport, ImportField } from '@/modules/import/contracts/import.ts'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  current: DataImport
  fields: ImportField[]
  busy: boolean
  locked: boolean
  missingRequiredCount: number
  duplicateMapping: boolean
}>()
const mapping = defineModel<Record<string, number | null>>({ required: true })
const emit = defineEmits<{ save: []; back: [] }>()
const { t } = useI18n()
const mappingGroups = computed(() => [
  { key: 'required_fields', fields: props.fields.filter((field) => field.required) },
  { key: 'optional_fields', fields: props.fields.filter((field) => !field.required) },
])
const requiredCount = computed(() => props.fields.filter((field) => field.required).length)
const mappingSummary = computed(() =>
  t('import::imports.mapping_summary', {
    matched: requiredCount.value - props.missingRequiredCount,
    total: requiredCount.value,
  }),
)
const columnOptions = computed(() => [
  { title: t('import::imports.unmapped'), value: null },
  ...props.current.headers.map((header, index) => ({
    title: `${index + 1} · ${header || t('import::imports.column_number', { number: index + 1 })}`,
    value: index,
  })),
])

function setMapping(field: string, column: number | null) {
  mapping.value = { ...mapping.value, [field]: column }
}

function mappingLabel(field: ImportField): string {
  return `${field.label}${field.required ? ' *' : ''}`
}
</script>

<template>
  <main class="import-mapping">
    <div class="import-mapping-heading">
      <h3>{{ t('import::imports.mapping') }}</h3>
      <p>{{ t('import::imports.mapping_hint') }}</p>
    </div>
    <div class="import-mapping-table">
      <div class="import-mapping-groups">
        <div
          class="import-mapping-columns"
          aria-hidden="true"
        >
          <span>{{ t('import::imports.system_field') }}</span>
          <span></span>
          <span>{{ t('import::imports.uploaded_column') }}</span>
        </div>
        <section
          v-for="group in mappingGroups"
          :key="group.key"
          class="import-mapping-group"
        >
          <h4>
            {{ t(`import::imports.${group.key}`) }}
            <span>{{ group.fields.length }}</span>
          </h4>
          <div
            v-for="field in group.fields"
            :key="field.key"
            class="import-mapping-row"
          >
            <div class="import-mapping-field">
              <strong>
                {{ field.label }}
                <span
                  v-if="field.required"
                  class="import-required"
                  aria-hidden="true"
                >
                  *
                </span>
              </strong>
              <small>{{ t(`import::imports.types.${field.type}`) }}</small>
            </div>
            <VIcon
              icon="tabler-arrow-right"
              size="16"
              class="import-mapping-arrow import-direction-icon"
            />
            <VSelect
              :model-value="mapping[field.key]"
              @update:model-value="setMapping(field.key, $event)"
              :items="columnOptions"
              :aria-label="mappingLabel(field)"
              :aria-required="field.required"
              :disabled="busy"
              :error="field.required && mapping[field.key] == null"
              density="compact"
              hide-details
              variant="outlined"
            />
          </div>
        </section>
      </div>
    </div>
  </main>
  <footer class="import-footer import-mapping-footer">
    <div
      class="import-mapping-feedback"
      aria-live="polite"
    >
      <div
        class="import-mapping-summary"
        :class="{ 'text-error': duplicateMapping || missingRequiredCount }"
      >
        <VIcon
          :icon="
            duplicateMapping || missingRequiredCount ? 'tabler-alert-circle' : 'tabler-circle-check'
          "
          :color="duplicateMapping || missingRequiredCount ? 'error' : 'success'"
          size="18"
        />
        <strong>
          {{ duplicateMapping ? t('import::imports.duplicate_mapping') : mappingSummary }}
        </strong>
      </div>
      <p v-if="current.status.id !== 'uploaded'">{{ t('import::imports.remap_hint') }}</p>
    </div>
    <div class="import-mapping-actions">
      <VBtn
        variant="outlined"
        :disabled="locked"
        @click="emit('back')"
      >
        {{
          t(
            `import::imports.${current.status.id !== 'uploaded' ? 'back_review' : 'choose_another_file'}`,
          )
        }}
      </VBtn>
      <VBtn
        color="primary"
        :disabled="locked || duplicateMapping || missingRequiredCount > 0"
        :loading="busy"
        variant="flat"
        @click="emit('save')"
      >
        {{ t('import::imports.stage_rows') }}
      </VBtn>
    </div>
  </footer>
</template>

<style lang="scss" scoped>
.import-direction-icon:dir(rtl) {
  transform: rotate(180deg);
}

.import-mapping {
  display: flex;
  flex-direction: column;
  width: 100%;
  min-height: 0;
  overflow: hidden;
  flex: 1;
  padding: 18px 24px;
}

.import-mapping-heading {
  flex-shrink: 0;
  margin-bottom: 16px;
}

.import-mapping-heading h3 {
  margin: 0;
  font-size: 18px;
}

.import-mapping-heading p {
  margin: 3px 0 0;
  color: rgba(var(--v-theme-on-surface), 0.65);
  font-size: 12px;
}

.import-mapping-table {
  display: flex;
  flex-direction: column;
  min-height: 0;
  border: 1px solid rgba(var(--v-border-color), 0.16);
  border-radius: 10px;
  overflow: hidden;
}

.import-mapping-groups {
  min-height: 0;
  overflow: auto;
  scrollbar-width: thin;
  scrollbar-gutter: stable;
}

.import-mapping-row,
.import-mapping-columns {
  display: grid;
  grid-template-columns: minmax(0, 0.85fr) 16px minmax(190px, 1.15fr);
  align-items: center;
  gap: 16px;
  padding: 8px 16px;
}

.import-mapping-row {
  min-height: 50px;
  border-block-end: 1px solid rgba(var(--v-border-color), 0.1);
}

.import-mapping-group:last-child .import-mapping-row:last-child {
  border-block-end: 0;
}

.import-mapping-field {
  display: flex;
  align-items: baseline;
  flex-wrap: wrap;
  gap: 2px 12px;
  min-width: 0;
}

.import-mapping-field strong {
  font-size: 13px;
  font-weight: 600;
  overflow-wrap: anywhere;
}

.import-mapping-field small {
  color: rgba(var(--v-theme-on-surface), 0.6);
  font-size: 12px;
}

.import-mapping-arrow {
  color: rgba(var(--v-theme-on-surface), 0.4);
}

.import-required {
  color: rgb(var(--v-theme-error));
  font-weight: 700;
}

.import-mapping-columns {
  position: sticky;
  top: 0;
  z-index: 1;
  flex-shrink: 0;
  min-height: 36px;
  background: rgb(var(--v-theme-surface));
  border-block-end: 1px solid rgba(var(--v-border-color), 0.12);
  color: rgba(var(--v-theme-on-surface), 0.8);
  font-size: 12px;
  font-weight: 600;
}

.import-mapping-group h4 {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  padding: 7px 16px;
  background: rgba(var(--v-theme-on-surface), 0.025);
  border-block-end: 1px solid rgba(var(--v-border-color), 0.1);
  font-size: 12px;
  font-weight: 600;
}

.import-mapping-group h4 span {
  color: rgba(var(--v-theme-on-surface), 0.65);
  font-size: 11px;
  padding: 0 6px;
  border-radius: 4px;
  background: rgba(var(--v-theme-on-surface), 0.05);
}

.import-mapping-summary {
  display: flex;
  align-items: center;
  gap: 8px;
}

.import-mapping-feedback {
  min-width: 0;
  font-size: 12px;
}

.import-mapping-feedback p {
  margin: 3px 0 0;
  color: rgba(var(--v-theme-on-surface), 0.65);
  font-size: 11px;
}

.import-mapping-actions {
  display: flex;
  flex-shrink: 0;
  gap: 10px;
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

.import-footer {
  flex-shrink: 0;
}

@media (max-width: 600px) {
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

  .import-mapping {
    padding: 18px 16px;
  }

  .import-mapping-columns {
    display: none;
  }

  .import-mapping-footer {
    gap: 10px;
  }

  .import-mapping-actions {
    flex-wrap: wrap;
  }

  .import-mapping-actions .v-btn {
    flex: 1;
    font-size: 12px;
    padding-inline: 12px;
  }

  .import-mapping-row {
    grid-template-columns: 1fr;
    gap: 8px;
    padding: 12px;
  }

  .import-mapping-arrow {
    display: none;
  }
}
</style>
