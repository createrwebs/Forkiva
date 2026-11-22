<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import FinanceFollowUp from './FinanceFollowUp.vue'

defineProps<{ settlements?: boolean; handovers?: boolean; closings?: boolean }>()

const { t } = useI18n()
</script>

<template>
  <div
    class="finance-loading"
    role="status"
    :aria-label="t('dailyfinance::daily_finance.loading')"
  >
    <div
      class="finance-loading__content"
      aria-hidden="true"
    >
      <div class="finance-overview">
        <div
          v-for="metric in 4"
          :key="metric"
          class="finance-panel finance-loading__metric"
        >
          <span class="finance-loading__bone finance-loading__icon" />
          <div class="finance-loading__stack">
            <span class="finance-loading__bone finance-loading__label" />
            <span class="finance-loading__bone finance-loading__value" />
          </div>
        </div>
      </div>
      <FinanceFollowUp
        v-if="settlements || handovers || closings"
        :settlements="settlements ? { summary: null, loading: true, error: false } : undefined"
        :cash="handovers ? { summary: null, loading: true, error: false } : undefined"
        :closing="closings ? { summary: null, loading: true, error: false } : undefined"
      />
      <div class="finance-columns">
        <div class="finance-panel">
          <div class="finance-section-heading">
            <span class="finance-loading__bone finance-loading__title" />
            <span class="finance-loading__bone finance-loading__label" />
          </div>
          <div class="finance-loading__mix">
            <div class="finance-loading__chart">
              <span class="finance-loading__bone finance-loading__ring" />
              <div class="finance-loading__stack">
                <span class="finance-loading__bone finance-loading__label" />
                <span class="finance-loading__bone finance-loading__caption" />
              </div>
            </div>
            <div class="finance-loading__legend">
              <div
                v-for="method in 4"
                :key="method"
                class="finance-loading__legend-row"
              >
                <span class="finance-loading__bone finance-loading__dot" />
                <span class="finance-loading__bone finance-loading__label" />
                <span class="finance-loading__bone finance-loading__caption" />
              </div>
            </div>
          </div>
          <div class="finance-loading__payments">
            <div class="finance-loading__payment-head">
              <span
                v-for="column in 4"
                :key="column"
                class="finance-loading__bone"
              />
            </div>
            <div
              v-for="method in 6"
              :key="method"
              class="finance-loading__payment-row"
            >
              <span class="finance-loading__bone finance-loading__label" />
              <span
                v-for="amount in 3"
                :key="amount"
                class="finance-loading__bone"
              />
            </div>
          </div>
          <div class="finance-loading__total">
            <span class="finance-loading__bone finance-loading__label" />
            <span class="finance-loading__bone finance-loading__label" />
          </div>
        </div>
        <div class="finance-cash-column">
          <div class="finance-panel finance-loading__cash">
            <div class="finance-loading__heading">
              <span class="finance-loading__bone finance-loading__title" />
              <span class="finance-loading__bone finance-loading__label" />
            </div>
            <div class="finance-loading__expected">
              <span class="finance-loading__bone finance-loading__icon" />
              <div class="finance-loading__stack">
                <span class="finance-loading__bone finance-loading__label" />
                <span class="finance-loading__bone finance-loading__value" />
              </div>
            </div>
            <div class="finance-loading__count">
              <div
                v-for="amount in 2"
                :key="amount"
                class="finance-loading__stack"
              >
                <span class="finance-loading__bone finance-loading__label" />
                <span class="finance-loading__bone finance-loading__label" />
              </div>
            </div>
            <div class="finance-loading__movements">
              <span class="finance-loading__bone finance-loading__title" />
              <div
                v-for="movement in 2"
                :key="movement"
                class="finance-loading__movement-row"
              >
                <span class="finance-loading__bone finance-loading__round" />
                <span class="finance-loading__bone finance-loading__label" />
                <span class="finance-loading__bone finance-loading__label" />
              </div>
            </div>
          </div>
          <div class="finance-panel">
            <div class="finance-loading__dues">
              <span class="finance-loading__bone finance-loading__title" />
              <span class="finance-loading__bone finance-loading__value" />
              <span class="finance-loading__bone" />
            </div>
            <div class="finance-loading__dues-footer">
              <span class="finance-loading__bone finance-loading__label" />
              <span class="finance-loading__bone finance-loading__dot" />
            </div>
          </div>
        </div>
      </div>
      <div class="finance-panel">
        <div class="finance-loading__sessions-heading">
          <span class="finance-loading__bone finance-loading__title" />
          <div class="finance-loading__tools">
            <div class="finance-loading__tabs">
              <span
                v-for="tab in 3"
                :key="tab"
                class="finance-loading__bone"
              />
            </div>
            <span class="finance-loading__bone finance-loading__search" />
          </div>
        </div>
        <div class="finance-loading__sessions">
          <div class="finance-loading__session-head">
            <span
              v-for="column in 7"
              :key="column"
              class="finance-loading__bone"
            />
          </div>
          <div
            v-for="session in 2"
            :key="session"
            class="finance-loading__session-row"
          >
            <span
              v-for="column in 7"
              :key="column"
              class="finance-loading__bone"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.finance-loading,
