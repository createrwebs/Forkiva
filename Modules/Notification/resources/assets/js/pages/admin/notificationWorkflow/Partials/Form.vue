<script lang="ts" setup>
import type {
  NotificationRecipientRole,
  NotificationWorkflow,
  NotificationWorkflowChannel,
  NotificationWorkflowChannelStates,
  NotificationWorkflowTranslation,
} from '@/modules/notification/contracts/NotificationWorkflow.ts'
import type { RouteLocationRaw } from 'vue-router'
import { useI18n } from 'vue-i18n'
import BaseForm from '@/modules/core/components/Form/BaseForm.vue'
import { useForm } from '@/modules/core/composables/form.ts'
import { useAppStore } from '@/modules/core/stores/appStore.ts'
import { useNotificationWorkflow } from '@/modules/notification/composables/notificationWorkflow.ts'

const props = defineProps<{
  item: NotificationWorkflow
}>()

const { t } = useI18n()
const router = useRouter()
const appStore = useAppStore()
const { updateWorkflow } = useNotificationWorkflow()

const form = useForm({
  channels: {} as NotificationWorkflowChannelStates,
})

const supportedChannels = computed(() =>
  props.item.channels.filter((channel) => channel.is_supported),
)
const recipients = computed(() => props.item.available_recipients || [])
const hasEnabledRecipient = computed(() => {
  for (const roles of Object.values(form.state.channels)) {
    if (Object.values(roles || {}).some(Boolean)) {
      return true
    }
  }

  return false
})

watch(() => props.item, hydrateForm, { immediate: true })

function hydrateForm() {
  form.state.channels = {}

  for (const channel of supportedChannels.value) {
    const channelState: Record<string, boolean> = {}

    for (const recipient of recipients.value) {
      if (!isRecipientSupported(channel, recipient)) {
        continue
      }

      channelState[recipient.key] = Boolean(
        props.item.channel_states?.[channel.key]?.[recipient.key],
      )
    }

    form.state.channels[channel.key] = channelState
  }
}

function workflowName(): string {
  return translateValue(props.item.name) || props.item.key
}

function workflowDescription(): string {
  return translateValue(props.item.description)
}

function recipientName(recipient: NotificationRecipientRole): string {
  return translateValue(recipient.name) || recipient.key
}

function isRecipientSupported(
  channel: NotificationWorkflowChannel,
  recipient: NotificationRecipientRole,
): boolean {
  return Boolean(props.item.recipient_support?.[channel.key]?.[recipient.key])
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

function channelIcon(channel: NotificationWorkflowChannel): string {
  if (channel.icon) {
    return channel.icon
  }

  const icons: Record<string, string> = {
    email: 'tabler-mail',
    in_app: 'tabler-device-mobile-message',
    push_notification: 'tabler-bell-ringing',
    sms: 'tabler-message-2',
    whatsapp: 'tabler-brand-whatsapp',
  }

  return icons[channel.key] || 'tabler-message'
}

async function submit() {
  if (form.loading.value || !hasEnabledRecipient.value) {
    return
  }

  const response = await form.submit(
    () =>
      updateWorkflow(props.item.key, {
        channels: form.state.channels,
      }),
    true,
  )

  if (!response) {
    return
  }

  await router.push({ name: 'admin.notification_settings.index' } as RouteLocationRaw)
}
</script>

<template>
  <BaseForm
    action="update"
    :disabled="!hasEnabledRecipient"
    :loading="form.loading.value"
    resource="notification_settings"
    @submit="submit"
  >
    <VRow>
      <VCol
        cols="12"
        md="4"
      >
        <VCard>
          <VCardTitle class="d-flex align-center">
            <VIcon
              class="me-2"
              icon="tabler-bell-cog"
              size="20"
            />
            {{ t('notification::notification_workflows.form.workflow_details') }}
          </VCardTitle>

          <VCardText>
            <VRow>
              <VCol cols="12">
                <VTextField
                  :label="t('notification::notification_workflows.table.workflow')"
                  :model-value="workflowName()"
                  readonly
                />
              </VCol>

              <VCol cols="12">
                <VTextarea
                  auto-grow
                  :label="t('notification::attributes.notification_templates.description')"
                  :model-value="workflowDescription()"
                  readonly
                  rows="4"
                />
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        md="8"
      >
        <VCard>
          <VCardTitle class="d-flex align-center">
            <VIcon
              class="me-2"
              icon="tabler-users-group"
              size="20"
            />
            {{ t('notification::notification_workflows.form.recipient_channels') }}
          </VCardTitle>

          <VCardText>
            <VAlert
              v-if="!hasEnabledRecipient"
              class="mb-4"
              color="error"
              variant="tonal"
            >
              {{
                form.errors.value.channels ||
                t('notification::notification_workflows.errors.at_least_one_recipient')
              }}
            </VAlert>

            <VTable class="notification-workflow-matrix">
              <thead>
                <tr>
                  <th>
                    {{ t('notification::attributes.notification_workflows.recipient') }}
                  </th>

                  <th
                    v-for="channel in supportedChannels"
                    :key="channel.key"
                    class="text-center"
                  >
                    <div class="d-inline-flex align-center ga-1">
                      <VIcon
                        :icon="channelIcon(channel)"
                        size="17"
                      />
                      <span>{{ channel.name }}</span>
                    </div>
                  </th>
                </tr>
              </thead>

              <tbody>
                <tr
                  v-for="recipient in recipients"
                  :key="recipient.key"
                >
                  <td>
                    <div class="d-flex flex-column">
                      <span class="font-weight-medium">
                        {{ recipientName(recipient) }}
                      </span>
                    </div>
                  </td>

                  <td
                    v-for="channel in supportedChannels"
                    :key="`${recipient.key}_${channel.key}`"
                    class="text-center"
                  >
                    <VSwitch
                      v-if="isRecipientSupported(channel, recipient)"
                      v-model="form.state.channels[channel.key]![recipient.key]"
                      color="primary"
                      density="compact"
                      hide-details
                      inset
                    />

                    <span
                      v-else
                      class="text-disabled"
                    >
                      -
                    </span>
                  </td>
                </tr>

                <tr v-if="recipients.length === 0">
                  <td
                    class="text-center text-disabled py-8"
                    :colspan="supportedChannels.length + 1"
                  >
                    {{ t('notification::notification_workflows.form.no_enabled_recipients') }}
                  </td>
                </tr>
              </tbody>
            </VTable>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </BaseForm>
</template>

<style lang="scss" scoped>
.notification-workflow-matrix {
  :deep(th),
  :deep(td) {
    vertical-align: middle;
  }

  :deep(.v-selection-control) {
    justify-content: center;
  }
}
</style>
