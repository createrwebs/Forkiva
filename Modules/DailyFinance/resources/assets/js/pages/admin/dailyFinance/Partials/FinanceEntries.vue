<script lang="ts" setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDailyFinanceEntries } from '@/modules/dailyfinance/composables/useDailyFinanceEntries'
import type {
  DailyFinanceEntryKind,
  DailyFinanceSummary,
} from '@/modules/dailyfinance/contracts/dailyFinance'
import FinanceDrawer from './FinanceDrawer.vue'
import FinanceSales from './FinanceSales.vue'
import FinanceReceipts from './FinanceReceipts.vue'
import FinanceEntriesLoading from './FinanceEntriesLoading.vue'
import FinanceMovements from './FinanceMovements.vue'

const props = defineProps<{ kind: DailyFinanceEntryKind; summary: DailyFinanceSummary }>()
const emit = defineEmits<{ close: []; kind: [value: DailyFinanceEntryKind] }>()
const { t } = useI18n()
const { result, loading, error, load } = useDailyFinanceEntries()
const page = ref(1)
const title = computed(() => t(`dailyfinance::daily_finance.entries.${props.kind}`))
const context = computed(() =>
  [
    props.summary.branches.find((branch) => branch.id === props.summary.meta.branch_id)?.name,
    props.summary.meta.date,
  ]
    .filter(Boolean)
    .join(' · '),
)
const payment = computed(() => ['collections', 'refunds'].includes(props.kind))
const cashSides = [
  { amount: 'cash_in', icon: 'tabler-arrow-down-left', parts: ['pay_ins'] },
  { amount: 'cash_out', icon: 'tabler-arrow-up-right', parts: ['payouts', 'cash_drops'] },
] as const
const total = computed(() => {
  if (props.kind === 'sales') return props.summary.sales?.total
  if (props.kind === 'dues') return props.summary.sales?.dues
  if (props.kind === 'collections' || props.kind === 'refunds')
    return props.summary.payments?.[props.kind]
  return null
})

function reload() {
  const branch = props.summary.meta.branch_id
  if (branch !== null)
    return load({
      branch_id: branch,
      date: props.summary.meta.date,
      kind: props.kind,
      page: page.value,
    })
}

watch(
  () => [props.kind, props.summary.meta.branch_id, props.summary.meta.date],
  () => {
    if (page.value === 1) reload()
    else page.value = 1
  },
  { immediate: true },
)
watch(page, reload)
</script>

