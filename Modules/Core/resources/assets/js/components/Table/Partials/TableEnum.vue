<script lang="ts" setup>
import { computed } from 'vue'

const props = defineProps<{ item: any; column: string }>()

const value = computed(() => props.item?.[props.column] ?? null)
const valueId = computed(() => value.value?.id ?? null)
const valueName = computed(() => value.value?.name ?? null)
const valueIcon = computed(() => value.value?.icon ?? null)
const valueColor = computed(() => value.value?.color ?? null)

function getIcon() {
  if (valueIcon.value) {
    return valueIcon.value
  }

  switch (valueId.value) {
    case 'priority': {
      return 'tabler-list-numbers'
    }
    case 'cheapest_quote': {
      return 'tabler-cash'
    }
    case 'fastest_eta': {
      return 'tabler-clock-bolt'
    }
    case 'balanced_score': {
      return 'tabler-scale'
    }
    case 'zone_based': {
      return 'tabler-map-pin-share'
    }
    case 'internal_first_then_external': {
      return 'tabler-switch-2'
    }
    case 'active': {
      return 'tabler-route'
    }
    case 'fixed_amount': {
      return 'tabler-currency-dollar'
    }
    case 'per_delivery': {
      return 'tabler-route'
    }
    case 'zone_flat': {
      return 'tabler-map-pin'
    }
    case 'batch_multiplier': {
      return 'tabler-layers-linked'
    }
    case 'peak_hour_bonus': {
      return 'tabler-clock-bolt'
    }
    case 'cod_commission': {
      return 'tabler-cash'
    }
    case 'cancellation_compensation': {
      return 'tabler-alert-triangle'
    }
    case 'percentage': {
      return 'tabler-percentage'
    }
    case 'multiplier': {
      return 'tabler-x'
    }
    case 'ready_for_pickup': {
      return 'tabler-package'
    }
    case 'pending_assignment': {
      return 'tabler-user-question'
    }
    case 'assigned': {
      return 'tabler-user-plus'
    }
    case 'picked_up':
    case 'courier_assigned': {
      return 'tabler-truck-delivery'
    }
    case 'accepted': {
      return 'tabler-circle-check'
    }
    case 'open': {
      return 'tabler-message-exclamation'
    }
    case 'reviewing': {
      return 'tabler-eye-search'
    }
    case 'rejected': {
      return 'tabler-circle-x'
    }
    case 'resolved': {
      return 'tabler-circle-check'
    }
    case 'reassigned': {
      return 'tabler-refresh'
    }
    case 'completed': {
      return 'tabler-checks'
    }
    case 'quote_requested': {
      return 'tabler-receipt'
    }
    case 'quoted': {
      return 'tabler-file-invoice'
    }
    case 'dispatch_requested': {
      return 'tabler-send'
    }
    case 'provider_accepted': {
      return 'tabler-clipboard-check'
    }
    case 'provider_rejected': {
      return 'tabler-circle-x'
    }
    case 'manual_rerun': {
      return 'tabler-player-track-next'
    }
    case 'provider_fallback': {
      return 'tabler-route'
    }
    case 'provider_timeout_sweep': {
      return 'tabler-hourglass-high'
    }
    case 'sla_sweep': {
      return 'tabler-bell-ringing'
    }
    case 'on_track': {
      return 'tabler-shield-check'
    }
    case 'at_risk': {
      return 'tabler-alert-triangle'
    }
    case 'breached': {
      return 'tabler-alarm'
    }
    case 'success': {
      return 'tabler-circle-check'
    }
    case 'blocked': {
      return 'tabler-alert-triangle'
    }
    case 'paused': {
      return 'tabler-pause'
    }
    case 'skipped': {
      return 'tabler-player-pause'
    }
    case 'escalated': {
      return 'tabler-arrows-transfer-up-down'
    }
    case 'exhausted': {
      return 'tabler-ban'
    }
    case 'failed': {
      return 'tabler-alert-circle'
    }
    case 'returned': {
      return 'tabler-corner-down-left'
    }
    case 'cancelled': {
      return 'tabler-ban'
    }
    case 'not_required': {
      return 'tabler-ban'
    }
    case 'not_requested': {
      return 'tabler-clock-hour-4'
    }
    case 'available': {
      return 'tabler-circle-check'
    }
    case 'active_delivery': {
      return 'tabler-navigation'
    }
    case 'heartbeat': {
      return 'tabler-activity-heartbeat'
    }
    case 'fresh': {
      return 'tabler-current-location'
    }
    case 'stale': {
      return 'tabler-clock-exclamation'
    }
    case 'stopped': {
      return 'tabler-player-stop'
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
    case 'busy': {
      return 'tabler-loader-2'
    }
    case 'on_the_way': {
      return 'tabler-route-alt-left'
    }
    case 'arrived': {
      return 'tabler-map-pin'
    }
    case 'offline': {
      return 'tabler-power'
    }
    case 'on_break': {
      return 'tabler-coffee'
    }
    case 'inactive': {
      return 'tabler-moon'
    }
    case 'sandbox': {
      return 'tabler-flask'
    }
    case 'production': {
      return 'tabler-rocket'
    }
    case 'mock': {
      return 'tabler-test-pipe'
    }
    case 'opening': {
      return 'tabler-book-upload'
    }
    case 'expense': {
      return 'tabler-receipt'
    }
    case 'replenishment': {
      return 'tabler-cash-banknote'
    }
    case 'transfer': {
      return 'tabler-arrows-transfer-up-down'
    }
    case 'count_variance': {
      return 'tabler-scale'
    }
    case 'output': {
      return 'tabler-arrow-up-right'
    }
    case 'input': {
      return 'tabler-arrow-down-left'
    }
    case 'collection': {
      return 'tabler-cash'
    }
    case 'application': {
      return 'tabler-file-invoice'
    }
    case 'refund': {
      return 'tabler-arrow-back-up'
    }
    case 'forfeiture': {
      return 'tabler-receipt-refund'
    }
    case 'reversal': {
      return 'tabler-arrow-back-up'
    }
    case 'posted': {
      return 'tabler-circle-check'
    }
    case 'reversed': {
      return 'tabler-arrow-back-up'
    }
    case 'delivery_cash_settlement': {
      return 'tabler-cash'
    }
    case 'order_delivery': {
      return 'tabler-truck-delivery'
    }
    case 'delivery_provider': {
      return 'tabler-building-store'
    }
    case 'daily_summary': {
      return 'tabler-calendar-stats'
    }
    case 'detailed': {
      return 'tabler-list-details'
    }
    case 'gross_payment': {
      return 'tabler-cash'
    }
    case 'allocated_amount': {
      return 'tabler-files'
    }
    case 'issued': {
      return 'tabler-certificate'
    }
    case 'remitted': {
      return 'tabler-building-bank'
    }
    case 'void': {
      return 'tabler-ban'
    }
    case 'quantity': {
      return 'tabler-packages'
    }
    case 'value': {
      return 'tabler-currency-dollar'
    }
    case 'custom': {
      return 'tabler-adjustments'
    }
    default: {
      return null
    }
  }
}

function getColor() {
  if (valueColor.value) {
    return valueColor.value
  }

  switch (valueId.value) {
    case 'tip': {
      return 'success'
    }
    case 'service_charge':
    case 'pool': {
      return 'info'
    }
    case 'equal_share':
    case 'direct': {
      return 'primary'
    }
    case 'weighted_points': {
      return 'warning'
    }
    case 'hybrid': {
      return 'secondary'
    }
    case 'in':
    case 'sale':
    case 'paid':
    case 'order':
    case 'volume':
    case 'original':
    case 'full_time':
    case 'created':
    case 'usage':
    case 'unlocked':
    case 'overtime_request_approved':
    case 'biometric':
    case 'cash':
    case 'check_in':
    case 'check_out':
    case 'present': {
      return 'success'
    }
    case 'opening':
    case 'collection': {
      return 'success'
    }
    case 'replenishment': {
      return 'success'
    }
    case 'output': {
      return 'success'
    }
    case 'input': {
      return 'info'
    }
    case 'expense': {
      return 'error'
    }
    case 'count_variance': {
      return 'warning'
    }
    case 'transfer': {
      return 'secondary'
    }
    case 'transfer_in': {
      return 'primary'
    }
    case 'moving_average': {
      return 'primary'
    }
    case 'fifo': {
      return 'info'
    }
    case 'standard': {
      return 'warning'
    }
    case 'quantity': {
      return 'primary'
    }
    case 'value': {
      return 'success'
    }
    case 'accepted': {
      return 'success'
    }
    case 'out':
    case 'return_supplier':
    case 'unpaid':
    case 'billing':
    case 'expire':
    case 'none':
    case 'credit_note':
    case 'return':
    case 'rejected':
    case 'overtime_request_rejected':
    case 'cancelled':
    case 'overtime_request_cancelled':
    case 'carryover':
    case 'qr':
    case 'rebuild':
    case 'locked':
    case 'void':
    case 'refund':
    case 'closed':
    case 'absent':
    case 'unapproved': {
      return 'error'
    }
    case 'forfeiture': {
      return 'error'
    }
    case 'waste': {
      return 'error'
    }
    case 'failed': {
      return 'error'
    }
    case 'exhausted': {
      return 'error'
    }
    case 'cancellation':
    case 'count':
    case 'spoil':
    case 'adjust_subtract':
    case 'partially_paid':
    case 'vip':
    case 'adjustment':
    case 'manual_adjust':
    case 'api':
    case 'updated':
    case 'adjusted':
    case 'break_start':
    case 'break_end':
    case 'nearest':
    case 'auto_closed':
    case 'deduction':
    case 'percentage':
    case 'pending':
    case 'closing_adjust':
    case 'provider_fallback':
    case 'provider_timeout_sweep':
    case 'at_risk':
    case 'quote_requested': {
      return 'warning'
    }
    case 'pay_in':
    case 'zatca':
    case 'adjust_add':
    case 'staff_meal':
    case 'sample':
    case 'capacity':
    case 'custom':
    case 'circle':
    case 'percent':
    case 'spooler':
    case 'part_time':
    case 'hour':
    case 'submitted':
    case 'accrual':
    case 'manual':
    case 'leave':
    case 'polygon':
    case 'fixed': {
      return 'info'
    }
    case 'assigned':
    case 'reassigned':
    case 'arrived':
    case 'quoted':
    case 'ready_for_pickup':
    case 'provider_accepted':
    case 'sla_sweep':
    case 'escalated': {
      return 'info'
    }
    case 'transfer_out': {
      return 'secondary'
    }
    case 'on_the_way':
    case 'picked_up':
    case 'dispatch_requested':
    case 'courier_assigned':
    case 'manual_rerun':
    case 'priority':
    case 'zone_based':
    case 'active': {
      return 'primary'
    }
    case 'global': {
      return 'primary'
    }
    case 'corporate':
    case 'zone_flat':
    case 'radius':
    case 'fixed_amount': {
      return 'secondary'
    }
    case 'recalculated':
    case 'calculated':
    case 'recalculate': {
      return '#e84393'
    }
    case 'adjust': {
      return 'info'
    }
    case 'pay_out': {
      return '#fd7e14'
    }
    case 'cash_drop': {
      return '#6f42c1'
    }
    case 'tip_in': {
      return '#20c997'
    }
    case 'tip_out': {
      return '#ffb300'
    }
    case 'correction': {
      return '#6c757d'
    }
    case 'takeaway': {
      return '#F7B731'
    }
    case 'dine_in': {
      return '#3867D6'
    }
    case 'pick_up': {
      return '#20BF6B'
    }
    case 'own_fleet': {
      return '#0F766E'
    }
    case 'external_courier': {
      return '#F2994A'
    }
    case 'pos_cashier': {
      return '#2D9CDB'
    }
    case 'driver_cod': {
      return '#20BF6B'
    }
    case 'online': {
      return '#3867D6'
    }
    case 'provider_cod': {
      return '#F2994A'
    }
    case 'primary':
    case 'approved':
    case 'regular':
    case 'web_browser_popup':
    case 'square':
    case 'simplified':
    case 'tcp':
    case 'monthly':
    case 'day':
    case 'mobile':
    case 'user':
    case 'mass':
    case 'holiday':
    case 'earning': {
      return 'primary'
    }
    case 'floor':
    case 'system':
    case 'off':
    case 'reviewed':
    case 'both':
    case 'auto_close':
    case 'rectangle':
    case 'usb_raw':
    case 'yearly':
    case 'reversal':
    case 'employer':
    case 'branch': {
      return 'secondary'
    }
    case 'ceiling': {
      return 'info'
    }
    case 'application': {
      return 'info'
    }
    case 'taxable_earning': {
      return 'success'
    }
    case 'taxable_earnings': {
      return 'success'
    }
    case 'non_taxable_earning': {
      return 'info'
    }
    case 'basic_salary': {
      return 'primary'
    }
    case 'gross_earnings': {
      return 'info'
    }
    case 'pre_tax_deduction': {
      return 'warning'
    }
    case 'post_tax_deduction': {
      return 'error'
    }
    case 'not_applicable': {
      return 'secondary'
    }
    case 'posted': {
      return 'success'
    }
    case 'reversed': {
      return 'warning'
    }
    case 'delivery_cash_settlement': {
      return 'success'
    }
    case 'order_delivery': {
      return 'primary'
    }
    case 'delivery_provider': {
      return 'secondary'
    }
    case 'daily_summary': {
      return 'primary'
    }
    case 'detailed': {
      return 'info'
    }
    case 'gross_payment': {
      return 'primary'
    }
    case 'allocated_amount': {
      return 'info'
    }
    case 'issued': {
      return 'info'
    }
    case 'remitted': {
      return 'success'
    }
    case 'cashier': {
      return '#2D9CDB'
    }
    case 'kitchen': {
      return '#F2994A'
    }
    case 'card': {
      return '#3867D6'
    }
    case 'earn': {
      return '#2ECC71'
    }
    case 'redeem': {
      return '#E74C3C'
    }
    case 'bonus': {
      return '#3498DB'
    }
    case 'penalty': {
      return '#E74C3C'
    }
    case 'per_delivery': {
      return '#2ECC71'
    }
    case 'available': {
      return 'success'
    }
    case 'active_delivery':
    case 'fresh': {
      return 'success'
    }
    case 'heartbeat': {
      return 'primary'
    }
    case 'stale':
    case 'upload_pending':
    case 'token_expired': {
      return 'warning'
    }
    case 'permission_needed':
    case 'permission_revoked':
    case 'app_terminated': {
      return 'error'
    }
    case 'busy': {
      return 'warning'
    }
    case 'on_track': {
      return 'success'
    }
    case 'breached':
    case 'provider_rejected': {
      return 'error'
    }
    case 'blocked': {
      return 'warning'
    }
    case 'paused':
    case 'skipped': {
      return 'secondary'
    }
    case 'success': {
      return 'success'
    }
    case 'offline': {
      return 'secondary'
    }
    case 'on_break': {
      return 'info'
    }
    case 'inactive': {
      return 'error'
    }
    case 'completed': {
      return 'success'
    }
    case 'returned': {
      return '#8B5CF6'
    }
    case 'sandbox': {
      return 'warning'
    }
    case 'production': {
      return 'success'
    }
    case 'mock': {
      return 'secondary'
    }
    default: {
      return 'default'
    }
  }
}
</script>

<template>
  <VChip
    v-if="valueName"
    :color="getColor()"
    size="small"
  >
    <VIcon
      v-if="getIcon()"
      :icon="getIcon()!"
      start
    />

    {{ valueName }}
  </VChip>

  <span v-else>{{ value ?? '-' }}</span>
</template>
