<script lang="ts" setup>
import { useI18n } from 'vue-i18n'

const emit = defineEmits<{
  customize: []
}>()

const { t } = useI18n()
</script>

<template>
  <VCard
    class="dashboard-empty-state"
    elevation="0"
  >
    <span class="dashboard-empty-state__icon">
      <VIcon
        icon="tabler-layout-dashboard"
        size="34"
      />
    </span>
    <h2>{{ t('dashboard::dashboards.empty.dashboard_title') }}</h2>
    <p>{{ t('dashboard::dashboards.empty.dashboard_description') }}</p>
    <VBtn
      color="primary"
      prepend-icon="tabler-adjustments"
      @click="emit('customize')"
    >
      {{ t('dashboard::dashboards.actions.customize') }}
    </VBtn>
  </VCard>
</template>

<style lang="scss" scoped>
.dashboard-empty-state {
  display: grid;
  min-block-size: 430px;
  align-content: center;
  border: 1px dashed rgba(var(--v-border-color), 0.22);
  border-radius: 20px;
  gap: 8px;
  justify-items: center;
  text-align: center;
}

.dashboard-empty-state__icon {
  display: grid;
  border-radius: 18px;
  margin-block-end: 5px;
  background: rgba(var(--v-theme-primary), 0.09);
  block-size: 68px;
  color: rgb(var(--v-theme-primary));
  inline-size: 68px;
  place-items: center;
}

.dashboard-empty-state h2 {
  font-size: 1.1rem;
}

.dashboard-empty-state p {
  margin-block-end: 8px;
  color: rgba(var(--v-theme-on-surface), 0.55);
  font-size: 0.8rem;
}
</style>
