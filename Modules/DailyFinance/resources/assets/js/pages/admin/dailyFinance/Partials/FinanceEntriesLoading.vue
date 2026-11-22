<script lang="ts" setup>
import { useI18n } from 'vue-i18n'

defineProps<{ sales: boolean; outstanding?: boolean }>()
const { t } = useI18n()
</script>

<template>
  <div
    class="finance-entries-loading"
    :class="{ 'finance-entries-loading--sales': sales }"
    role="status"
    :aria-label="t('dailyfinance::daily_finance.loading')"
    aria-busy="true"
  >
    <div aria-hidden="true">
      <div
        v-for="row in 6"
        :key="row"
        class="finance-entries-loading__row"
      >
        <span class="finance-entries-loading__bone finance-entries-loading__icon" />
        <div class="finance-entries-loading__reference">
          <span class="finance-entries-loading__bone finance-entries-loading__title" />
          <span class="finance-entries-loading__bone finance-entries-loading__date" />
        </div>
        <div class="finance-entries-loading__labels">
          <span
            v-for="label in sales ? 3 : 1"
            :key="label"
            class="finance-entries-loading__bone finance-entries-loading__chip"
          />
        </div>
        <div class="finance-entries-loading__amount">
          <span
            v-if="outstanding"
            class="finance-entries-loading__bone finance-entries-loading__amount-label"
          />
          <span class="finance-entries-loading__bone finance-entries-loading__amount-value" />
        </div>
        <span class="finance-entries-loading__bone finance-entries-loading__chevron" />
      </div>
      <span class="finance-entries-loading__bone finance-entries-loading__count" />
    </div>
  </div>
</template>

<style lang="scss" scoped>
.finance-entries-loading__bone {
  position: relative;
  display: block;
  max-inline-size: 100%;
  overflow: hidden;
  border-radius: 4px;
  background: rgba(var(--v-theme-on-surface), 0.07);
}

.finance-entries-loading__bone::after {
  position: absolute;
  inset: 0;
  content: '';
  background: linear-gradient(90deg, transparent, rgba(var(--v-theme-surface), 0.7), transparent);
  animation: finance-entries-loading-shimmer 1.8s ease-in-out infinite;
}

.finance-entries-loading__row {
  display: grid;
  min-block-size: 68px;
  grid-template-columns: 38px minmax(0, 1fr) auto minmax(80px, auto) 18px;
  align-items: center;
  gap: 8px 12px;
  padding: 12px 10px;
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.1);
}

.finance-entries-loading__icon {
  inline-size: 38px;
  block-size: 38px;
  border-radius: 11px;
}

.finance-entries-loading__reference {
  display: grid;
  gap: 6px;
  min-inline-size: 0;
}

.finance-entries-loading__title {
  inline-size: 140px;
  block-size: 14px;
}

.finance-entries-loading__date {
  inline-size: 150px;
  block-size: 10px;
}

.finance-entries-loading__labels {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.finance-entries-loading__chip {
  inline-size: 86px;
  block-size: 24px;
  border-radius: 6px;
}

.finance-entries-loading__amount {
  display: grid;
  gap: 5px;
  inline-size: 80px;
  justify-self: end;
}

.finance-entries-loading__amount-value {
  block-size: 17px;
}

.finance-entries-loading__amount-label {
  inline-size: 64px;
  block-size: 10px;
  justify-self: end;
}

.finance-entries-loading__chevron {
  inline-size: 12px;
  block-size: 8px;
  justify-self: center;
}

.finance-entries-loading__count {
  inline-size: 64px;
  block-size: 12px;
  margin-block-start: 20px;
}

.finance-entries-loading--sales .finance-entries-loading__row {
  min-block-size: 60px;
  grid-template-columns: 36px minmax(150px, 1fr) auto minmax(80px, auto) 18px;
  padding: 8px 12px;
}

.finance-entries-loading--sales .finance-entries-loading__icon {
  inline-size: 36px;
  block-size: 36px;
  border-radius: 10px;
}

.finance-entries-loading--sales .finance-entries-loading__chip {
  inline-size: 66px;
}

@keyframes finance-entries-loading-shimmer {
  from {
    transform: translateX(-100%);
  }

  to {
    transform: translateX(100%);
  }
}

@media (max-width: 760px) {
  .finance-entries-loading--sales .finance-entries-loading__row {
    grid-template-columns: 36px minmax(0, 1fr) auto 18px;
  }

  .finance-entries-loading--sales .finance-entries-loading__labels {
    grid-column: 2 / -1;
    grid-row: 2;
  }
}

@media (max-width: 600px) {
  .finance-entries-loading__row {
    grid-template-columns: 38px minmax(0, 1fr) minmax(0, auto) 18px;
    gap: 8px;
  }

  .finance-entries-loading__labels {
    grid-column: 2 / -1;
    grid-row: 2;
  }

  .finance-entries-loading__amount {
    grid-column: 3;
    grid-row: 1;
  }

  .finance-entries-loading__chevron {
    grid-column: 4;
    grid-row: 1;
  }
}

@media (max-width: 480px) {
  .finance-entries-loading--sales .finance-entries-loading__row {
    grid-template-columns: minmax(0, 1fr) minmax(0, auto) 18px;
    padding: 10px;
    gap: 8px;
  }

  .finance-entries-loading--sales .finance-entries-loading__icon {
    display: none;
  }

  .finance-entries-loading--sales .finance-entries-loading__labels {
    grid-column: 1 / -1;
  }

  .finance-entries-loading--sales .finance-entries-loading__amount {
    grid-column: 2;
  }

  .finance-entries-loading--sales .finance-entries-loading__chevron {
    grid-column: 3;
  }
}

@media (prefers-reduced-motion: reduce) {
  .finance-entries-loading__bone::after {
    display: none;
    animation: none;
  }
}
</style>
