<script lang="ts" setup></script>

<template>
  <div
    class="layout-wrapper layout-blank"
    data-allow-mismatch
  >
    <RouterView />
  </div>
</template>

<style lang="scss">
@use '@/assets/scss/template/index.scss' as template;
</style>

<style lang="scss" scoped>
.layout-wrapper.layout-blank {
  flex-direction: column;
}
</style>
