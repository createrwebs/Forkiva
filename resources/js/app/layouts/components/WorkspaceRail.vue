<script lang="ts" setup>
import type { SidebarItem } from '@/modules/core/contracts/SidebarItem.ts'
import type { AdminWorkspaceId } from '@/app/layouts/config/adminWorkspaces.ts'
import { useI18n } from 'vue-i18n'
import { adminWorkspaces } from '@/app/layouts/config/adminWorkspaces.ts'
import {
  findFirstAccessibleWorkspaceDestination,
  isWorkspaceFeatureEnabled,
} from '@/app/layouts/config/workspaceVisibility.ts'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import { sidebarRegistry } from '@/modules/core/services/SidebarService.ts'
import { useAppStore } from '@/modules/core/stores/appStore.ts'

const props = defineProps<{
  activeWorkspace: AdminWorkspaceId
  isMenuCollapsed: boolean
}>()

const emit = defineEmits<{
  toggleMenu: []
}>()

const { t } = useI18n()
const appStore = useAppStore()
const { hasPermission } = useAuth()

function hasWorkspaceAccess(item: SidebarItem): boolean {
  const visible = typeof item.visible === 'function' ? item.visible() : item.visible !== false

  if (!visible) {
    return false
  }

  if (!item.permission || (Array.isArray(item.permission) && item.permission.length === 0)) {
    return true
  }

  return hasPermission(item.permission)
}

function firstAccessibleDestination(workspace?: string): SidebarItem | undefined {
  return findFirstAccessibleWorkspaceDestination(
    sidebarRegistry.build('admin', false, workspace),
    hasWorkspaceAccess,
  )
}

const visibleWorkspaces = computed(() =>
  adminWorkspaces.flatMap((workspace) => {
    if (!isWorkspaceFeatureEnabled(workspace, appStore.settings.features)) {
      return []
    }

    const destination = firstAccessibleDestination(workspace.sidebarWorkspace)
    const to = destination?.to
    if (!to) {
      return []
    }

    return [{ ...workspace, destination: { ...destination, to } }]
  }),
)

const menuToggleLabel = computed(() =>
  props.isMenuCollapsed ? 'Expand workspace menu' : 'Collapse workspace menu',
)

const menuToggleIcon = computed(() => {
  if (appStore.isRtl) {
    return props.isMenuCollapsed
      ? 'tabler-layout-sidebar-right-expand'
      : 'tabler-layout-sidebar-right-collapse'
  }

  return props.isMenuCollapsed
    ? 'tabler-layout-sidebar-left-expand'
    : 'tabler-layout-sidebar-left-collapse'
})
</script>

<template>
  <div class="workspace-rail">
    <RouterLink
      class="workspace-brand"
      :title="appStore.appName"
      to="/admin"
    >
      <span class="workspace-brand-mark">
        <img
          v-if="appStore.logo"
          :alt="appStore.appName"
          :src="appStore.logo"
        />
        <VIcon
          v-else
          icon="tabler-tools-kitchen-2"
        />
      </span>
    </RouterLink>

    <nav
      :aria-label="t('admin::sidebar.workspaces')"
      class="workspace-list"
    >
      <RouterLink
        v-for="workspace in visibleWorkspaces"
        :key="workspace.id"
        :aria-current="workspace.id === activeWorkspace ? 'page' : undefined"
        class="workspace-link"
        :class="{ 'workspace-link-active': workspace.id === activeWorkspace }"
        :to="workspace.destination.to"
      >
        <span class="workspace-icon">
          <VIcon :icon="workspace.icon" />
        </span>
        <span class="workspace-label">
          {{ t(workspace.label) }}
        </span>
      </RouterLink>
    </nav>

    <VBtn
      aria-controls="workspace-sub-sidebar"
      :aria-expanded="!isMenuCollapsed"
      :aria-label="menuToggleLabel"
      class="workspace-menu-toggle"
      color="default"
      :icon="menuToggleIcon"
      :title="menuToggleLabel"
      variant="text"
      @click="emit('toggleMenu')"
    />
  </div>
</template>

<style lang="scss" scoped>
.workspace-rail {
  display: flex;
  align-items: center;
  flex: 0 0 var(--forkiva-workspace-rail-width);
  flex-direction: column;
  overflow: hidden;
  border-inline-end: 1px solid var(--forkiva-sidebar-border);
  background: var(--forkiva-workspace-sidebar-bg);
  color: var(--forkiva-sidebar-text-color);
  inline-size: var(--forkiva-workspace-rail-width);
}

.workspace-brand {
  display: flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 var(--forkiva-layout-topbar-height);
  color: inherit;
  inline-size: 100%;
  outline: none;

  &:hover,
  &:focus-visible {
    .workspace-brand-mark {
      border-color: rgba(var(--forkiva-sidebar-accent-rgb), 0.52);
      box-shadow:
        0 0.625rem 1.5rem rgba(0, 22, 39, 0.28),
        0 0 0 0.1875rem rgba(var(--forkiva-sidebar-accent-rgb), 0.12);
      transform: translateY(-0.0625rem);
    }
  }

  &:focus-visible .workspace-brand-mark {
    outline: 2px solid rgb(var(--forkiva-sidebar-accent-rgb));
    outline-offset: 3px;
  }
}

.workspace-brand-mark {
  display: grid;
  overflow: hidden;
  border: 1px solid rgba(255, 255, 255, 0.8);
  border-radius: 1rem;
  background: #fff;
  block-size: 3rem;
  box-shadow:
    0 0.5rem 1.25rem rgba(0, 22, 39, 0.24),
    inset 0 0 0 1px rgba(12, 51, 75, 0.04);
  inline-size: 3rem;
  place-items: center;
  transition:
    border-color 0.18s ease,
    box-shadow 0.18s ease,
    transform 0.18s ease;

  img {
    display: block;
    block-size: 2.25rem;
    inline-size: 2.25rem;
    object-fit: contain;
  }

  .v-icon {
    color: #0c334b;
    font-size: 1.6rem;
  }
}

