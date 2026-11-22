<script lang="ts" setup>
import { useDisplay } from 'vuetify'
import VerticalNav from '@/app/layouts/components/VerticalNav.vue'
import { readSidebarOpen } from '@/modules/userPreference/helpers/userPreference.ts'
import { useUserPreference } from '@/modules/userPreference/composables/userPreference.ts'

const props = withDefaults(
  defineProps<{
    workspaceNavigation?: boolean
  }>(),
  {
    workspaceNavigation: false,
  },
)

const isOverlayNavActive = ref(false)
const isLayoutOverlayVisible = ref(false)
const toggleIsOverlayNavActive = useToggle(isOverlayNavActive)
const isVerticalNavRail = ref(!readSidebarOpen())
const preferences = useUserPreference()

const route = useRoute()
const { mdAndDown } = useDisplay()

watch(
  () => preferences.values.value?.sidebarOpen,
  (open) => {
    if (typeof open === 'boolean') isVerticalNavRail.value = !open
  },
  { immediate: true },
)

const isVerticalNavRailActive = computed(
  () => isVerticalNavRail.value && !mdAndDown.value && !route.meta.hiddenSidebar,
)
const isWorkspaceMenuCollapsed = computed(() =>
  mdAndDown.value ? !isOverlayNavActive.value : isVerticalNavRail.value,
)

function toggleIsVerticalNavRail() {
  isVerticalNavRail.value = !isVerticalNavRail.value
  preferences.setSidebarOpen(!isVerticalNavRail.value)
}

function toggleWorkspaceMenu() {
  if (mdAndDown.value) {
    toggleIsOverlayNavActive(!isOverlayNavActive.value)
    return
  }

  toggleIsVerticalNavRail()
}

// Keep the page overlay synced with the mobile nav overlay state.
syncRef(isOverlayNavActive, isLayoutOverlayVisible)
</script>

<template>
  <div
    :class="[
      mdAndDown && 'layout-overlay-nav',
      isVerticalNavRailActive && 'layout-vertical-nav-collapsed layout-vertical-nav-rail',
      props.workspaceNavigation && 'layout-workspace-navigation',
      route.meta.layoutWrapperClasses,
    ]"
    class="layout-wrapper layout-nav-type-vertical layout-navbar-static layout-footer-static layout-content-width-fluid"
  >
    <VerticalNav
      :is-overlay-nav-active="isOverlayNavActive"
      :is-rail-active="isVerticalNavRailActive"
      :is-workspace-menu-collapsed="isWorkspaceMenuCollapsed"
      :is-workspace-nav="props.workspaceNavigation"
      :toggle-is-overlay-nav-active="toggleIsOverlayNavActive"
    >
      <template
        v-if="props.workspaceNavigation"
        #workspace-rail
      >
        <slot
          :is-workspace-menu-collapsed="isWorkspaceMenuCollapsed"
          name="workspace-rail"
          :toggle-workspace-menu="toggleWorkspaceMenu"
        />
      </template>

      <template #nav-header>
        <slot
          :is-vertical-nav-rail="isVerticalNavRail"
          :is-vertical-nav-rail-active="isVerticalNavRailActive"
          :toggle-is-overlay-nav-active="toggleIsOverlayNavActive"
          :toggle-is-vertical-nav-rail="toggleIsVerticalNavRail"
          name="vertical-nav-header"
        />
      </template>

      <template #before-nav-items>
        <slot name="before-vertical-nav-items" />
      </template>

      <template #default>
        <slot name="vertical-nav-content" />
      </template>

      <template #after-nav-items>
        <slot name="after-vertical-nav-items" />
      </template>
    </VerticalNav>

    <div
      :class="{
        'padding-inline-start-none': route.meta.hiddenSidebar,
        'layout-content-with-hidden-navbar': route.meta.hiddenTopHeader,
      }"
      class="layout-content-wrapper"
    >
      <header
        :class="{ 'd-none': route.meta.hiddenTopHeader }"
        class="layout-navbar layout-navbar-pinned navbar-blur"
      >
        <div class="navbar-content-container">
          <slot
            :is-vertical-nav-rail="isVerticalNavRail"
            :is-vertical-nav-rail-active="isVerticalNavRailActive"
            :toggle-is-vertical-nav-rail="toggleIsVerticalNavRail"
            :toggle-vertical-overlay-nav-active="toggleIsOverlayNavActive"
            name="navbar"
          />
        </div>
      </header>

      <main class="layout-page-content">
        <div class="page-content-container">
          <slot />
        </div>
      </main>

      <footer class="layout-footer">
        <div class="footer-content-container">
          <slot name="footer" />
        </div>
      </footer>
    </div>

    <div
      :class="{ visible: isLayoutOverlayVisible }"
      class="layout-overlay"
      @click="isLayoutOverlayVisible = !isLayoutOverlayVisible"
    />
  </div>
</template>