.finance-loading__content {
  display: grid;
  gap: inherit;
  min-inline-size: 0;
}

.finance-loading__bone {
  position: relative;
  display: block;
  block-size: 12px;
  inline-size: 100%;
  max-inline-size: 100%;
  overflow: hidden;
  border-radius: 4px;
  background: rgba(var(--v-theme-on-surface), 0.07);
}

.finance-loading__bone::after {
  position: absolute;
  inset: 0;
  content: '';
  background: linear-gradient(90deg, transparent, rgba(var(--v-theme-surface), 0.7), transparent);
  animation: finance-loading-shimmer 1.8s ease-in-out infinite;
}

.finance-loading__stack {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 12px;
  min-inline-size: 0;
  flex: 1;
}

.finance-loading__title {
  inline-size: 160px;
  block-size: 16px;
}

.finance-loading__label {
  inline-size: 96px;
}

.finance-loading__caption {
  inline-size: 40px;
  block-size: 10px;
}

.finance-loading__value {
  inline-size: 170px;
  block-size: 28px;
}

.finance-loading__icon {
  flex: 0 0 46px;
  inline-size: 46px;
  block-size: 46px;
  border-radius: 8px;
}

.finance-loading__metric {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  padding: 20px;
  min-block-size: 100px;
}

.finance-loading__metric .finance-loading__stack {
  padding-block-start: 6px;
}

.finance-loading__mix {
  display: grid;
  grid-template-columns: minmax(190px, 1fr) minmax(160px, 1fr);
  align-items: center;
  gap: 24px;
  padding: 8px 32px 24px;
}

.finance-loading__chart {
  display: grid;
  place-items: center;
  inline-size: 190px;
  block-size: 190px;
  justify-self: center;
}

.finance-loading__chart > * {
  grid-area: 1 / 1;
}

.finance-loading__chart .finance-loading__stack {
  justify-items: center;
}

.finance-loading__ring {
  block-size: 100%;
  border-radius: 50%;
  mask-image: radial-gradient(circle, transparent 51%, #000 52%);
}

.finance-loading__legend {
  display: grid;
  gap: 24px;
}

.finance-loading__legend-row {
  display: grid;
  grid-template-columns: 10px minmax(0, 1fr) 30px;
  align-items: center;
  gap: 10px;
}

.finance-loading__dot {
  inline-size: 10px;
  block-size: 10px;
  border-radius: 50%;
}

.finance-loading__payments {
  margin-inline: 24px;
}

.finance-loading__payment-head,
.finance-loading__payment-row {
  display: grid;
  grid-template-columns: minmax(0, 1.6fr) repeat(3, minmax(0, 1fr));
  align-items: center;
  gap: 24px;
  padding: 15px 10px;
}

.finance-loading__payment-head,
.finance-loading__session-head {
  background: rgba(var(--v-theme-on-surface), 0.025);
}

.finance-loading__payment-row,
.finance-loading__movement-row {
  border-block-end: 1px solid rgba(var(--v-border-color), 0.09);
}

.finance-loading__total {
  display: flex;
  justify-content: flex-end;
  gap: 14px;
  padding: 24px;
}

.finance-loading__cash {
  padding: 20px;
}

.finance-loading__heading,
.finance-loading__dues-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
}

