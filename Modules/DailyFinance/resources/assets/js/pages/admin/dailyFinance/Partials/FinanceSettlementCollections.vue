<script lang="ts" setup>
import { nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { useSettlementCollections } from '@/modules/dailyfinance/composables/useSettlementCollections'
import type { SettlementMeta } from '@/modules/dailyfinance/contracts/paymentSettlement'
import FinanceSettlementDateFilter from './FinanceSettlementDateFilter.vue'

const props = defineProps<{
  state: ReturnType<typeof useSettlementCollections>
  meta: SettlementMeta
  busy: boolean
}>()
const { t } = useI18n()
const {
  method,
  from,
  to,
  search,
  sources,
  selected,
  selectedOnly,
  visibleSources,
  pageSelected,
  pageIndeterminate,
  loading,
  loadError,
  loadSources,
  applyFilters,
  toggle,
  selectPage,
  clearSelection,
} = props.state
const viewport = ref<HTMLElement | null>(null)
function changeDates(range: { from: string; to: string }) {
  from.value = range.from
  to.value = range.to
}
watch([loading, selectedOnly], () => {
  void nextTick(() => {
    if (viewport.value) viewport.value.scrollTop = 0
  })
})
</script>

<template>
  <section
    class="settlement-collections"
    :aria-label="t('dailyfinance::payment_settlements.collections')"
  >
    <div class="settlement-collections__filters">
      <VSelect
        v-model="method"
        :items="meta.methods"
        item-title="name"
        item-value="id"
        :label="t('dailyfinance::payment_settlements.method')"
        :prepend-inner-icon="meta.methods.find((item) => item.id === method)?.icon"
        :disabled="busy"
        hide-details
      />
      <FinanceSettlementDateFilter
        :from="from"
        :to="to"
        :today="meta.today"
        :disabled="busy"
        @change="changeDates"
      />
    </div>
    <VTextField
      :model-value="search"
      :placeholder="t('dailyfinance::payment_settlements.search_short')"
      :aria-label="t('dailyfinance::payment_settlements.search')"
      prepend-inner-icon="tabler-search"
      clearable
      :disabled="busy"
      maxlength="120"
      hide-details
      @update:model-value="search = $event ?? ''"
      @keydown.enter.prevent="applyFilters"
    />
    <div class="settlement-collections__toolbar">
      <div
        class="settlement-collections__views"
        :aria-label="t('dailyfinance::payment_settlements.collection_view')"
        role="group"
      >
        <button
          type="button"
          :class="{ 'is-active': !selectedOnly }"
          :aria-pressed="!selectedOnly"
          @click="selectedOnly = false"
        >
          {{ t('dailyfinance::payment_settlements.available') }}
          <span v-if="sources">{{ sources.pagination.total }}</span>
        </button>
        <button
          type="button"
          :class="{ 'is-active': selectedOnly }"
          :aria-pressed="selectedOnly"
          @click="selectedOnly = true"
        >
          {{ t('dailyfinance::payment_settlements.selected_tab') }}
          <span>{{ selected.size }}</span>
        </button>
      </div>
      <VBtn
        v-if="selected.size"
        color="secondary"
        variant="text"
        size="small"
        :disabled="busy"
        @click="clearSelection"
      >
        {{ t('dailyfinance::payment_settlements.clear_selection') }}
      </VBtn>
    </div>
    <div class="settlement-collections__table">
      <div
        ref="viewport"
        class="settlement-collections__viewport"
        role="region"
        :aria-label="t('dailyfinance::payment_settlements.collections')"
        tabindex="0"
        :aria-busy="loading && !selectedOnly"
      >
        <table>
          <thead>
            <tr>
              <th class="settlement-collections__check">
                <VCheckboxBtn
                  v-if="!selectedOnly"
                  :model-value="pageSelected"
                  :indeterminate="pageIndeterminate"
                  :disabled="busy || loading || !sources?.data.some((row) => row.selectable)"
                  :aria-label="t('dailyfinance::payment_settlements.select_page')"
                  @update:model-value="selectPage"
                />
              </th>
              <th>{{ t('dailyfinance::payment_settlements.order') }}</th>
              <th class="settlement-collections__date">
                {{ t('dailyfinance::payment_settlements.collected') }}
                <VIcon
                  icon="tabler-arrow-down"
                  size="13"
                  class="ms-1"
                />
              </th>
              <th class="settlement-collections__amount">
                {{ t('dailyfinance::payment_settlements.collection_amount') }}
              </th>
            </tr>
          </thead>
          <tbody
            v-if="loading && !selectedOnly"
            :aria-label="t('dailyfinance::payment_settlements.loading')"
            role="status"
          >
            <tr
              v-for="n in 7"
              :key="n"
              class="settlement-collections__skeleton"
            >
              <td><span /></td>
              <td><span /></td>
              <td class="settlement-collections__date"><span /></td>
              <td><span /></td>
            </tr>
          </tbody>
          <tbody v-else-if="!loadError || selectedOnly">
            <tr
              v-for="row in visibleSources"
              :key="row.id"
              :class="{ 'is-selected': selected.has(row.id), 'is-unavailable': !row.selectable }"
              @click="toggle(row, !selected.has(row.id))"
            >
              <td
                class="settlement-collections__check"
                @click.stop
              >
                <VCheckboxBtn
                  :model-value="selected.has(row.id)"
                  :disabled="
                    busy || !row.selectable || (!selected.has(row.id) && selected.size >= 500)
                  "
                  :aria-label="row.reference"
                  @update:model-value="toggle(row, !!$event)"
                />
              </td>
              <td class="settlement-collections__reference">
                <strong>{{ row.reference }}</strong>
                <small v-if="row.transaction_id">{{ row.transaction_id }}</small>
                <small class="settlement-collections__mobile-date">
                  <bdi>{{ row.collected_at }}</bdi>
                </small>
                <small
                  v-if="!row.selectable"
                  class="text-warning"
                >
                  {{ t('dailyfinance::payment_settlements.source_precision') }}
                </small>
              </td>
              <td class="settlement-collections__date">
                <bdi>{{ row.collected_at }}</bdi>
              </td>
              <td class="settlement-collections__amount">
                <strong>
                  <bdi>{{ row.amount.formatted }}</bdi>
                </strong>
              </td>
            </tr>
          </tbody>
        </table>
        <div
          v-if="loadError && !selectedOnly"
          class="settlement-collections__empty"
          role="alert"
        >
          <VIcon
            icon="tabler-cloud-off"
            size="32"
            color="secondary"
          />
          <p>{{ loadError }}</p>
          <VBtn
            color="primary"
            variant="tonal"
            @click="applyFilters"
          >
            {{ t('dailyfinance::payment_settlements.retry') }}
          </VBtn>
        </div>
        <div
          v-else-if="(!loading || selectedOnly) && !visibleSources.length"
          class="settlement-collections__empty"
        >
          <VIcon
            :icon="search ? 'tabler-search' : 'tabler-receipt-off'"
            size="32"
            color="secondary"
          />
          <strong>
            {{
              t(
                search
                  ? 'dailyfinance::payment_settlements.no_search_results'
                  : selectedOnly
                    ? 'dailyfinance::payment_settlements.no_selection'
                    : 'dailyfinance::payment_settlements.no_sources',
              )
            }}
          </strong>
          <p>
            {{
              t(
                search
                  ? 'dailyfinance::payment_settlements.search_hint'
                  : selectedOnly
                    ? 'dailyfinance::payment_settlements.no_selection_hint'
                    : 'dailyfinance::payment_settlements.no_sources_hint',
              )
            }}
          </p>
          <VBtn
            v-if="search"
            color="secondary"
            variant="tonal"
            size="small"
            @click="search = ''"
          >
            {{ t('dailyfinance::payment_settlements.clear_search') }}
          </VBtn>
          <VBtn
            v-else-if="selectedOnly"
            color="secondary"
            variant="tonal"
            size="small"
            @click="selectedOnly = false"
          >
            {{ t('dailyfinance::payment_settlements.show_available') }}
          </VBtn>
        </div>
      </div>
      <footer class="settlement-collections__pagination">
        <span>
          {{
            t('dailyfinance::payment_settlements.collection_count', {
              count: selectedOnly ? visibleSources.length : (sources?.pagination.total ?? '—'),
            })
          }}
        </span>
        <VPagination
          v-if="!selectedOnly && sources && sources.pagination.last_page > 1"
          :length="sources.pagination.last_page"
          :model-value="sources.pagination.current_page"
          :total-visible="3"
          :disabled="loading"
          density="compact"
          @update:model-value="loadSources($event)"
        />
      </footer>
    </div>
    <p
      v-if="selected.size >= 500"
      class="settlement-collections__limit"
      role="status"
    >
      {{ t('dailyfinance::payment_settlements.selection_limit') }}
    </p>
  </section>
</template>

<style lang="scss" scoped>
.settlement-collections {
  display: flex;
  flex-direction: column;
  gap: 14px;
  min-block-size: 0;
  flex: 1;
  padding: 20px 22px 12px;
}
.settlement-collections > :not(.settlement-collections__table) {
  flex: 0 0 auto;
}
.settlement-collections__filters {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: 16px;
  align-items: center;
}
.settlement-collections__toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}
.settlement-collections__views {
  display: flex;
  background: rgba(var(--v-theme-secondary), 0.04);
  border-radius: 8px;
  padding: 3px;
}
.settlement-collections__views button {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 7px 12px;
  border-radius: 6px;
  color: rgba(var(--v-theme-on-surface), 0.7);
  font-size: 0.8125rem;
}
.settlement-collections__views .is-active {
  background: rgb(var(--v-theme-surface));
  color: rgb(var(--v-theme-secondary));
  box-shadow: 0 1px 4px rgba(var(--v-theme-secondary), 0.12);
  font-weight: 600;
}
.settlement-collections__views span {
  font-size: 0.6875rem;
  padding: 1px 5px;
  border-radius: 4px;
  background: rgba(var(--v-theme-secondary), 0.06);
}
.settlement-collections__table {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-block-size: 0;
  border: 1px solid rgba(var(--v-theme-secondary), 0.12);
  border-radius: 8px;
  overflow: hidden;
}
.settlement-collections__viewport {
  flex: 1;
  min-block-size: 0;
  overflow: auto;
  overscroll-behavior: contain;
  scrollbar-width: thin;
  scrollbar-color: rgba(var(--v-theme-secondary), 0.2) transparent;
}
.settlement-collections table {
  inline-size: 100%;
  border-collapse: separate;
  border-spacing: 0;
  table-layout: fixed;
}
.settlement-collections thead {
  position: sticky;
  inset-block-start: 0;
  z-index: 1;
}
.settlement-collections th {
  background: rgb(var(--v-theme-surface));
  box-shadow: inset 0 -1px rgba(var(--v-theme-secondary), 0.12);
  color: rgba(var(--v-theme-on-surface), 0.7);
  font-size: 0.75rem;
  font-weight: 500;
  text-align: start;
  padding: 0 12px;
  block-size: 42px;
}
.settlement-collections td {
  padding: 10px 12px;
  border-block-end: 1px solid rgba(var(--v-theme-secondary), 0.08);
  font-size: 0.8125rem;
  block-size: 56px;
}
.settlement-collections tbody tr {
  cursor: pointer;
}
.settlement-collections tbody tr:hover {
  background: rgba(var(--v-theme-secondary), 0.025);
}
.settlement-collections tbody .is-selected {
  background: rgba(var(--v-theme-primary), 0.07);
}
.settlement-collections tbody .is-unavailable {
  cursor: default;
}
.settlement-collections .settlement-collections__check {
  inline-size: 52px;
  padding-inline: 8px 0;
}
.settlement-collections__reference {
  overflow-wrap: anywhere;
}
.settlement-collections__reference strong {
  color: rgb(var(--v-theme-secondary));
  font-weight: 600;
}
.settlement-collections__reference small {
  display: block;
  margin-block-start: 3px;
  font-size: 0.6875rem;
  color: rgba(var(--v-theme-on-surface), 0.6);
}
.settlement-collections__date {
  inline-size: 190px;
  color: rgba(var(--v-theme-on-surface), 0.6);
}
.settlement-collections .settlement-collections__amount {
  inline-size: 130px;
  text-align: end;
  font-variant-numeric: tabular-nums;
  overflow-wrap: anywhere;
  color: rgb(var(--v-theme-secondary));
}
.settlement-collections__reference .settlement-collections__mobile-date {
  display: none;
}
.settlement-collections__pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  flex: 0 0 auto;
  min-block-size: 48px;
  padding: 4px 12px;
  border-block-start: 1px solid rgba(var(--v-theme-secondary), 0.1);
  font-size: 0.75rem;
  color: rgba(var(--v-theme-on-surface), 0.65);
}
.settlement-collections__empty {
  display: grid;
  place-items: center;
  gap: 12px;
  padding: 36px 18px;
  text-align: center;
  font-size: 0.8125rem;
}
.settlement-collections__empty p {
  margin: 0;
  color: rgba(var(--v-theme-on-surface), 0.65);
}
.settlement-collections__skeleton span {
  display: block;
  block-size: 12px;
  border-radius: 4px;
  background: rgba(var(--v-theme-secondary), 0.07);
}
.settlement-collections__limit {
  margin: 0;
  font-size: 0.75rem;
  color: rgb(var(--v-theme-warning));
}
@media (max-width: 600px) {
  .settlement-collections {
    padding: 14px 16px 8px;
    gap: 10px;
  }
  .settlement-collections__filters {
    gap: 10px;
    grid-template-columns: minmax(0, 1fr);
  }
  .settlement-collections__date {
    display: none;
  }
  .settlement-collections__reference .settlement-collections__mobile-date {
    display: block;
  }
  .settlement-collections .settlement-collections__amount {
    inline-size: 100px;
  }
  .settlement-collections .settlement-collections__check {
    inline-size: 40px;
    padding-inline: 2px 0;
  }
  .settlement-collections th,
  .settlement-collections td {
    padding-inline: 6px;
  }
  .settlement-collections__views button {
    padding-inline: 8px;
  }
  .settlement-collections__toolbar {
    gap: 4px;
  }
  .settlement-collections__toolbar > .v-btn {
    font-size: 0.6875rem;
    padding-inline: 6px;
  }
  .settlement-collections__pagination {
    padding-inline: 6px;
  }
}
</style>
