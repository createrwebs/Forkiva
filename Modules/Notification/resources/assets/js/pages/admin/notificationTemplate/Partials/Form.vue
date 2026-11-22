<script lang="ts" setup>
import type { SupportedLanguages } from '@/modules/core/contracts/AppState.ts'
import type {
  NotificationTemplate,
  NotificationTemplateFormPayload,
  NotificationTemplateMessage,
  NotificationTemplateMessageVariants,
  NotificationTemplateTranslation,
} from '@/modules/notification/contracts/NotificationTemplate.ts'
import type { NotificationRecipientRole } from '@/modules/notification/contracts/NotificationWorkflow.ts'
import type { ServiceItem } from '@/modules/service/contracts/Service.ts'
import type { RouteLocationRaw } from 'vue-router'
import { useI18n } from 'vue-i18n'
import BaseForm from '@/modules/core/components/Form/BaseForm.vue'
import SimpleEditor from '@/modules/core/components/Form/SimpleEditor.vue'
import { useForm } from '@/modules/core/composables/form.ts'
import { useAppStore } from '@/modules/core/stores/appStore.ts'
import { useNotificationTemplate } from '@/modules/notification/composables/notificationTemplate.ts'

const DEFAULT_RECIPIENT_ROLE = 'default'

type MessageField = 'title' | 'subject' | 'content'
type TemplateField = 'name' | 'description'

interface FocusedField {
  channel: string
  recipientRole: string
  field: MessageField
  locale: string
}

interface RichEditorExpose {
  insertToken: (token: string) => void
  focusEditor: () => void
}

const props = defineProps<{
  item: NotificationTemplate
}>()

const { t } = useI18n()
const appStore = useAppStore()
const { updateTemplate } = useNotificationTemplate()
const router = useRouter()

const selectedTemplate = ref<NotificationTemplate | null>(null)
const activeChannel = ref<string>('')
const activeRecipientByChannel = ref<Record<string, string>>({})
const lastFocusedField = ref<FocusedField | null>(null)
const lastFocusedTarget = ref<HTMLInputElement | HTMLTextAreaElement | null>(null)
const emailEditors = ref<Record<string, RichEditorExpose | null>>({})

const form = useForm<NotificationTemplateFormPayload>({
  name: {},
  description: {},
  messages: {},
})

watch(
  () => props.item,
  (value) => {
    selectedTemplate.value = value
    hydrateForm()
  },
  { immediate: true },
)

function hydrateForm() {
  const template = selectedTemplate.value
  form.state.name = normalizeTranslations(template?.name || {})
  form.state.description = normalizeTranslations(template?.description || {})
  form.state.messages = {}
  activeRecipientByChannel.value = {}
  lastFocusedField.value = null
  lastFocusedTarget.value = null
  emailEditors.value = {}

  if (!template) {
    return
  }

  for (const channel of template.channels || []) {
    const existingMessages = template.messages?.[channel.key]
    const channelMessages: NotificationTemplateMessageVariants = {}
    const recipients = recipientRolesForChannel(channel)

    for (const recipient of recipients) {
      channelMessages[recipient.key] = normalizeMessage(
        channel,
        existingMessageVariant(existingMessages, recipient.key),
      )
    }

    form.state.messages[channel.key] = channelMessages
    activeRecipientByChannel.value[channel.key] = recipients[0]?.key || DEFAULT_RECIPIENT_ROLE
  }

  activeChannel.value = template.channels?.[0]?.key || ''
}

function normalizeMessage(
  channel: ServiceItem,
  message?: NotificationTemplateMessage,
): NotificationTemplateMessage {
  const normalized: NotificationTemplateMessage = {
    content: normalizeTranslations(message?.content || {}),
  }

  if (isEmail(channel)) {
    normalized.subject = normalizeTranslations(message?.subject || {})
    return normalized
  }

  if (supportsTitle(channel)) {
    normalized.title = normalizeTranslations(message?.title || {})
  }

  return normalized
}

