<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import TableOrderTracker from '@/modules/seatingPlan/pages/public/tableOrder/Partials/TableOrderTracker.vue'

defineProps<{
  activeOrder: Record<string, any> | null
  appLogo: string | null
  appLogoFallback: string
  branch: Record<string, any>
  session: Record<string, any>
  table: Record<string, any>
  trackingRefreshing: boolean
}>()

defineEmits<{
  (event: 'refresh-tracking'): void
}>()

const { t } = useI18n()
</script>

<template>
  <div class="table-order-sticky-shell">
    <header class="table-order-header">
      <div class="table-order-identity">
        <div class="table-order-identity__mark">
          <img
            v-if="appLogo"
            :alt="t('seatingplan::tables.table_order.app_logo_alt')"
            :src="appLogo"
          />

          <span v-else>{{ appLogoFallback }}</span>
        </div>

        <div class="table-order-identity__copy">
          <div class="table-order-branch">{{ branch.name }}</div>
          <div class="table-order-table">{{ table.name }}</div>
        </div>
      </div>

      <div class="table-order-header__actions">
        <TableOrderTracker
          v-if="activeOrder"
          :active-order="activeOrder"
          :refreshing="trackingRefreshing"
          variant="action"
          @refresh="$emit('refresh-tracking')"
        />

        <span
          class="table-order-status"
          :class="{ 'table-order-status--closed': !session.available }"
        >
          {{
            session.available
              ? t('seatingplan::tables.table_order.ordering_open')
              : t('seatingplan::tables.table_order.ask_staff')
          }}
        </span>
      </div>
    </header>
  </div>
</template>
