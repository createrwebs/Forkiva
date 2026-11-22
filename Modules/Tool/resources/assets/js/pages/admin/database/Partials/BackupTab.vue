<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import type { DatabaseBackup, DatabaseCapabilities } from '@/modules/tool/contracts/DatabaseTool.ts'

defineProps<{
  backups: DatabaseBackup[]
  database: DatabaseCapabilities | null
  loading: boolean
  isAnyActionRunning: boolean
  downloadLoadingByFile: Record<string, boolean>
  restoreRowLoadingByFile: Record<string, boolean>
}>()

defineEmits<{
  backup: []
  download: [fileName: string]
  restore: [fileName: string]
}>()

const { t } = useI18n()
const { can } = useAuth()
</script>

<template>
  <VCardText>
    <div class="database-section-heading">
      <div>
        <div class="text-h6 font-weight-bold">
          {{ t('tool::database.tabs.backup') }}
        </div>
        <div class="text-body-2 text-medium-emphasis mt-1">
          {{ t('tool::database.backup_note_details') }}
        </div>
      </div>
      <VBtn
        v-if="can('admin.database_tools.backup')"
        color="primary"
        :disabled="isAnyActionRunning || !database?.backup_available || !database.preflight_ready"
        :loading="loading"
        @click="$emit('backup')"
      >
        <VIcon
          icon="tabler-database-export"
          start
        />
        {{ t('tool::database.tabs.backup') }}
      </VBtn>
    </div>

    <VAlert
      class="mb-6"
      color="info"
      density="comfortable"
      variant="tonal"
    >
      <div class="font-weight-bold mb-1">{{ t('tool::database.backup_note_title') }}</div>
      <div>{{ t('tool::database.safety_backup_note') }}</div>
    </VAlert>

    <div class="text-subtitle-1 font-weight-bold mb-4">
      {{ t('tool::database.latest_backups') }}
    </div>

    <div class="database-table-frame">
      <VTable density="comfortable">
        <thead>
          <tr>
            <th>{{ t('tool::database.columns.name') }}</th>
            <th>{{ t('tool::database.columns.size') }}</th>
            <th>{{ t('tool::database.columns.created_at') }}</th>
            <th>{{ t('tool::database.columns.driver') }}</th>
            <th>{{ t('tool::database.columns.integrity') }}</th>
            <th>{{ t('admin::admin.table.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="backups.length === 0">
            <td
              class="text-medium-emphasis"
              colspan="6"
            >
              {{ t('tool::database.no_backups') }}
            </td>
          </tr>
          <tr
            v-for="item in backups"
            :key="item.name"
          >
            <td
              class="database-backup-name"
              :title="item.name"
            >
              {{ item.name }}
            </td>
            <td>{{ item.size_human }}</td>
            <td>{{ item.created_at }}</td>
            <td>
              <VChip
                size="small"
                variant="tonal"
              >
                {{ item.driver?.toUpperCase() || '—' }}
              </VChip>
            </td>
            <td>
              <span
                v-if="item.checksum_sha256"
                :title="item.checksum_sha256"
              >
                {{ item.checksum_sha256.slice(0, 12) }}…
              </span>
              <span v-else>—</span>
            </td>
            <td>
              <div class="d-flex align-center ga-2">
                <VBtn
                  v-if="can('admin.database_tools.download')"
                  :disabled="isAnyActionRunning && !downloadLoadingByFile[item.name]"
                  :loading="downloadLoadingByFile[item.name]"
                  size="small"
                  variant="tonal"
                  @click="$emit('download', item.name)"
                >
                  <VIcon
                    icon="tabler-download"
                    start
                  />
                  {{ t('admin::admin.buttons.download') }}
                </VBtn>
                <VBtn
                  v-if="can('admin.database_tools.restore')"
                  color="error"
                  :disabled="
                    !database?.restore_available ||
                    !database.preflight_ready ||
                    (isAnyActionRunning && !restoreRowLoadingByFile[item.name])
                  "
                  :loading="restoreRowLoadingByFile[item.name]"
                  size="small"
                  variant="tonal"
                  @click="$emit('restore', item.name)"
                >
                  <VIcon
                    icon="tabler-database-import"
                    start
                  />
                  {{ t('tool::database.tabs.restore') }}
                </VBtn>
              </div>
            </td>
          </tr>
        </tbody>
      </VTable>
    </div>
  </VCardText>
</template>

<style lang="scss" scoped>
.database-section-heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 24px;
  margin-block-end: 28px;
}

.database-table-frame {
  overflow-x: auto;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 12px;
}

.database-table-frame :deep(th) {
  color: rgba(var(--v-theme-on-surface), 68%);
  background: rgba(var(--v-theme-on-surface), 2%);
  font-size: 0.75rem;
  font-weight: 700;
  white-space: nowrap;
}

.database-table-frame :deep(td) {
  white-space: nowrap;
}

.database-table-frame :deep(.database-backup-name) {
  overflow: hidden;
  width: 32%;
  max-width: 320px;
  text-overflow: ellipsis;
}

.database-table-frame :deep(th:last-child),
.database-table-frame :deep(td:last-child) {
  min-width: 210px;
}

@media (max-width: 599px) {
  .database-section-heading {
    align-items: stretch;
    flex-direction: column;
    gap: 16px;
  }

  .database-section-heading :deep(.v-btn) {
    width: 100%;
  }
}
</style>
