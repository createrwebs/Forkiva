<script lang="ts" setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>

<template>
  <div
    class="finance-session-loading"
    role="status"
    :aria-label="t('dailyfinance::daily_finance.loading')"
    aria-busy="true"
  >
    <div aria-hidden="true">
      <div class="finance-session-loading__information">
        <div class="finance-session-loading__information-header">
          <span class="finance-session-loading__bone finance-session-loading__name" />
          <div class="finance-session-loading__identity">
            <span class="finance-session-loading__bone finance-session-loading__icon" />
            <div>
              <span class="finance-session-loading__bone finance-session-loading__name" />
              <span class="finance-session-loading__bone finance-session-loading__label" />
            </div>
          </div>
        </div>
        <div class="finance-session-loading__timeline">
          <div
            v-for="event in 2"
            :key="event"
          >
            <span class="finance-session-loading__bone finance-session-loading__label" />
            <span class="finance-session-loading__bone finance-session-loading__name" />
            <span class="finance-session-loading__bone finance-session-loading__date" />
          </div>
        </div>
      </div>
      <div class="finance-session-loading__position">
        <div class="finance-session-loading__expected">
          <span class="finance-session-loading__bone finance-session-loading__label" />
          <span class="finance-session-loading__bone finance-session-loading__amount" />
        </div>
        <div class="finance-session-loading__settlement">
          <div
            v-for="amount in 2"
            :key="amount"
          >
            <span class="finance-session-loading__bone finance-session-loading__label" />
            <span class="finance-session-loading__bone finance-session-loading__value" />
          </div>
        </div>
      </div>
      <div class="finance-session-loading__tabs">
        <span
          v-for="tab in 3"
          :key="tab"
          class="finance-session-loading__bone finance-session-loading__tab"
        />
      </div>
      <div class="finance-session-loading__ledger">
        <div
          v-for="row in 3"
          :key="row"
        >
          <span class="finance-session-loading__bone finance-session-loading__label" />
          <span class="finance-session-loading__bone finance-session-loading__value" />
        </div>
      </div>
      <div class="finance-session-loading__totals">
        <div
          v-for="total in 3"
          :key="total"
        >
          <span class="finance-session-loading__bone finance-session-loading__label" />
          <span class="finance-session-loading__bone finance-session-loading__value" />
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.finance-session-loading__bone {
  position: relative;
  display: block;
  max-inline-size: 100%;
  overflow: hidden;
  border-radius: 4px;
  background: rgba(var(--v-theme-on-surface), 0.07);
}

.finance-session-loading__bone::after {
  position: absolute;
  inset: 0;
  content: '';
  background: linear-gradient(90deg, transparent, rgba(var(--v-theme-surface), 0.7), transparent);
  animation: finance-session-loading-shimmer 1.8s ease-in-out infinite;
}

.finance-session-loading__identity {
  display: flex;
  align-items: center;
  gap: 12px;
}

.finance-session-loading__information {
  margin-block: 10px 20px;
}

.finance-session-loading__information-header {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 16px;
  padding-block-end: 18px;
}

.finance-session-loading__information-header > .finance-session-loading__name {
  inline-size: 120px;
  margin: 0;
}

.finance-session-loading__identity .finance-session-loading__name {
  inline-size: 95px;
}

.finance-session-loading__timeline > div {
  display: grid;
  grid-template-columns: minmax(0, 0.8fr) minmax(0, 1fr) auto;
  align-items: center;
  gap: 12px;
  padding-block: 18px;
  border-block-start: 1px solid rgba(var(--v-theme-on-surface), 0.1);
}

.finance-session-loading__timeline > div:last-child {
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.1);
}

.finance-session-loading__timeline .finance-session-loading__name {
  margin: 0;
}

.finance-session-loading__date {
  inline-size: 155px;
  block-size: 12px;
}

.finance-session-loading__icon {
  inline-size: 32px;
  block-size: 32px;
  border-radius: 50%;
}

.finance-session-loading__label {
  inline-size: 95px;
  block-size: 10px;
}

.finance-session-loading__name {
  inline-size: 145px;
  block-size: 14px;
  margin-block-end: 8px;
}

.finance-session-loading__position {
  overflow: hidden;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  border-radius: 12px;
}

.finance-session-loading__expected {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 22px 20px;
  background: rgba(var(--v-theme-on-surface), 0.07);
}

.finance-session-loading__amount {
  inline-size: 180px;
  block-size: 36px;
}

.finance-session-loading__settlement {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 20px;
  padding: 20px;
}

.finance-session-loading__value {
  inline-size: 110px;
  block-size: 20px;
  margin-block-start: 8px;
}

.finance-session-loading__tabs {
  display: flex;
  gap: 24px;
  padding-block: 12px;
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.1);
  margin-block-start: 20px;
}

.finance-session-loading__tab {
  inline-size: 95px;
  block-size: 14px;
}

.finance-session-loading__ledger > div {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-block: 20px;
}

.finance-session-loading__ledger > div + div {
  border-block-start: 1px solid rgba(var(--v-theme-on-surface), 0.1);
}

.finance-session-loading__ledger .finance-session-loading__value {
  margin: 0;
}

.finance-session-loading__totals {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 20px;
  padding: 20px;
  border-radius: 8px;
  background: rgba(var(--v-theme-on-surface), 0.035);
  margin-block-start: 8px;
}

.finance-session-loading__totals .finance-session-loading__value {
  block-size: 18px;
}

@keyframes finance-session-loading-shimmer {
  from {
    transform: translateX(-100%);
  }

  to {
    transform: translateX(100%);
  }
}

@media (max-width: 600px) {
  .finance-session-loading__timeline > div {
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 8px;
    padding-block: 14px;
  }

  .finance-session-loading__date {
    grid-column: 1 / -1;
  }

  .finance-session-loading__expected,
  .finance-session-loading__settlement {
    padding: 20px 16px;
    gap: 14px;
  }

  .finance-session-loading__totals {
    padding: 16px 12px;
    gap: 10px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .finance-session-loading__bone::after {
    display: none;
    animation: none;
  }
}
</style>
