<script lang="ts" setup>
import { ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  title: string
  context?: string
  persistent?: boolean
  compact?: boolean
  narrow?: boolean
}>()
const emit = defineEmits<{ close: [] }>()
const { t } = useI18n()
const titleId = useId()
const open = ref(true)
const activator = document.activeElement

function close() {
  if (!props.persistent) open.value = false
}

defineExpose({ close })

function afterLeave() {
  if (activator instanceof HTMLElement && activator.isConnected) {
    activator.focus({ preventScroll: true })
  }

  emit('close')
}
</script>

<template>
  <VDialog
    v-model="open"
    :aria-labelledby="titleId"
    :persistent="persistent"
    class="finance-drawer"
    :class="{ 'finance-drawer--narrow': narrow }"
    scrollable
    transition="finance-drawer-slide"
    @after-leave="afterLeave"
  >
    <VCard class="finance-drawer__card">
      <header
        :class="{ 'finance-drawer__header--compact': compact }"
        class="finance-drawer__header"
      >
        <div class="finance-drawer__title">
          <h2 :id="titleId">{{ title }}</h2>
          <slot name="context">
            <p v-if="context">{{ context }}</p>
          </slot>
        </div>
        <div
          v-if="$slots['header-end']"
          class="finance-drawer__header-end"
        >
          <slot name="header-end" />
        </div>
        <VBtn
          :aria-label="t('dailyfinance::daily_finance.actions.dismiss')"
          :disabled="persistent"
          color="on-surface"
          icon="tabler-x"
          variant="text"
          class="finance-drawer__close"
          @click="close"
        />
      </header>
      <VDivider />
      <VCardText class="finance-drawer__body"><slot /></VCardText>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.finance-drawer {
  --finance-drawer-offset: 100%;
}

.finance-drawer.v-locale--is-rtl {
  --finance-drawer-offset: -100%;
}

.finance-drawer :deep(.finance-drawer-slide-enter-active),
.finance-drawer :deep(.finance-drawer-slide-leave-active) {
  transition: transform 240ms cubic-bezier(0.2, 0, 0, 1);
}

.finance-drawer :deep(.finance-drawer-slide-enter-from),
.finance-drawer :deep(.finance-drawer-slide-leave-to) {
  transform: translateX(var(--finance-drawer-offset));
}

.finance-drawer :deep(.v-overlay__content) {
  position: absolute;
  inset-inline-end: 0;
  inset-block: 0;
  margin: 0;
  inline-size: min(820px, 100%);
  max-inline-size: 100%;
  max-block-size: 100%;
}

.finance-drawer__card {
  border-radius: 0 !important;
  block-size: 100%;
  --session-line: rgba(var(--v-theme-on-surface), 0.1);
  --session-shadow: none;
}

.finance-drawer--narrow :deep(.v-overlay__content) {
  inline-size: min(640px, 100%);
}

.finance-drawer__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  padding: 24px;
}

.finance-drawer__title {
  flex: 1;
  min-inline-size: 0;
  overflow-wrap: anywhere;
}

.finance-drawer__header-end {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
  flex-shrink: 0;
  min-inline-size: 0;
}

.finance-drawer__close {
  flex-shrink: 0;
}

.finance-drawer__header h2 {
  font-size: 1.4rem;
  font-weight: 700;
}

.finance-drawer__header p {
  margin: 8px 0 0;
  font-size: 0.8rem;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

.finance-drawer__header--compact {
  align-items: center;
  padding-block: 12px;
}

.finance-drawer__header--compact h2 {
  font-size: 1.2rem;
  line-height: 1.4;
}

.finance-drawer__header--compact p {
  margin-block-start: 3px;
  font-size: 0.75rem;
  line-height: 1.5;
}

.finance-drawer__body {
  padding: 10px 24px !important;
}

@media (prefers-reduced-motion: reduce) {
  .finance-drawer :deep(.finance-drawer-slide-enter-active),
  .finance-drawer :deep(.finance-drawer-slide-leave-active) {
    transition: none;
  }
}

@media (max-width: 600px) {
  .finance-drawer__header {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
    gap: 12px;
    padding: 18px;
  }

  .finance-drawer__title {
    grid-column: 1;
    grid-row: 1;
  }

  .finance-drawer__close {
    grid-column: 2;
    grid-row: 1;
  }

  .finance-drawer__header-end {
    grid-column: 1 / -1;
    grid-row: 2;
    flex-wrap: wrap;
  }

  .finance-drawer__header--compact {
    padding-block: 12px;
  }

  .finance-drawer__body {
    padding: 18px !important;
  }
}
</style>
