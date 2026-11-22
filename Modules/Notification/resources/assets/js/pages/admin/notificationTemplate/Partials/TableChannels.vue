<script lang="ts" setup>
import type { ServiceItem } from '@/modules/service/contracts/Service.ts'

defineProps<{
  item: {
    channels?: ServiceItem[]
  }
}>()

function channelIcon(channel: ServiceItem): string {
  if (channel.icon) {
    return channel.icon
  }

  const icons: Record<string, string> = {
    email: 'tabler-mail',
    in_app: 'tabler-device-mobile-message',
    push_notification: 'tabler-bell-ringing',
    whatsapp: 'tabler-brand-whatsapp',
  }

  return icons[channel.key] || 'tabler-message'
}
</script>

<template>
  <div class="d-flex flex-wrap ga-2 py-2">
    <VChip
      v-for="channel in item.channels || []"
      :key="channel.key"
      size="small"
      variant="tonal"
    >
      <VIcon
        class="me-1"
        :icon="channelIcon(channel)"
        size="14"
      />
      {{ channel.name }}
    </VChip>
  </div>
</template>
