<script lang="ts" setup>
import TimePicker from '@/modules/core/components/Form/TimePicker.vue'

defineProps<{
  dayLabel: string
  icon: string
  enabled: boolean
  open: string
  close: string
  openLabel: string
  closeLabel: string
}>()

const emit = defineEmits<{
  'update:enabled': [value: boolean]
  'update:open': [value: string]
  'update:close': [value: string]
}>()
</script>

<template>
  <VSheet
    class="working-day-card"
    :class="{ 'working-day-card--disabled': !enabled }"
    rounded="xl"
    variant="outlined"
  >
    <div class="working-day-card__header">
      <div class="d-flex align-center ga-3">
        <VAvatar
          color="primary"
          rounded="lg"
          size="34"
          variant="tonal"
        >
          <VIcon
            :icon="icon"
            size="18"
          />
        </VAvatar>

        <span class="text-subtitle-1 font-weight-medium">{{ dayLabel }}</span>
      </div>

      <VSwitch
        hide-details
        inset
        :model-value="enabled"
        @update:model-value="emit('update:enabled', Boolean($event))"
      />
    </div>

    <VRow class="mt-1">
      <VCol
        cols="12"
        sm="6"
      >
        <TimePicker
          :disabled="!enabled"
          :label="openLabel"
          :model-value="open"
          required
          use24h
          @update:model-value="emit('update:open', String($event ?? ''))"
        />
      </VCol>

      <VCol
        cols="12"
        sm="6"
      >
        <TimePicker
          :disabled="!enabled"
          :label="closeLabel"
          :model-value="close"
          required
          use24h
          @update:model-value="emit('update:close', String($event ?? ''))"
        />
      </VCol>
    </VRow>
  </VSheet>
</template>

<style lang="scss" scoped>
.working-day-card {
  height: 100%;
  padding: 16px;
  border-color: rgba(var(--v-border-color), var(--v-border-opacity));
  transition:
    opacity 0.2s ease,
    background-color 0.2s ease;
}

.working-day-card--disabled {
  opacity: 0.72;
  background: rgba(var(--v-theme-surface-variant), 0.18);
}

.working-day-card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
</style>
