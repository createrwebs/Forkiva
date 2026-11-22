<script lang="ts" setup>
import { routeViewKey } from '@/app/router/viewKey'
import DefaultLayoutWithVerticalNav from '@/app/layouts/components/DefaultLayoutWithVerticalNav.vue'
import { isAdminWorkspaceId } from '@/app/layouts/config/adminWorkspaces.ts'
import { useAuthStore } from '@/modules/auth/stores/authStore.ts'

const route = useRoute()
const authStore = useAuthStore()

const workspace = computed(() =>
  isAdminWorkspaceId(route.meta.workspace) ? route.meta.workspace : 'restaurant',
)

// A token switch can resolve to the same route. Scope the view to the account so
// account-specific dashboard data, permissions, and local layout state are reloaded.
const routerViewKey = computed(() => routeViewKey(route, authStore.activeAccountId))
</script>

<template>
  <DefaultLayoutWithVerticalNav
    target="admin"
    :workspace="workspace"
    workspace-navigation
  >
    <RouterView :key="routerViewKey" />
  </DefaultLayoutWithVerticalNav>
</template>

<style lang="scss">
@use '@/assets/scss/layouts/styles/default-layout';
</style>