function normalizeTranslations(
  translations: NotificationTemplateTranslation | string | null,
): NotificationTemplateTranslation {
  const normalized: NotificationTemplateTranslation = {}

  for (const language of appStore.supportedLanguages) {
    normalized[language.id] =
      typeof translations === 'object' && translations
        ? normalizeLegacyPlaceholders(String(translations[language.id] || ''))
        : ''
  }

  if (typeof translations === 'string') {
    normalized[appStore.defaultLanguage.id] = normalizeLegacyPlaceholders(translations)
  }

  return normalized
}

function normalizeLegacyPlaceholders(value: string): string {
  return value.replace(/(?<!:):([A-Z_]\w*)/gi, '{$1}')
}

function existingMessageVariant(
  messages: NotificationTemplate['messages'][string] | undefined,
  recipientRole: string,
): NotificationTemplateMessage | undefined {
  if (!isRecord(messages)) {
    return undefined
  }

  if (looksLikeMessage(messages)) {
    return recipientRole === DEFAULT_RECIPIENT_ROLE
      ? (messages as NotificationTemplateMessage)
      : undefined
  }

  const variant = messages[recipientRole]
  return looksLikeMessage(variant) ? variant : undefined
}

function looksLikeMessage(value: unknown): value is NotificationTemplateMessage {
  return (
    isRecord(value) &&
    (Object.prototype.hasOwnProperty.call(value, 'title') ||
      Object.prototype.hasOwnProperty.call(value, 'subject') ||
      Object.prototype.hasOwnProperty.call(value, 'content'))
  )
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return Boolean(value) && typeof value === 'object' && !Array.isArray(value)
}

function isEmail(channel: ServiceItem): boolean {
  return channel.key === 'email'
}

function supportsTitle(channel: ServiceItem): boolean {
  return channel.key === 'in_app' || channel.key === 'push_notification'
}