<style lang="scss">
@use '@configured-variables' as variables;
@use '@/assets/scss/layouts/styles/placeholders';
@use '@/assets/scss/layouts/styles/mixins';

.layout-wrapper.layout-nav-type-vertical {
  --forkiva-layout-topbar-height: #{variables.$layout-vertical-nav-navbar-height};
  --forkiva-sidebar-accent-rgb: var(--v-theme-primary);
  --forkiva-sidebar-expanded-width: #{variables.$layout-vertical-nav-width};
  --forkiva-sidebar-on-accent-rgb: var(--v-theme-on-primary);
  --forkiva-sidebar-bg: rgb(var(--v-theme-sidebar-surface));
  --forkiva-sidebar-border: rgba(var(--v-theme-on-sidebar-surface), var(--v-border-opacity));
  --forkiva-sidebar-muted-color: rgba(
    var(--v-theme-on-sidebar-surface),
    var(--v-medium-emphasis-opacity)
  );
  --forkiva-sidebar-shadow-bg-rgb: var(--v-theme-sidebar-surface);
  --forkiva-sidebar-text-color: rgba(
    var(--v-theme-on-sidebar-surface),
    var(--v-high-emphasis-opacity)
  );

  &.layout-vertical-nav-rail {
    --forkiva-vertical-nav-rail-width: 4rem;
    --forkiva-vertical-nav-rail-item-size: 2.75rem;
  }

  &.layout-workspace-navigation {
    --forkiva-sidebar-expanded-width: 21.25rem;
    --forkiva-workspace-sidebar-bg: var(--forkiva-sidebar-bg);
    --forkiva-workspace-rail-width: 4.75rem;
  }

  &.layout-workspace-navigation.layout-vertical-nav-rail {
    --forkiva-vertical-nav-rail-width: var(--forkiva-workspace-rail-width);
  }

  // TODO(v2): Check why we need height in vertical nav & min-height in horizontal nav
  block-size: 100%;

  .layout-content-wrapper {
    display: flex;
    flex-direction: column;
    flex-grow: 1;
    min-block-size: 100dvh;
    transition: padding-inline-start 0.2s ease-in-out;
    will-change: padding-inline-start;

    @media screen and (min-width: 1280px) {
      padding-inline-start: var(
        --forkiva-sidebar-expanded-width,
        #{variables.$layout-vertical-nav-width}
      );
    }
  }

  .padding-inline-start-none {
    padding-inline-start: 0;
  }

  .layout-vertical-nav {
    background-color: var(--forkiva-sidebar-bg) !important;
    color: var(--forkiva-sidebar-text-color);

    &.layout-vertical-nav-workspace .vertical-nav-context {
      background-color: var(--forkiva-sidebar-bg) !important;
      color: var(--forkiva-sidebar-text-color);

      .nav-header {
        border-block-end: 1px solid var(--forkiva-sidebar-border);
      }
    }

    &.layout-vertical-nav-workspace.layout-vertical-nav-workspace-menu-collapsed
      .vertical-nav-context {
      border: 0;
      background: transparent !important;
      box-shadow: none;
    }

    .nav-header {
      box-sizing: border-box;
      block-size: var(--forkiva-layout-topbar-height);
      background-color: var(--forkiva-sidebar-bg);
      margin-block-start: 0 !important;
      min-block-size: var(--forkiva-layout-topbar-height);
      padding-block: 0 !important;
    }

    .nav-items {
      background-color: var(--forkiva-sidebar-bg);
    }

    .vertical-nav-items-shadow {
      background: linear-gradient(
        var(--forkiva-sidebar-bg) 5%,
        rgba(var(--forkiva-sidebar-shadow-bg-rgb), 75%) 45%,
        rgba(var(--forkiva-sidebar-shadow-bg-rgb), 20%) 80%,
        transparent
      );
    }

    .app-logo,
    .app-logo-title,
    .nav-group-arrow,
    .nav-item-title,
    .nav-link > a,
    .nav-group-label {
      color: var(--forkiva-sidebar-text-color);
    }

    .nav-item-icon,
    .nav-section-title {
      color: var(--forkiva-sidebar-muted-color) !important;
    }

    .nav-link > .router-link-exact-active,
    .nav-link > .router-main-link-active,
    .nav-group.open
      .nav-group-label:has(
        + .nav-group-children-wrapper > .nav-group-children > .nav-link > .router-link-exact-active
      ),
    .nav-group.open
      .nav-group-label:has(
        + .nav-group-children-wrapper > .nav-group-children > .nav-link > .router-main-link-active
      ) {
      background-color: rgb(var(--forkiva-sidebar-accent-rgb));
      color: rgb(var(--forkiva-sidebar-on-accent-rgb));

      .nav-group-arrow,
      .nav-item-icon,
      .nav-item-title {
        color: rgb(var(--forkiva-sidebar-on-accent-rgb)) !important;
      }
    }

    &.layout-vertical-nav-workspace
      .vertical-nav-context
      .nav-group-children
      .nav-link
      > .router-link-exact-active,
    &.layout-vertical-nav-workspace
      .vertical-nav-context
      .nav-group-children
      .nav-link
      > .router-main-link-active {
      background-color: rgba(var(--forkiva-sidebar-accent-rgb), 0.1) !important;
      color: rgb(var(--forkiva-sidebar-accent-rgb));

      .nav-item-icon,
      .nav-item-title {
        color: rgb(var(--forkiva-sidebar-accent-rgb)) !important;
      }
    }
  }

  .layout-navbar {
    z-index: variables.$layout-vertical-nav-layout-navbar-z-index;

    .navbar-content-container {
      block-size: var(--forkiva-layout-topbar-height);
    }

    @at-root {
      .layout-wrapper.layout-nav-type-vertical {
        .layout-navbar {
          @if variables.$layout-vertical-nav-navbar-is-contained {
            @include mixins.boxed-content;
          }

          // else
          @else {
            .navbar-content-container {
              @include mixins.boxed-content;
            }
          }
        }
      }
    }
  }

  .layout-navbar.layout-navbar-pinned {
    position: fixed;
    z-index: variables.$layout-vertical-nav-layout-navbar-z-index;
    flex-shrink: 0;
    border-radius: 0 !important;
    background-color: rgb(var(--v-theme-surface));
    block-size: var(--forkiva-layout-topbar-height);
    border-block-end: thin solid rgba(var(--v-border-color), var(--v-border-opacity));
    box-shadow: none !important;
    inline-size: calc(
      100% - var(--forkiva-sidebar-expanded-width, #{variables.$layout-vertical-nav-width})
    );
    inset-block-start: 0;
    inset-inline-start: var(
      --forkiva-sidebar-expanded-width,
      #{variables.$layout-vertical-nav-width}
    );
    margin: 0 !important;
    padding: 0 !important;

    &.navbar-blur::after {
      content: none;
    }

    > .navbar-content-container {
      border-radius: 0 !important;
      background-color: inherit;
      block-size: 100% !important;
      box-shadow: none !important;
      inline-size: 100%;
      margin: 0 !important;
      max-inline-size: none !important;
      padding-block: 0 !important;
      padding-inline: 1.25rem !important;
    }
  }

  .layout-page-content {
    padding-block-start: calc(var(--forkiva-layout-topbar-height) + 0.5rem);
  }

  .layout-content-wrapper.layout-content-with-hidden-navbar {
    .layout-page-content {
      padding-block-start: 0.5rem;
    }
  }

  &.layout-vertical-nav-collapsed .layout-navbar.layout-navbar-pinned {
    @media screen and (min-width: 1280px) {
      inline-size: calc(
        100% - var(
            --forkiva-vertical-nav-rail-width,
            #{variables.$layout-vertical-nav-collapsed-width}
          )
      );
      inset-inline-start: var(
        --forkiva-vertical-nav-rail-width,
        #{variables.$layout-vertical-nav-collapsed-width}
      );
    }
  }

  .layout-content-wrapper.padding-inline-start-none {
    .layout-navbar.layout-navbar-pinned {
      inline-size: 100%;
      inset-inline-start: 0;
    }
  }

  @media screen and (max-width: 1279px) {
    .layout-navbar.layout-navbar-pinned {
      inline-size: 100%;
      inset-inline-start: 0;
    }
  }

  &.layout-navbar-sticky .layout-navbar {
    @extend %layout-navbar-sticky;
  }

  &.layout-navbar-hidden .layout-navbar {
    @extend %layout-navbar-hidden;
  }

  // 👉 Footer
  .layout-footer {
    @include mixins.boxed-content;
  }

  // 👉 Layout overlay
  .layout-overlay {
    position: fixed;
    z-index: variables.$layout-overlay-z-index;
    background-color: rgb(0 0 0 / 60%);
    cursor: pointer;
    inset: 0;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.25s ease-in-out;
    will-change: opacity;

    &.visible {
      opacity: 1;
      pointer-events: auto;
    }
  }

  // Adjust right column pl when vertical nav is collapsed
  &.layout-vertical-nav-collapsed .layout-content-wrapper {
    @media screen and (min-width: 1280px) {
      padding-inline-start: var(
        --forkiva-vertical-nav-rail-width,
        #{variables.$layout-vertical-nav-collapsed-width}
      );
    }
  }

  &.layout-vertical-nav-collapsed .layout-content-wrapper.padding-inline-start-none {
    @media screen and (min-width: 1280px) {
      padding-inline-start: 0;
    }
  }

  // 👉 Content height fixed
  &.layout-content-height-fixed {
    .layout-content-wrapper {
      max-block-size: 100dvh;
    }

    .layout-page-content {
      display: flex;
      overflow: hidden;

      .page-content-container {
        inline-size: 100%;

        > :first-child {
          max-block-size: 100%;
          overflow-y: auto;
        }
      }
    }
  }
}
</style>
