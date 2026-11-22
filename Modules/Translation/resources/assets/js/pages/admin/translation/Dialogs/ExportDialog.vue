<script lang="ts" setup>
import type { ExportMode } from '@/modules/translation/contracts/Translation.ts'
import { useI18n } from 'vue-i18n'

defineProps<{
  modelValue: boolean
  exporting: boolean
  exportTargetLocale: string
  exportMode: ExportMode
  exportScope: 'single' | 'all'
  languageItems: { title: string; value: string }[]
  exportModeItems: readonly { title: string; value: ExportMode }[]
  exportScopeItems: readonly { title: string; value: 'single' | 'all' }[]
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (e: 'update:exportTargetLocale', value: string): void
  (e: 'update:exportMode', value: ExportMode): void
  (e: 'update:exportScope', value: 'single' | 'all'): void
  (e: 'apply'): void
}>()

const { t } = useI18n()
</script>

<template>
  <VDialog
    max-width="640"
    :model-value="modelValue"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between">
        {{ t('translation::translations.export_dialog.title') }}
        <VBtn
          icon="tabler-x"
          variant="text"
          @click="emit('update:modelValue', false)"
        />
      </VCardTitle>
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            md="6"
          >
            <VSelect
              :items="exportScopeItems"
              :label="t('translation::translations.export_dialog.type')"
              :model-value="exportScope"
              @update:model-value="emit('update:exportScope', $event)"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <VSelect
              :items="exportModeItems"
              :label="t('translation::translations.export_dialog.file_content')"
              :model-value="exportMode"
              @update:model-value="emit('update:exportMode', $event)"
            />
          </VCol>
          <VCol
            v-if="exportScope !== 'all'"
            cols="12"
          >
            <VSelect
              :items="languageItems"
              :label="t('translation::translations.language')"
              :model-value="exportTargetLocale"
              @update:model-value="emit('update:exportTargetLocale', $event)"
            />
          </VCol>
        </VRow>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          variant="text"
          @click="emit('update:modelValue', false)"
        >
          {{ t('translation::translations.close') }}
        </VBtn>
        <VBtn
          color="primary"
          :loading="exporting"
          @click="emit('apply')"
        >
          {{ t('translation::translations.export') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
