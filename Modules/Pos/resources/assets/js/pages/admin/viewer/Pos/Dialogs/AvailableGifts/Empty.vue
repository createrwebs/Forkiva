<script lang="ts" setup>
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>

<template>
  <div class="empty-state">
    <div class="empty-icon">
      <VIcon
        color="primary"
        icon="tabler-gift-off"
        size="34"
      />
    </div>

    <h2>
      {{ t('pos::pos_viewer.empty_no_gifts_available.title') }}
    </h2>

    <p>
      {{ t('pos::pos_viewer.empty_no_gifts_available.message') }}
    </p>
  </div>
</template>

<style lang="scss" scoped>
.empty-state {
  display: flex;
  min-height: 260px;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 24px;
  color: rgba(var(--v-theme-on-surface), 0.8);
  text-align: center;

  h2 {
    margin-top: 16px;
    color: rgb(var(--v-theme-on-surface));
    font-size: 1rem;
    font-weight: 750;
  }

  p {
    max-width: 430px;
    margin-top: 6px;
    color: rgba(var(--v-theme-on-surface), 0.58);
    font-size: 0.8125rem;
    line-height: 1.55;
  }
}

.empty-icon {
  display: grid;
  width: 64px;
  height: 64px;
  place-items: center;
  border: 1px dashed rgba(var(--v-theme-primary), 0.32);
  border-radius: 20px;
  background: rgba(var(--v-theme-primary), 0.08);
}
</style>
