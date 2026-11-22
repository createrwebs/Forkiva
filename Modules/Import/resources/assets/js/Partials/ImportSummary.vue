<script setup lang="ts">
import type { DataImport } from '@/modules/import/contracts/import.ts'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  current: DataImport
  validated: boolean
  working: boolean
  result: boolean
  pollError: string | null
}>()
const { t } = useI18n()
const displayStatus = computed(() =>
  props.current.status.id === 'ready' && !props.validated
    ? t('import::imports.statuses.staged')
    : props.current.status.name,
)
const noticeTone = computed(() => {
  const status = props.current.status.id
  if (status === 'completed' || (status === 'ready' && props.validated)) return 'success'
  if (['failed', 'validation_failed', 'partially_completed'].includes(status)) return 'error'
  if (status === 'cancelled') return 'secondary'
  return 'primary'
})
const notice = computed(() => {
  if (props.current.failure_reason) return props.current.failure_reason
  if (props.current.status.id === 'validation_failed') return t('import::imports.fix_errors')
  if (props.working)
    return t(
      `import::imports.${props.current.requires_processing ? 'browser_processing_hint' : 'background_hint'}`,
    )
  if (props.result) return t(`import::imports.result_hints.${props.current.status.id}`)
  return t(`import::imports.${props.validated ? 'ready_hint' : 'validate_hint'}`)
})
</script>

<template>
  <div
    class="import-notice"
    :class="{
      'import-notice-error':
        current.status.id === 'validation_failed' ||
        current.status.id === 'failed' ||
        current.status.id === 'partially_completed',
      'import-notice-success':
        (validated && current.status.id === 'ready') || current.status.id === 'completed',
    }"
    role="status"
    aria-live="polite"
  >
    <div class="import-notice-content">
      <div class="import-notice-heading">
        <VIcon
          :icon="
            noticeTone === 'error'
              ? 'tabler-alert-circle'
              : noticeTone === 'success'
                ? 'tabler-circle-check'
                : 'tabler-info-circle'
          "
          :color="noticeTone"
          size="18"
        />
        <VChip
          :color="noticeTone"
          size="small"
          variant="tonal"
        >
          {{ displayStatus }}
        </VChip>
        <span
          v-if="current.status.id === 'validation_failed'"
          class="text-error"
        >
          {{ current.invalid_rows }} {{ t('import::imports.invalid_short') }}
        </span>
      </div>
      <p>{{ notice }}</p>
      <p
        v-if="pollError"
        class="text-error"
      >
        {{ pollError }}
      </p>
    </div>
    <div
      v-if="validated"
      class="import-counts"
    >
      <span>
        <strong>{{ current.new_rows }}</strong>
        {{ t('import::imports.new_short') }}
      </span>
      <span>
        <strong>{{ current.update_rows }}</strong>
        {{ t('import::imports.updates_short') }}
      </span>
      <span>
        <strong>{{ current.skipped_rows }}</strong>
        {{ t('import::imports.skipped_short') }}
      </span>
    </div>
    <div
      v-else-if="result"
      class="import-counts"
    >
      <span>
        <strong>{{ current.imported_rows }}</strong>
        {{ t('import::imports.imported_short') }}
      </span>
      <span>
        <strong>{{ current.failed_rows }}</strong>
        {{ t('import::imports.failed_short') }}
      </span>
      <span>
        <strong>{{ current.skipped_rows }}</strong>
        {{ t('import::imports.skipped_short') }}
      </span>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.import-notice {
  display: flex;
  flex-shrink: 0;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  padding: 10px 14px;
  margin: 8px 24px 12px;
  border: 1px solid rgba(var(--v-theme-primary), 0.15);
  border-radius: 6px;
  background: rgba(var(--v-theme-primary), 0.04);
}

.import-notice-content {
  min-width: 0;
}

.import-notice-heading {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  font-size: 12px;
  font-weight: 600;
}

.import-notice p {
  margin: 5px 0 0;
  font-size: 12px;
  color: rgba(var(--v-theme-on-surface), 0.7);
}

.import-notice-success {
  background: rgba(var(--v-theme-success), 0.06);
  border-color: rgba(var(--v-theme-success), 0.2);
}

.import-notice-error {
  background: rgba(var(--v-theme-error), 0.04);
  border-color: rgba(var(--v-theme-error), 0.2);
}

.import-counts {
  display: flex;
  gap: 20px;
  flex-shrink: 0;
}

.import-counts span {
  display: flex;
  align-items: baseline;
  gap: 6px;
  font-size: 12px;
}

.import-counts strong {
  font-size: 18px;
}

@media (max-width: 900px) {
  .import-notice {
    align-items: flex-start;
    flex-direction: column;
  }
}

@media (max-width: 600px) {
  .import-notice {
    margin: 0 16px 12px;
    padding: 12px;
    gap: 8px;
  }

  .import-counts strong {
    font-size: 20px;
  }
}
</style>
