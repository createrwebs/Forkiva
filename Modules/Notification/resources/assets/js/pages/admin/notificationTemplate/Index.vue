<script lang="ts" setup>
import type { TableAction, TableHeader } from '@/modules/core/contracts/Table.ts'
import type { NotificationTemplate } from '@/modules/notification/contracts/NotificationTemplate.ts'
import { useI18n } from 'vue-i18n'
import TableChannels from './Partials/TableChannels.vue'

const router = useRouter()
const { t } = useI18n()

const headers: TableHeader[] = [
  {
    title: t('notification::notification_templates.table.template'),
    value: 'name',
    sortable: true,
  },
  { title: t('notification::notification_templates.table.key'), value: 'key', sortable: true },
  {
    title: t('notification::notification_templates.table.channels'),
    value: 'channels',
    sortable: false,
  },
  { title: t('admin::admin.table.updated_at'), value: 'updated_at', sortable: true },
]

const cellComponents = {
  channels: {
    component: TableChannels,
  },
}

const actions: TableAction[] = [
  {
    key: 'edit',
    onClick: (item) => {
      void router.push({
        name: 'admin.notification_templates.edit',
        params: { key: (item as NotificationTemplate).key },
      })
    },
  },
]
</script>

<template>
  <SmartDataTable
    :actions="actions"
    api-uri="/v1/notification-templates"
    :cell-components="cellComponents"
    :header-actions="[]"
    :headers="headers"
    item-id="key"
    module="notification"
    name="notification_template"
    resource="notification_templates"
  />
</template>
