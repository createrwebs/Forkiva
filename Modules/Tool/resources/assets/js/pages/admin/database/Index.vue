<script lang="ts" setup>
import { onBeforeMount } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'
import { useDatabaseTool } from '@/modules/tool/composables/databaseTool.ts'
import BackupTab from '@/modules/tool/pages/admin/database/Partials/BackupTab.vue'
import RestoreTab from '@/modules/tool/pages/admin/database/Partials/RestoreTab.vue'

const { t } = useI18n()
const { mdAndUp } = useDisplay()
const {
  backups,
  database,
  downloadLoadingByFile,
  file,
  isAnyActionRunning,
  loadData,
  loading,
  restoreLoading,
  restoreRowLoadingByFile,
  runBackup,
  runDownloadBackup,
  runRestore,
  runRestoreFromBackup,
  tab,
} = useDatabaseTool()

onBeforeMount(async () => {
  await loadData()
})
</script>

<template>
  <div
    v-if="loading && !database"
    aria-live="polite"
    class="database-tool-loading-state"
    role="status"
  >
    <div class="database-tool-loader">
      <VProgressCircular
        color="primary"
        indeterminate
        size="50"
        width="3"
      />
    </div>
  </div>
  <VCard
    v-else
    class="database-tool-card overflow-hidden"
  >
    <div class="database-tool-shell">
      <aside class="database-tool-rail">
        <div
          v-if="database"
          class="database-engine"
        >
          <div class="database-engine__icon">
            <VIcon
              icon="tabler-database"
              size="24"
            />
          </div>
          <div class="min-w-0">
            <div class="text-caption text-medium-emphasis mb-1">
              {{ t('tool::database.engine_status') }}
            </div>
            <div class="d-flex flex-wrap align-center ga-2">
              <span class="text-subtitle-1 font-weight-bold">
                {{ database.driver.toUpperCase() }}
              </span>
              <VChip
                :color="
                  database.backup_available && database.restore_available ? 'success' : 'warning'
                "
                size="x-small"
                variant="tonal"
              >
                {{
                  t(
                    database.backup_available && database.restore_available
                      ? 'tool::database.available'
                      : 'tool::database.unavailable',
                  )
                }}
              </VChip>
            </div>
          </div>
        </div>

        <VTabs
          v-model="tab"
          :direction="mdAndUp ? 'vertical' : 'horizontal'"
          :grow="!mdAndUp"
          class="database-tool-nav"
          color="primary"
        >
          <VTab
            :disabled="isAnyActionRunning"
            value="backup"
          >
            <VIcon
              icon="tabler-database-export"
              start
            />
            {{ t('tool::database.tabs.backup') }}
          </VTab>
          <VTab
            :disabled="isAnyActionRunning"
            value="restore"
          >
            <VIcon
              icon="tabler-database-import"
              start
            />
            {{ t('tool::database.tabs.restore') }}
          </VTab>
        </VTabs>
      </aside>

      <main class="database-tool-content">
        <div
          v-if="database && (database.missing_tools.length || database.readiness_blockers.length)"
          class="database-tool-alerts"
        >
          <VAlert
            v-if="database.missing_tools.length"
            color="warning"
            density="compact"
            variant="tonal"
          >
            {{
              t('tool::database.missing_tools', {
                tools: database.missing_tools.join(', '),
              })
            }}
          </VAlert>
          <VAlert
            v-if="database.readiness_blockers.length"
            color="error"
            density="compact"
            variant="tonal"
          >
            <div class="font-weight-bold mb-1">
              {{ t('tool::database.preflight_failed_title') }}
            </div>
            <ul class="ps-5 mb-0">
              <li
                v-for="blocker in database.readiness_blockers"
                :key="blocker"
              >
                {{ t(`tool::database.readiness_blockers.${blocker}`) }}
              </li>
            </ul>
          </VAlert>
        </div>

        <VWindow v-model="tab">
          <VWindowItem value="backup">
            <BackupTab
              :backups="backups"
              :database="database"
              :download-loading-by-file="downloadLoadingByFile"
              :is-any-action-running="isAnyActionRunning"
              :loading="loading"
              :restore-row-loading-by-file="restoreRowLoadingByFile"
              @backup="runBackup"
              @download="runDownloadBackup"
              @restore="runRestoreFromBackup"
            />
          </VWindowItem>

          <VWindowItem value="restore">
            <RestoreTab
              :database="database"
              :file="file"
              :is-any-action-running="isAnyActionRunning"
              :restore-loading="restoreLoading"
              @restore="runRestore"
              @update:file="file = $event"
            />
          </VWindowItem>
        </VWindow>
      </main>
    </div>
  </VCard>
</template>

<style lang="scss" scoped>
.database-tool-card {
  position: relative;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  box-shadow: 0 12px 32px rgba(15, 23, 42, 6%);
}

.database-tool-loading-state {
  display: grid;
  min-height: 620px;
  place-items: center;
}

.database-tool-loader {
  position: relative;
  display: grid;
  width: 76px;
  height: 76px;
  place-items: center;
}

.database-tool-loader__icon {
  position: absolute;
}

.database-tool-shell {
  display: grid;
  min-height: 620px;
  grid-template-columns: 264px minmax(0, 1fr);
}

.database-tool-rail {
  padding: 22px 18px;
  background: rgb(var(--v-theme-surface));
}

.database-engine {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 8px;
}

.database-engine__icon {
  display: grid;
  width: 46px;
  height: 46px;
  flex: 0 0 46px;
  color: rgb(var(--v-theme-primary));
  border: 1px solid rgba(var(--v-theme-primary), 18%);
  border-radius: 12px;
  background: rgba(var(--v-theme-primary), 9%);
  place-items: center;
}

.database-tool-nav {
  width: 100%;
  margin-block-start: 18px;
  border: 0 !important;
}

.database-tool-nav :deep(.v-slide-group__content) {
  gap: 6px;
}

.database-tool-nav :deep(.v-tab) {
  position: relative;
  min-height: 48px;
  justify-content: flex-start;
  padding-inline: 14px;
  border: 1px solid transparent;
  border-radius: 10px;
  letter-spacing: 0;
  text-transform: none;
  transition:
    background-color 160ms ease,
    border-color 160ms ease,
    box-shadow 160ms ease;
}

.database-tool-nav :deep(.v-tab:hover:not(.v-tab--selected)) {
  background: rgba(var(--v-theme-on-surface), 4%);
}

.database-tool-nav :deep(.v-tab--selected) {
  background: rgba(var(--v-theme-primary), 10%);
}

.database-tool-nav :deep(.v-tab__slider) {
  display: none;
}

.database-tool-content {
  min-width: 0;
  background: rgb(var(--v-theme-surface));
}

.database-tool-alerts {
  display: grid;
  gap: 12px;
  padding: 28px 32px 0;
}

.database-tool-content :deep(.v-card-text) {
  padding: 32px;
}

@media (max-width: 959px) {
  .database-tool-shell {
    min-height: auto;
    grid-template-columns: minmax(0, 1fr);
  }

  .database-tool-rail {
    padding: 20px;
    border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    border-inline-end: 0;
  }

  .database-engine {
    padding: 13px;
  }

  .database-tool-nav {
    margin-block-start: 14px;
  }

  .database-tool-nav :deep(.v-tab) {
    justify-content: center;
  }

  .database-tool-alerts {
    padding: 20px 20px 0;
  }

  .database-tool-content :deep(.v-card-text) {
    padding: 24px 20px;
  }

  .database-tool-loading-state {
    min-height: 460px;
  }
}
</style>
