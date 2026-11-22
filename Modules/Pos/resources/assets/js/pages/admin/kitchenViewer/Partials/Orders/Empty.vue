<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
defineProps<{ hasActiveFilters: boolean }>()
defineEmits<{ 'clear-filters': [] }>()
const { t } = useI18n()
</script>

<template>
  <div
    class="kds-empty"
    role="status"
  >
    <span class="kds-empty__icon">
      <VIcon
        :icon="hasActiveFilters ? 'tabler-search' : 'tabler-chef-hat'"
        size="36"
      />
    </span>
    <h3>{{ t(hasActiveFilters ? 'pos::kds.empty_filtered' : 'pos::kds.empty') }}</h3>
    <p>{{ t(hasActiveFilters ? 'pos::kds.empty_filtered_hint' : 'pos::kds.empty_hint') }}</p>
    <button
      v-if="hasActiveFilters"
      type="button"
      class="kds-clear-button"
      @click="$emit('clear-filters')"
    >
      {{ t('pos::kds.clear_filters') }}
    </button>
  </div>
</template>
