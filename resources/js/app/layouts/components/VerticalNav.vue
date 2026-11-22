<script lang="ts" setup>
import type { Component } from 'vue'
import { storeToRefs } from 'pinia'
import { useDisplay } from 'vuetify'
import { useAppStore } from '@/modules/core/stores/appStore.ts'

interface Props {
  tag?: string | Component
  isOverlayNavActive: boolean
  isRailActive?: boolean
  isWorkspaceMenuCollapsed?: boolean
  isWorkspaceNav?: boolean
  toggleIsOverlayNavActive: (value: boolean) => void
}

const props = withDefaults(defineProps<Props>(), {
  isRailActive: false,
  isWorkspaceMenuCollapsed: false,
  isWorkspaceNav: false,
  tag: 'aside',
})

const { mdAndDown } = useDisplay()
const appStore = useAppStore()
const { isRtl, logo, appName } = storeToRefs(appStore)

const refNav = ref()
const isRailHovered = ref(false)
let activeNavScrollFrame: number | undefined
let activeNavScrollTimeout: number | undefined

/*
ℹ️ Close overlay side when route is changed
Close overlay vertical nav when link is clicked
*/
const route = useRoute()

watch(
  () => route.fullPath,
  () => {
    props.toggleIsOverlayNavActive(false)
  },
)

const isVerticalNavScrolled = ref(false)
const updateIsVerticalNavScrolled = (val: boolean) => (isVerticalNavScrolled.value = val)

function handleNavScroll(evt: Event) {
  isVerticalNavScrolled.value = (evt.target as HTMLElement).scrollTop > 0
}

function setRailHovered(value: boolean) {
  isRailHovered.value = props.isRailActive && !props.isWorkspaceNav && value
}

watch(
  () => props.isRailActive,
  (value) => {
    if (!value) {
      isRailHovered.value = false
    }
  },
)

watch(
  () => props.isOverlayNavActive,
  (value) => {
    if (value) {
      scheduleActiveNavScroll()
    }
  },
)

function getNavItemsContainer() {
  return refNav.value?.querySelector?.('.nav-items') as HTMLElement | null
}

function getActiveNavTarget(container: HTMLElement) {
  const activeLink = container.querySelector<HTMLElement>(
    '.router-link-exact-active, .router-main-link-active',
  )

  if (!activeLink) {
    return null
  }

  const activeGroup = activeLink.closest<HTMLElement>('.nav-group')

  if (activeGroup && props.isRailActive) {
    return activeGroup.querySelector<HTMLElement>('.nav-group-label') ?? activeGroup
  }

  return activeLink.closest<HTMLElement>('.nav-link, .nav-group') ?? activeLink
}

function scrollActiveNavItemIntoView() {
  const container = getNavItemsContainer()

  if (!container) {
    return
  }

  const target = getActiveNavTarget(container)

  if (!target) {
    return
  }

  const containerRect = container.getBoundingClientRect()
  const targetRect = target.getBoundingClientRect()

  if (!containerRect.height || !targetRect.height) {
    return
  }

  const targetOffsetTop = container.scrollTop + targetRect.top - containerRect.top

  if (targetOffsetTop <= container.clientHeight / 2) {
    container.scrollTo({ behavior: 'auto', top: 0 })
    return
  }

  const targetCenter = targetRect.top - containerRect.top + targetRect.height / 2
  const nextScrollTop = container.scrollTop + targetCenter - container.clientHeight / 2

  container.scrollTo({
    behavior: 'auto',
    top: Math.max(0, nextScrollTop),
  })
}

function scheduleActiveNavScroll() {
  void nextTick(() => {
    if (typeof window === 'undefined') {
      return
    }

    if (activeNavScrollFrame !== undefined) {
      window.cancelAnimationFrame(activeNavScrollFrame)
    }

    if (activeNavScrollTimeout !== undefined) {
      window.clearTimeout(activeNavScrollTimeout)
    }

    activeNavScrollFrame = window.requestAnimationFrame(() => {
      activeNavScrollFrame = undefined
      scrollActiveNavItemIntoView()
    })

    activeNavScrollTimeout = window.setTimeout(() => {
      activeNavScrollTimeout = undefined
      scrollActiveNavItemIntoView()
    }, 350)
  })
}

onMounted(() => {
  scheduleActiveNavScroll()
})

