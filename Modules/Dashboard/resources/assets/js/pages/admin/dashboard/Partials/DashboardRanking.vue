<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import type { DashboardRankingItem } from '@/modules/dashboard/contracts/dashboard.ts'
import DashboardEmpty from './DashboardEmpty.vue'

defineProps<{
  items: DashboardRankingItem[]
  products: boolean
}>()

const { t, locale } = useI18n()
</script>

<template>
  <DashboardEmpty v-if="items.length === 0" />
  <div
    v-else
    class="dashboard-ranking"
  >
    <div
      v-for="(item, index) in items"
      :key="item.id"
      class="dashboard-ranking__item"
    >
      <span
        :class="{ featured: index < 3 }"
        class="dashboard-ranking__rank"
      >
        {{ index + 1 }}
      </span>
      <VAvatar
        v-if="products"
        color="surface-variant"
        rounded="lg"
        size="42"
      >
        <VImg
          v-if="item.thumbnail"
          :alt="item.name"
          :src="item.thumbnail"
          cover
        />
        <VIcon
          v-else
          icon="tabler-tools-kitchen-2"
          size="20"
        />
      </VAvatar>
      <span
        v-else
        class="dashboard-ranking__branch"
      >
        <VIcon
          icon="tabler-building-store"
          size="19"
        />
      </span>
      <div class="dashboard-ranking__identity min-w-0">
        <strong class="text-truncate">{{ item.name ?? item.label }}</strong>
        <span v-if="products">
          {{ new Intl.NumberFormat(locale).format(item.total_quantity ?? 0) }}
          {{ t('dashboard::dashboards.labels.quantity_sold') }}
        </span>
        <span v-else>
          {{ new Intl.NumberFormat(locale).format(item.orders ?? 0) }}
          {{ t('dashboard::dashboards.labels.orders') }}
        </span>
      </div>
      <strong class="dashboard-ranking__value">
        {{ item.total_sales?.formatted ?? item.value?.formatted }}
      </strong>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.dashboard-ranking {
  display: grid;
  gap: 2px;
}

.dashboard-ranking__item {
  display: grid;
  min-block-size: 59px;
  grid-template-columns: 28px 42px minmax(0, 1fr) auto;
  align-items: center;
  gap: 10px;
  padding-block: 7px;
  border-block-end: 1px solid rgba(var(--v-border-color), 0.08);
}

.dashboard-ranking__item:last-child {
  border-block-end: 0;
}

.dashboard-ranking__rank {
  color: rgba(var(--v-theme-on-surface), 0.42);
  font-size: 0.77rem;
  font-weight: 700;
  text-align: center;
}

.dashboard-ranking__rank.featured {
  color: rgb(var(--v-theme-primary));
}

.dashboard-ranking__branch {
  display: grid;
  border-radius: 11px;
  background: rgba(var(--v-theme-secondary), 0.08);
  block-size: 42px;
  color: rgb(var(--v-theme-secondary));
  inline-size: 42px;
  place-items: center;
}

.dashboard-ranking__identity {
  display: grid;
  gap: 2px;
}

.dashboard-ranking__identity strong {
  font-size: 0.82rem;
}

.dashboard-ranking__identity span {
  color: rgba(var(--v-theme-on-surface), 0.5);
  font-size: 0.7rem;
}

.dashboard-ranking__value {
  font-size: 0.8rem;
  white-space: nowrap;
}
</style>
