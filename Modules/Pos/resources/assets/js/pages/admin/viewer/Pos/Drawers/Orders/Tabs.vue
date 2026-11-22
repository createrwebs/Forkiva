<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useTheme } from 'vuetify'
import { getContrast } from 'vuetify/lib/util/colorUtils.js'
import { useAuth } from '@/modules/auth/composables/auth.ts'

const props = defineProps<{
  disabled: boolean
  modelValue: string
}>()

const emit = defineEmits<{
  (e: 'on-click'): void
  (e: 'update:modelValue', value: string): void
}>()

const tab = computed({
  get: () => props.modelValue,
  set: (val: string) => {
    if (val === props.modelValue) {
      return
    }

    emit('update:modelValue', val)
    emit('on-click')
  },
})

const { can } = useAuth()
const { t } = useI18n()
const theme = useTheme()
const iconColor = computed(() => {
  const primary = theme.current.value.colors.primary

  return getContrast(primary, '#000000') > getContrast(primary, '#ffffff') ? '#000000' : '#ffffff'
})
</script>

<template>
  <VTabs
    v-if="can('admin.orders.upcoming') && can('admin.orders.active')"
    v-model="tab"
    :disabled="disabled"
    class="order-status-tabs"
    :style="{ '--order-tab-icon-color': iconColor }"
    :aria-label="t('pos::pos_viewer.order_management')"
    height="44"
    hide-slider
    grow
  >
    <VTab value="active">
      <span
        class="order-status-tabs__icon"
        aria-hidden="true"
      >
        <VIcon
          icon="tabler-clipboard-list"
          size="18"
        />
      </span>
      {{ t('pos::pos_viewer.active_orders') }}
    </VTab>
    <VTab value="upcoming">
      <span
        class="order-status-tabs__icon"
        aria-hidden="true"
      >
        <VIcon
          icon="tabler-calendar-time"
          size="18"
        />
      </span>
      {{ t('pos::pos_viewer.upcoming_orders') }}
    </VTab>
  </VTabs>
</template>

<style lang="scss" scoped>
.order-status-tabs {
  flex: 0 0 auto;
  height: 54px;
  padding: 4px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.08);
  border-radius: 14px;
  background: rgba(var(--v-theme-on-surface), 0.03);

  :deep(.v-slide-group__content) {
    gap: 4px;
  }

  :deep(.v-tab) {
    flex: 1 1 0;
    min-width: 0;
    padding-inline: 12px;
    border: 1px solid transparent;
    border-radius: 10px !important;
    color: rgba(var(--v-theme-on-surface), 0.72);
    font-size: 13px;
    font-weight: 600;
    letter-spacing: 0;
    text-transform: none;
    transition:
      background-color 0.18s,
      border-color 0.18s,
      box-shadow 0.18s;
  }

  :deep(.v-btn__content) {
    gap: 10px;
    white-space: normal;
    line-height: 1.2;
  }

  :deep(.v-tab--selected) {
    border-color: rgba(var(--v-theme-primary), 0.5);
    background: color-mix(in srgb, rgb(var(--v-theme-primary)) 12%, rgb(var(--v-theme-surface)));
    color: rgb(var(--v-theme-on-surface)) !important;
    font-weight: 700;
    box-shadow: 0 2px 6px rgba(var(--v-theme-primary), 0.12);
  }

  &__icon {
    display: grid;
    place-items: center;
    flex: 0 0 28px;
    width: 28px;
    height: 28px;
    border-radius: 8px;
    background: rgba(var(--v-theme-on-surface), 0.06);
  }

  :deep(.v-tab--selected) .order-status-tabs__icon {
    background: rgb(var(--v-theme-primary));
    color: var(--order-tab-icon-color);
  }

  :deep(.v-tab:not(.v-tab--selected):hover) {
    background: rgba(var(--v-theme-on-surface), 0.05);
  }

  :deep(.v-tab:focus-visible) {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: -3px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .order-status-tabs :deep(.v-tab) {
    transition: none;
  }
}
</style>
