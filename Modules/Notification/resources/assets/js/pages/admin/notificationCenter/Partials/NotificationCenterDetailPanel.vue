<script lang="ts" setup>
import type { RuntimeNotificationItem } from '@/modules/notification/contracts/NotificationCenter.ts'
import { useI18n } from 'vue-i18n'
import { computed } from 'vue'

const { t } = useI18n()

const props = defineProps<{
  selectedItem: RuntimeNotificationItem | null
  mdAndDown: boolean
  loading?: boolean
}>()

const emptyMessage = computed(() => {
  return t('notification::notifications.empty_state')
})

const selectedMessage = computed(() => {
  return t('notification::notifications.selected_notification')
})

const selectedTitle = computed(() => {
  if (!props.selectedItem) {
    return selectedMessage.value
  }

  return props.selectedItem.title || props.selectedItem.subject || selectedMessage.value
})
</script>

<template>
  <div class="notification-center-detail-panel">
    <div
      v-if="loading"
      class="notification-center-detail-panel__state"
    >
      <VProgressCircular
        color="primary"
        indeterminate
        size="28"
      />
    </div>

    <div
      v-else-if="selectedItem"
      class="notification-center-detail-panel__wrapper"
    >
      <div class="notification-center-detail-panel__header">
        <div>
          <div class="text-h6 font-weight-medium">
            {{ selectedTitle }}
          </div>

          <div class="text-body-2 text-medium-emphasis">
            {{ selectedItem.created_at }}
          </div>
        </div>
      </div>

      <div
        v-if="selectedItem.subject"
        class="notification-center-detail-panel__subject text-subtitle-1 font-weight-medium"
      >
        {{ selectedItem.subject }}
      </div>

      <div class="notification-center-detail-panel__content">
        {{ selectedItem.content || '' }}
      </div>
    </div>

    <div
      v-else
      class="notification-center-detail-panel__state notification-center-detail-panel__state--empty text-medium-emphasis"
    >
      <div
        class="notification-center-detail-panel__empty-content notification-center-detail-panel__empty-content--detail"
      >
        <VIcon
          class="mb-3"
          icon="tabler-bell-off"
          size="34"
        />

        <div class="text-body-1 font-weight-medium text-high-emphasis">
          {{ mdAndDown ? emptyMessage : selectedMessage }}
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.notification-center-detail-panel {
  height: 100%;

  &__wrapper {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    height: 100%;
    padding: 1.25rem;
  }

  &__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
  }

  &__subject {
    color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
  }

  &__content {
    flex: 1 1 auto;
    overflow: auto;
    color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
    line-height: 1.75;
    white-space: pre-wrap;
  }

  &__state {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    min-height: 14rem;
    padding: 1rem;
    text-align: center;

    &--empty {
      flex-direction: column;
      gap: 0.75rem;
    }
  }

  &__empty-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    max-width: 18rem;
    text-align: center;

    &--detail {
      min-height: 18rem;
      max-width: 22rem;
    }
  }
}
</style>
