<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { PosSessionDetail } from '@/modules/pos/contracts/sessionReconciliation'

const props = defineProps<{ item: PosSessionDetail; reviewMode?: boolean }>()
const { t } = useI18n()
const reconciliationState = computed(() => {
  const item = props.item
  if (
    item.status.id !== 'closed' ||
    !item.cash_reconciliation ||
    !item.cash_over_short ||
    !item.declared_cash
  )
    return null
  return item.cash_over_short.amount === 0
    ? 'balanced'
    : item.cash_over_short.amount < 0
      ? 'shortage'
      : 'surplus'
})
const rows = computed(() => {
  const item = props.item
  const rows = [{ key: 'opening_float', value: item.opening_float, strong: false }]
  if (item.status.id !== 'closed' && !props.reviewMode) return rows
  if (item.cash_reconciliation) {
    rows.push(
      { key: 'cash_in', value: item.cash_reconciliation.cash_in, strong: false },
      { key: 'cash_out', value: item.cash_reconciliation.cash_out, strong: false },
      { key: 'expected_cash', value: item.cash_reconciliation.expected_cash, strong: true },
    )
  } else {
    rows.push({ key: 'cash_collections', value: item.system_cash_sales ?? null, strong: false })
  }
  if (!props.reviewMode || item.declared_cash) {
    rows.push({ key: 'actual_cash', value: item.declared_cash, strong: true })
  }
  return rows
})
</script>

<template>
  <VCard
    :data-reconciliation="reconciliationState"
    class="cash-summary"
    variant="flat"
  >
    <div class="cash-heading">
      <span
        aria-hidden="true"
        class="cash-heading-icon"
      >
        <VIcon
          icon="tabler-calculator"
          size="24"
        />
      </span>
      <div>
        <h2>{{ t('pos::pos_sessions.show.cash_reconciliation_title') }}</h2>
        <p
          v-if="item.cash_reconciliation"
          class="text-body-2 text-medium-emphasis mb-0"
        >
          {{ t('pos::pos_sessions.show.cash_formula') }}
        </p>
      </div>
    </div>
    <p
      v-if="item.status.id === 'closed' && !item.cash_reconciliation"
      class="text-body-2 text-medium-emphasis mt-4 mb-0"
    >
      {{ t('pos::pos_sessions.show.legacy_cash_summary') }}
    </p>
    <dl class="cash-rows">
      <div
        v-for="row in rows"
        :key="row.key"
        :class="[
          'cash-row',
          {
            'cash-row--strong': row.strong,
            'cash-row--actual': row.key === 'actual_cash',
            'cash-row--balanced': row.key === 'actual_cash' && reconciliationState === 'balanced',
            'cash-row--variance':
              row.key === 'actual_cash' &&
              reconciliationState &&
              reconciliationState !== 'balanced',
          },
        ]"
      >
        <dt>
          <VIcon
            v-if="row.key === 'actual_cash' && reconciliationState"
            :icon="
              reconciliationState === 'balanced'
                ? 'tabler-circle-check-filled'
                : 'tabler-alert-circle'
            "
            aria-hidden="true"
            size="24"
          />
          {{ t(`pos::pos_sessions.show.${row.key}`) }}
        </dt>
        <dd>
          <bdi>{{ row.value?.formatted ?? t('pos::pos_sessions.show.not_available') }}</bdi>
        </dd>
      </div>
    </dl>
    <div
      v-if="reconciliationState"
      :class="['cash-result', { 'cash-result--variance': reconciliationState !== 'balanced' }]"
    >
      <VIcon
        :icon="
          reconciliationState === 'balanced' ? 'tabler-circle-check-filled' : 'tabler-alert-circle'
        "
        aria-hidden="true"
        size="30"
      />
      <div>
        <strong>
          {{
            t(
              `pos::pos_sessions.show.${reconciliationState === 'balanced' ? 'cash_balanced' : reconciliationState}`,
            )
          }}
        </strong>
        <p>
          {{
            t(
              `pos::pos_sessions.show.${reconciliationState === 'balanced' ? 'cash_balanced_description' : 'cash_variance_description'}`,
            )
          }}
        </p>
      </div>
    </div>
  </VCard>
</template>

<style lang="scss" scoped>
.cash-summary {
  padding: 20px;
  border: 1px solid var(--session-line, rgba(var(--v-border-color), var(--v-border-opacity)));
  border-radius: 12px;
  box-shadow: var(--session-shadow);
}
.cash-heading {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}
.cash-heading-icon {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  inline-size: 50px;
  block-size: 50px;
  border-radius: 10px;
  color: rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.1);
}
.cash-heading h2 {
  font-size: 1.25rem;
  font-weight: 700;
  line-height: 1.4;
  margin-block-end: 3px;
  color: rgb(var(--v-theme-on-surface));
}
.cash-heading p {
  font-size: 0.875rem !important;
  line-height: 1.5;
}
.cash-rows {
  margin-block-start: 22px;
}
.cash-row {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 12px;
  padding: 12px;
  border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
.cash-row:last-child:not(.cash-row--strong) {
  border: 0;
  padding-block-end: 0;
}
.cash-row dt {
  display: flex;
  align-items: center;
  gap: 10px;
  color: rgba(var(--v-theme-on-surface), 0.8);
  font-size: 0.9375rem;
}
.cash-row dt :deep(.v-icon) {
  flex-shrink: 0;
}
.cash-row dd {
  margin: 0;
  text-align: end;
  flex-shrink: 0;
  font-variant-numeric: tabular-nums;
  font-size: 1rem;
  color: rgb(var(--v-theme-on-surface));
}
.cash-row--strong {
  min-block-size: 54px;
  padding: 14px 18px;
  margin-block-start: 12px;
  border: 0;
  border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.035);
  font-weight: 600;
}
.cash-row--strong dt {
  color: inherit;
}
.cash-row--balanced {
  color: rgb(var(--session-positive, var(--v-theme-success)));
  border: 1px solid rgba(var(--session-positive, var(--v-theme-success)), 0.14);
  background: rgba(var(--session-positive, var(--v-theme-success)), 0.08);
}
.cash-row--balanced dd {
  color: inherit;
}
.cash-row--variance {
  color: rgb(var(--v-theme-on-surface));
  border: 1px solid rgba(var(--v-theme-warning), 0.2);
  background: rgba(var(--v-theme-warning), 0.08);
}
.cash-row--variance :deep(.v-icon) {
  color: rgb(var(--v-theme-warning));
}
.cash-result {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  padding: 20px;
  margin-block-start: 16px;
  border-radius: 12px;
  color: rgb(var(--session-positive, var(--v-theme-success)));
  background: rgba(var(--session-positive, var(--v-theme-success)), 0.075);
}
.cash-result > :deep(.v-icon) {
  flex-shrink: 0;
  margin-block-start: 2px;
}
.cash-result strong {
  font-size: 1rem;
  line-height: 1.5;
}
.cash-result p {
  margin: 4px 0 0;
  font-size: 0.8125rem;
  line-height: 1.55;
}
.cash-result--variance {
  color: rgb(var(--v-theme-on-surface));
  background: rgba(var(--v-theme-warning), 0.08);
}
.cash-result--variance > :deep(.v-icon) {
  color: rgb(var(--v-theme-warning));
}
@media (max-width: 800px) {
  .cash-summary {
    padding: 18px;
  }
  .cash-row {
    gap: 12px;
    padding-inline: 8px;
  }
}
</style>
