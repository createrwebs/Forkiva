<script lang="ts" setup>
import type { RuntimeNotificationItem } from '@/modules/notification/contracts/NotificationCenter.ts'

const props = defineProps<{
  notification: RuntimeNotificationItem
  isActive: boolean
  fallbackTitle: string
  loading?: boolean
  disabled?: boolean
}>()

const itemTitle = computed(() => {
  return props.notification.title || props.notification.subject || props.fallbackTitle
})

const itemContent = computed(() => {
  return props.notification.content || props.notification.subject || props.notification.type || ''
})

defineEmits<{
  (event: 'select', notification: RuntimeNotificationItem): void
}>()
</script>

<template>
  <button
    class="notification-center-list-item"
    :class="{
      'notification-center-list-item--active': isActive,
      'notification-center-list-item--unread': !notification.read_at,
      'notification-center-list-item--loading': loading,
    }"
    :disabled="disabled"
    type="button"
    @click="$emit('select', notification)"
  >
    <div class="notification-center-list-item__header">
      <div class="text-body-1 font-weight-medium">
        {{ itemTitle }}
      </div>

      <VProgressCircular
        v-if="loading"
        color="primary"
        indeterminate
        size="16"
        width="2"
      />

      <div
        v-else-if="!notification.read_at"
        class="notification-center-list-item__dot"
      />
    </div>

    <div class="text-caption text-medium-emphasis">
      {{ notification.created_at }}
    </div>

    <div class="notification-center-list-item__text text-body-2 text-medium-emphasis">
      {{ itemContent }}
    </div>
  </button>
</template>

<style lang="scss" scoped>
.notification-center-list-item {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  padding: 1rem;
  text-align: start;
  width: 100%;
  transition: background-color 0.2s ease;
  cursor: pointer;

  &:hover {
    background: rgba(var(--v-theme-on-surface), 0.03);
  }

  &:disabled {
    cursor: default;
    opacity: 0.72;
  }

  &--active {
    background: rgba(var(--v-theme-primary), 0.08);
  }

  &--loading {
    pointer-events: none;
  }

  &--unread .text-body-1 {
    font-weight: 600;
  }

  &__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
  }

  &__dot {
    border-radius: 999px;
    background: rgb(var(--v-theme-primary));
    block-size: 0.5rem;
    flex: 0 0 auto;
    inline-size: 0.5rem;
  }

  &__text {
    overflow: hidden;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    white-space: normal;
  }
}
</style>