onBeforeUnmount(() => {
  if (typeof window === 'undefined') {
    return
  }

  if (activeNavScrollFrame !== undefined) {
    window.cancelAnimationFrame(activeNavScrollFrame)
  }

  if (activeNavScrollTimeout !== undefined) {
    window.clearTimeout(activeNavScrollTimeout)
  }
})
</script>

<template>
  <Component
    :is="props.tag"
    ref="refNav"
    class="layout-vertical-nav overlay-nav"
    :class="[
      {
        visible: isOverlayNavActive,
        scrolled: isVerticalNavScrolled,
        hovered: isRailHovered,
        'overlay-nav': mdAndDown,
        'layout-vertical-nav-rail': props.isRailActive,
        'layout-vertical-nav-workspace-menu-collapsed': props.isWorkspaceMenuCollapsed,
        'layout-vertical-nav-workspace': props.isWorkspaceNav,
        'layout-vertical-nav-rtl': isRtl,
        'layout-with-hidden-vertical-nav': route.meta.hiddenSidebar,
        'layout-with-hidden-vertical-nav-rtl': isRtl && route.meta.hiddenSidebar,
      },
    ]"
    data-allow-mismatch
    @focusin="setRailHovered(true)"
    @focusout="setRailHovered(false)"
    @mouseenter="setRailHovered(true)"
    @mouseleave="setRailHovered(false)"
  >
    <slot name="workspace-rail" />

    <div
      :aria-hidden="props.isWorkspaceNav && props.isWorkspaceMenuCollapsed ? 'true' : undefined"
      class="vertical-nav-context"
      :id="props.isWorkspaceNav ? 'workspace-sub-sidebar' : undefined"
      :inert="props.isWorkspaceNav && props.isWorkspaceMenuCollapsed"
    >
      <!-- 👉 Header -->
      <div class="nav-header">
        <slot name="nav-header">
          <RouterLink
            class="app-logo app-title-wrapper"
            to="/"
          >
            <img
              v-if="logo"
              alt="logo"
              height="50px"
              :src="logo"
              width="50px"
            />

            <h1 class="leading-normal">
              {{ appName }}
            </h1>
          </RouterLink>
        </slot>
      </div>

      <slot name="before-nav-items">
        <div class="vertical-nav-items-shadow" />
      </slot>

      <slot
        name="nav-items"
        :update-is-vertical-nav-scrolled="updateIsVerticalNavScrolled"
      >
        <ul
          class="nav-items"
          @scroll.passive="handleNavScroll"
        >
          <slot />
        </ul>
      </slot>

      <slot name="after-nav-items" />
    </div>
  </Component>
</template>

<style lang="scss" scoped>
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
</style>

<style lang="scss">
@use '@configured-variables' as variables;
@use '@/assets/scss/layouts/styles/mixins';

