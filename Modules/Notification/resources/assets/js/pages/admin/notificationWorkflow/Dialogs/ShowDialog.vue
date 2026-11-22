<script lang="ts" setup>
import type {
  NotificationRecipientRole,
  NotificationWorkflow,
  NotificationWorkflowListItem,
  NotificationWorkflowTranslation,
} from '@/modules/notification/contracts/NotificationWorkflow.ts'
import { useI18n } from 'vue-i18n'
import { useAppStore } from '@/modules/core/stores/appStore.ts'
import { useNotificationWorkflow } from '@/modules/notification/composables/notificationWorkflow.ts'

const props = defineProps<{
  modelValue: boolean
  item: NotificationWorkflowListItem
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
}>()

const { t } = useI18n()
const appStore = useAppStore()
const { getWorkflow } = useNotificationWorkflow()
const loading = ref(false)
const workflow = ref<NotificationWorkflow | null>(null)

const supportedChannels = computed(
  () => workflow.value?.channels.filter((channel) => channel.is_supported) || [],
)
const channelRows = computed(() =>
  supportedChannels.value.map((channel) => {
    const recipients = enabledRecipients(channel.key)

    return {
      icon: channelIcon(channel.key),
      key: channel.key,
      name: channelName(channel.key),
      recipients,
    }
  }),
)

watch(
  () => [props.modelValue, props.item.key] as const,
  async ([isOpen, key]) => {
    if (!isOpen || !key) {
      return
    }

    loading.value = true
    workflow.value = await getWorkflow(key, true)
    loading.value = false
  },
  { immediate: true },
)

function close() {
  emit('update:modelValue', false)
}

function translateValue(
  value: string | NotificationWorkflowTranslation | null | undefined,
): string {
  if (!value) {
    return ''
  }

  if (typeof value === 'string') {
    return value
  }

  return String(
    value[appStore.appCurrentLocale] ||
      value[appStore.locale] ||
      Object.values(value).find(Boolean) ||
      '',
  )
}

function recipientName(recipient: NotificationRecipientRole): string {
  return translateValue(recipient.name) || recipient.key
}

function channelIcon(channelKey: string): string {
  const channel = supportedChannels.value.find((item) => item.key === channelKey)

  if (channel?.icon) {
    return channel.icon
  }

  const icons: Record<string, string> = {
    email: 'tabler-mail',
    in_app: 'tabler-device-mobile-message',
    push_notification: 'tabler-bell-ringing',
    sms: 'tabler-message-2',
    whatsapp: 'tabler-brand-whatsapp',
  }

  return icons[channelKey] || 'tabler-message'
}

function channelName(channelKey: string): string {
  return supportedChannels.value.find((channel) => channel.key === channelKey)?.name || channelKey
}

function enabledRecipients(channelKey: string): NotificationRecipientRole[] {
  const channelState = workflow.value?.channel_states?.[channelKey] || {}

  return (
    workflow.value?.available_recipients.filter((recipient) =>
      Boolean(channelState[recipient.key]),
    ) || []
  )
}
</script>

