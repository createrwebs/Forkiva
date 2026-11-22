<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import type { DailyFinanceSummary } from '@/modules/dailyfinance/contracts/dailyFinance'
import FinanceHint from './FinanceHint.vue'

defineProps<{
  sessions: DailyFinanceSummary['sessions']
  movements: DailyFinanceSummary['movements']
}>()
const emit = defineEmits<{ movements: [] }>()
const { t } = useI18n()
</script>

<template>
  <section class="finance-panel finance-cash">
    <header class="finance-cash__header">
      <div class="finance-cash__heading">
        <h2>{{ t('dailyfinance::daily_finance.cash.title') }}</h2>
        <FinanceHint :text="t('dailyfinance::daily_finance.cash.hint')" />
      </div>
      <VBtn
        v-if="movements"
        variant="text"
        size="small"
        :aria-label="t('dailyfinance::daily_finance.actions.movements')"
        @click="emit('movements')"
      >
        {{ t('dailyfinance::daily_finance.actions.view_movements') }}
      </VBtn>
    </header>
    <template v-if="sessions">
      <div class="finance-cash__expected">
        <VAvatar
          color="primary"
          variant="tonal"
          rounded="lg"
          size="46"
        >
          <VIcon
            icon="tabler-cash"
            size="26"
          />
        </VAvatar>
        <div>
          <span>{{ t('dailyfinance::daily_finance.cash.expected') }}</span>
          <strong>
            <bdi>
              {{
                sessions.expected?.formatted ?? t('dailyfinance::daily_finance.cash.unavailable')
              }}
            </bdi>
          </strong>
        </div>
      </div>
      <dl class="finance-cash__count">
        <div>
          <dt>{{ t('dailyfinance::daily_finance.cash.counted') }}</dt>
          <dd>
            <bdi>
              {{
                sessions.counted?.formatted ??
                t(
                  `dailyfinance::daily_finance.cash.${sessions.open_count > 0 ? 'not_counted' : 'unavailable'}`,
                )
              }}
            </bdi>
          </dd>
        </div>
        <div>
          <dt>{{ t('dailyfinance::daily_finance.cash.variance') }}</dt>
          <dd :class="{ 'text-error': sessions.variance && sessions.variance.amount !== 0 }">
            <bdi>{{ sessions.variance?.formatted ?? '—' }}</bdi>
          </dd>
        </div>
      </dl>
    </template>
    <p
      v-else
      class="text-medium-emphasis text-body-2 my-5"
    >
      {{ t('dailyfinance::daily_finance.restricted') }}
    </p>
    <div class="finance-cash__movements">
      <div class="finance-cash__heading">
        <h3>{{ t('dailyfinance::daily_finance.movements.title') }}</h3>
        <FinanceHint :text="t('dailyfinance::daily_finance.movements.hint')" />
      </div>
      <template v-if="movements">
        <dl>
          <div
            v-for="(key, index) in ['cash_in', 'cash_out'] as const"
            :key="key"
            class="finance-cash__movement"
          >
            <dt>
              <VAvatar
                :color="index === 0 ? 'success' : 'error'"
                variant="tonal"
                size="30"
              >
                <VIcon
                  :icon="index === 0 ? 'tabler-arrow-down-left' : 'tabler-arrow-up-right'"
                  size="18"
                />
              </VAvatar>
              {{ t(`dailyfinance::daily_finance.movements.${key}`) }}
            </dt>
            <dd>
              <bdi>{{ movements[key].formatted }}</bdi>
            </dd>
          </div>
        </dl>
      </template>
      <p
        v-else
        class="text-medium-emphasis text-body-2 mt-3"
      >
        {{ t('dailyfinance::daily_finance.restricted') }}
      </p>
    </div>
  </section>
</template>

<style lang="scss" scoped>
.finance-cash {
  padding: 20px;
}

.finance-cash__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.finance-cash__header > .v-btn {
  margin-inline-end: -10px;
}

.finance-cash__heading {
  display: flex;
  align-items: center;
  gap: 6px;
}

.finance-cash__heading h3 {
  font-size: 0.9rem;
  font-weight: 600;
}

.finance-cash__expected {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 12px;
  margin-block: 12px;
  background: rgba(var(--v-theme-primary), 0.045);
  border: 1px solid rgba(var(--v-theme-primary), 0.16);
  border-radius: 10px;
}

.finance-cash__expected > div {
  min-inline-size: 0;
}

.finance-cash__expected span {
  font-size: 0.8rem;
}

.finance-cash__expected strong {
  display: block;
  font-size: clamp(1.5rem, 2vw, 2rem);
  font-weight: 750;
  overflow-wrap: anywhere;
  font-variant-numeric: tabular-nums;
}

.finance-cash__count {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  padding-block-end: 16px;
}

.finance-cash__count > div + div {
  border-inline-start: 1px solid rgba(var(--v-border-color), 0.1);
  padding-inline-start: 16px;
}

.finance-cash__count dt {
  font-size: 0.75rem;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

.finance-cash__count dd {
  margin: 6px 0 0;
  font-size: 0.9rem;
  font-weight: 600;
  overflow-wrap: anywhere;
}

.finance-cash__movements {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.1);
  padding-block-start: 12px;
}

.finance-cash__movement {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding-block: 8px;
  border-block-end: 1px solid rgba(var(--v-border-color), 0.08);
}

.finance-cash__movement dt {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 0.82rem;
}

.finance-cash__movement dd {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}
</style>
