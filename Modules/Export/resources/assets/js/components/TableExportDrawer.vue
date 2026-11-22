<script lang="ts" setup>
import type { PaginationMeta } from '@/modules/core/contracts/Table.ts'
import type { TableExportItem, TableExportListFilters } from '@/modules/export/contracts/export.ts'
import { storeToRefs } from 'pinia'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAppStore } from '@/modules/core/stores/appStore.ts'

const props = defineProps<{
  modelValue: boolean
  exports: TableExportItem[]
  exportMethods: Record<string, unknown>[]
  filters: TableExportListFilters
  loading: boolean
  pagination: PaginationMeta
  perPage: number
  downloadingById: Record<number, boolean>
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (e: 'update:filters', value: TableExportListFilters): void
  (e: 'update:page', value: number): void
  (e: 'update:perPage', value: number): void
  (e: 'download', item: TableExportItem): void
}>()

const { t } = useI18n()
const appStore = useAppStore()
const { isRtl } = storeToRefs(appStore)

const open = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
})

const drawerTransition = computed(() =>
  isRtl.value ? 'slide-x-transition' : 'slide-x-reverse-transition',
)
const hasExports = computed(() => props.exports.length > 0)
const showFilterMenu = ref(false)
const page = ref(props.pagination.current_page)
const maxVisiblePages = 5

const downloadedOptions = computed(() => [
  { title: t('export::exports.all_download_statuses'), value: null },
  { title: t('export::exports.downloaded'), value: '1' },
  { title: t('export::exports.not_downloaded'), value: '0' },
])

const fileTypeOptions = computed(() => [
  { title: t('export::exports.all_file_types'), value: null },
  ...props.exportMethods.map((item) => ({
    title: item.name,
    value: item.id,
  })),
])

function statusColor(status: string) {
  return (
    {
      pending: 'warning',
      processing: 'info',
      completed: 'success',
      failed: 'error',
    }[status] || 'default'
  )
}

function statusIcon(status: string) {
  return (
    {
      pending: 'tabler-clock-hour-4',
      processing: 'tabler-loader-2',
      completed: 'tabler-circle-check',
      failed: 'tabler-alert-triangle',
    }[status] || 'tabler-file'
  )
}

function handleFilterChange(key: keyof TableExportListFilters, value: string | null) {
  emit('update:filters', {
    ...props.filters,
    [key]: value,
  })
}

watch(
  () => props.pagination.current_page,
  (value) => {
    page.value = value
  },
)

watch(page, (value) => {
  emit('update:page', value)
})

const activeFilterCount = computed(() => {
  return [props.filters.format, props.filters.is_downloaded].filter(
    (value) => value !== null && value !== undefined && value !== '',
  ).length
})

const visiblePages = computed(() => {
  const total = props.pagination.last_page
  const current = page.value
  const pages: number[] = []

  if (total === 0) return pages

  let start = Math.max(current - Math.floor(maxVisiblePages / 2), 1)
  let end = start + maxVisiblePages - 1

  if (end > total) {
    end = total
    start = Math.max(end - maxVisiblePages + 1, 1)
  }

  for (let index = start; index <= end; index++) {
    pages.push(index)
  }

  return pages
})

const hasLeadingEllipsis = computed(() => {
  const firstPage = visiblePages.value[0]
  return typeof firstPage === 'number' && firstPage > 2
})

const hasTrailingEllipsis = computed(() => {
  const lastPage = visiblePages.value[visiblePages.value.length - 1]
  return typeof lastPage === 'number' && lastPage < props.pagination.last_page - 1
})
</script>

