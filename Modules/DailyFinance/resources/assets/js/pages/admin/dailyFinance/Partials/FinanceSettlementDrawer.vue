<script lang="ts" setup>
import { computed, defineAsyncComponent, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/modules/auth/composables/auth'
import { usePaymentSettlements } from '@/modules/dailyfinance/composables/usePaymentSettlements'
import { useFinanceTabScroll } from '@/modules/dailyfinance/composables/useFinanceTabScroll'
import type {
  PaymentSettlement,
  SettlementStatusFilter,
} from '@/modules/dailyfinance/contracts/paymentSettlement'
import FinanceDrawer from './FinanceDrawer.vue'
import FinanceSettlementHistoryItem from './FinanceSettlementHistoryItem.vue'

const FinanceSettlementForm = defineAsyncComponent(() => import('./FinanceSettlementForm.vue'))
const FinanceSettlementDetails = defineAsyncComponent(
  () => import('./FinanceSettlementDetails.vue'),
)
const props = defineProps<{
  branchId: number
  branchName: string
  date: string
  initialStatus?: SettlementStatusFilter | null
}>()
const emit = defineEmits<{ close: []; changed: [] }>()
const { t } = useI18n()
const { can } = useAuth()
const { meta, history, status, method, loading, error, load } = usePaymentSettlements(
  () => props.branchId,
  props.initialStatus ?? null,
)
const creating = ref(false)
const refreshing = ref(false)
const selectedId = ref<number | null>(null)
const viewport = ref<HTMLElement | null>(null)
const controls = ref<HTMLElement | null>(null)
const { focusTab, scrollTabs } = useFinanceTabScroll(controls, status)
const statuses: (SettlementStatusFilter | null)[] = [
  null,
  'outstanding',
  'pending',
  'partial',
  'settled',
  'cancelled',
]
const filtered = computed(() => status.value !== null || method.value !== null)
const methods = computed(() => [
  { id: null, name: t('dailyfinance::payment_settlements.all_methods') },
  ...(meta.value?.methods ?? []),
])

watch(history, () => {
  void nextTick(() => {
    if (viewport.value) viewport.value.scrollTop = 0
  })
})

function resetFilters() {
  status.value = null
  method.value = null
}

function created(record: PaymentSettlement) {
  creating.value = false
  selectedId.value = record.id
  void load()
  emit('changed')
}

function changed() {
  emit('changed')
  void load(history.value?.pagination.current_page ?? 1)
}

async function refresh() {
  refreshing.value = true

  try {
    await load(history.value?.pagination.current_page ?? 1)
  } finally {
    refreshing.value = false
  }
}
</script>

<template>
  <FinanceDrawer
    class="settlement-drawer"
    :title="t('dailyfinance::payment_settlements.title')"
    compact
    :persistent="creating || selectedId !== null"
    @close="emit('close')"
  >
    <template #context>
      <p class="settlement-workspace__branch">
        <VIcon
          icon="tabler-building-store"
          size="15"
        />
        {{ branchName }}
      </p>
    </template>
    <template #header-end>
      <VBtn
        v-if="can('admin.daily_finance_settlements.create')"
        class="settlement-workspace__create"
        color="primary"
        prepend-icon="tabler-plus"
        :disabled="loading || !!error || !meta"
        @click="creating = true"
      >
        {{ t('dailyfinance::payment_settlements.new') }}
      </VBtn>
    </template>
    <section class="settlement-workspace">
      <div
        ref="controls"
        class="settlement-workspace__controls"
      >
        <VTabs
          v-model="status"
          class="settlement-workspace__tabs"
          color="secondary"
          slider-color="primary"
          density="compact"
          show-arrows
          :scroll-to-active="false"
          @focusin.capture="focusTab"
          @click.capture="scrollTabs"
        >
          <VTab
            v-for="value in statuses"
            :key="value ?? 'all'"
            :value="value"
            :disabled="loading && !meta"
          >
            {{
              t(
                value
                  ? 'dailyfinance::payment_settlements.tabs.' + value
                  : 'dailyfinance::payment_settlements.all',
              )
            }}
          </VTab>
        </VTabs>
        <div class="settlement-workspace__filters">
          <VSelect
            v-model="method"
            :items="methods"
            item-title="name"
            item-value="id"
            :label="t('dailyfinance::payment_settlements.method')"
            :disabled="!meta"
            hide-details
          />
          <VBtn
            icon="tabler-refresh"
            :aria-label="t('dailyfinance::payment_settlements.refresh')"
            variant="text"
            color="secondary"
            :disabled="loading"
            :loading="refreshing"
            @click="refresh"
          />
        </div>
      </div>
      <div
        ref="viewport"
        class="settlement-workspace__viewport"
        role="region"
        :aria-label="t('dailyfinance::payment_settlements.history')"
        :aria-busy="loading"
        tabindex="0"
      >
        <div
          v-if="!error && (loading || history?.data.length)"
          class="settlement-workspace__columns"
          aria-hidden="true"
        >
          <span>{{ t('dailyfinance::payment_settlements.settlement') }}</span>
          <span>{{ t('dailyfinance::payment_settlements.bank_account') }}</span>
          <div class="settlement-workspace__amount-columns">
            <span
              v-for="key in ['expected', 'actual', 'remaining'] as const"
              :key="key"
            >
              {{ t('dailyfinance::payment_settlements.columns.' + key) }}
            </span>
          </div>
        </div>
        <div
          v-if="error"
          class="settlement-workspace__empty"
          role="alert"
        >
          <VAvatar
            color="warning"
            variant="tonal"
            size="64"
          >
            <VIcon
              icon="tabler-cloud-off"
              size="30"
            />
          </VAvatar>
          <h3>{{ t('dailyfinance::payment_settlements.load_error') }}</h3>
          <p>{{ error }}</p>
          <VBtn
            color="primary"
            variant="tonal"
            prepend-icon="tabler-refresh"
            @click="changed"
          >
            {{ t('dailyfinance::payment_settlements.retry') }}
          </VBtn>
        </div>
        <div
          v-else-if="loading"
          class="settlement-workspace__list"
          role="status"
          :aria-label="t('dailyfinance::payment_settlements.loading')"
        >
          <FinanceSettlementHistoryItem
            v-for="n in 5"
            :key="n"
          />
        </div>
        <div
          v-else-if="!history?.data.length"
          class="settlement-workspace__empty"
        >
          <VAvatar
            color="secondary"
            variant="tonal"
            size="64"
          >
            <VIcon
              :icon="filtered ? 'tabler-filter-off' : 'tabler-building-bank'"
              size="30"
            />
          </VAvatar>
          <h3>
            {{ t('dailyfinance::payment_settlements.' + (filtered ? 'no_match' : 'empty')) }}
          </h3>
          <p>
            {{
              t('dailyfinance::payment_settlements.' + (filtered ? 'filter_hint' : 'empty_hint'))
            }}
          </p>
          <VBtn
            v-if="filtered"
            color="secondary"
            variant="tonal"
            prepend-icon="tabler-filter-off"
            @click="resetFilters"
          >
            {{ t('dailyfinance::payment_settlements.reset_filters') }}
          </VBtn>
          <VBtn
            v-else-if="can('admin.daily_finance_settlements.create')"
            color="primary"
            variant="tonal"
            :disabled="!meta"
            prepend-icon="tabler-plus"
            @click="creating = true"
          >
            {{ t('dailyfinance::payment_settlements.new') }}
          </VBtn>
        </div>
        <div
          v-else
          class="settlement-workspace__list"
        >
          <FinanceSettlementHistoryItem
            v-for="row in history?.data"
            :key="row.id"
            :item="row"
            @open="selectedId = $event"
          />
        </div>
      </div>
      <footer class="settlement-workspace__footer">
        <template v-if="history">
          <span>
            {{
              t('dailyfinance::payment_settlements.page', {
                current: history.pagination.current_page,
                total: history.pagination.last_page,
              })
            }}
          </span>
          <VPagination
            v-if="history.pagination.last_page > 1"
            :model-value="history.pagination.current_page"
            :length="history.pagination.last_page"
            :total-visible="3"
            density="compact"
            :disabled="loading"
            @update:model-value="load($event)"
          />
        </template>
        <span
          v-else-if="loading"
          class="settlement-workspace__loading-page"
          aria-hidden="true"
        />
      </footer>
    </section>
    <FinanceSettlementForm
      v-if="creating && meta"
      :branch-id="branchId"
      :branch-name="branchName"
      :date="date"
      :meta="meta"
      @close="creating = false"
      @saved="created"
    />
    <FinanceSettlementDetails
      v-if="selectedId"
      :id="selectedId"
      :branch-id="branchId"
      :branch-name="branchName"
      @close="selectedId = null"
      @changed="changed"
    />
  </FinanceDrawer>
</template>

<style lang="scss" scoped>
:global(.settlement-drawer .finance-drawer__body) {
  display: flex;
  flex-direction: column;
  min-block-size: 0;
  padding: 0 !important;
  overflow: hidden !important;
}

.settlement-workspace {
  --settlement-columns: minmax(145px, 1fr) minmax(140px, 1.05fr) minmax(260px, 1.8fr) 17px;
  display: flex;
  flex: 1;
  flex-direction: column;
  min-block-size: 0;
}

.settlement-workspace__branch {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 2px 0 0;
  font-size: 0.75rem;
  line-height: 1.5;
}

.settlement-workspace__controls {
  display: flex;
  flex: 0 0 auto;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 10px 24px;
}

.settlement-workspace__tabs {
  flex: 1;
  min-inline-size: 0;
  border-block-end: 0 !important;
  --v-tabs-height: 44px;
}

.settlement-workspace__tabs :deep(.v-slide-group__content) {
  gap: 8px;
}

.settlement-workspace__tabs :deep(.v-tab) {
  min-inline-size: auto;
  padding-inline: 10px;
  border-radius: 0;
  color: rgba(var(--v-theme-on-surface), 0.7);
  font-size: 0.8125rem;
  // Keep responsive padding out of the active tab's scroll calculation.
  transition-property: color, background-color, opacity, box-shadow;
}

.settlement-workspace__tabs :deep(.v-tab--selected) {
  color: rgb(var(--v-theme-secondary)) !important;
  font-weight: 600;
}

.settlement-workspace__tabs :deep(.v-tab__slider) {
  block-size: 2px !important;
}

.settlement-workspace__filters {
  display: flex;
  align-items: center;
  gap: 10px;
}

.settlement-workspace__filters .v-select {
  inline-size: 190px;
}

.settlement-workspace__filters > .v-btn {
  flex-shrink: 0;
  inline-size: 38px;
  block-size: 38px;
  border-radius: 6px;
}

.settlement-workspace__viewport {
  flex: 1;
  min-block-size: 0;
  border-block-start: 1px solid rgba(var(--v-theme-secondary), 0.1);
  overflow: auto;
  overscroll-behavior: contain;
  scrollbar-width: thin;
  scrollbar-color: rgba(var(--v-theme-secondary), 0.2) transparent;
}

.settlement-workspace__list {
  display: flex;
  flex-direction: column;
}

.settlement-workspace__columns {
  position: sticky;
  inset-block-start: 0;
  z-index: 1;
  display: grid;
  grid-template-columns: var(--settlement-columns);
  align-items: center;
  gap: 18px;
  min-block-size: 42px;
  padding: 10px 24px;
  border-block-end: 1px solid rgba(var(--v-theme-secondary), 0.09);
  background: rgb(var(--v-theme-background));
  color: rgba(var(--v-theme-on-surface), 0.7);
  font-size: 0.8125rem;
  font-weight: 500;
}

.settlement-workspace__amount-columns {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
  text-align: end;
}

.settlement-workspace__footer {
  display: flex;
  flex: 0 0 auto;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  min-block-size: 64px;
  padding: 10px 24px;
  border-block-start: 1px solid rgba(var(--v-theme-secondary), 0.1);
  color: rgba(var(--v-theme-on-surface), 0.7);
  font-size: 0.8125rem;
}

.settlement-workspace__loading-page {
  inline-size: 90px;
  block-size: 13px;
  border-radius: 4px;
  background: rgba(var(--v-theme-secondary), 0.07);
}

.settlement-workspace__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 14px;
  min-block-size: 100%;
  padding: 32px 24px;
  text-align: center;
}

.settlement-workspace__empty h3,
.settlement-workspace__empty p {
  margin: 0;
}

.settlement-workspace__empty h3 {
  font-size: 1.0625rem;
  color: rgb(var(--v-theme-secondary));
}

.settlement-workspace__empty p {
  max-inline-size: 340px;
  color: rgba(var(--v-theme-on-surface), 0.65);
  font-size: 0.8125rem;
  line-height: 1.6;
}

@media (max-width: 700px) {
  .settlement-workspace__controls {
    flex-wrap: wrap;
    gap: 12px;
    padding: 0 18px 14px;
  }

  .settlement-workspace__tabs {
    flex-basis: 100%;
  }

  .settlement-workspace__columns {
    display: none;
  }

  .settlement-workspace__filters {
    inline-size: 100%;
  }

  .settlement-workspace__filters .v-select {
    flex: 1;
    inline-size: auto;
    min-inline-size: 0;
  }

  .settlement-workspace__tabs :deep(.v-tab) {
    padding-inline: 9px;
  }

  .settlement-workspace__footer {
    padding-inline: 18px;
  }
}

@media (max-width: 600px) {
  .settlement-workspace__create {
    inline-size: 100%;
  }
}
</style>