// 👉 Vertical Nav
.layout-vertical-nav {
  position: fixed;
  z-index: variables.$layout-vertical-nav-z-index;
  display: flex;
  flex-direction: column;
  block-size: 100%;
  inline-size: var(--forkiva-sidebar-expanded-width, #{variables.$layout-vertical-nav-width});
  inset-block-start: 0;
  inset-inline-start: 0;
  transition:
    inline-size 0.25s ease-in-out,
    box-shadow 0.25s ease-in-out;
  will-change: transform, inline-size;

  .vertical-nav-context {
    position: relative;
    display: flex;
    flex: 1 1 auto;
    flex-direction: column;
    overflow: hidden;
    block-size: 100%;
    inline-size: 100%;
    min-inline-size: 0;
    transition:
      flex-basis 0.2s ease,
      inline-size 0.2s ease,
      opacity 0.15s ease,
      visibility 0s linear;
  }

  &.layout-vertical-nav-workspace {
    flex-direction: row;

    .vertical-nav-context {
      border-inline-end: thin solid rgba(var(--v-border-color), var(--v-border-opacity));
      background: rgb(var(--v-theme-surface));
    }

    &.layout-vertical-nav-workspace-menu-collapsed {
      box-shadow: none;

      .vertical-nav-context {
        visibility: hidden;
        flex: 0 0 0;
        border: 0;
        background: transparent;
        inline-size: 0;
        opacity: 0;
        pointer-events: none;
      }
    }
  }

  .nav-header {
    display: flex;
    align-items: center;

    .header-action {
      cursor: pointer;

      @at-root {
        #{variables.$selector-vertical-nav-mini} .nav-header .header-action {
          &.nav-pin,
          &.nav-unpin {
            display: none !important;
          }
        }
      }
    }
  }

  .app-title-wrapper {
    margin-inline-end: auto;
  }

  .nav-items {
    block-size: 100%;
    overflow-x: hidden;
    overflow-y: auto;
  }

  .nav-item-title {
    overflow: hidden;
    margin-inline-end: auto;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  // 👉 Collapsed
  .layout-vertical-nav-collapsed & {
    &:not(.hovered) {
      inline-size: var(
        --forkiva-vertical-nav-rail-width,
        #{variables.$layout-vertical-nav-collapsed-width}
      );

      .nav-header {
        justify-content: center;
        padding-inline: 0;

        .app-title-wrapper {
          justify-content: center;
          margin-inline-end: 0;
        }

        .app-logo {
          column-gap: 0;
          justify-content: center;
          inline-size: 100%;
          transform: none !important;
        }

        .app-logo h1,
        .app-logo-title,
        .nav-rail-toggle {
          display: none !important;
        }
      }

      .nav-items {
        display: flex;
        align-items: center;
        flex-direction: column;
        padding-inline: 0;
      }

      .nav-link,
      .nav-group {
        display: flex;
        flex: 0 0 auto;
        inline-size: 100%;
        justify-content: center;
        margin-inline: 0;
        overflow: visible;
      }

      .nav-link > a,
      .nav-group-label {
        position: relative;
        box-sizing: border-box;
        flex: 0 0 var(--forkiva-vertical-nav-rail-item-size, 3rem) !important;
        block-size: var(--forkiva-vertical-nav-rail-item-size, 3rem) !important;
        inline-size: var(--forkiva-vertical-nav-rail-item-size, 3rem) !important;
        justify-content: center;
        border-radius: 0.875rem;
        margin-inline: calc(
          (
              var(
                  --forkiva-vertical-nav-rail-width,
                  #{variables.$layout-vertical-nav-collapsed-width}
                ) - var(--forkiva-vertical-nav-rail-item-size, 3rem)
            ) /
            2
        ) !important;
        padding: 0 !important;
      }

      .nav-item-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-inline: 0;
        margin-inline-end: 0 !important;
      }

      .nav-item-title,
      .nav-item-badge,
      .nav-group-arrow {
        display: none !important;
      }

      .router-link-exact-active,
      .router-main-link-active,
      .nav-group-label:has(
        + .nav-group-children-wrapper > .nav-group-children > .nav-link > .router-link-exact-active
      ),
      .nav-group-label:has(
        + .nav-group-children-wrapper > .nav-group-children > .nav-link > .router-main-link-active
      ) {
        &::after {
          content: none !important;
          display: none !important;
        }
      }

      .nav-group-children-wrapper {
        display: none;
      }

      .nav-section-title {
        flex: 0 0 auto;
        inline-size: 100%;
        block-size: 0.75rem;
        margin-block: 0.5rem;

        .title-wrapper {
          justify-content: center;
          gap: 0;
        }

        .title-text {
          overflow: hidden;
          inline-size: 100%;
          color: transparent;
          font-size: 0;

          &::before {
            flex: 1 1 auto;
            margin-inline-start: 0;
          }
        }
      }
    }

    &.layout-vertical-nav-workspace:not(.hovered) {
      .vertical-nav-context {
        visibility: hidden;
        flex: 0 0 0;
        border: 0;
        background: transparent;
        inline-size: 0;
        opacity: 0;
        pointer-events: none;
      }
    }
  }
}

@media (prefers-reduced-motion: reduce) {
  .layout-vertical-nav,
  .layout-vertical-nav .vertical-nav-context {
    transition: none;
  }
}

// Small screen vertical nav transition
@media (max-width: 1279px) {
  .layout-vertical-nav {
    inline-size: min(22.5rem, 92vw);

    &:not(.visible) {
      transform: translateX(-100%);
    }

    transition: transform 0.25s ease-in-out;

    &.layout-vertical-nav-workspace {
      flex-direction: column;

      .vertical-nav-context {
        inline-size: 100%;
      }
    }
  }

  .layout-vertical-nav-rtl {
    &:not(.visible) {
      transform: translateX(100%);
    }
  }
}

.layout-with-hidden-vertical-nav {
  &:not(.visible) {
    transform: translateX(-100%);
  }

  transition: transform 0.25s ease-in-out;
}

.layout-with-hidden-vertical-nav-rtl {
  &:not(.visible) {
    transform: translateX(100%);
  }
}
</style>