<template>
  <VNavigationDrawer
    v-model="open"
    class="export-history-drawer"
    :location="isRtl ? 'left' : 'right'"
    temporary
    :transition="drawerTransition"
    :width="600"
  >
    <div class="export-drawer d-flex flex-column h-100">
      <div class="export-drawer__header">
        <div class="d-flex align-center justify-space-between ga-3">
          <div>
            <h3 class="text-h5 font-weight-bold text-high-emphasis">
              {{ t('export::exports.histories') }}
            </h3>
          </div>

          <div class="d-flex align-center ga-1">
            <VMenu
              v-model="showFilterMenu"
              :close-on-content-click="false"
              location="bottom end"
              offset-y
            >
              <template #activator="{ props: menuProps }">
                <VBadge
                  class="export-drawer__filter-badge"
                  :content="activeFilterCount"
                  :model-value="activeFilterCount > 0"
                  offset-y="-2"
                >
                  <VBtn
                    color="default"
                    icon="tabler-filter-filled"
                    v-bind="menuProps"
                    variant="text"
                  />
                </VBadge>
              </template>

              <VCard
                class="pa-4"
                rounded="lg"
                width="320"
              >
                <div class="d-flex align-center justify-space-between mb-3">
                  <p class="font-weight-bold mb-0">
                    {{ t('admin::admin.filters_title') }}
                  </p>
                  <VBtn
                    color="error"
                    :disabled="loading || activeFilterCount === 0"
                    size="small"
                    variant="text"
                    @click="
                      emit('update:filters', {
                        format: null,
                        is_downloaded: null,
                      })
                    "
                  >
                    {{ t('admin::admin.buttons.reset') }}
                  </VBtn>
                </div>

                <div class="d-flex flex-column ga-3">
                  <VSelect
                    density="comfortable"
                    hide-details
                    item-title="title"
                    item-value="value"
                    :items="fileTypeOptions"
                    :label="t('export::exports.file_type')"
                    :model-value="filters.format"
                    variant="outlined"
                    @update:model-value="handleFilterChange('format', $event)"
                  />

                  <VSelect
                    density="comfortable"
                    hide-details
                    item-title="title"
                    item-value="value"
                    :items="downloadedOptions"
                    :label="t('export::exports.download_status')"
                    :model-value="filters.is_downloaded"
                    variant="outlined"
                    @update:model-value="handleFilterChange('is_downloaded', $event)"
                  />
                </div>
              </VCard>
            </VMenu>

            <VBtn
              color="default"
              icon="tabler-x"
              variant="text"
              @click="open = false"
            />
          </div>
        </div>
      </div>

      <div class="export-drawer__content">
        <div
          v-if="loading && !hasExports"
          class="export-drawer__loading"
        >
          <VProgressCircular
            color="primary"
            indeterminate
            size="42"
          />
        </div>

        <div
          v-else-if="!hasExports"
          class="export-empty-state"
        >
          <VIcon
            class="mb-3"
            color="medium-emphasis"
            icon="tabler-folder-open"
            size="34"
          />
          <div class="text-subtitle-1 font-weight-bold text-high-emphasis mb-1">
            {{ t('export::exports.histories') }}
          </div>
          <div class="text-body-2 text-medium-emphasis">
            {{ t('export::exports.empty_state') }}
          </div>
        </div>

        <div
          v-else
          class="export-list"
        >
          <div
            v-for="item in exports"
            :key="item.id"
            class="export-item"
            :class="{ 'export-item--pending-download': !item.is_downloaded }"
          >
            <div class="export-item__body">
              <div
                class="export-item__icon"
                :class="`export-item__icon--${item.format.id}`"
              >
                <VIcon
                  color="white"
                  icon="tabler-file-spreadsheet"
                  size="18"
                />
              </div>

              <div class="export-item__content">
                <div class="export-item__top">
                  <div class="min-w-0">
                    <div class="text-subtitle-1 font-weight-bold text-high-emphasis text-truncate">
                      {{ item.file_name || `export-${item.id}.${item.format.id}` }}
                    </div>
                    <div class="export-item__subline">
                      <VChip
                        size="small"
                        variant="tonal"
                      >
                        {{ (item.format.name || item.format.id).toUpperCase() }}
                      </VChip>

                      <VChip
                        :color="statusColor(item.status.id)"
                        size="small"
                        variant="tonal"
                      >
                        <VIcon
                          :icon="statusIcon(item.status.id)"
                          size="14"
                          start
                        />
                        {{ item.status.name }}
                      </VChip>
                    </div>
                  </div>
                </div>

                <div class="export-item__meta">
                  <div class="export-item__meta-item">
                    <span>{{ t('admin::admin.table.created_at') }}</span>
                    <strong>{{ item.created_at || '-' }}</strong>
                  </div>

                  <div class="export-item__meta-item">
                    <span>{{ t('export::exports.finished_at') }}</span>
                    <strong>{{ item.finished_at || '-' }}</strong>
                  </div>

                  <div class="export-item__meta-item">
                    <span>{{ t('export::exports.downloaded_at') }}</span>
                    <strong>{{ item.downloaded_at || '-' }}</strong>
                  </div>
                </div>

                <VAlert
                  v-if="item.error_message"
                  class="export-item__error"
                  color="error"
                  density="comfortable"
                  variant="tonal"
                >
                  {{ item.error_message }}
                </VAlert>
              </div>
            </div>

            <div class="export-item__actions">
              <VBtn
                v-if="item.download_url"
                block
                color="primary"
                :loading="downloadingById[item.id]"
                rounded="lg"
                variant="flat"
                @click="$emit('download', item)"
              >
                <VIcon
                  icon="tabler-download"
                  start
                />
                {{ t('admin::admin.buttons.download') }}
              </VBtn>
            </div>
          </div>
        </div>
      </div>

      <div
        v-if="pagination.total > 0"
        class="export-drawer__footer"
      >
        <div class="export-drawer__pagination">
          <div class="export-drawer__per-page">
            <span class="text-caption">{{ t('admin::admin.table.show') }}</span>
            <VSelect
              density="compact"
              :disabled="loading"
              hide-details
              :items="[10, 15, 25, 40, 50, 100]"
              :model-value="perPage"
              style="max-width: 84px"
              variant="outlined"
              @update:model-value="$emit('update:perPage', Number($event))"
            />
            <span class="text-caption">{{ t('admin::admin.table.entries') }}</span>
          </div>

          <div class="export-drawer__page-actions">
            <VBtn
              :disabled="loading || page <= 1"
              icon="tabler-chevrons-left"
              size="small"
              variant="text"
              @click="page = 1"
            />

            <VBtn
              :disabled="loading || page <= 1"
              icon="tabler-chevron-left"
              size="small"
              variant="text"
              @click="page--"
            />

            <VBtn
              v-if="pagination.last_page > 0"
              :color="page === 1 ? 'primary' : undefined"
              :disabled="loading"
              min-width="36"
              size="small"
              :variant="page === 1 ? 'flat' : 'text'"
              @click="page = 1"
            >
              1
            </VBtn>

            <span
              v-if="hasLeadingEllipsis"
              class="export-drawer__ellipsis"
            >
              …
            </span>

            <VBtn
              v-for="number in visiblePages"
              v-show="number !== 1 && number !== pagination.last_page"
              :key="number"
              :color="page === number ? 'primary' : undefined"
              :disabled="loading"
              min-width="36"
              size="small"
              :variant="page === number ? 'flat' : 'text'"
              @click="page = number"
            >
              {{ number }}
            </VBtn>

            <span
              v-if="hasTrailingEllipsis"
              class="export-drawer__ellipsis"
            >
              …
            </span>

            <VBtn
              v-if="pagination.last_page > 1"
              :color="page === pagination.last_page ? 'primary' : undefined"
              :disabled="loading"
              min-width="36"
              size="small"
              :variant="page === pagination.last_page ? 'flat' : 'text'"
              @click="page = pagination.last_page"
            >
              {{ pagination.last_page }}
            </VBtn>

            <VBtn
              :disabled="loading || page >= pagination.last_page"
              icon="tabler-chevron-right"
              size="small"
              variant="text"
              @click="page++"
            />

            <VBtn
              :disabled="loading || page >= pagination.last_page"
              icon="tabler-chevrons-right"
              size="small"
              variant="text"
              @click="page = pagination.last_page"
            />
          </div>
        </div>
      </div>
    </div>
  </VNavigationDrawer>
