<script lang="ts" setup>
import { enumName, formatNumber, type UpcomingReservation } from '../types.ts'
import AnalyticsEmpty from './AnalyticsEmpty.vue'

defineProps<{ rows: UpcomingReservation[]; canShowReservation?: boolean }>()
const emit = defineEmits<{ show: [reservation: UpcomingReservation] }>()
function tables(reservation: UpcomingReservation) {
  return reservation.tables?.length ? reservation.tables.map((table) => table.name).join(', ') : '-'
}
</script>

<template>
  <AnalyticsEmpty
    v-if="rows.length === 0"
    icon="tabler-calendar-off"
    :message="$t('reservation::frontend.analytics.no_upcoming_reservations')"
  />
  <div
    v-else
    class="analytics-upcoming"
  >
    <VTable density="compact">
      <thead>
        <tr>
          <th>{{ $t('reservation::frontend.analytics.time') }}</th>
          <th>{{ $t('reservation::frontend.analytics.customer') }}</th>
          <th>{{ $t('reservation::frontend.analytics.reservation_number') }}</th>
          <th class="text-center">{{ $t('reservation::frontend.analytics.guests') }}</th>
          <th>{{ $t('reservation::frontend.analytics.tables') }}</th>
          <th>{{ $t('reservation::frontend.analytics.status') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="reservation in rows"
          :key="reservation.id"
          :class="{ clickable: canShowReservation }"
          @click="canShowReservation && emit('show', reservation)"
        >
          <td>
            <strong>{{ reservation.start_time }}</strong>
            <span>{{ reservation.reservation_date }}</span>
          </td>
          <td>{{ reservation.customer_name }}</td>
          <td>{{ reservation.reservation_number }}</td>
          <td class="text-center">{{ formatNumber(reservation.guest_count) }}</td>
          <td>{{ tables(reservation) }}</td>
          <td>
            <VChip
              :color="reservation.status?.color || 'secondary'"
              size="x-small"
              variant="tonal"
            >
              {{ enumName(reservation.status) }}
            </VChip>
          </td>
        </tr>
      </tbody>
    </VTable>
  </div>
</template>

<style lang="scss" scoped>
.analytics-upcoming {
  overflow-x: auto;
}
.analytics-upcoming :deep(.v-table) {
  min-inline-size: 720px;
}
.analytics-upcoming :deep(th) {
  height: 34px !important;
  color: rgba(var(--v-theme-on-surface), 0.56) !important;
  font-size: 0.66rem;
  font-weight: 750 !important;
  letter-spacing: 0.01em;
}
.analytics-upcoming :deep(td) {
  height: 43px !important;
  color: rgba(var(--v-theme-on-surface), 0.78);
  font-size: 0.73rem;
}
.analytics-upcoming td:first-child {
  display: grid;
  align-content: center;
}
.analytics-upcoming td:first-child strong {
  font-size: 0.74rem;
}
.analytics-upcoming td:first-child span {
  color: rgba(var(--v-theme-on-surface), 0.45);
  font-size: 0.62rem;
}
.analytics-upcoming tr.clickable {
  cursor: pointer;
}
.analytics-upcoming tr.clickable:hover {
  background: rgba(var(--v-theme-primary), 0.05);
}
</style>
