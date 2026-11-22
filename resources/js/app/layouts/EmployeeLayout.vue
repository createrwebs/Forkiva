<script lang="ts" setup>
import { routeViewKey } from '@/app/router/viewKey'
import DefaultLayoutWithVerticalNav from '@/app/layouts/components/DefaultLayoutWithVerticalNav.vue'

const route = useRoute()

const routerViewKey = computed(() => routeViewKey(route))
</script>

<template>
  <DefaultLayoutWithVerticalNav
    home-to="/employee"
    :target="'employee'"
  >
    <div class="employee-layout-shell">
      <RouterView :key="routerViewKey" />
    </div>
  </DefaultLayoutWithVerticalNav>
</template>

<style lang="scss">
@use '@/assets/scss/layouts/styles/default-layout';

.employee-layout-shell {
  min-height: calc(100vh - 120px);
}
</style>