</template>

<style lang="scss" scoped>
.export-drawer {
  background: rgb(var(--v-theme-surface));
}

.export-drawer__header {
  padding: 10px 15px;
  border-bottom: 1px solid rgba(var(--v-theme-on-surface), 0.08);
  background: rgb(var(--v-theme-surface));
}

.export-drawer__filter-badge :deep(.v-badge__badge) {
  background-color: rgba(var(--v-theme-primary), 0.14) !important;
  color: rgb(var(--v-theme-primary)) !important;
  font-size: 12px;
  border-radius: 8px;
  padding: 0 6px;
  height: 18px;
  min-width: 18px;
  font-weight: 500;
}

.export-drawer__content {
  flex: 1;
  min-height: 0;
  overflow: hidden;
  padding: 16px 24px 20px;
}

.export-drawer__footer {
  border-top: 1px solid rgba(var(--v-theme-on-surface), 0.08);
  background: rgb(var(--v-theme-surface));
  flex-shrink: 0;
  padding: 14px 24px;
}

.export-drawer__loading,
.export-empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  min-height: 280px;
  border: 1px dashed rgba(var(--v-theme-on-surface), 0.16);
  border-radius: 16px;
  background: rgba(var(--v-theme-on-surface), 0.02);
}

.export-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  height: 100%;
  overflow: auto;
  padding-inline-end: 2px;
}

