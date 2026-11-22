<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  meta: Record<string, any>
  form: Record<string, any>
}>()

const { t } = useI18n()
const paymentMode = computed({
  get: () => props.form.state?.payment_mode,
  set: (val) => {
    if (!props.form.loading.value) {
      props.form.state.payment_mode = val
    }
  },
})
</script>

<template>
  <div
    class="payment-mode-section"
    data-payment-field="payment_mode"
    tabindex="-1"
  >
    <div
      class="payment-modes-container"
      role="group"
      :aria-label="t('order::orders.payment_mode')"
    >
      <button
        v-for="mode in meta.paymentModes"
        :key="mode.id"
        type="button"
        class="payment-mode"
        :class="{ active: mode.id === paymentMode }"
        :aria-pressed="mode.id === paymentMode"
        :disabled="form.loading.value"
        @click="paymentMode = mode.id"
      >
        <span
          class="payment-mode-icon"
          aria-hidden="true"
        >
          <VIcon
            :icon="mode.id === 'full' ? 'tabler-circle-check' : 'tabler-chart-pie-2'"
            size="18"
          />
        </span>
        <span>{{ mode.name }}</span>
      </button>
    </div>
    <p
      v-if="form.errors.value.payment_mode"
      class="text-error"
    >
      {{ form.errors.value.payment_mode }}
    </p>
  </div>
</template>

<style lang="scss" scoped>
.payment-mode-section {
  margin-bottom: 24px;
}
.payment-modes-container {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 4px;
  padding: 4px;
  width: 400px;
  max-width: 100%;
  background: rgba(var(--v-theme-on-surface), 0.035);
  border: 1px solid rgba(var(--v-theme-on-surface), 0.1);
  border-radius: 13px;
  overflow: hidden;
}
.payment-mode {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 9px;
  min-width: 0;
  min-height: 44px;
  padding: 7px 10px;
  border: 1px solid transparent;
  border-radius: 9px;
  font-size: 14px;
  font-weight: 600;
  line-height: 1.3;
  background: transparent;
  color: rgb(var(--v-theme-on-surface));
  transition:
    background-color 0.15s,
    border-color 0.15s;
  &.active {
    background: color-mix(in srgb, rgb(var(--v-theme-primary)) 10%, rgb(var(--v-theme-surface)));
    border-color: rgba(var(--v-theme-primary), 0.5);
    box-shadow: 0 1px 3px rgba(var(--v-theme-on-surface), 0.06);
    font-weight: 700;
  }
  &:not(.active):not(:disabled):hover {
    background: rgba(var(--v-theme-on-surface), 0.06);
  }
  &:focus-visible {
    outline: 2px solid rgb(var(--v-theme-on-surface));
    outline-offset: -4px;
  }
  &:disabled {
    opacity: 0.5;
  }
}
.payment-mode-icon {
  display: grid;
  place-items: center;
  flex: 0 0 26px;
  width: 26px;
  height: 26px;
  border-radius: 7px;
  background: rgba(var(--v-theme-on-surface), 0.06);
}
.payment-mode.active .payment-mode-icon {
  background: rgb(var(--v-theme-primary));
  color: var(--payment-primary-text);
}
.payment-mode:disabled .payment-mode-icon {
  background: transparent;
  color: inherit;
}
@media (prefers-reduced-motion: reduce) {
  .payment-mode {
    transition: none;
  }
}
</style>