<template>
  <VDialog
    max-width="760"
    :model-value="modelValue"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard>
      <VCardTitle
        class="border-b pb-2 mb-4 d-flex align-center justify-space-between font-weight-bold text-h6"
      >
        <span class="d-flex align-center">
          <VIcon
            class="me-2"
            icon="tabler-bell-cog"
            size="20"
          />
          {{ t('notification::notification_workflows.notification_setting') }}
        </span>

        <VBtn
          color="primary"
          icon="tabler-x"
          size="small"
          variant="text"
          @click="close"
        />
      </VCardTitle>

      <VCardText>
        <div
          v-if="loading"
          class="d-flex justify-center py-10"
        >
          <VProgressCircular
            color="primary"
            indeterminate
          />
        </div>

        <VRow v-else-if="workflow">
          <VCol
            cols="12"
            md="6"
          >
            <div class="text-caption text-medium-emphasis mb-1">
              {{ t('notification::notification_workflows.table.workflow') }}
            </div>
            <div class="text-body-1 font-weight-medium">
              {{ translateValue(workflow.name) }}
            </div>
          </VCol>

          <VCol
            cols="12"
            md="6"
          >
            <div class="text-caption text-medium-emphasis mb-1">
              {{ t('notification::notification_workflows.table.key') }}
            </div>
            <VChip
              size="small"
              variant="tonal"
            >
              {{ workflow.key }}
            </VChip>
          </VCol>

          <VCol cols="12">
            <div class="text-caption text-medium-emphasis mb-1">
              {{ t('notification::attributes.notification_templates.description') }}
            </div>
            <div class="text-body-2">
              {{ translateValue(workflow.description) || '-' }}
            </div>
          </VCol>

          <VCol cols="12">
            <VDivider class="my-2" />
          </VCol>

          <VCol cols="12">
            <div class="recipient-channels">
              <div class="recipient-channels__header">
                <div class="recipient-channels__title">
                  <VIcon
                    icon="tabler-users-group"
                    size="18"
                  />
                  <span>
                    {{ t('notification::notification_workflows.form.recipient_channels') }}
                  </span>
                </div>
              </div>

              <div class="recipient-channels__list">
                <div
                  v-for="channel in channelRows"
                  :key="channel.key"
                  class="recipient-channels__row"
                >
                  <div class="recipient-channels__channel">
                    <div class="recipient-channels__icon">
                      <VIcon
                        :icon="channel.icon"
                        size="18"
                      />
                    </div>

                    <div class="min-w-0">
                      <div class="recipient-channels__name">
                        {{ channel.name }}
                      </div>
                    </div>
                  </div>

                  <div class="recipient-channels__recipients">
                    <VChip
                      v-for="recipient in channel.recipients"
                      :key="recipient.key"
                      size="small"
                      variant="tonal"
                    >
                      {{ recipientName(recipient) }}
                    </VChip>

                    <span
                      v-if="!channel.recipients.length"
                      class="recipient-channels__empty"
                    >
                      <VIcon
                        icon="tabler-user-off"
                        size="16"
                      />
                      {{ t('notification::notification_workflows.form.no_enabled_recipients') }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </VCol>
        </VRow>

        <div
          v-else
          class="text-center text-disabled py-10"
        >
          {{ t('core::errors.an_unexpected_error_occurred') }}
        </div>
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.recipient-channels {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px;
  overflow: hidden;

  &__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    background: rgba(var(--v-theme-on-surface), 0.02);
  }

  &__title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
    font-weight: 600;
  }

  &__list {
    display: flex;
    flex-direction: column;
  }

  &__row {
    display: grid;
    align-items: center;
    grid-template-columns: minmax(11rem, 15rem) minmax(0, 1fr);
    gap: 1rem;
    padding: 0.9rem 1rem;
    border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  }

  &__row:last-child {
    border-block-end: 0;
  }

  &__channel {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
  }

  &__icon {
    display: flex;
    flex: 0 0 auto;
    align-items: center;
    justify-content: center;
    inline-size: 2rem;
    block-size: 2rem;
    border-radius: 8px;
    background: rgba(var(--v-theme-primary), 0.1);
    color: rgb(var(--v-theme-primary));
  }

  &__name {
    overflow: hidden;
    font-weight: 600;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  &__recipients {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 0.5rem;
    min-width: 0;
  }

  &__empty {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    color: rgba(var(--v-theme-on-surface), var(--v-disabled-opacity));
    font-size: 0.8125rem;
  }
}

@media (max-width: 767px) {
  .recipient-channels {
    &__row {
      grid-template-columns: 1fr;
      gap: 0.65rem;
    }

    &__recipients {
      justify-content: flex-start;
    }
  }
}
</style>
