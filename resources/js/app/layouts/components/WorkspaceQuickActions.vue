<script lang="ts" setup>
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/modules/auth/composables/auth.ts'

const { t } = useI18n()
const { can } = useAuth()

const actions = computed(() =>
  [
    {
      key: 'pos',
      label: 'POS',
      ariaLabel: t('pos::pos.pos_viewer'),
      icon: 'tabler-cash-register',
      permission: 'admin.pos.index',
      to: { name: 'admin.pos.index' },
    },
    {
      key: 'kds',
      label: 'KDS',
      ariaLabel: t('pos::pos.kitchen_viewer'),
      icon: 'tabler-chef-hat',
      permission: 'admin.pos.kitchen_viewer',
      to: { name: 'admin.pos.kitchen_viewer' },
    },
  ].filter((action) => can(action.permission)),
)
</script>

<template>
  <div
    v-if="actions.length"
    class="workspace-quick-actions"
  >
    <div class="workspace-quick-actions__panel">
      <RouterLink
        v-for="action in actions"
        :key="action.key"
        :aria-label="action.ariaLabel"
        class="workspace-quick-action"
        :class="`workspace-quick-action--${action.key}`"
        :to="action.to"
      >
        <span class="workspace-quick-action__icon">
          <VIcon :icon="action.icon" />
        </span>
        <span class="workspace-quick-action__label">{{ action.label }}</span>
      </RouterLink>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.workspace-quick-actions {
  flex: 0 0 auto;
  margin-block: 0.75rem 0.625rem;
  padding-inline: 1rem;
}

.workspace-quick-actions__panel {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(6.25rem, 1fr));
  overflow: hidden;
  border: 1px solid var(--forkiva-sidebar-border);
  border-radius: 0.875rem;
  background: rgba(var(--v-theme-on-sidebar-surface), 0.035);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.025);
}

.workspace-quick-action {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.55rem;
  min-block-size: 3.25rem;
  background: transparent;
  color: var(--forkiva-sidebar-text-color);
  font-size: 0.8125rem;
  font-weight: 750;
  letter-spacing: 0.025em;
  line-height: 1.1rem;
  outline: none;
  padding: 0.625rem 0.75rem;
  text-align: center;
  transition:
    background-color 0.18s ease,
    color 0.18s ease;

  & + & {
    border-inline-start: 1px solid var(--forkiva-sidebar-border);
  }

  &:hover,
  &:focus-visible {
    background: rgba(var(--forkiva-sidebar-accent-rgb), 0.11);
    color: #fff;
  }

  &:focus-visible {
    box-shadow: inset 0 0 0 2px rgb(var(--forkiva-sidebar-accent-rgb));
  }

  &.router-link-active {
    background: rgba(var(--forkiva-sidebar-accent-rgb), 0.13);
    color: #fff;
  }
}

.workspace-quick-action__icon {
  display: grid;
  flex: 0 0 1.625rem;
  block-size: 1.625rem;
  color: rgb(var(--forkiva-sidebar-accent-rgb));
  inline-size: 1.625rem;
  place-items: center;

  .v-icon {
    font-size: 1.35rem;
  }
}

.workspace-quick-action__label {
  flex: 1 1 auto;
  min-inline-size: 0;
}

@media (prefers-reduced-motion: reduce) {
  .workspace-quick-action {
    transition: none;
  }
}
</style>
