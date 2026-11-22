<script lang="ts" setup>
import type { TableHeader } from '@/modules/core/contracts/Table.ts'
import type { RouteLocationRaw } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import SmartDataTable from '@/modules/core/components/Table/SmartDataTable.vue'
import TableMoney from '@/modules/core/components/Table/Partials/TableMoney.vue'

const { t } = useI18n()
const { can } = useAuth()
const router = useRouter()
const headers: TableHeader[] = [
  {
    title: t('giftcard::gift_card_breakage_batches.table.reference_no'),
    value: 'reference_no',
    sortable: true,
  },
  {
    title: t('giftcard::gift_card_breakage_batches.table.status'),
    value: 'status',
    sortable: true,
  },
  {
    title: t('giftcard::gift_card_breakage_batches.table.total_amount'),
    value: 'total_amount',
    sortable: true,
  },
  {
    title: t('giftcard::gift_card_breakage_batches.table.economic_date'),
    value: 'economic_date',
    sortable: true,
  },
]

function onRowClick(_event: MouseEvent, value: any) {
  if (can('admin.gift_card_breakage_batches.show'))
    router.push({
      name: 'admin.gift_card_breakage_batches.show',
      params: { id: value.item.id },
    } as RouteLocationRaw)
}
</script>

<template>
  <SmartDataTable
    :actions="[{ key: 'show' }]"
    api-uri="/v1/gift-card-breakage-batches"
    :cell-components="{
      total_amount: {
        component: TableMoney,
        props: { column: 'total_amount' },
      },
    }"
    :header-actions="can('admin.gift_card_breakage_batches.create') ? [{ key: 'create' }] : []"
    :headers="headers"
    module="giftcard"
    name="gift_card_breakage_batch"
    :on-row-click="onRowClick"
    resource="gift_card_breakage_batches"
  />
</template>
