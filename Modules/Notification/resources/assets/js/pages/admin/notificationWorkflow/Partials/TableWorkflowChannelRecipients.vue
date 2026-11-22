<script lang="ts" setup>
import type {
  NotificationRecipientRole,
  NotificationWorkflow,
  NotificationWorkflowChannel,
  NotificationWorkflowTranslation,
} from '@/modules/notification/contracts/NotificationWorkflow.ts'
import { useAppStore } from '@/modules/core/stores/appStore.ts'

const props = defineProps<{
  item: NotificationWorkflow
  channel: NotificationWorkflowChannel
}>()

const appStore = useAppStore()

const enabledRecipients = computed(() => {
  const channelState = props.item.channel_states?.[props.channel.key] || {}

  return props.item.available_recipients.filter((recipient) => Boolean(channelState[recipient.key]))
})

const visibleRecipients = computed(() => enabledRecipients.value.slice(0, 3))
const remainingRecipientsCount = computed(() =>
  Math.max(enabledRecipients.value.length - visibleRecipients.value.length, 0),
)

function recipientName(recipient: NotificationRecipientRole): string {
  return translateValue(recipient.name) || recipient.key
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
</script>

<template>
  <div
    v-if="channel.is_supported"
    class="d-flex flex-wrap ga-1 py-2"
  >
    <VChip
      v-for="recipient in visibleRecipients"
      :key="recipient.key"
      size="small"
      variant="tonal"
    >
      {{ recipientName(recipient) }}
    </VChip>

    <VChip
      v-if="remainingRecipientsCount"
      size="small"
      variant="tonal"
    >
      +{{ remainingRecipientsCount }}
    </VChip>

    <span
      v-if="!enabledRecipients.length"
      class="text-disabled text-caption"
    >
      {{ $t('notification::notification_workflows.form.no_enabled_recipients') }}
    </span>
  </div>

  <span
    v-else
    class="text-disabled"
  >
    -
  </span>
</template>
