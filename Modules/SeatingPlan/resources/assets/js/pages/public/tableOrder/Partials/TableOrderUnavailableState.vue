<script lang="ts" setup>
  import { useI18n } from 'vue-i18n'

  defineProps<{
    message: string
    title: string
  }>()

  defineEmits<{
    (event: 'retry'): void
  }>()

  const { t } = useI18n()
</script>

<template>
  <div class="table-order-state table-order-state--unavailable">
    <div class="unavailable-ambient unavailable-ambient--one" aria-hidden="true" />
    <div class="unavailable-ambient unavailable-ambient--two" aria-hidden="true" />

    <section aria-live="polite" class="unavailable-panel">
      <div class="unavailable-copy">
        <h1 class="unavailable-title">{{ title }}</h1>
        <p class="unavailable-message">{{ message }}</p>

        <VBtn
          class="unavailable-action"
          color="primary"
          size="large"
          variant="flat"
          @click="$emit('retry')"
        >
          <VIcon icon="tabler-refresh" start />
          {{ t('seatingplan::tables.table_order.try_again') }}
        </VBtn>

        <div class="unavailable-help">
          <VIcon icon="tabler-user-question" size="20" />
          <span>{{ t('seatingplan::tables.table_order.ask_staff_help') }}</span>
        </div>
      </div>

      <div class="unavailable-visual" aria-hidden="true">
        <span class="unavailable-visual__wash" />
        <span class="unavailable-visual__dots" />
        <span class="unavailable-visual__napkin">
          <i />
          <i />
          <i />
        </span>
        <span class="unavailable-visual__plate">
          <span class="unavailable-visual__plate-ring" />
        </span>
        <span class="unavailable-visual__utensil unavailable-visual__utensil--fork">
          <VIcon icon="tabler-tools-kitchen-2" size="54" />
        </span>
        <span class="unavailable-visual__cup">
          <VIcon icon="tabler-coffee" size="40" />
        </span>
        <span class="unavailable-visual__leaf">
          <VIcon icon="tabler-salad" size="54" />
        </span>
      </div>
    </section>
  </div>
</template>
