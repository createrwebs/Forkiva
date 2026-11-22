import type { ServiceItem } from '@/modules/service/contracts/Service.ts'
import type {
  NotificationRecipientRole,
  NotificationWorkflowChannelStates,
} from '@/modules/notification/contracts/NotificationWorkflow.ts'

export interface NotificationTemplateVariable {
  key: string
  token: string
}

export type NotificationTemplateContentType = 'text' | 'html'

export type NotificationTemplateTranslation = Record<string, string | null>

export interface NotificationTemplateMessage {
  title?: NotificationTemplateTranslation | null
  subject?: NotificationTemplateTranslation | null
  content: NotificationTemplateTranslation
  content_type?: NotificationTemplateContentType
}

export type NotificationTemplateMessageVariants = Record<string, NotificationTemplateMessage>

export type NotificationTemplateMessages = Record<string, NotificationTemplateMessageVariants>

export type NotificationTemplateResponseMessages = Record<
  string,
  NotificationTemplateMessage | NotificationTemplateMessageVariants
>

export interface NotificationTemplate {
  key: string
  name: string | NotificationTemplateTranslation
  description?: string | NotificationTemplateTranslation | null
  channels?: ServiceItem[]
  available_recipients?: NotificationRecipientRole[]
  recipient_support?: NotificationWorkflowChannelStates
  supported_variables: NotificationTemplateVariable[]
  content_types_text?: string | null
  supported_variables_text?: string | null
  messages: NotificationTemplateResponseMessages
  created_at?: string | null
  updated_at?: string | null
}

export interface NotificationTemplatePayload {
  name: NotificationTemplateTranslation
  description?: NotificationTemplateTranslation | null
  messages: NotificationTemplateMessages
}
export interface NotificationTemplateFormPayload extends NotificationTemplatePayload {}
