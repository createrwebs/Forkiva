<script lang="ts" setup>
import type { TableAction, TableHeader } from '@/modules/core/contracts/Table.ts'
import type { NotificationWorkflowListItem } from '@/modules/notification/contracts/NotificationWorkflow.ts'
import { useI18n } from 'vue-i18n'
import ShowDialog from './Dialogs/ShowDialog.vue'

const table = ref()
const router = useRouter()
const { t } = useI18n()
const showDialog = ref(false)
const selectedItem = ref<NotificationWorkflowListItem | null>(null)

const headers: TableHeader[] = [
  {
    title: t('notification::notification_workflows.table.workflow'),
    value: 'name',
    sortable: true,
  },
  { title: t('notification::notification_workflows.table.key'), value: 'key', sortable: true },
  { title: t('admin::admin.table.updated_at'), value: 'updated_at', sortable: true },
]

const actions: TableAction[] = [
  {
    key: 'show',
    permission: 'admin.notification_workflows.index',
    onClick: (item) => openShowDialog(item as NotificationWorkflowListItem),
  },
  {
    key: 'edit',
    onClick: (item) => {
      void router.push({
        name: 'admin.notification_settings.edit',
        params: { key: (item as NotificationWorkflowListItem).key },
      })
    },
  },
]

function openShowDialog(item: NotificationWorkflowListItem) {
  selectedItem.value = item
  showDialog.value = true
}

function onRowClick(event: MouseEvent, value: any) {
  openShowDialog(value.item as NotificationWorkflowListItem)
}
</script>

<template>
  <SmartDataTable
    ref="table"
    :actions="actions"
    api-uri="/v1/notification-workflows"
    :header-actions="[]"
    :headers="headers"
    item-id="key"
    module="notification"
    name="notification_workflow"
    :on-row-click="onRowClick"
    resource="notification_workflows"
  />

  <ShowDialog
    v-if="showDialog && selectedItem"
    v-model="showDialog"
    :item="selectedItem"
  />
</template>
