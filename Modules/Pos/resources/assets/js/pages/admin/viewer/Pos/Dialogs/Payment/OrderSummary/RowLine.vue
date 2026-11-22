<script lang="ts" setup>
withDefaults(
  defineProps<{
    title: string
    value: string | number
    grandTotal?: boolean
    changeReturn?: boolean
  }>(),
  { grandTotal: false, changeReturn: false },
)
</script>

<template>
  <div
    class="row-line"
    :class="{ 'grand-total': grandTotal, 'change-return': changeReturn }"
  >
    <span class="row-title">{{ title }}</span>
    <span class="row-value">{{ value }}</span>
  </div>
</template>

<style lang="scss" scoped>
.row-line {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
  gap: 12px;
  font-size: 14px;
}

.row-value {
  font-weight: 600;
  white-space: nowrap;
}

.grand-total {
  border-top: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  border-bottom: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  padding-top: 0.9rem;
  padding-bottom: 0.9rem;
  font-size: 1rem;

  .row-title {
    font-weight: bold;
  }

  .row-value {
    color: rgb(var(--v-theme-on-surface));
    font-size: 24px;
  }
}

.change-return {
  .row-title {
    font-weight: bold;
  }

  .row-value {
    color: rgb(var(--v-theme-success));
  }
}
</style>
