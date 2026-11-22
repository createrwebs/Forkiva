<script lang="ts" setup>
import { computed } from 'vue'
import { formatNumber, type TableUtilizationRow } from '../types.ts'
import AnalyticsEmpty from './AnalyticsEmpty.vue'

const props = defineProps<{ rows: TableUtilizationRow[] }>()
const maximum = computed(() =>
  Math.max(...props.rows.map((row) => Number(row.reservations_count || 0)), 1),
)
</script>

<template>
  <AnalyticsEmpty
    v-if="rows.length === 0"
    icon="tabler-armchair-off"
  />
  <div
    v-else
    class="analytics-tables"
  >
    <div
      v-for="(row, index) in rows"
      :key="row.table_id"
      class="analytics-tables__row"
    >
      <span
        class="analytics-tables__rank"
        :class="{ featured: index < 3 }"
      >
        {{ index + 1 }}
      </span>
      <span class="analytics-tables__icon">
        <VIcon
          icon="tabler-armchair-2"
          size="18"
        />
      </span>
      <div class="analytics-tables__identity">
        <strong class="text-truncate">{{ row.table_name }}</strong>
        <div class="analytics-tables__bar">
          <i :style="{ width: `${(Number(row.reservations_count || 0) / maximum) * 100}%` }" />
        </div>
      </div>
      <div class="analytics-tables__value">
        <strong>{{ formatNumber(row.reservations_count) }}</strong>
        <span>{{ $t('reservation::frontend.analytics.reservations') }}</span>
      </div>
      <div class="analytics-tables__value">
        <strong>{{ formatNumber(row.guests_count) }}</strong>
        <span>{{ $t('reservation::frontend.analytics.guests') }}</span>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.analytics-tables {
  display: grid;
}
.analytics-tables__row {
  display: grid;
  min-block-size: 52px;
  grid-template-columns: 24px 36px minmax(90px, 1fr) 76px 68px;
  align-items: center;
  gap: 9px;
  border-block-end: 1px solid rgba(var(--v-border-color), 0.08);
}
.analytics-tables__row:last-child {
  border-block-end: 0;
}
.analytics-tables__rank {
  color: rgba(var(--v-theme-on-surface), 0.4);
  font-size: 0.74rem;
  font-weight: 700;
  text-align: center;
}
.analytics-tables__rank.featured {
  color: rgb(var(--v-theme-primary));
}
.analytics-tables__icon {
  display: grid;
  border-radius: 10px;
  background: rgba(var(--v-theme-warning), 0.09);
  block-size: 34px;
  color: rgb(var(--v-theme-warning));
  inline-size: 34px;
  place-items: center;
}
.analytics-tables__identity {
  display: grid;
  min-inline-size: 0;
  gap: 6px;
  font-size: 0.79rem;
}
.analytics-tables__bar {
  overflow: hidden;
  border-radius: 999px;
  background: rgba(var(--v-theme-on-surface), 0.07);
  block-size: 4px;
}
.analytics-tables__bar i {
  display: block;
  border-radius: inherit;
  background: #0f766e;
  block-size: 100%;
}
.analytics-tables__value {
  display: grid;
  justify-items: end;
}
.analytics-tables__value strong {
  font-size: 0.78rem;
}
.analytics-tables__value span {
  color: rgba(var(--v-theme-on-surface), 0.48);
  font-size: 0.62rem;
}
@media (max-width: 500px) {
  .analytics-tables__row {
    grid-template-columns: 20px 34px minmax(80px, 1fr) 58px;
  }
  .analytics-tables__value:last-child {
    display: none;
  }
}
</style>
