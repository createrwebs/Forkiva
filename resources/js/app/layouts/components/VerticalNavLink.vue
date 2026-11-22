<script lang="ts" setup>
import type { NavLink } from '@/modules/core/contracts/Theme.ts'

const props = defineProps<{ item: NavLink }>()
const route = useRoute()

const normalizeName = (name?: string | symbol | null) => {
  if (typeof name === 'string') {
    return name.split('.').filter(Boolean).slice(0, 2).join('.')
  }

  return ''
}

const isActive = computed(() => {
  const target = props.item.to
  const targetName =
    target && typeof target === 'object' && 'name' in target ? target.name : undefined
  if (props.item.exact) {
    return route.name === targetName
  }

  const targetParams =
    target && typeof target === 'object' && 'params' in target ? target.params : undefined

  if (targetParams && Object.keys(targetParams).length > 0) {
    return (
      route.name === targetName &&
      Object.entries(targetParams).every(
        ([key, value]) => String(route.params[key]) === String(value),
      )
    )
  }

  return normalizeName(route.name) === normalizeName(targetName ?? null)
})
</script>

<template>
  <li
    :class="{ disabled: item.disable }"
    class="nav-link"
  >
    <Component
      :is="item.to ? 'RouterLink' : 'a'"
      :class="{ 'router-main-link-active': isActive }"
      :href="item.href"
      :target="item.target"
      :to="item.to"
    >
      <VIcon
        :icon="item.icon || 'tabler-circle-filled'"
        class="nav-item-icon"
      />
      <!-- 👉 Title -->
      <span class="nav-item-title">
        {{ item.title }}
      </span>
      <span
        :class="item.badgeClass"
        class="nav-item-badge"
      >
        {{ item.badgeContent }}
      </span>
    </Component>
  </li>
</template>

<style lang="scss">
.layout-vertical-nav {
  .nav-link a {
    display: flex;
    align-items: center;
    cursor: pointer;
  }
}
</style>
