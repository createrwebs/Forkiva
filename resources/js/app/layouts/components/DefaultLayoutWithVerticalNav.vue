<script lang="ts" setup>
import type { TargetName } from '@/modules/core/contracts/Target.ts'
import type { AdminWorkspaceId } from '@/app/layouts/config/adminWorkspaces.ts'
import { useI18n } from 'vue-i18n'
import Footer from '@/app/layouts/components/Footer.vue'
import HeaderPage from '@/app/layouts/components/HeaderPage.vue'
import NavbarAction from '@/app/layouts/components/NavbarAction.vue'
import NavbarFullscreenSwitcher from '@/app/layouts/components/NavbarFullscreenSwitcher.vue'
import NavItems from '@/app/layouts/components/NavItems.vue'
import UserProfile from '@/app/layouts/components/UserProfile/Index.vue'
import VerticalNavLayout from '@/app/layouts/components/VerticalNavLayout.vue'
import WorkspaceQuickActions from '@/app/layouts/components/WorkspaceQuickActions.vue'
import WorkspaceRail from '@/app/layouts/components/WorkspaceRail.vue'
import { getAdminWorkspace } from '@/app/layouts/config/adminWorkspaces.ts'
import { useAppStore } from '@/modules/core/stores/appStore.ts'
import NotificationBell from '@/modules/notification/components/admin/NotificationBell.vue'

const props = withDefaults(
  defineProps<{
    target: TargetName
    homeTo?: string
    workspaceNavigation?: boolean
    workspace?: AdminWorkspaceId
  }>(),
  {
    homeTo: undefined,
    workspaceNavigation: false,
    workspace: 'restaurant',
  },
)

const appStore = useAppStore()
const { t } = useI18n()
const route = useRoute()
const sidebarLgNone = computed(
  () => !['admin.pos.kitchen_viewer', 'admin.pos.index'].includes(route.name as string),
)
const isPosScreen = computed(() =>
  ['admin.pos.index', 'admin.pos.customer_viewer', 'admin.pos.kitchen_viewer'].includes(
    route.name as string,
  ),
)
const activeWorkspace = computed(() =>
  props.workspaceNavigation ? getAdminWorkspace(props.workspace) : null,
)
</script>

<template>
  <VerticalNavLayout
    :class="{ 'pos-layout': isPosScreen }"
    :workspace-navigation="workspaceNavigation"
  >
    <template #navbar="{ toggleVerticalOverlayNavActive }">
      <div class="d-flex h-100 align-center">
        <div
          id="main-header-left-content"
          class="d-flex align-center w-60 gap-3"
        >
          <NavbarAction :class="{ 'd-lg-none': sidebarLgNone }">
            <VBtn
              aria-label="Open navigation"
              color="default"
              icon="tabler-menu-2"
              title="Open navigation"
              variant="text"
              @click="toggleVerticalOverlayNavActive(true)"
            />
          </NavbarAction>
        </div>

        <VSpacer />

        <NotificationBell class="me-2" />
        <NavbarFullscreenSwitcher class="me-2" />
        <UserProfile />
      </div>
    </template>

    <template
      v-if="workspaceNavigation && activeWorkspace"
      #workspace-rail="{ isWorkspaceMenuCollapsed, toggleWorkspaceMenu }"
    >
      <WorkspaceRail
        :active-workspace="activeWorkspace.id"
        :is-menu-collapsed="isWorkspaceMenuCollapsed"
        @toggle-menu="toggleWorkspaceMenu"
      />
    </template>

    <template #vertical-nav-header="{ toggleIsOverlayNavActive }">
      <div
        v-if="workspaceNavigation && activeWorkspace"
        class="workspace-context-header"
      >
        <span class="workspace-context-copy">
          <strong>{{ t(activeWorkspace.label) }}</strong>
          <small>{{ t('admin::sidebar.workspace') }}</small>
        </span>

        <span
          aria-hidden="true"
          class="workspace-context-icon"
        >
          <VIcon :icon="activeWorkspace.icon" />
        </span>
      </div>

      <template v-else>
        <RouterLink
          class="app-logo app-title-wrapper"
          :to="homeTo || '/admin'"
        >
          <img
            v-if="appStore.logo"
            alt="logo"
            :src="appStore.logo"
            width="40px"
          />

          <h1 class="app-logo-title">
            {{ appStore.appName }}
          </h1>
        </RouterLink>

        <VBtn
          class="d-block"
          :class="{ 'd-lg-none': sidebarLgNone }"
          color="default"
          icon="bx-x"
          variant="text"
          @click="toggleIsOverlayNavActive(false)"
        />
      </template>
    </template>

    <template #before-vertical-nav-items>
      <WorkspaceQuickActions v-if="activeWorkspace?.id === 'restaurant'" />
    </template>

    <template #vertical-nav-content>
      <NavItems
        :target="target"
        :workspace="activeWorkspace?.sidebarWorkspace"
      />
    </template>

    <HeaderPage />
    <slot />

    <template
      v-if="(route?.name as string) !== 'admin.pos.index'"
      #footer
    >
      <Footer />
    </template>
  </VerticalNavLayout>
</template>

<style lang="scss">
@use '@/assets/scss/template/index.scss' as template;
@use '@/assets/scss/layouts/styles/index.scss' as layoutStyles;
</style>

<style lang="scss" scoped>
.meta-key {
  border: thin solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 6px;
  block-size: 1.5625rem;
  line-height: 1.3125rem;
  padding-block: 0.125rem;
  padding-inline: 0.25rem;
}

.app-logo {
  display: flex;
  align-items: center;
  column-gap: 0.75rem;

  .app-logo-title {
    font-size: 1.25rem;
    font-weight: 500;
    line-height: 1.75rem;
    text-transform: uppercase;
  }
}

.workspace-context-header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  block-size: 100%;
  inline-size: 100%;
  min-inline-size: 0;
}

.workspace-context-copy {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  overflow: hidden;
  min-inline-size: 0;

  strong,
  small {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  strong {
    color: var(--forkiva-sidebar-text-color);
    font-size: 1.0625rem;
    font-weight: 700;
    letter-spacing: -0.01em;
    line-height: 1.4rem;
  }

  small {
    color: var(--forkiva-sidebar-muted-color);
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.01em;
    line-height: 1rem;
  }
}

.workspace-context-icon {
  display: grid;
  flex: 0 0 2.75rem;
  border: 1px solid rgba(var(--v-theme-primary), 0.36);
  border-radius: 0.875rem;
  background: rgba(var(--v-theme-primary), 0.14);
  block-size: 2.75rem;
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.03);
  color: rgb(var(--v-theme-primary));
  inline-size: 2.75rem;
  margin-inline-start: auto;
  place-items: center;

  .v-icon {
    font-size: 1.55rem;
  }
}
</style>
