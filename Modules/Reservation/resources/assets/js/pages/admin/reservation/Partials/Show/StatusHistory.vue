<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  item: Record<string, any>
}>()

const { t } = useI18n()
const logs = computed(() => props.item.status_logs || [])
</script>

<template>
  <VCard>
    <VCardTitle class="d-flex align-center">
      <VIcon
        class="me-2"
        icon="tabler-history"
        size="20"
      />
      <span>{{ t('reservation::frontend.status_history') }}</span>
    </VCardTitle>

    <VCardText>
      <VAlert
        v-if="logs.length === 0"
        type="info"
        variant="tonal"
      >
        {{ t('reservation::frontend.no_status_history') }}
      </VAlert>

      <VTimeline
        v-else
        class="reservation-status-history"
        density="compact"
        side="end"
      >
        <VTimelineItem
          v-for="log in logs"
          :key="log.id"
          :dot-color="log.new_status?.color || 'primary'"
          icon="tabler-circle-check"
          size="small"
        >
          <div class="reservation-status-history__item">
            <div class="d-flex align-center justify-space-between flex-wrap ga-2">
              <VChip
                :color="log.new_status?.color || 'default'"
                size="small"
                variant="tonal"
              >
                {{ log.new_status?.name || '-' }}
              </VChip>

              <span class="text-caption text-medium-emphasis">
                {{ log.changed_at || '-' }}
              </span>
            </div>

            <div class="text-caption text-medium-emphasis mt-1">
              {{ t('reservation::frontend.changed_by') }}:
              {{ log.changed_by?.name || t('admin::admin.table.unknown') }}
            </div>

            <div
              v-if="log.old_status?.name"
              class="text-caption text-medium-emphasis"
            >
              {{ log.old_status.name }} - {{ log.new_status?.name || '-' }}
            </div>

            <div
              v-if="log.reason"
              class="text-body-2 mt-2"
            >
              <span class="font-weight-medium">
                {{ t('reservation::attributes.reservation_status_logs.reason') }}:
              </span>
              {{ log.reason }}
            </div>

            <div
              v-if="log.note"
              class="text-body-2 mt-1"
            >
              <span class="font-weight-medium">
                {{ t('reservation::attributes.reservation_status_logs.note') }}:
              </span>
              {{ log.note }}
            </div>
          </div>
        </VTimelineItem>
      </VTimeline>
    </VCardText>
  </VCard>
</template>

<style lang="scss" scoped>
.reservation-status-history {
  margin-inline-start: -10px;
}

.reservation-status-history__item {
  border: 1px dashed rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  padding: 12px 14px;
}
</style>
