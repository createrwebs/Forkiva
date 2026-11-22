<script lang="ts" setup>
import { computed } from 'vue'

const props = withDefaults(defineProps<{ item: any; column?: string }>(), {
  column: 'status',
})

const status = computed(() => props.item?.[props.column] ?? props.item?.status ?? null)
const statusId = computed(() => status.value?.id ?? status.value ?? null)
const statusName = computed(() => status.value?.name ?? status.value?.id ?? status.value ?? '-')
const statusIcon = computed(() => status.value?.icon ?? null)
const statusColor = computed(() => status.value?.color ?? null)

function getIcon() {
  if (statusIcon.value) {
    return statusIcon.value
  }

  switch (statusId.value) {
    case 'pending': {
      return 'tabler-clock'
    }
    case 'received': {
      return 'tabler-circle-check'
    }
    case 'settled': {
      return 'tabler-circle-check'
    }
    case 'partial': {
      return 'tabler-chart-pie-2'
    }
    case 'cancelled': {
      return 'tabler-circle-x'
    }
    case 'active_delivery':
    case 'fresh': {
      return 'tabler-navigation'
    }
    case 'heartbeat': {
      return 'tabler-activity-heartbeat'
    }
    case 'stale': {
      return 'tabler-clock-exclamation'
    }
    case 'upload_pending': {
      return 'tabler-cloud-upload'
    }
    case 'permission_needed':
    case 'permission_revoked': {
      return 'tabler-map-pin-off'
    }
    case 'token_expired': {
      return 'tabler-shield-x'
    }
    case 'app_terminated': {
      return 'tabler-alert-triangle'
    }
    case 'offline':
    case 'stopped': {
      return 'tabler-circle-off'
    }
    default: {
      return null
    }
  }
}

function getColor() {
  if (statusColor.value) {
    return statusColor.value
  }

  switch (statusId.value) {
    case 'received':
    case 'settled':
    case 'completed':
    case 'served':
    case 'issued':
    case 'accepted':
    case 'approved':
    case 'validated':
    case 'reconciled':
    case 'resolved':
    case 'present':
    case 'paid':
    case 'posted':
    case 'closed':
    case 'active':
    case 'active_delivery':
    case 'available':
    case 'delivered':
    case 'on_track':
    case 'fresh': {
      return 'success'
    }
    case 'cancelled':
    case 'failed':
    case 'revoked':
    case 'expired':
    case 'rejected':
    case 'refunded':
    case 'void':
    case 'absent':
    case 'breached':
    case 'provider_rejected': {
      return 'error'
    }
    case 'pending':
    case 'partial':
    case 'pending_approval':
    case 'declared':
    case 'pending_assignment':
    case 'queued':
    case 'processing':
    case 'reversed':
    case 'preparing':
    case 'busy':
    case 'reserved':
    case 'late':
    case 'off':
    case 'on_break':
    case 'incomplete':
    case 'quote_requested':
    case 'blocked':
    case 'stale':
    case 'upload_pending':
    case 'token_expired': {
      return 'warning'
    }
    case 'partially_received':
    case 'confirmed':
    case 'heartbeat':
    case 'ready':
    case 'ready_for_pickup':
    case 'disputed':
    case 'reassigned':
    case 'cleaning':
    case 'used':
    case 'draft':
    case 'reviewed':
    case 'occupied':
    case 'leave':
    case 'holiday':
    case 'arrived':
    case 'quoted':
    case 'provider_accepted':
    case 'at_risk':
    case 'merged': {
      return 'info'
    }
    case 'picked_up':
    case 'on_the_way':
    case 'dispatch_requested':
    case 'courier_assigned':
    case 'assigned':
    case 'open': {
      return 'primary'
    }
    case 'offline':
    case 'stopped':
    case 'inactive':
    case 'disabled':
    case 'not_required':
    case 'not_requested':
    case 'paused':
    case 'skipped': {
      return 'secondary'
    }
    case 'permission_needed':
    case 'permission_revoked':
    case 'app_terminated': {
      return 'error'
    }
    case 'returned': {
      return '#8B5CF6'
    }
    case 'recalculated':
    case 'calculated':
    case 'recalculate': {
      return '#e84393'
    }
    case 'exhausted': {
      return 'error'
    }
    case 'success': {
      return 'success'
    }
    case 'escalated': {
      return 'info'
    }
    default: {
      return 'default'
    }
  }
}
</script>

<template>
  <VChip
    v-if="statusId"
    :color="getColor()"
    size="small"
  >
    <VIcon
      v-if="getIcon()"
      :icon="getIcon()!"
      start
    />
    {{ statusName }}
  </VChip>
  <span v-else>{{ statusName }}</span>
</template>
