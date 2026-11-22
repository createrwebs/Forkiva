<script setup lang="ts">
import { useI18n } from 'vue-i18n'

defineProps<{ step: number }>()
const { t } = useI18n()
const steps = ['upload_step', 'match_columns', 'review_validate', 'result']
</script>

<template>
  <ol
    class="import-steps"
    :aria-label="t('import::imports.progress')"
  >
    <li
      v-for="(label, index) in steps"
      :key="label"
      :class="{ 'is-active': step === index + 1, 'is-complete': step > index + 1 }"
      :aria-current="step === index + 1 ? 'step' : undefined"
    >
      <span class="import-step-number">
        <VIcon
          v-if="step > index + 1"
          icon="tabler-check"
          size="16"
        />
        <template v-else>{{ index + 1 }}</template>
      </span>
      <span class="import-step-label">{{ t(`import::imports.${label}`) }}</span>
    </li>
  </ol>
</template>

<style lang="scss" scoped>
.import-steps {
  display: flex;
  flex-shrink: 0;
  gap: 14px;
  list-style: none;
  margin: 0;
  padding: 10px 24px 14px;
}

.import-steps li {
  display: flex;
  flex: 1;
  min-width: 0;
  align-items: center;
  gap: 10px;
  color: rgba(var(--v-theme-on-surface), 0.58);
  font-size: 12px;
  font-weight: 500;
}

.import-steps li:last-child {
  flex: 0 0 auto;
}

.import-steps li:not(:last-child)::after {
  content: '';
  height: 1px;
  min-width: 16px;
  flex: 1;
  margin-inline-start: 4px;
  background: rgba(var(--v-border-color), 0.2);
}

.import-steps li.is-complete::after {
  background: rgba(var(--v-theme-primary), 0.45);
}

.import-step-label {
  white-space: nowrap;
}

.import-step-number {
  display: grid;
  flex-shrink: 0;
  width: 24px;
  height: 24px;
  place-items: center;
  border: 1px solid rgba(var(--v-border-color), 0.28);
  border-radius: 50%;
  font-size: 12px;
}

.import-steps .is-active {
  color: rgb(var(--v-theme-on-surface));
  font-weight: 700;
}

.is-active .import-step-number {
  background: rgb(var(--v-theme-primary));
  border-color: transparent;
  color: rgb(var(--v-theme-on-primary));
}

.is-complete .import-step-number {
  border-color: rgba(var(--v-theme-primary), 0.25);
  background: rgba(var(--v-theme-primary), 0.08);
  color: rgb(var(--v-theme-primary));
}

@media (max-width: 850px) {
  .import-steps {
    gap: 8px;
    padding-inline: 20px;
  }

  .import-steps li {
    gap: 6px;
  }

  .import-step-label {
    white-space: normal;
  }
}

@media (max-width: 600px) {
  .import-steps {
    gap: 8px;
    padding: 12px;
  }

  .import-steps li {
    flex: 1;
    flex-direction: column;
    text-align: center;
    font-size: 10px;
    gap: 6px;
  }

  .import-steps li:last-child {
    flex: 1;
  }

  .import-steps li:not(:last-child)::after {
    display: none;
  }

  .import-step-label {
    white-space: normal;
  }
}
</style>
