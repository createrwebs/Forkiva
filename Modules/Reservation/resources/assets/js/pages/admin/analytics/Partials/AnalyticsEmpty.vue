<script lang="ts" setup>
withDefaults(defineProps<{ icon?: string; message?: string }>(), {
  icon: 'tabler-chart-bar-off',
  message: '',
})
</script>

<template>
  <div class="analytics-empty">
    <VIcon
      :icon="icon"
      size="32"
    />
    <span>{{ message || $t('reservation::frontend.analytics.no_chart_data') }}</span>
  </div>
</template>

<style lang="scss" scoped>
.analytics-empty {
  display: grid;
  min-block-size: 190px;
  align-content: center;
  gap: 9px;
  color: rgba(var(--v-theme-on-surface), 0.48);
  font-size: 0.76rem;
  justify-items: center;
  text-align: center;
}
</style>
