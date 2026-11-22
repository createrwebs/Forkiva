<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import type { PaymentSettlement } from '@/modules/dailyfinance/contracts/paymentSettlement'

defineProps<{ record: PaymentSettlement }>()
const tab = defineModel<'receipts' | 'sources'>({ required: true })
const { t } = useI18n()
</script>

<template>
  <div class="settlement-detail__activity">
    <VTabs
      v-model="tab"
      color="secondary"
      slider-color="primary"
      density="compact"
      class="settlement-detail__tabs"
      show-arrows
    >
      <VTab value="receipts">
        {{ t('dailyfinance::payment_settlements.receipts') }}
        <span>{{ record.receipts?.length ?? 0 }}</span>
      </VTab>
      <VTab value="sources">
        {{ t('dailyfinance::payment_settlements.sources') }}
        <span>{{ record.items_count }}</span>
      </VTab>
    </VTabs>
    <section
      v-if="tab === 'receipts'"
      class="settlement-detail__list"
      :aria-label="t('dailyfinance::payment_settlements.receipts')"
      :tabindex="record.receipts?.length ? 0 : undefined"
    >
      <div
        v-if="!record.receipts?.length"
        class="settlement-detail__empty"
      >
        <span>
          <VIcon
            icon="tabler-building-bank"
            size="26"
          />
        </span>
        <div>
          <strong>
            {{
              t(
                `dailyfinance::payment_settlements.${record.status.id === 'cancelled' ? 'cancelled_no_receipts' : 'no_receipts'}`,
              )
            }}
          </strong>
          <p v-if="record.status.id !== 'cancelled'">
            {{ t('dailyfinance::payment_settlements.no_receipts_hint') }}
          </p>
        </div>
      </div>
      <article
        v-for="receipt in record.receipts"
        :key="receipt.id"
        class="settlement-detail__row"
      >
        <span class="settlement-detail__row-icon settlement-detail__row-icon--credit">
          <VIcon
            icon="tabler-arrow-down-left"
            size="20"
          />
        </span>
        <div class="settlement-detail__row-content">
          <strong>
            <bdi>{{ receipt.reference }}</bdi>
          </strong>
          <small>
            <bdi>{{ receipt.received_date_formatted }}</bdi>
            <span aria-hidden="true">·</span>
            {{ receipt.created_by.name ?? '—' }}
          </small>
          <p
            v-if="receipt.variance_reason"
            class="settlement-detail__reason"
          >
            {{ receipt.variance_reason }}
          </p>
        </div>
        <div class="settlement-detail__row-value">
          <strong>
            <bdi>{{ receipt.amount.formatted }}</bdi>
          </strong>
          <VChip
            size="x-small"
            :color="receipt.is_final ? 'success' : 'info'"
          >
            {{
              t(
                `dailyfinance::payment_settlements.${receipt.is_final ? 'final_credit' : 'partial_credit'}`,
              )
            }}
          </VChip>
        </div>
      </article>
    </section>
    <section
      v-else
      class="settlement-detail__list"
      :aria-label="t('dailyfinance::payment_settlements.sources')"
      tabindex="0"
    >
      <div
        v-for="item in record.items"
        :key="item.id"
        class="settlement-detail__row"
      >
        <span class="settlement-detail__row-icon">
          <VIcon
            icon="tabler-receipt"
            size="20"
          />
        </span>
        <div class="settlement-detail__row-content">
          <strong>
            <bdi>{{ item.reference }}</bdi>
          </strong>
          <small>
            <bdi>{{ item.collected_at }}</bdi>
          </small>
        </div>
        <div class="settlement-detail__row-value">
          <strong>
            <bdi>{{ item.amount.formatted }}</bdi>
          </strong>
        </div>
      </div>
    </section>
  </div>
</template>

<style lang="scss" scoped>
.settlement-detail__row-icon {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  background: rgba(var(--v-theme-secondary), 0.055);
  color: rgb(var(--v-theme-secondary));
}

.settlement-detail__tabs {
  border-block-end: 1px solid rgba(var(--v-theme-secondary), 0.12);
}

.settlement-detail__tabs :deep(.v-tab) {
  font-size: 0.8125rem;
  padding-inline: 12px;
  text-transform: none;
  letter-spacing: normal;
}

.settlement-detail__tabs span {
  display: inline-grid;
  place-items: center;
  min-inline-size: 20px;
  block-size: 20px;
  padding-inline: 5px;
  margin-inline-start: 8px;
  border-radius: 10px;
  background: rgba(var(--v-theme-secondary), 0.06);
  font-size: 0.6875rem;
}

.settlement-detail__list {
  max-block-size: 232px;
  overflow-y: auto;
  scrollbar-width: thin;
  overscroll-behavior: contain;
}

.settlement-detail__list:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: -2px;
}

.settlement-detail__empty {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  padding: 26px 12px 12px;
  min-block-size: 116px;
  font-size: 0.8125rem;
}

.settlement-detail__empty > span {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  inline-size: 52px;
  block-size: 52px;
  border-radius: 50%;
  background: rgba(var(--v-theme-secondary), 0.055);
  color: rgb(var(--v-theme-secondary));
}

.settlement-detail__empty strong {
  color: rgb(var(--v-theme-secondary));
}

.settlement-detail__empty p {
  margin: 5px 0 0;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

.settlement-detail__row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 4px;
  min-block-size: 76px;
}

.settlement-detail__row + .settlement-detail__row {
  border-block-start: 1px solid rgba(var(--v-theme-secondary), 0.09);
}

.settlement-detail__row-icon {
  inline-size: 36px;
  block-size: 36px;
  border-radius: 50%;
}

.settlement-detail__row-icon--credit {
  background: rgba(var(--v-theme-success), 0.09);
  color: rgb(var(--v-theme-success));
}

.settlement-detail__row-content {
  display: grid;
  gap: 4px;
  min-inline-size: 0;
  flex: 1;
}

.settlement-detail__row strong {
  color: rgb(var(--v-theme-secondary));
  font-size: 0.875rem;
  overflow-wrap: anywhere;
}

.settlement-detail__row small {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
  font-size: 0.75rem;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

.settlement-detail__row-value {
  display: grid;
  justify-items: end;
  gap: 5px;
  flex-shrink: 0;
  max-inline-size: 40%;
  text-align: end;
  font-variant-numeric: tabular-nums;
}

.settlement-detail__reason {
  margin: 2px 0 0;
  padding-inline-start: 8px;
  border-inline-start: 2px solid rgba(var(--v-theme-warning), 0.4);
  font-size: 0.75rem;
  color: rgba(var(--v-theme-on-surface), 0.7);
  overflow-wrap: anywhere;
  white-space: pre-wrap;
}

@media (max-width: 600px) {
  .settlement-detail__empty {
    padding-inline: 0;
    gap: 12px;
  }

  .settlement-detail__row {
    gap: 8px;
  }

  .settlement-detail__row-icon {
    inline-size: 28px;
    block-size: 28px;
  }
}
</style>
