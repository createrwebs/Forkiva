<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { GiftCardAnalyticsWidgetKind } from '@/modules/giftcard/contracts/giftCardAnalytics.ts'

const props = defineProps<{
  kind: Exclude<GiftCardAnalyticsWidgetKind, 'metric' | 'chart'>
  data: Record<string, any>
}>()

const { locale, t } = useI18n()

const hasData = computed(() => {
  if (props.kind === 'cards') {
    return Boolean(props.data.status_mix?.length || props.data.scope_mix?.length)
  }
  if (props.kind === 'transactions') return Boolean(props.data.type_breakdown?.length)
  if (props.kind === 'batches') return Boolean(props.data.top_batches?.length)
  if (props.kind === 'branches') return Boolean(props.data.highlights?.length)
  return Boolean(props.data.cards?.length)
})

function formatNumber(value: unknown) {
  return new Intl.NumberFormat(locale.value).format(Number(value ?? 0))
}

function transactionTypeLabel(value: unknown) {
  if (value && typeof value === 'object' && 'name' in value) {
    return String((value as { name?: unknown }).name ?? '—')
  }

  return String(value ?? '—')
}
</script>

<template>
  <div
    v-if="!hasData"
    class="gift-card-details__empty"
  >
    <VIcon
      icon="tabler-chart-bar-off"
      size="30"
    />
    {{ t('giftcard::gift_cards.analytics.no_data_available') }}
  </div>

  <div
    v-else-if="kind === 'cards'"
    class="gift-card-details"
  >
    <div class="gift-card-details__summary">
      <div
        v-for="item in data.status_mix"
        :key="`status-${item.key}`"
        class="gift-card-details__summary-item"
      >
        <span>{{ item.label }}</span>
        <strong>{{ formatNumber(item.count) }}</strong>
      </div>
      <div
        v-for="item in data.scope_mix"
        :key="`scope-${item.key}`"
        class="gift-card-details__summary-item gift-card-details__summary-item--muted"
      >
        <span>{{ item.label }}</span>
        <strong>{{ formatNumber(item.count) }}</strong>
      </div>
    </div>

    <div class="gift-card-details__section-title">
      {{ t('giftcard::gift_cards.analytics.top_balances') }}
    </div>
    <div class="gift-card-details__list">
      <div
        v-for="item in data.top_balances"
        :key="item.id"
        class="gift-card-details__row"
      >
        <span
          class="gift-card-details__avatar"
          aria-hidden="true"
        >
          {{ String(item.code || '?').charAt(0) }}
        </span>
        <div class="gift-card-details__copy">
          <strong>{{ item.code }}</strong>
          <span>
            {{ item.customer || '—' }} ·
            {{ item.branch || t('giftcard::gift_cards.analytics.global_pool') }}
          </span>
        </div>
        <strong class="gift-card-details__value">
          {{ item.current_balance?.formatted || '—' }}
        </strong>
      </div>
    </div>
  </div>

  <div
    v-else-if="kind === 'transactions'"
    class="gift-card-details"
  >
    <div class="gift-card-details__summary gift-card-details__summary--transactions">
      <div
        v-for="item in data.type_breakdown"
        :key="item.key"
        class="gift-card-details__summary-item"
      >
        <span>{{ item.label }}</span>
        <strong>{{ formatNumber(item.count) }}</strong>
        <small>{{ item.amount?.formatted || '—' }}</small>
      </div>
    </div>

    <div class="gift-card-details__section-title">
      {{ t('giftcard::gift_cards.analytics.recent_transactions') }}
    </div>
    <div class="gift-card-details__list">
      <div
        v-for="item in data.recent_transactions"
        :key="item.id"
        class="gift-card-details__row"
      >
        <span
          class="gift-card-details__avatar gift-card-details__avatar--transaction"
          aria-hidden="true"
        >
          <VIcon
            icon="tabler-arrows-exchange"
            size="17"
          />
        </span>
        <div class="gift-card-details__copy">
          <strong>{{ item.code || item.uuid }}</strong>
          <span>
            {{ transactionTypeLabel(item.type_label) }} ·
            {{ item.branch || t('giftcard::gift_cards.analytics.global_pool') }}
          </span>
        </div>
        <div class="gift-card-details__value">
          <strong>{{ item.amount?.formatted || '—' }}</strong>
          <span>{{ item.transaction_at }}</span>
        </div>
      </div>
    </div>
  </div>

  <div
    v-else-if="kind === 'batches'"
    class="gift-card-details"
  >
    <div class="gift-card-details__summary">
      <div class="gift-card-details__summary-item">
        <span>{{ t('giftcard::gift_cards.analytics.total_batches') }}</span>
        <strong>{{ formatNumber(data.summary?.total_batches) }}</strong>
      </div>
      <div class="gift-card-details__summary-item">
        <span>{{ t('giftcard::gift_cards.analytics.total_batch_quantity') }}</span>
        <strong>{{ formatNumber(data.summary?.total_quantity) }}</strong>
      </div>
      <div class="gift-card-details__summary-item">
        <span>{{ t('giftcard::gift_cards.analytics.batch_face_value') }}</span>
        <strong>{{ data.summary?.total_value?.formatted || '—' }}</strong>
      </div>
    </div>
    <div class="gift-card-details__table-wrap">
      <VTable density="compact">
        <thead>
          <tr>
            <th>{{ t('giftcard::gift_card_batches.gift_card_batch') }}</th>
            <th>{{ t('giftcard::gift_cards.table.branch') }}</th>
            <th>{{ t('giftcard::gift_cards.analytics.generated') }}</th>
            <th>{{ t('giftcard::gift_cards.analytics.used') }}</th>
            <th>{{ t('giftcard::gift_cards.analytics.remaining') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="item in data.top_batches"
            :key="item.id"
          >
            <td>{{ item.name }}</td>
            <td>{{ item.branch || t('giftcard::gift_cards.analytics.global_pool') }}</td>
            <td>{{ formatNumber(item.cards_generated) }}</td>
            <td>{{ formatNumber(item.cards_used) }}</td>
            <td>{{ formatNumber(item.cards_remaining) }}</td>
          </tr>
        </tbody>
      </VTable>
    </div>
  </div>

  <div
    v-else-if="kind === 'branches'"
    class="gift-card-details gift-card-details__list"
  >
    <div
      v-for="item in data.highlights"
      :key="`${item.branch_id}-${item.branch_name}`"
      class="gift-card-details__row gift-card-details__row--branch"
    >
      <span
        class="gift-card-details__avatar gift-card-details__avatar--branch"
        aria-hidden="true"
      >
        <VIcon
          icon="tabler-building-store"
          size="17"
        />
      </span>
      <div class="gift-card-details__copy">
        <strong>{{ item.branch_name }}</strong>
        <span>
          {{ formatNumber(item.cards_count) }} {{ t('giftcard::gift_cards.analytics.cards') }}
        </span>
      </div>
      <div class="gift-card-details__value">
        <strong>{{ item.outstanding_balance?.formatted || '—' }}</strong>
        <span>
          {{ t('giftcard::gift_cards.analytics.redeemed_value') }}:
          {{ item.redeemed_value?.formatted || '—' }}
        </span>
      </div>
    </div>
  </div>

  <div
    v-else
    class="gift-card-details"
  >
    <div class="gift-card-details__summary">
      <div class="gift-card-details__summary-item gift-card-details__summary-item--danger">
        <span>{{ t('giftcard::gift_cards.analytics.expiring_in_7_days') }}</span>
        <strong>{{ formatNumber(data.summary?.expiring_in_7_days) }}</strong>
      </div>
      <div class="gift-card-details__summary-item">
        <span>{{ t('giftcard::gift_cards.analytics.expiring_in_30_days') }}</span>
        <strong>{{ formatNumber(data.summary?.expiring_in_30_days) }}</strong>
      </div>
      <div class="gift-card-details__summary-item gift-card-details__summary-item--muted">
        <span>{{ t('giftcard::gift_cards.analytics.expired_cards') }}</span>
        <strong>{{ formatNumber(data.summary?.expired_cards) }}</strong>
      </div>
    </div>
    <div class="gift-card-details__list">
      <div
        v-for="item in data.cards"
        :key="item.id"
        class="gift-card-details__row"
      >
        <span
          class="gift-card-details__avatar gift-card-details__avatar--expiry"
          aria-hidden="true"
        >
          <VIcon
            icon="tabler-calendar-exclamation"
            size="17"
          />
        </span>
        <div class="gift-card-details__copy">
          <strong>{{ item.code }}</strong>
          <span>
            {{ item.customer || '—' }} ·
            {{ item.branch || t('giftcard::gift_cards.analytics.global_pool') }}
          </span>
        </div>
        <div class="gift-card-details__value">
          <strong>{{ item.current_balance?.formatted || '—' }}</strong>
          <span>{{ item.expiry_date || '—' }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.gift-card-details {
  display: grid;
  gap: 16px;
}

.gift-card-details__summary {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 9px;
}

.gift-card-details__summary--transactions {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.gift-card-details__summary-item {
  display: grid;
  min-inline-size: 0;
  gap: 3px;
  padding: 11px 12px;
  border: 1px solid rgba(var(--v-theme-primary), 0.1);
  border-radius: 12px;
  background: rgba(var(--v-theme-primary), 0.045);
}

.gift-card-details__summary-item--muted {
  border-color: rgba(var(--v-border-color), 0.11);
  background: rgba(var(--v-theme-on-surface), 0.025);
}

.gift-card-details__summary-item--danger {
  border-color: rgba(var(--v-theme-error), 0.12);
  background: rgba(var(--v-theme-error), 0.055);
}

.gift-card-details__summary-item span,
.gift-card-details__summary-item small {
  overflow: hidden;
  color: rgba(var(--v-theme-on-surface), 0.56);
  font-size: 0.69rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.gift-card-details__summary-item strong {
  overflow: hidden;
  color: rgb(var(--v-theme-on-surface));
  font-size: 1rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.gift-card-details__section-title {
  color: rgba(var(--v-theme-on-surface), 0.64);
  font-size: 0.7rem;
  font-weight: 750;
  letter-spacing: 0.055em;
  text-transform: uppercase;
}

.gift-card-details__list {
  display: grid;
  overflow: auto;
  max-block-size: 250px;
}

.gift-card-details__row {
  display: grid;
  grid-template-columns: 36px minmax(0, 1fr) auto;
  align-items: center;
  gap: 10px;
  padding: 10px 2px;
  border-block-end: 1px solid rgba(var(--v-border-color), 0.08);
}

.gift-card-details__row:last-child {
  border-block-end: 0;
}

.gift-card-details__avatar {
  display: grid;
  border-radius: 10px;
  background: rgba(var(--v-theme-primary), 0.1);
  block-size: 34px;
  color: rgb(var(--v-theme-primary));
  font-size: 0.78rem;
  font-weight: 800;
  inline-size: 34px;
  place-items: center;
}

.gift-card-details__avatar--transaction {
  background: rgba(var(--v-theme-info), 0.1);
  color: rgb(var(--v-theme-info));
}

.gift-card-details__avatar--branch {
  background: rgba(var(--v-theme-secondary), 0.1);
  color: rgb(var(--v-theme-secondary));
}

.gift-card-details__avatar--expiry {
  background: rgba(var(--v-theme-error), 0.1);
  color: rgb(var(--v-theme-error));
}

.gift-card-details__copy,
.gift-card-details__value {
  display: grid;
  min-inline-size: 0;
  gap: 2px;
}

.gift-card-details__copy strong,
.gift-card-details__copy span,
.gift-card-details__value strong,
.gift-card-details__value span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.gift-card-details__copy strong,
.gift-card-details__value strong {
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.77rem;
}

.gift-card-details__copy span,
.gift-card-details__value span {
  color: rgba(var(--v-theme-on-surface), 0.54);
  font-size: 0.67rem;
}

.gift-card-details__value {
  max-inline-size: 180px;
  text-align: end;
}

.gift-card-details__table-wrap {
  overflow: auto;
  max-block-size: 270px;
}

.gift-card-details__table-wrap :deep(th) {
  color: rgba(var(--v-theme-on-surface), 0.55) !important;
  font-size: 0.68rem !important;
}

.gift-card-details__table-wrap :deep(td) {
  font-size: 0.74rem !important;
}

.gift-card-details__empty {
  display: grid;
  min-block-size: 230px;
  align-content: center;
  justify-items: center;
  gap: 8px;
  color: rgba(var(--v-theme-on-surface), 0.48);
  font-size: 0.76rem;
}

@media (max-width: 600px) {
  .gift-card-details__summary {
    grid-template-columns: 1fr;
  }

  .gift-card-details__row {
    grid-template-columns: 34px minmax(0, 1fr);
  }

  .gift-card-details__value {
    grid-column: 2;
    max-inline-size: none;
    text-align: start;
  }
}
</style>
