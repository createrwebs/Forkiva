<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { Money } from '@/modules/core/contracts/Money'

const props = defineProps<{ amount: Money }>()
const emit = defineEmits<{ review: [] }>()
const { t } = useI18n()
const hasDues = computed(() => props.amount.amount > 0)
</script>

<template>
  <button
    type="button"
    class="finance-panel finance-dues"
    aria-haspopup="dialog"
    @click="emit('review')"
  >
    <span class="finance-dues__body">
      <span class="finance-dues__heading">
        <span>{{ t('dailyfinance::daily_finance.dues.title') }}</span>
        <VIcon
          icon="tabler-receipt"
          size="22"
        />
      </span>
      <strong class="finance-dues__amount">
        <bdi>{{ amount.formatted }}</bdi>
      </strong>
      <span class="finance-dues__context">{{ t('dailyfinance::daily_finance.dues.hint') }}</span>
    </span>
    <span class="finance-dues__footer">
      <span
        class="finance-dues__state"
        :class="{ 'finance-dues__state--settled': !hasDues }"
      >
        <VIcon
          :icon="hasDues ? 'tabler-clock' : 'tabler-circle-check'"
          size="18"
        />
        {{ t(`dailyfinance::daily_finance.dues.${hasDues ? 'pending' : 'settled'}`) }}
      </span>
      <VIcon
        class="finance-dues__chevron"
        icon="tabler-arrow-right"
        size="20"
      />
    </span>
  </button>
</template>

<style lang="scss" scoped>
button.finance-dues.finance-panel {
  display: flex;
  flex-direction: column;
  padding: 0;
  color: rgb(var(--v-theme-on-surface));
  text-align: start;
  cursor: pointer;
  transition: border-color 160ms ease;
}

button.finance-dues.finance-panel:hover {
  border-color: rgba(var(--v-theme-primary), 0.5);
}

.finance-dues:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 3px;
}

.finance-dues__body {
  display: flex;
  flex-direction: column;
  inline-size: 100%;
  padding: 22px 24px 20px;
}

.finance-dues__heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  font-size: 0.95rem;
  font-weight: 650;
}

.finance-dues__heading .v-icon {
  color: rgba(var(--v-theme-on-surface), 0.35);
}

.finance-dues__amount {
  margin-block: 16px 8px;
  font-size: clamp(1.75rem, 2.4vw, 2.25rem);
  font-weight: 700;
  line-height: 1.4;
  font-variant-numeric: tabular-nums;
  overflow-wrap: anywhere;
}

.finance-dues__context {
  color: rgba(var(--v-theme-on-surface), 0.6);
  font-size: 0.75rem;
  line-height: 1.7;
}

.finance-dues__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 14px 24px;
  inline-size: 100%;
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08);
  background: rgba(var(--v-theme-on-surface), 0.025);
}

.finance-dues__state {
  display: flex;
  align-items: center;
  gap: 8px;
  color: rgb(var(--v-theme-primary));
  font-size: 0.75rem;
  font-weight: 600;
}

.finance-dues__state--settled {
  color: rgb(var(--v-theme-success));
}

.finance-dues__chevron {
  color: rgba(var(--v-theme-on-surface), 0.6);
}

.finance-dues:dir(rtl) .finance-dues__chevron {
  transform: rotate(180deg);
}
</style>
