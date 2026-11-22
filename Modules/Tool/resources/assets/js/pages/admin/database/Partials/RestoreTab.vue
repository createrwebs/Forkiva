<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import type { DatabaseCapabilities } from '@/modules/tool/contracts/DatabaseTool.ts'

const emit = defineEmits<{
  'update:file': [value: File | null]
  restore: []
}>()

const { t } = useI18n()
const { can } = useAuth()

const props = defineProps<{
  file: File | null
  database: DatabaseCapabilities | null
  isAnyActionRunning: boolean
  restoreLoading: boolean
}>()

const acceptedFiles = computed(() => {
  return props.database?.format === 'sqlite' ? '.sqlite,.sqlite3,.db' : '.sql,.txt'
})

function updateFile(value: File | File[] | null) {
  if (Array.isArray(value)) {
    emit('update:file', value[0] || null)
    return
  }

  emit('update:file', value)
}
</script>

<template>
  <VCardText>
    <div class="database-restore-workspace">
      <div class="mb-7">
        <div class="text-h6 font-weight-bold">
          {{ t('tool::database.tabs.restore') }}
        </div>
        <div class="text-body-2 text-medium-emphasis mt-1">
          {{ t('tool::database.restore_warning_details') }}
        </div>
      </div>

      <VAlert
        class="mb-5"
        color="error"
        density="comfortable"
        variant="tonal"
      >
        <div class="font-weight-bold mb-1">
          {{ t('tool::database.restore_warning_title') }}
        </div>
        <div>{{ t('tool::database.safety_backup_note') }}</div>
      </VAlert>

      <VFileInput
        v-if="can('admin.database_tools.restore')"
        :accept="acceptedFiles"
        :disabled="isAnyActionRunning || !database?.restore_available || !database.preflight_ready"
        :label="t('tool::database.backup_file')"
        :model-value="file"
        class="mb-4"
        prepend-inner-icon="tabler-file-database"
        variant="outlined"
        @update:model-value="updateFile"
      />

      <VBtn
        v-if="can('admin.database_tools.restore')"
        :disabled="
          !file || isAnyActionRunning || !database?.restore_available || !database.preflight_ready
        "
        :loading="restoreLoading"
        color="error"
        @click="$emit('restore')"
      >
        <VIcon
          icon="tabler-database-import"
          start
        />
        {{ t('tool::database.tabs.restore') }}
      </VBtn>
    </div>
  </VCardText>
</template>

<style lang="scss" scoped>
.database-restore-workspace {
  max-width: 720px;
}
</style>
