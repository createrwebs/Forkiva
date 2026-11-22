<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { PosSessionDetail } from '@/modules/pos/contracts/sessionReconciliation'

const props = withDefaults(defineProps<{ item: PosSessionDetail; overview?: boolean }>(), {
  overview: true,
})
const { t } = useI18n()
const closed = computed(() => props.item.status.id === 'closed')
const reconciliationState = computed(() => {
  const item = props.item
  if (!closed.value || !item.cash_reconciliation || !item.declared_cash || !item.cash_over_short)
    return null

  return item.cash_over_short.amount === 0
    ? 'balanced'
    : item.cash_over_short.amount < 0
      ? 'shortage'
      : 'surplus'
})
const rows = computed(() => {
  const item = props.item
  const rows = [{ key: 'opening_float', value: item.opening_float, icon: 'tabler-wallet' }]
  if (item.cash_reconciliation) {
    rows.push(
      { key: 'cash_in', value: item.cash_reconciliation.cash_in, icon: 'tabler-arrow-down-left' },
      { key: 'cash_out', value: item.cash_reconciliation.cash_out, icon: 'tabler-arrow-up-right' },
    )
  } else {
    rows.push({
      key: 'cash_collections',
      value: item.system_cash_sales ?? null,
      icon: 'tabler-cash',
    })
  }

  return rows
})
</script>

<template>
  <section
    class="finance-session-cash"
    :data-reconciliation="reconciliationState"
  >
    <section
      v-if="closed"
      class="finance-session-cash__position"
    >
      <dl class="finance-session-cash__expected">
        <dt>{{ t('dailyfinance::daily_finance.cash.expected') }}</dt>
        <dd>
          <bdi>
            {{
              item.cash_reconciliation?.expected_cash.formatted ??
              t('pos::pos_sessions.show.not_available')
            }}
          </bdi>
        </dd>
      </dl>
      <dl class="finance-session-cash__settlement">
        <div>
          <dt>{{ t('dailyfinance::daily_finance.cash.counted') }}</dt>
          <dd>
            <bdi>
              {{ item.declared_cash?.formatted ?? t('pos::pos_sessions.show.not_available') }}
            </bdi>
          </dd>
        </div>
        <div>
          <dt>{{ t('dailyfinance::daily_finance.cash.variance') }}</dt>
          <dd
            v-if="reconciliationState"
            class="finance-session-cash__result"
          >
            <bdi>{{ item.cash_over_short?.formatted }}</bdi>
            <span class="finance-session-cash__state">
              {{ t(`pos::pos_sessions.show.${reconciliationState}`) }}
            </span>
          </dd>
          <dd v-else>
            <bdi>
              {{ item.cash_over_short?.formatted ?? t('pos::pos_sessions.show.not_available') }}
            </bdi>
          </dd>
        </div>
      </dl>
    </section>
    <section
      v-else
      class="finance-session-cash__opening"
    >
      <dl class="finance-session-cash__expected">
        <dt>{{ t('pos::pos_sessions.show.opening_float') }}</dt>
        <dd>
          <bdi>
            {{ item.opening_float?.formatted ?? t('pos::pos_sessions.show.not_available') }}
          </bdi>
        </dd>
      </dl>
      <div class="finance-session-cash__pending">
        <VIcon
          icon="tabler-clock"
          size="22"
          aria-hidden="true"
        />
        <p>{{ t('pos::pos_sessions.show.open_summary') }}</p>
      </div>
    </section>
    <slot />
    <template v-if="closed && overview !== false">
      <p
        v-if="!item.cash_reconciliation"
        class="finance-session-cash__legacy"
      >
        {{ t('pos::pos_sessions.show.legacy_cash_summary') }}
      </p>
      <dl class="finance-session-cash__ledger">
        <div
          v-for="row in rows"
          :key="row.key"
        >
          <VIcon
            :icon="row.icon"
            size="22"
            aria-hidden="true"
          />
          <dt>{{ t(`pos::pos_sessions.show.${row.key}`) }}</dt>
          <dd>
            <bdi>{{ row.value?.formatted ?? t('pos::pos_sessions.show.not_available') }}</bdi>
          </dd>
        </div>
      </dl>
    </template>
  </section>
</template>

<style lang="scss" scoped>
.finance-session-cash__position,
.finance-session-cash__opening {
  overflow: hidden;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  border-radius: 12px;
}

.finance-session-cash dt {
  font-size: 0.8125rem;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

.finance-session-cash dd {
  margin: 0;
  font-variant-numeric: tabular-nums;
  font-weight: 600;
  overflow-wrap: anywhere;
}

.finance-session-cash__expected {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 22px 20px;
  background: rgb(var(--v-theme-secondary));
  color: rgb(var(--v-theme-on-secondary));
}

.finance-session-cash__expected dt {
  color: inherit;
  font-weight: 500;
}

.finance-session-cash__expected dd {
  font-size: 1.75rem;
  font-weight: 700;
  line-height: 1.3;
  text-align: end;
}

.finance-session-cash__settlement {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 20px;
  padding: 20px;
}

.finance-session-cash__settlement > div + div {
  padding-inline-start: 20px;
  border-inline-start: 1px solid rgba(var(--v-theme-on-surface), 0.1);
}

.finance-session-cash__settlement dd {
  margin-block-start: 6px;
  font-size: 1.375rem;
  line-height: 1.4;
}

.finance-session-cash__result {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 4px;
}

.finance-session-cash__state {
  font-size: 0.75rem;
  font-weight: 400;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

.finance-session-cash__ledger > div {
  display: grid;
  grid-template-columns: 22px minmax(0, 1fr) auto;
  align-items: center;
  gap: 16px;
  padding: 18px 4px;
}

.finance-session-cash__ledger > div + div {
  border-block-start: 1px solid rgba(var(--v-theme-on-surface), 0.1);
}

.finance-session-cash__ledger dd {
  font-size: 1rem;
  text-align: end;
}

.finance-session-cash__ledger :deep(.v-icon) {
  color: rgba(var(--v-theme-on-surface), 0.65);
}

.finance-session-cash__pending {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 20px;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

.finance-session-cash__pending :deep(.v-icon) {
  flex-shrink: 0;
}

.finance-session-cash__pending p,
.finance-session-cash__legacy {
  margin: 0;
  font-size: 0.8125rem;
  line-height: 1.6;
}

.finance-session-cash__legacy {
  margin-block: 16px;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

@media (max-width: 600px) {
  .finance-session-cash__expected {
    padding: 20px 16px;
    flex-wrap: wrap;
    gap: 8px 16px;
  }

  .finance-session-cash__expected dd {
    margin-inline-start: auto;
    font-size: 1.5rem;
  }

  .finance-session-cash__settlement {
    padding: 16px;
    gap: 14px;
  }

  .finance-session-cash__settlement > div + div {
    padding-inline-start: 14px;
  }

  .finance-session-cash__settlement dd {
    font-size: 1.125rem;
  }

  .finance-session-cash__ledger > div {
    gap: 12px;
  }
}
</style>
