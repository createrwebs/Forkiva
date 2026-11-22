<script lang="ts" setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>

<template>
  <div class="dashboard-empty">
    <span class="dashboard-empty__icon">
      <VIcon
        icon="tabler-chart-dots"
        size="28"
      />
    </span>
    <strong>{{ t('dashboard::dashboards.empty.title') }}</strong>
    <span>{{ t('dashboard::dashboards.empty.description') }}</span>
  </div>
</template>

<style lang="scss" scoped>
.dashboard-empty {
  display: grid;
  min-block-size: 190px;
  align-content: center;
  justify-items: center;
  color: rgba(var(--v-theme-on-surface), 0.55);
  gap: 6px;
  text-align: center;
}

.dashboard-empty__icon {
  display: grid;
  border-radius: 50%;
  margin-block-end: 5px;
  background: rgba(var(--v-theme-primary), 0.09);
  block-size: 54px;
  color: rgb(var(--v-theme-primary));
  inline-size: 54px;
  place-items: center;
}

.dashboard-empty strong {
  color: rgba(var(--v-theme-on-surface), 0.8);
  font-size: 0.88rem;
}

.dashboard-empty span:last-child {
  font-size: 0.76rem;
}
</style>
