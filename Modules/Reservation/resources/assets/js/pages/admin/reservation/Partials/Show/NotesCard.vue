<script lang="ts" setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  item: Record<string, any>
}>()

const { t } = useI18n()

type NoteField = {
  color: string
  icon: string
  label: string
  value?: string | null
}

function hasValue(value?: string | null): boolean {
  return value !== null && value !== undefined && String(value).trim() !== ''
}

const fields = computed<NoteField[]>(() =>
  [
    {
      label: t('reservation::attributes.reservations.special_requests'),
      value: props.item.special_requests,
      icon: 'tabler-message',
      color: 'info',
    },
    {
      label: t('reservation::attributes.reservations.internal_notes'),
      value: props.item.internal_notes,
      icon: 'tabler-notes',
      color: 'secondary',
    },
    {
      label: t('reservation::attributes.reservations.cancellation_reason'),
      value: props.item.cancellation_reason,
      icon: 'tabler-ban',
      color: 'error',
    },
    {
      label: t('reservation::frontend.rejection_reason'),
      value: props.item.rejection_reason,
      icon: 'tabler-circle-x',
      color: 'error',
    },
    {
      label: t('reservation::frontend.no_show_reason'),
      value: props.item.no_show_reason,
      icon: 'tabler-user-x',
      color: 'warning',
    },
  ].filter((field) => hasValue(field.value)),
)
</script>

<template>
  <VCard>
    <VCardTitle class="d-flex align-center">
      <VIcon
        class="me-2"
        icon="tabler-notes"
        size="18"
      />
      <span>{{ t('reservation::frontend.notes') }}</span>
    </VCardTitle>

    <VCardText>
      <div
        v-if="fields.length > 0"
        class="reservation-notes"
      >
        <VSheet
          v-for="field in fields"
          :key="field.label"
          border
          class="reservation-notes__item"
          rounded="lg"
        >
          <div class="reservation-notes__header">
            <VIcon
              :color="field.color"
              :icon="field.icon"
              size="18"
            />
            <span>{{ field.label }}</span>
          </div>

          <div class="reservation-notes__body">
            {{ field.value }}
          </div>
        </VSheet>
      </div>

      <VAlert
        v-else
        type="info"
        variant="tonal"
      >
        {{ t('reservation::frontend.no_notes_available') }}
      </VAlert>
    </VCardText>
  </VCard>
</template>

<style lang="scss" scoped>
.reservation-notes {
  display: grid;
  gap: 12px;
}

.reservation-notes__item {
  border-style: dashed;
  padding: 12px 14px;
}

.reservation-notes__header {
  align-items: center;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  display: flex;
  font-size: 0.82rem;
  font-weight: 600;
  gap: 8px;
  margin-bottom: 8px;
}

.reservation-notes__body {
  color: rgb(var(--v-theme-on-surface));
  line-height: 1.55;
  white-space: pre-line;
}
</style>
