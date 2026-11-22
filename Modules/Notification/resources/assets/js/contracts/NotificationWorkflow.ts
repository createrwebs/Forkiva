import type { ServiceItem } from '@/modules/service/contracts/Service.ts'

export type NotificationWorkflowTranslation = Record<string, string | null>
export type NotificationWorkflowChannelStates = Record<string, Record<string, boolean>>

export interface NotificationRecipientRole {
  id: number | string
  key: string
  name: string | NotificationWorkflowTranslation
}

export interface NotificationWorkflowFormPayload {
  channels: NotificationWorkflowChannelStates
}

export interface NotificationWorkflowChannel extends ServiceItem {
  enabled_recipients?: string[]
  is_enabled?: boolean
  is_supported?: boolean
}

export interface NotificationWorkflowListItem {
  key: string
  name: string | NotificationWorkflowTranslation
  updated_at?: string | null
}

export interface NotificationWorkflow extends NotificationWorkflowListItem {
  description?: string | NotificationWorkflowTranslation | null
  channels: NotificationWorkflowChannel[]
  available_recipients: NotificationRecipientRole[]
  channel_states: NotificationWorkflowChannelStates
  channel_support: Record<string, boolean>
  recipient_support: NotificationWorkflowChannelStates
  order: number
  created_at?: string | null
}
