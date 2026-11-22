<script lang="ts" setup>
import { useId } from 'vue'

withDefaults(
  defineProps<{
    title: string
    message: string
    icon?: string
    context?: string
    actionText?: string
    actionIcon?: string
    onClickAction?: () => void
    hasActionButton?: boolean
  }>(),
  {
    hasActionButton: false,
    actionIcon: 'tabler-plus',
  },
)

const titleId = useId()
</script>

<template>
  <VSheet
    :aria-labelledby="titleId"
    class="configuration-alert"
    elevation="0"
    role="status"
  >
    <div
      aria-hidden="true"
      class="configuration-alert__visual"
    >
      <VIcon
        :icon="icon || 'tabler-alert-circle'"
        size="66"
      />
      <span class="configuration-alert__indicator">
        <VIcon
          icon="tabler-alert-circle"
          size="18"
        />
      </span>
    </div>
    <div class="configuration-alert__content">
      <h2
        :id="titleId"
        class="configuration-alert__title"
      >
        {{ title }}
      </h2>
      <p class="configuration-alert__message">{{ message }}</p>
      <p
        v-if="context"
        class="configuration-alert__context"
      >
        <VIcon
          aria-hidden="true"
          icon="tabler-building-store"
          size="17"
        />
        <span>{{ context }}</span>
      </p>
      <div
        v-if="hasActionButton && actionText && onClickAction"
        class="configuration-alert__action"
      >
        <VBtn
          class="configuration-alert__button"
          color="primary"
          :prepend-icon="actionIcon"
          @click="onClickAction"
        >
          {{ actionText }}
          <VIcon
            icon="tabler-arrow-right"
            class="configuration-alert__arrow"
            size="18"
            end
          />
        </VBtn>
      </div>
    </div>
  </VSheet>
</template>

<style lang="scss" scoped>
.configuration-alert {
  display: grid;
  grid-template-columns: 144px minmax(0, 1fr);
  gap: 28px;
  padding: 28px;
  inline-size: 100%;
  max-inline-size: 720px;
  border: 1px solid rgba(var(--v-theme-primary), 0.18);
  border-radius: 18px;
  background: rgb(var(--v-theme-surface));
  box-shadow: 0 12px 40px -24px rgba(var(--v-theme-on-surface), 0.2);
  text-align: start;
}
.configuration-alert__visual {
  position: relative;
  display: grid;
  place-items: center;
  min-block-size: 166px;
  border-radius: 13px;
  color: rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.07);
}
.configuration-alert__indicator {
  position: absolute;
  inset-block-start: 16px;
  inset-inline-end: 16px;
  color: rgb(var(--v-theme-primary));
}
.configuration-alert__content {
  align-self: center;
  min-inline-size: 0;
  padding-block: 4px;
}
.configuration-alert__title {
  margin: 0;
  font-size: clamp(20px, 2.5vw, 24px);
  line-height: 1.4;
  font-weight: 750;
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
  text-wrap: balance;
}
.configuration-alert__message {
  margin-block: 12px 0;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-size: 14px;
  line-height: 1.75;
  white-space: pre-line;
}
.configuration-alert__context {
  display: flex;
  align-items: center;
  gap: 7px;
  margin-block: 16px 0;
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
  font-size: 12px;
  overflow-wrap: anywhere;
}
.configuration-alert__context > .v-icon {
  flex-shrink: 0;
  color: rgb(var(--v-theme-primary));
}
.configuration-alert__action {
  margin-block-start: 22px;
}
.configuration-alert__button {
  min-block-size: 44px;
  height: auto;
  padding: 11px 18px;
  border-radius: 9px;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: normal;
  text-transform: none;
}
.configuration-alert__button :deep(.v-btn__content) {
  white-space: normal;
}
[dir='rtl'] .configuration-alert__arrow {
  transform: rotate(180deg);
}
@media (max-width: 600px) {
  .configuration-alert {
    grid-template-columns: minmax(0, 1fr);
    gap: 18px;
    padding: 24px;
  }
  .configuration-alert__visual {
    inline-size: 76px;
    min-block-size: 76px;
  }
  .configuration-alert__visual > .v-icon {
    font-size: 40px !important;
  }
  .configuration-alert__indicator {
    inset-block-start: -6px;
    inset-inline-end: -6px;
    border: 3px solid rgb(var(--v-theme-surface));
    border-radius: 50%;
    background: rgb(var(--v-theme-surface));
  }
  .configuration-alert__button {
    inline-size: 100%;
  }
}
</style>
