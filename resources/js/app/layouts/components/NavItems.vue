<script lang="ts" setup>
import type { SidebarItem } from '@/modules/core/contracts/SidebarItem.ts'
import type { TargetName } from '@/modules/core/contracts/Target'

import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import VerticalNavGroup from '@/app/layouts/components/VerticalNavGroup.vue'
import VerticalNavLink from '@/app/layouts/components/VerticalNavLink.vue'

import VerticalNavSectionTitle from '@/app/layouts/components/VerticalNavSectionTitle.vue'
import { useAuth } from '@/modules/auth/composables/auth'
import { sidebarRegistry } from '@/modules/core/services/SidebarService.ts'

const props = defineProps<{
  target: TargetName
  workspace?: string
}>()

const { t } = useI18n()
const { hasPermission } = useAuth()

const isVisible = (visible?: boolean | (() => boolean)): boolean => {
  if (typeof visible === 'function') {
    return visible()
  }

  return visible !== false
}

const isAllowed = (permission?: string[] | string): boolean => {
  if (!permission) {
    return true
  }

  if (Array.isArray(permission) && permission.length === 0) {
    return true
  }

  return hasPermission(permission)
}

function mapLinkItem(item: SidebarItem) {
  return {
    title: translate(item),
    to: item.to,
    exact: item.exact,
    icon: item.icon || undefined,
    href: item.href,
    target: item.target,
    disable: item.disable,
    badgeContent: item.badgeContent,
    badgeClass: item.badgeClass,
  }
}

function translate(item: SidebarItem): string {
  return t(item.label)
}

const sidebarItems = computed(() =>
  sidebarRegistry
    .build(props.target, false, props.workspace)
    .filter((item) => isVisible(item.visible) && isAllowed(item.permission)),
)
</script>

<template>
  <template
    v-for="item in sidebarItems"
    :key="item.key"
  >
    <VerticalNavSectionTitle
      v-if="item.isHeading"
      :item="{
        heading: translate(item),
        badgeContent: item.badgeContent,
        badgeClass: item.badgeClass,
      }"
    />

    <VerticalNavLink
      v-else-if="!item.children || item.children.length === 0"
      :item="mapLinkItem(item)"
    />

    <VerticalNavGroup
      v-else
      :item="{ title: translate(item), icon: item.icon }"
    >
      <template
        v-for="subItem in item.children"
        :key="subItem.key"
      >
        <VerticalNavLink
          v-if="isVisible(subItem.visible) && isAllowed(subItem.permission)"
          :item="mapLinkItem(subItem)"
        />
      </template>
    </VerticalNavGroup>
  </template>
</template>
