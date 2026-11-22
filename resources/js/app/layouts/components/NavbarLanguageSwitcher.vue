<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import { useAppStore } from '@/modules/core/stores/appStore.ts'
import { useUserPreference } from '@/modules/userPreference/composables/userPreference.ts'

const appStore = useAppStore()
const preferences = useUserPreference()
const { t, locale } = useI18n()

async function changeLanguage(value: string) {
  await preferences.setLanguage(value)
}
</script>

<template>
  <div class="profile-language">
    <span class="profile-language__label">
      <VIcon
        icon="tabler-language"
        size="20"
      />
      {{ t('translation::translations.language') }}
    </span>
    <VSelect
      :model-value="locale"
      :aria-label="t('translation::translations.language')"
      class="profile-language__select"
      density="compact"
      hide-details
      item-title="name"
      item-value="id"
      :items="appStore.supportedLanguages"
      :menu-props="{ maxHeight: 280 }"
      variant="outlined"
      @update:model-value="changeLanguage"
    />
  </div>
</template>

<style lang="scss" scoped>
.profile-language {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.profile-language__label {
  display: inline-flex;
  align-items: center;
  gap: 0.65rem;
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.85rem;
  font-weight: 600;
}

.profile-language__select {
  flex: 0 1 160px;
  min-inline-size: 0;

  :deep(.v-field) {
    border-radius: 10px;
    font-size: 0.8rem;
  }
}
</style>