.finance-loading__heading {
  min-block-size: 28px;
}

.finance-loading__expected {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-block: 12px;
  padding: 16px 12px;
  border: 1px solid rgba(var(--v-border-color), 0.08);
  border-radius: 10px;
  background: rgba(var(--v-theme-on-surface), 0.025);
}

.finance-loading__count {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
  padding-block: 4px 20px;
}

.finance-loading__count > div + div {
  border-inline-start: 1px solid rgba(var(--v-border-color), 0.1);
  padding-inline-start: 16px;
}

.finance-loading__movements {
  padding-block-start: 16px;
  border-block-start: 1px solid rgba(var(--v-border-color), 0.1);
}

.finance-loading__movements > .finance-loading__title {
  margin-block-end: 8px;
}

.finance-loading__movement-row {
  display: grid;
  grid-template-columns: 30px minmax(0, 1fr) 90px;
  align-items: center;
  gap: 10px;
  padding-block: 8px;
}

.finance-loading__round {
  inline-size: 30px;
  block-size: 30px;
  border-radius: 50%;
}

.finance-loading__dues {
  display: grid;
  gap: 20px;
  padding: 26px 24px;
}

.finance-loading__dues .finance-loading__value {
  block-size: 36px;
}

.finance-loading__dues-footer {
  padding: 18px 24px;
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08);
  background: rgba(var(--v-theme-on-surface), 0.025);
}

.finance-loading__sessions-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 16px 24px;
  padding: 20px 24px;
}

.finance-loading__tools {
  display: flex;
  align-items: center;
  gap: 24px;
  margin-inline-start: auto;
}

.finance-loading__tabs {
  display: grid;
  grid-template-columns: repeat(3, 48px);
  gap: 20px;
}

.finance-loading__search {
  inline-size: 260px;
  block-size: 40px;
  border-radius: 6px;
}

.finance-loading__sessions {
  overflow-x: auto;
}

.finance-loading__session-head,
.finance-loading__session-row {
  display: grid;
  grid-template-columns: 1.6fr 1fr 1.5fr 0.7fr repeat(3, 1fr);
  align-items: center;
  gap: 36px;
  min-inline-size: 980px;
  padding: 16px 24px;
  border-block-start: 1px solid rgba(var(--v-border-color), 0.1);
}

.finance-loading__session-row {
  min-block-size: 76px;
}

@keyframes finance-loading-shimmer {
  from {
    transform: translateX(-100%);
  }

  to {
    transform: translateX(100%);
  }
}

@media (max-width: 1200px) {
  .finance-loading__metric {
    gap: 10px;
    padding: 16px;
  }
}

@media (max-width: 700px) {
  .finance-loading__sessions-heading {
    padding: 18px;
  }

  .finance-loading__tools {
    inline-size: 100%;
    flex-wrap: wrap;
    gap: 14px;
  }

  .finance-loading__search {
    flex: 1 1 200px;
  }
}

@media (max-width: 600px) {
  .finance-loading__metric {
    flex-direction: column;
    gap: 8px;
  }

  .finance-loading__metric .finance-loading__stack {
    inline-size: 100%;
    padding: 0;
  }

  .finance-loading__mix {
    grid-template-columns: minmax(0, 1fr);
    gap: 20px;
    padding-inline: 24px;
  }

  .finance-loading__payments {
    margin-inline: 18px;
  }

  .finance-loading__payment-head {
    display: none;
  }

  .finance-loading__payment-row {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 24px 12px;
    padding: 18px 0;
  }

  .finance-loading__payment-row > :first-child {
    grid-column: 1 / -1;
  }
}

@media (prefers-reduced-motion: reduce) {
  .finance-loading__bone::after {
    display: none;
    animation: none;
  }
}
</style>
