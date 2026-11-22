<script setup lang="ts">
withDefaults(defineProps<{ title: string; description: string; icon?: string }>(), {
  icon: 'tabler-user',
})
</script>

<template>
  <section class="profile-section">
    <header class="profile-section__header">
      <div class="profile-section__heading">
        <span class="profile-section__icon">
          <VIcon
            :icon="icon"
            size="26"
          />
        </span>
        <div>
          <h2>{{ title }}</h2>
          <p>{{ description }}</p>
        </div>
      </div>
    </header>
    <div class="profile-section__body"><slot /></div>
    <footer
      v-if="$slots.actions"
      class="profile-section__actions"
    >
      <slot name="actions" />
    </footer>
  </section>
</template>

<style scoped lang="scss">
.profile-section {
  padding: 0;
  &__header {
    margin-bottom: 28px;
    h2 {
      font-size: 1.25rem;
      line-height: 1.5;
      margin: 0 0 4px;
      font-weight: 700;
    }
    p {
      margin: 0;
      color: rgba(var(--v-theme-on-surface), 0.65);
      font-size: 0.85rem;
      line-height: 1.6;
    }
  }
  &__heading {
    display: flex;
    align-items: center;
    gap: 14px;
  }
  &__icon {
    display: grid;
    flex: 0 0 44px;
    width: 44px;
    height: 44px;
    place-items: center;
    border: 1px solid rgba(var(--v-theme-primary), 0.28);
    border-radius: 12px;
    color: rgb(var(--v-theme-primary));
  }
  &__body {
    min-width: 0;
  }
  &__actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    gap: 10px;
  }
  :deep(.v-field),
  :deep(.v-btn) {
    border-radius: 8px;
  }
}
@media (max-width: 700px) {
  .profile-section {
    padding: 0;
  }
}
</style>