@media (prefers-reduced-motion: reduce) {
  .workspace-brand-mark {
    transition: none;
  }
}

.workspace-list {
  display: flex;
  align-items: stretch;
  flex: 1 1 auto;
  flex-direction: column;
  gap: 0.375rem;
  inline-size: 100%;
  min-block-size: 0;
  overflow-y: auto;
  padding-block: 0.75rem;
  padding-inline: 0.5rem;
  scrollbar-width: none;

  &::-webkit-scrollbar {
    display: none;
  }
}

.workspace-menu-toggle {
  flex: 0 0 2.75rem;
  border: 1px solid var(--forkiva-sidebar-border);
  border-radius: 0.875rem;
  background: rgba(var(--v-theme-on-sidebar-surface), 0.1);
  box-shadow: 0 0.25rem 0.75rem rgba(0, 23, 43, 0.18);
  color: var(--forkiva-sidebar-text-color);
  margin-block: 0.375rem 0.75rem;

  :deep(.v-icon) {
    font-size: 1.35rem;
  }

  &:hover,
  &:focus-visible {
    border-color: rgba(var(--v-theme-on-sidebar-surface), 0.28);
    background: rgba(var(--v-theme-on-sidebar-surface), 0.17);
    color: var(--forkiva-sidebar-text-color);
  }

  &:focus-visible {
    outline: 2px solid rgba(var(--forkiva-sidebar-accent-rgb), 0.9);
    outline-offset: 2px;
  }
}

.workspace-link {
  position: relative;
  display: flex;
  align-items: center;
  flex-direction: column;
  gap: 0.35rem;
  border-radius: 0.875rem;
  color: var(--forkiva-sidebar-muted-color);
  min-block-size: 4.5rem;
  padding: 0.75rem 0.25rem 0.625rem;
  text-align: center;
  transition:
    background-color 0.18s ease,
    box-shadow 0.18s ease,
    color 0.18s ease,
    transform 0.18s ease;

  &::before {
    position: absolute;
    border-radius: 0 0.25rem 0.25rem 0;
    background: rgb(var(--forkiva-sidebar-accent-rgb));
    block-size: 2rem;
    content: '';
    inline-size: 0.2rem;
    inset-block-start: 50%;
    inset-inline-start: -0.5rem;
    opacity: 0;
    transform: translateY(-50%);
  }

  &:hover,
  &:focus-visible {
    background: rgba(var(--v-theme-on-sidebar-surface), 0.08);
    color: var(--forkiva-sidebar-text-color);
  }

  &:focus-visible {
    outline: 2px solid rgba(var(--forkiva-sidebar-accent-rgb), 0.9);
    outline-offset: 2px;
  }
}

.workspace-link-active {
  background: rgba(var(--v-theme-on-sidebar-surface), 0.11);
  box-shadow:
    inset 0 0 0 1px rgba(var(--v-theme-on-sidebar-surface), 0.12),
    0 0.5rem 1.25rem rgba(0, 23, 43, 0.2);
  color: rgb(var(--forkiva-sidebar-on-accent-rgb));

  &::before {
    opacity: 1;
  }

  &:hover,
  &:focus-visible {
    background: rgba(var(--v-theme-on-sidebar-surface), 0.15);
  }

  .workspace-icon {
    background: rgb(var(--forkiva-sidebar-accent-rgb));
    box-shadow: 0 0.375rem 0.875rem rgba(var(--forkiva-sidebar-accent-rgb), 0.3);
    color: rgb(var(--forkiva-sidebar-on-accent-rgb));
    transform: translateY(-0.0625rem);
  }

  .workspace-label {
    color: rgb(var(--forkiva-sidebar-on-accent-rgb));
    font-weight: 700;
  }
}

.workspace-icon {
  display: grid;
  border-radius: 0.65rem;
  block-size: 2rem;
  inline-size: 2rem;
  place-items: center;
  transition:
    background-color 0.18s ease,
    box-shadow 0.18s ease,
    color 0.18s ease,
    transform 0.18s ease;

  .v-icon {
    font-size: 1.45rem;
  }
}

.workspace-label {
  display: -webkit-box;
  overflow: hidden;
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.005em;
  line-height: 0.9rem;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}

@media (max-width: 1279px) {
  .workspace-rail {
    flex: 0 0 auto;
    flex-direction: row;
    border-block-end: 1px solid var(--forkiva-sidebar-border);
    border-inline-end: 0;
    block-size: 5.75rem;
    inline-size: 100%;
  }

  .workspace-brand {
    display: none;
  }

  .workspace-list {
    align-items: center;
    flex-direction: row;
    gap: 0.25rem;
    overflow-x: auto;
    padding: 0.5rem;
    scrollbar-width: none;

    &::-webkit-scrollbar {
      display: none;
    }
  }

  .workspace-menu-toggle {
    margin: 0 0.5rem 0 0;
  }

  .workspace-link {
    flex: 1 0 4.5rem;
    min-block-size: 4.5rem;
    min-inline-size: 4.5rem;
    padding-block: 0.5rem;

    &::before {
      border-radius: 0.25rem 0.25rem 0 0;
      block-size: 0.2rem;
      inline-size: 2rem;
      inset-block: auto -0.5rem;
      inset-inline-start: 50%;
      transform: translateX(-50%);
    }
  }
}
</style>