<template>
  <FinanceDrawer
    :title="title"
    :context="context"
    compact
    @close="emit('close')"
  >
    <template
      v-if="total"
      #header-end
    >
      <div class="finance-header-total">
        <span>
          {{
            t(
              kind === 'sales'
                ? 'dailyfinance::daily_finance.sales.total'
                : kind === 'dues'
                  ? 'dailyfinance::daily_finance.dues.total'
                  : `dailyfinance::daily_finance.receipts.total_${kind}`,
            )
          }}
        </span>
        <strong>
          <bdi>{{ total.formatted }}</bdi>
        </strong>
      </div>
    </template>
    <dl
      v-if="kind === 'movements' && summary.movements"
      class="finance-entry-movement-totals"
    >
      <div
        v-for="side in cashSides"
        :key="side.amount"
        class="finance-movement-total"
        :class="`finance-movement-total--${side.amount}`"
      >
        <dt>
          <VIcon
            :icon="side.icon"
            size="18"
          />
          {{ t(`dailyfinance::daily_finance.movements.${side.amount}`) }}
        </dt>
        <dd>
          <bdi>{{ summary.movements[side.amount].formatted }}</bdi>
        </dd>
        <dd class="finance-movement-total__parts">
          <span
            v-for="key in side.parts"
            :key="key"
          >
            {{ t(`dailyfinance::daily_finance.movements.short_${key}`) }}
            <bdi>{{ summary.movements[key].formatted }}</bdi>
          </span>
        </dd>
      </div>
    </dl>
    <VTabs
      v-if="payment"
      :model-value="kind"
      class="finance-receipt-tabs"
      density="compact"
      @update:model-value="emit('kind', $event as DailyFinanceEntryKind)"
    >
      <VTab
        value="collections"
        prepend-icon="tabler-arrow-down-left"
      >
        {{ t('dailyfinance::daily_finance.metrics.collections') }}
      </VTab>
      <VTab
        value="refunds"
        prepend-icon="tabler-arrow-up-right"
      >
        {{ t('dailyfinance::daily_finance.metrics.refunds') }}
      </VTab>
    </VTabs>
    <VAlert
      v-if="error"
      type="error"
      variant="tonal"
      role="alert"
    >
      {{
        t(
          error === 403
            ? 'dailyfinance::daily_finance.errors.forbidden'
            : 'dailyfinance::daily_finance.entries.error',
        )
      }}
      <VBtn
        variant="text"
        @click="reload"
      >
        {{ t('dailyfinance::daily_finance.actions.retry') }}
      </VBtn>
    </VAlert>
    <FinanceEntriesLoading
      v-else-if="loading"
      :sales="kind === 'sales' || kind === 'dues'"
      :outstanding="kind === 'dues'"
    />
    <template v-else-if="result">
      <FinanceSales
        v-if="kind === 'sales' || kind === 'dues'"
        :entries="result.data"
        :outstanding="kind === 'dues'"
      />
      <FinanceReceipts
        v-else-if="kind === 'collections' || kind === 'refunds'"
        :entries="result.data"
        :kind="kind"
      />
      <FinanceMovements
        v-else-if="kind === 'movements'"
        :entries="result.data"
      />
      <div class="finance-entry-pagination">
        <span>
          {{
            t(`dailyfinance::daily_finance.${kind === 'dues' ? 'dues' : 'entries'}.count`, {
              count: result.pagination.total,
            })
          }}
        </span>
        <VPagination
          v-if="result.pagination.last_page > 1"
          v-model="page"
          :length="result.pagination.last_page"
          :total-visible="3"
          density="comfortable"
        />
      </div>
    </template>
  </FinanceDrawer>
</template>

<style lang="scss" scoped>
.finance-receipt-tabs {
  margin-block: 6px 18px;
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.1);
}

.finance-header-total {
  flex-shrink: 0;
  text-align: end;
}

.finance-header-total span {
  display: block;
  font-size: 0.7rem;
  line-height: 1.4;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

.finance-header-total strong {
  display: block;
  font-size: 1.3rem;
  line-height: 1.5;
  color: rgb(var(--v-theme-primary));
  font-variant-numeric: tabular-nums;
}

.finance-entry-movement-totals {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  margin-block: 6px 18px;
}

.finance-movement-total {
  --movement-tone: var(--v-theme-success);
  padding: 16px;
  border: 1px solid rgba(var(--movement-tone), 0.15);
  border-radius: 12px;
  background: rgba(var(--movement-tone), 0.04);
}

.finance-movement-total--cash_out {
  --movement-tone: var(--v-theme-error);
}

.finance-movement-total dt {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.75rem;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

.finance-movement-total dt .v-icon {
  color: rgb(var(--movement-tone));
}

.finance-movement-total dd {
  margin: 8px 0 0;
  font-weight: 700;
  font-size: 1.35rem;
  font-variant-numeric: tabular-nums;
  overflow-wrap: anywhere;
}

.finance-movement-total .finance-movement-total__parts {
  display: flex;
  flex-wrap: wrap;
  gap: 6px 12px;
  margin-block-start: 10px;
  font-size: 0.7rem;
  font-weight: 400;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

.finance-movement-total__parts bdi {
  margin-inline-start: 4px;
  font-weight: 600;
  overflow-wrap: anywhere;
}

.finance-entry-pagination {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding-block-start: 20px;
  font-size: 0.8rem;
}
</style>
