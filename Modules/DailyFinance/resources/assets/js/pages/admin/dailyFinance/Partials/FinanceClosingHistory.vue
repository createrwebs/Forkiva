<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import TableStatus from '@/modules/core/components/Table/Partials/TableStatus.vue'
import type { DayClosing, DayClosingHistory } from '@/modules/dailyfinance/contracts/dayClosing'

defineProps<{ history: DayClosingHistory }>()
const emit = defineEmits<{ review: [record: DayClosing]; page: [page: number] }>()
const { t } = useI18n()
</script>

<template>
  <section class="closing-history">
    <header class="closing-history__header">
      <h3>{{ t('dailyfinance::day_closings.history_title') }}</h3>
      <span>
        {{ t('dailyfinance::day_closings.history_count', { count: history.pagination.total }) }}
      </span>
    </header>
    <button
      v-for="record in history.data"
      :key="record.id"
      type="button"
      class="closing-history__item"
      :aria-label="t('dailyfinance::day_closings.review_record', { reference: record.reference })"
      aria-haspopup="dialog"
      @click="emit('review', record)"
    >
      <span class="closing-history__row">
        <span class="closing-history__identity">
          <span class="closing-history__icon">
            <VIcon
              icon="tabler-calendar-check"
              size="24"
              aria-hidden="true"
            />
          </span>
          <span class="closing-history__date">
            <strong>
              <bdi dir="ltr">{{ record.date_formatted }}</bdi>
            </strong>
            <small>{{ record.reference }}</small>
          </span>
        </span>
        <span class="closing-history__totals">
          <span>
            <small>{{ t('dailyfinance::daily_finance.metrics.sales') }}</small>
            <strong>
              <bdi>
                {{
                  record.summary.sales?.total.formatted ?? t('dailyfinance::day_closings.available')
                }}
              </bdi>
            </strong>
          </span>
          <span>
            <small>{{ t('dailyfinance::daily_finance.metrics.collections') }}</small>
            <strong>
              <bdi>
                {{
                  record.summary.payments?.collections.formatted ??
                  t('dailyfinance::day_closings.available')
                }}
              </bdi>
            </strong>
          </span>
          <span
            class="closing-history__variance"
            :class="{
              'closing-history__variance--difference': record.summary.sessions?.variance?.amount,
            }"
          >
            <small>{{ t('dailyfinance::day_closings.variance') }}</small>
            <strong>
              <bdi>
                {{
                  record.summary.sessions?.variance?.formatted ??
                  t('dailyfinance::day_closings.available')
                }}
              </bdi>
            </strong>
          </span>
        </span>
        <TableStatus :item="record" />
        <VIcon
          icon="tabler-chevron-right"
          size="18"
          class="closing-history__chevron"
          aria-hidden="true"
        />
      </span>
      <span class="closing-history__audit">
        <span>
          <VIcon
            icon="tabler-user"
            size="15"
            aria-hidden="true"
          />
          <span>{{ t('dailyfinance::day_closings.saved_by') }} {{ record.created_by.name }}</span>
        </span>
        <bdi dir="ltr">{{ record.created_at }}</bdi>
      </span>
    </button>
    <footer
      v-if="history.pagination.last_page > 1"
      class="closing-history__pagination"
    >
      <span>
        {{
          t('dailyfinance::day_closings.history_page', {
            current: history.pagination.current_page,
            total: history.pagination.last_page,
          })
        }}
      </span>
      <VPagination
        :length="history.pagination.last_page"
        :model-value="history.pagination.current_page"
        :total-visible="3"
        @update:model-value="emit('page', $event)"
      />
    </footer>
  </section>
</template>

<style lang="scss" scoped>
.closing-history {
  display: grid;
  gap: 10px;
}

.closing-history__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.closing-history__header h3 {
  margin: 0;
  font-size: 1.125rem;
}

.closing-history__header > span {
  font-size: 0.8125rem;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

.closing-history__item {
  display: grid;
  min-inline-size: 0;
  padding: 14px 16px;
  overflow: hidden;
  border: 1px solid rgba(var(--v-theme-secondary), 0.14);
  border-radius: 10px;
  background: rgb(var(--v-theme-surface));
  color: rgb(var(--v-theme-on-surface));
  text-align: start;
  cursor: pointer;
  transition:
    border-color 160ms ease,
    box-shadow 160ms ease;
}

.closing-history__item:hover {
  border-color: rgba(var(--v-theme-secondary), 0.35);
  box-shadow: 0 3px 12px rgba(var(--v-theme-secondary), 0.06);
}

.closing-history__item:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 3px;
}

.closing-history__identity {
  display: flex;
  align-items: center;
  gap: 10px;
}

.closing-history__row {
  display: grid;
  grid-template-columns: minmax(150px, 1.1fr) minmax(0, 2fr) auto 18px;
  align-items: center;
  gap: 16px;
}

.closing-history__icon {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  inline-size: 36px;
  block-size: 36px;
  border-radius: 8px;
  background: rgb(var(--v-theme-secondary));
  color: rgb(var(--v-theme-on-secondary));
}

.closing-history__date {
  display: grid;
  flex: 1;
  min-inline-size: 0;
  gap: 4px;
}

.closing-history__date strong {
  font-size: 0.9375rem;
}

.closing-history__item small {
  font-size: 0.8125rem;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

.closing-history__totals {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}

.closing-history__totals > span {
  display: grid;
  gap: 4px;
  min-inline-size: 0;
}

.closing-history__totals strong {
  color: rgb(var(--v-theme-secondary));
  font-size: 1rem;
}

.closing-history__audit {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px 16px;
  margin-block-start: 10px;
  padding-block-start: 10px;
  border-block-start: 1px solid rgba(var(--v-theme-secondary), 0.08);
  font-size: 0.75rem;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

.closing-history__audit > span {
  display: flex;
  align-items: center;
  gap: 6px;
  min-inline-size: 0;
  overflow-wrap: anywhere;
}

.closing-history__variance {
  text-align: start;
}

.closing-history__variance--difference,
.closing-history__variance--difference strong {
  color: rgb(var(--v-theme-primary));
}

.closing-history__pagination {
  position: sticky;
  inset-block-end: -10px;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 8px;
  padding-block: 10px;
  border-block-start: 1px solid rgba(var(--v-theme-secondary), 0.12);
  background: rgb(var(--v-theme-surface));
}

.closing-history__pagination > span {
  font-size: 0.8125rem;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

.closing-history__item bdi {
  overflow-wrap: anywhere;
  font-variant-numeric: tabular-nums;
}

:global(.v-locale--is-rtl) .closing-history__chevron {
  transform: rotate(180deg);
}

@media (prefers-reduced-motion: reduce) {
  .closing-history__item {
    transition: none;
  }
}

@media (max-width: 600px) {
  .closing-history__item {
    padding: 14px;
  }

  .closing-history__row {
    grid-template-columns: minmax(0, 1fr) auto 18px;
    gap: 14px 10px;
  }

  .closing-history__totals {
    grid-row: 2;
    grid-column: 1 / -1;
  }

  .closing-history__pagination {
    inset-block-end: -18px;
    justify-content: center;
  }

  .closing-history__pagination > span {
    inline-size: 100%;
    text-align: center;
  }
}
</style>