.export-item {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 164px;
  gap: 18px;
  padding: 16px 18px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.07);
  border-radius: 16px;
  background: rgb(var(--v-theme-surface));
  align-items: center;
  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    transform 0.2s ease;
  position: relative;
}

.export-item__body {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  min-width: 0;
}

.export-item__icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 52px;
  height: 52px;
  border-radius: 14px;
  flex-shrink: 0;
  background: rgb(var(--v-theme-primary));
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.12);
}

.export-item__icon--csv {
  background: rgb(var(--v-theme-success));
}

.export-item__icon--xlsx {
  background: rgb(var(--v-theme-info));
}

.export-item__icon--xls {
  background: rgb(var(--v-theme-warning));
}

.export-item__content {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.export-item__top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.export-item__top-chips {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 8px;
  flex-shrink: 0;
}

.export-item__subline {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 4px;
  font-size: 12px;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
}

.export-item__dot {
  width: 4px;
  height: 4px;
  border-radius: 999px;
  background: rgba(var(--v-theme-on-surface), 0.22);
}

.export-item__meta {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}

.export-item__meta-item {
  display: grid;
  gap: 2px;
  min-width: 0;
}

.export-item__meta-item span {
  display: block;
  font-size: 12px;
  letter-spacing: 0.01em;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
}

.export-item__meta-item strong {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
}

.export-item__actions {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  justify-content: flex-end;
  gap: 12px;
  padding-inline-start: 18px;
  border-inline-start: 1px solid rgba(var(--v-theme-on-surface), 0.08);
}

.export-item__actions :deep(.v-btn) {
  font-weight: 600;
  margin-top: auto;
}

.export-item:hover {
  border-color: rgba(var(--v-theme-primary), 0.22);
  transform: translateY(-1px);
}

.export-item__error {
  margin-top: 2px;
}

.export-item--pending-download::after {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(
    180deg,
    rgba(var(--v-theme-on-surface), 0.02) 0%,
    rgba(var(--v-theme-on-surface), 0.045) 100%
  );
  pointer-events: none;
  border-radius: 16px;
}

.export-drawer__pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 16px;
}

.export-drawer__per-page {
  display: flex;
  align-items: center;
  gap: 8px;
}

.export-drawer__page-actions {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-wrap: wrap;
  gap: 6px;
}

.export-drawer__ellipsis {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
}

@media (max-width: 959px) {
  .export-item {
    grid-template-columns: 1fr;
  }

  .export-item__actions {
    padding-inline-start: 0;
    padding-top: 14px;
    border-inline-start: 0;
    border-top: 1px solid rgba(var(--v-theme-on-surface), 0.08);
  }
}

@media (max-width: 767px) {
  .export-drawer__header,
  .export-drawer__content {
    padding-inline: 16px;
  }

  .export-drawer__footer {
    padding-inline: 16px;
  }

  .export-drawer__per-page {
    inline-size: 100%;
    justify-content: center;
  }

  .export-drawer__page-actions {
    inline-size: 100%;
  }

  .export-item {
    padding: 14px;
  }

  .export-item__body {
    gap: 12px;
  }

  .export-item__icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
  }

  .export-item__top {
    flex-direction: column;
    align-items: flex-start;
  }

  .export-item__top-chips {
    justify-content: flex-start;
  }

  .export-item__meta {
    grid-template-columns: 1fr;
  }

  .export-item__actions {
    gap: 10px;
  }
}

:global(.export-history-drawer + .v-navigation-drawer__scrim) {
  position: fixed !important;
  inset: 0 !important;
  width: 100vw !important;
  height: 100vh !important;
}
</style>