function channelIcon(channel: ServiceItem): string {
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

function recipientRolesForChannel(channel: ServiceItem): NotificationRecipientRole[] {
  const template = selectedTemplate.value
  const recipients: NotificationRecipientRole[] = [
    {
      id: DEFAULT_RECIPIENT_ROLE,
      key: DEFAULT_RECIPIENT_ROLE,
      name: 'Default',
    },
  ]

  for (const recipient of template?.available_recipients || []) {
    if (isRecipientSupported(channel.key, recipient.key)) {
      recipients.push(recipient)
    }
  }

  return recipients
}

function isRecipientSupported(channel: string, recipientRole: string): boolean {
  if (recipientRole === DEFAULT_RECIPIENT_ROLE) {
    return true
  }

  return Boolean(selectedTemplate.value?.recipient_support?.[channel]?.[recipientRole])
}

function recipientName(recipient: NotificationRecipientRole): string {
  return translateValue(recipient.name) || recipient.key
}

function translateValue(
  value: string | NotificationTemplateTranslation | null | undefined,
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

function activeRecipient(channel: string): string {
  return activeRecipientByChannel.value[channel] || DEFAULT_RECIPIENT_ROLE
}

function setActiveRecipient(channel: string, recipientRole: unknown) {
  activeRecipientByChannel.value[channel] = String(recipientRole || DEFAULT_RECIPIENT_ROLE)
}

function templateFieldError(field: TemplateField, language: SupportedLanguages): string {
  return form.errors.value?.[`${field}.${language.id}`] || form.errors.value?.[field] || ''
}

function templateFieldValue(field: TemplateField, language: SupportedLanguages): string {
  return String(form.state[field]?.[language.id] || '')
}

function updateTemplateField(field: TemplateField, language: SupportedLanguages, value: unknown) {
  form.state[field] = form.state[field] || {}
  form.state[field][language.id] = String(value || '')
}

function fieldError(
  channel: string,
  recipientRole: string,
  field: MessageField,
  language: SupportedLanguages,
): string {
  return (
    form.errors.value?.[`messages.${channel}.${recipientRole}.${field}.${language.id}`] ||
    form.errors.value?.[`messages.${channel}.${recipientRole}.${field}`] ||
    form.errors.value?.[`messages.${channel}.${field}.${language.id}`] ||
    form.errors.value?.[`messages.${channel}.${field}`] ||
    ''
  )
}

function rememberFocusedField(
  channel: string,
  recipientRole: string,
  field: MessageField,
  language: SupportedLanguages,
  event: FocusEvent,
) {
  lastFocusedField.value = { channel, recipientRole, field, locale: language.id }
  lastFocusedTarget.value = event.target as HTMLInputElement | HTMLTextAreaElement
}

function rememberEditorFocus(channel: string, recipientRole: string, language: SupportedLanguages) {
  lastFocusedField.value = {
    channel,
    recipientRole,
    field: 'content',
    locale: language.id,
  }
  lastFocusedTarget.value = null
}

function setEmailEditorRef(
  channel: string,
  recipientRole: string,
  language: SupportedLanguages,
  component: unknown,
) {
  emailEditors.value[emailEditorKey(channel, recipientRole, language.id)] =
    component as RichEditorExpose | null
}

function emailEditorKey(channel: string, recipientRole: string, locale: string): string {
  return `${channel}:${recipientRole}:${locale}`
}

function messageValue(
  channel: string,
  recipientRole: string,
  field: MessageField,
  language: SupportedLanguages,
): string {
  return translatedMessageValue(channel, recipientRole, field, language.id)
}

function updateMessageField(
  channel: string,
  recipientRole: string,
  field: MessageField,
  language: SupportedLanguages,
  value: unknown,
) {
  if (!form.state.messages[channel]?.[recipientRole]) {
    return
  }

  updateTranslatedMessageField(channel, recipientRole, field, language.id, String(value || ''))
}

function translatedMessageValue(
  channel: string,
  recipientRole: string,
  field: MessageField,
  locale: string,
): string {
  const translations = form.state.messages[channel]?.[recipientRole]?.[field]

  if (!translations || typeof translations !== 'object') {
    return ''
  }

  return String(translations[locale] || '')
}

function updateTranslatedMessageField(
  channel: string,
  recipientRole: string,
  field: MessageField,
  locale: string,
  value: string,
) {
  const message = form.state.messages[channel]?.[recipientRole]
  if (!message) {
    return
  }

  const translations = message[field] || {}
  translations[locale] = value
  message[field] = translations as never
}

function insertVariable(token: string) {
  const field = lastFocusedField.value || defaultFocusedField()
  if (!field) {
    return
  }

  const message = form.state.messages[field.channel]?.[field.recipientRole]
  const target = lastFocusedTarget.value
  const value = translatedMessageValue(
    field.channel,
    field.recipientRole,
    field.field,
    field.locale,
  )

  if (!message) {
    return
  }

  if (target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement) {
    const start = target.selectionStart
    const end = target.selectionEnd

    if (start === null || end === null) {
      updateTranslatedMessageField(
        field.channel,
        field.recipientRole,
        field.field,
        field.locale,
        `${value}${token}`,
      )
      return
    }

    updateTranslatedMessageField(
      field.channel,
      field.recipientRole,
      field.field,
      field.locale,
      `${value.slice(0, start)}${token}${value.slice(end)}`,
    )

    nextTick(() => {
      target.focus()
      target.selectionStart = start + token.length
      target.selectionEnd = start + token.length
    })
    return
  }

  if (
    field.field === 'content' &&
    selectedTemplate.value?.channels?.some(
      (channel) => channel.key === field.channel && isEmail(channel),
    )
  ) {
    emailEditors.value[
      emailEditorKey(field.channel, field.recipientRole, field.locale)
    ]?.insertToken(token)
    return
  }

  updateTranslatedMessageField(
    field.channel,
    field.recipientRole,
    field.field,
    field.locale,
    `${value}${token}`,
  )
}

function defaultFocusedField(): FocusedField | null {
  const firstChannel = selectedTemplate.value?.channels?.[0]
  if (!firstChannel) {
    return null
  }

  return {
    channel: firstChannel.key,
    recipientRole: activeRecipient(firstChannel.key),
    field: 'content',
    locale: appStore.defaultLanguage.id,
  }
}

async function submit() {
  const template = selectedTemplate.value
  if (!template || form.loading.value) {
    return
  }

  const response = (await form.submit(
    () =>
      updateTemplate(template.key, {
        name: form.state.name,
        description: form.state.description,
        messages: form.state.messages,
      }),
    true,
  )) as Record<string, unknown> | false

  if (!response) {
    return
  }

  await router.push({ name: 'admin.notification_templates.index' } as RouteLocationRaw)
}
</script>

<template>
  <BaseForm
    v-if="selectedTemplate"
    action="update"
    :has-multiple-language="true"
    :loading="form.loading.value"
    resource="notification_templates"
    @submit="submit"
  >
    <template #default="{ currentLanguage }">
      <VRow>
        <VCol
          cols="12"
          md="4"
        >
          <VCard>
            <VCardTitle class="d-flex align-center">
              <VIcon
                class="me-2"
                icon="tabler-template"
                size="20"
              />
              {{ t('notification::notification_templates.form.template_details') }}
            </VCardTitle>

            <VCardText>
              <VRow>
                <VCol cols="12">
                  <VTextField
                    :error="!!templateFieldError('name', currentLanguage)"
                    :error-messages="templateFieldError('name', currentLanguage)"
                    :label="
                      t('notification::attributes.notification_templates.name') +
                      ` ( ${currentLanguage.name} )`
                    "
                    :model-value="templateFieldValue('name', currentLanguage)"
                    @update:model-value="updateTemplateField('name', currentLanguage, $event)"
                  />
                </VCol>

                <VCol cols="12">
                  <VTextarea
                    auto-grow
                    :error="!!templateFieldError('description', currentLanguage)"
                    :error-messages="templateFieldError('description', currentLanguage)"
                    :label="
                      t('notification::attributes.notification_templates.description') +
                      ` ( ${currentLanguage.name} )`
                    "
                    :model-value="templateFieldValue('description', currentLanguage)"
                    rows="4"
                    @update:model-value="
                      updateTemplateField('description', currentLanguage, $event)
                    "
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
                icon="tabler-mail"
                size="20"
              />
              {{ t('notification::notification_templates.form.message_content') }}
            </VCardTitle>

            <VCardText>
              <div class="variable-list mb-6">
                <VChip
                  v-for="variable in selectedTemplate.supported_variables"
                  :key="variable.key"
                  size="small"
                  @click="insertVariable(variable.token)"
                >
                  {{ variable.token }}
                </VChip>
              </div>

              <VTabs
                v-model="activeChannel"
                color="primary"
              >
                <VTab
                  v-for="channel in selectedTemplate.channels || []"
                  :key="channel.key"
                  :value="channel.key"
                >
                  <VIcon
                    class="me-2"
                    :icon="channelIcon(channel)"
                    size="18"
                  />
                  {{ channel.name }}
                </VTab>
              </VTabs>

              <VWindow
                v-model="activeChannel"
                class="mt-4"
              >
                <VWindowItem
                  v-for="channel in selectedTemplate.channels || []"
                  :key="channel.key"
                  :value="channel.key"
                >
                  <div class="recipient-tabs mb-5">
                    <VTabs
                      color="primary"
                      density="compact"
                      :model-value="activeRecipient(channel.key)"
                      @update:model-value="setActiveRecipient(channel.key, $event)"
                    >
                      <VTab
                        v-for="recipient in recipientRolesForChannel(channel)"
                        :key="`${channel.key}_${recipient.key}`"
                        :value="recipient.key"
                      >
                        {{ recipientName(recipient) }}
                      </VTab>
                    </VTabs>
                  </div>

                  <template
                    v-for="recipient in recipientRolesForChannel(channel)"
                    :key="`${channel.key}_${recipient.key}_fields`"
                  >
                    <VRow
                      v-if="activeRecipient(channel.key) === recipient.key"
                      class="mt-1"
                    >
                      <VCol
                        v-if="isEmail(channel)"
                        cols="12"
                      >
                        <VTextField
                          :error="
                            !!fieldError(channel.key, recipient.key, 'subject', currentLanguage)
                          "
                          :error-messages="
                            fieldError(channel.key, recipient.key, 'subject', currentLanguage)
                          "
                          :label="
                            t('notification::attributes.notification_template_messages.subject') +
                            ` ( ${currentLanguage.name} )`
                          "
                          :model-value="
                            messageValue(channel.key, recipient.key, 'subject', currentLanguage)
                          "
                          @focus="
                            rememberFocusedField(
                              channel.key,
                              recipient.key,
                              'subject',
                              currentLanguage,
                              $event,
                            )
                          "
                          @update:model-value="
                            updateMessageField(
                              channel.key,
                              recipient.key,
                              'subject',
                              currentLanguage,
                              $event,
                            )
                          "
                        />
                      </VCol>

                      <VCol
                        v-if="supportsTitle(channel)"
                        cols="12"
                      >
                        <VTextField
                          :error="
                            !!fieldError(channel.key, recipient.key, 'title', currentLanguage)
                          "
                          :error-messages="
                            fieldError(channel.key, recipient.key, 'title', currentLanguage)
                          "
                          label="Title"
                          :model-value="
                            messageValue(channel.key, recipient.key, 'title', currentLanguage)
                          "
                          @focus="
                            rememberFocusedField(
                              channel.key,
                              recipient.key,
                              'title',
                              currentLanguage,
                              $event,
                            )
                          "
                          @update:model-value="
                            updateMessageField(
                              channel.key,
                              recipient.key,
                              'title',
                              currentLanguage,
                              $event,
                            )
                          "
                        />
                      </VCol>

                      <VCol cols="12">
                        <SimpleEditor
                          v-if="isEmail(channel)"
                          :ref="
                            (component) =>
                              setEmailEditorRef(
                                channel.key,
                                recipient.key,
                                currentLanguage,
                                component,
                              )
                          "
                          :error-messages="
                            fieldError(channel.key, recipient.key, 'content', currentLanguage)
                          "
                          :label="
                            t('notification::attributes.notification_template_messages.content') +
                            ` ( ${currentLanguage.name} )`
                          "
                          :model-value="
                            messageValue(channel.key, recipient.key, 'content', currentLanguage)
                          "
                          @focus="rememberEditorFocus(channel.key, recipient.key, currentLanguage)"
                          @update:model-value="
                            updateMessageField(
                              channel.key,
                              recipient.key,
                              'content',
                              currentLanguage,
                              $event,
                            )
                          "
                        />

                        <VTextarea
                          v-else
                          auto-grow
                          :error="
                            !!fieldError(channel.key, recipient.key, 'content', currentLanguage)
                          "
                          :error-messages="
                            fieldError(channel.key, recipient.key, 'content', currentLanguage)
                          "
                          :label="
                            t('notification::attributes.notification_template_messages.content') +
                            ` ( ${currentLanguage.name} )`
                          "
                          :model-value="
                            messageValue(channel.key, recipient.key, 'content', currentLanguage)
                          "
                          :rows="5"
                          @focus="
                            rememberFocusedField(
                              channel.key,
                              recipient.key,
                              'content',
                              currentLanguage,
                              $event,
                            )
                          "
                          @update:model-value="
                            updateMessageField(
                              channel.key,
                              recipient.key,
                              'content',
                              currentLanguage,
                              $event,
                            )
                          "
                        />
                      </VCol>
                    </VRow>
                  </template>
                </VWindowItem>
              </VWindow>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </template>
  </BaseForm>
</template>

<style lang="scss" scoped>
.variable-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.recipient-tabs {
  border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

:deep(.v-chip) {
  cursor: pointer;
  font-family: monospace;
}
</style>
