<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type {
  DashboardComparison,
  DashboardMoney,
} from '@/modules/dashboard/contracts/dashboard.ts'

type GrossProfitData = {
  gross_profit: DashboardMoney
  cost: DashboardMoney
  margin_percent: number
  comparison: DashboardComparison
}

type FulfillmentData = {
  average_service_minutes: number
  average_completion_minutes: number
  cancelled_or_refunded: number
  completion_rate: number
}

const props = defineProps<{
  data: GrossProfitData | FulfillmentData
  grossProfit: boolean
}>()

const { t, locale } = useI18n()

const gross = computed(() => props.data as GrossProfitData)
const fulfillment = computed(() => props.data as FulfillmentData)
const marginDegrees = computed(() => Math.min(100, Math.max(0, gross.value.margin_percent)) * 3.6)
</script>

<template>
  <div
    v-if="grossProfit"
    class="dashboard-profit"
  >
    <div class="dashboard-profit__hero">
      <span>{{ t('dashboard::dashboards.labels.gross_profit') }}</span>
      <strong>{{ gross.gross_profit.formatted }}</strong>
      <small
        v-if="gross.comparison.percent !== null"
        :class="gross.comparison.direction >= 0 ? 'text-success' : 'text-error'"
      >
        <VIcon
          :icon="gross.comparison.direction >= 0 ? 'tabler-trending-up' : 'tabler-trending-down'"
          size="15"
        />
        {{ Math.abs(gross.comparison.percent ?? 0) }}%
        {{ t('dashboard::dashboards.comparison.previous_period') }}
      </small>
    </div>
    <div
      :style="{ '--margin': `${marginDegrees}deg` }"
      class="dashboard-profit__ring"
    >
      <div>
        <strong>{{ gross.margin_percent }}%</strong>
        <span>{{ t('dashboard::dashboards.labels.margin') }}</span>
      </div>
    </div>
    <div class="dashboard-profit__cost">
      <span>{{ t('dashboard::dashboards.labels.food_cost') }}</span>
      <strong>{{ gross.cost.formatted }}</strong>
    </div>
  </div>

  <div
    v-else
    class="dashboard-health"
  >
    <div class="dashboard-health__meter">
      <span>{{ t('dashboard::dashboards.labels.completion_rate') }}</span>
      <strong>{{ fulfillment.completion_rate }}%</strong>
      <VProgressLinear
        :model-value="fulfillment.completion_rate"
        color="success"
        height="8"
        rounded
      />
    </div>
    <div class="dashboard-health__stats">
      <div>
        <VIcon
          color="primary"
          icon="tabler-clock-play"
        />
        <span>{{ t('dashboard::dashboards.labels.average_service_time') }}</span>
        <strong>
          {{
            t('dashboard::dashboards.labels.minutes', {
              value: new Intl.NumberFormat(locale).format(fulfillment.average_service_minutes),
            })
          }}
        </strong>
      </div>
      <div>
        <VIcon
          color="secondary"
          icon="tabler-circle-check"
        />
        <span>{{ t('dashboard::dashboards.labels.average_completion_time') }}</span>
        <strong>
          {{
            t('dashboard::dashboards.labels.minutes', {
              value: new Intl.NumberFormat(locale).format(fulfillment.average_completion_minutes),
            })
          }}
        </strong>
      </div>
      <div>
        <VIcon
          color="error"
          icon="tabler-alert-hexagon"
        />
        <span>{{ t('dashboard::dashboards.labels.cancelled_or_refunded') }}</span>
        <strong>
          {{ new Intl.NumberFormat(locale).format(fulfillment.cancelled_or_refunded) }}
        </strong>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.dashboard-profit {
  display: grid;
  min-block-size: 210px;
  grid-template-columns: 1fr 144px;
  align-items: center;
  gap: 18px;
}

.dashboard-profit__hero,
.dashboard-profit__cost {
  display: grid;
  gap: 5px;
}

.dashboard-profit__hero span,
.dashboard-profit__cost span,
.dashboard-profit__ring span {
  color: rgba(var(--v-theme-on-surface), 0.54);
  font-size: 0.72rem;
}

.dashboard-profit__hero strong {
  font-size: 1.7rem;
  font-weight: 800;
  letter-spacing: -0.04em;
}

.dashboard-profit__hero small {
  display: flex;
  align-items: center;
  gap: 4px;
}

.dashboard-profit__ring {
  display: grid;
  border-radius: 50%;
  background: conic-gradient(#16a34a var(--margin), rgba(22, 163, 74, 0.1) 0);
  block-size: 132px;
  inline-size: 132px;
  place-items: center;
}

.dashboard-profit__ring > div {
  display: grid;
  border-radius: 50%;
  background: rgb(var(--v-theme-surface));
  block-size: 98px;
  inline-size: 98px;
  place-content: center;
  text-align: center;
}

.dashboard-profit__ring strong {
  font-size: 1.3rem;
}

.dashboard-profit__cost {
  grid-column: 1 / -1;
  padding: 12px 14px;
  border-radius: 12px;
  background: rgba(var(--v-theme-on-surface), 0.035);
}

.dashboard-health {
  display: grid;
  min-block-size: 210px;
  align-content: center;
  gap: 24px;
}

.dashboard-health__meter {
  display: grid;
  grid-template-columns: 1fr auto;
  align-items: end;
  gap: 7px 16px;
}

.dashboard-health__meter span,
.dashboard-health__stats span {
  color: rgba(var(--v-theme-on-surface), 0.58);
  font-size: 0.74rem;
}

.dashboard-health__meter strong {
  font-size: 1.3rem;
}

.dashboard-health__meter :deep(.v-progress-linear) {
  grid-column: 1 / -1;
}

.dashboard-health__stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
}

.dashboard-health__stats > div {
  display: grid;
  gap: 6px;
  padding: 14px;
  border-radius: 13px;
  background: rgba(var(--v-theme-on-surface), 0.035);
}

@media (max-width: 600px) {
  .dashboard-health__stats {
    grid-template-columns: 1fr;
  }
}
</style>
