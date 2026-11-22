<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import type { Money } from '@/modules/core/contracts/Money'
import FinanceHint from './FinanceHint.vue'

defineProps<{
  label: string
  value: Money | null
  hint: string
  unavailable: string
  icon: string
  color: string
  interactive?: boolean
}>()
const emit = defineEmits<{ review: [] }>()
const { t } = useI18n()
</script>

<template>
  <section class="finance-metric finance-panel">
    <component
      :is="interactive ? 'button' : 'div'"
      :type="interactive ? 'button' : undefined"
      class="finance-metric__content"
      :class="{ 'finance-metric__content--interactive': interactive }"
      :aria-label="
        interactive
          ? t('dailyfinance::daily_finance.actions.metric_records', { metric: label })
          : undefined
      "
      :aria-haspopup="interactive ? 'dialog' : undefined"
      @click="interactive && emit('review')"
    >
      <VAvatar
        tag="span"
        :color="color"
        variant="tonal"
        rounded="lg"
        size="46"
      >
        <VIcon
          :icon="icon"
          size="24"
        />
      </VAvatar>
      <span class="finance-metric__body">
        <span class="finance-metric__label">{{ label }}</span>
        <strong :class="{ 'finance-metric__missing': !value }">
          <bdi>{{ value?.formatted ?? unavailable }}</bdi>
        </strong>
      </span>
    </component>
    <div class="finance-metric__hint">
      <FinanceHint :text="hint" />
    </div>
  </section>
</template>

<style lang="scss" scoped>
.finance-metric {
  position: relative;
}

.finance-metric__content {
  inline-size: 100%;
  block-size: 100%;
  color: inherit;
  text-align: start;
  display: flex;
  align-items: flex-start;
  gap: 16px;
  padding: 20px;
  border-radius: inherit;
}

.finance-metric__content--interactive {
  cursor: pointer;
  transition: background-color 160ms ease;
}

.finance-metric__content--interactive:hover {
  background: rgba(var(--v-theme-primary), 0.04);
}

.finance-metric__content--interactive:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: -2px;
}

.finance-metric__hint {
  position: absolute;
  inset-block-start: 18px;
  inset-inline-end: 14px;
}

.finance-metric__body {
  min-inline-size: 0;
  flex: 1;
}

.finance-metric__label {
  display: flex;
  align-items: center;
  padding-inline-end: 22px;
  font-size: 0.82rem;
  font-weight: 600;
  min-block-size: 24px;
}

.finance-metric strong {
  display: block;
  font-size: clamp(1.3rem, 2vw, 1.85rem);
  line-height: 1.4;
  font-weight: 750;
  font-variant-numeric: tabular-nums;
  overflow-wrap: anywhere;
}

.finance-metric .finance-metric__missing {
  font-size: 1.1rem;
  color: rgba(var(--v-theme-on-surface), 0.6);
}

@media (max-width: 1200px) {
  .finance-metric__content {
    gap: 10px;
    padding: 16px;
  }
}

@media (max-width: 600px) {
  .finance-metric__content {
    flex-direction: column;
    gap: 8px;
  }

  .finance-metric__body {
    inline-size: 100%;
  }

  .finance-metric strong {
    font-size: 1.3rem;
  }
}
</style>
