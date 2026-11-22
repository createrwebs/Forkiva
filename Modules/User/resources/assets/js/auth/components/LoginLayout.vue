<script setup lang="ts">
import { useAppStore } from '@/modules/core/stores/appStore.ts'

const appStore = useAppStore()
</script>

<template>
  <VThemeProvider theme="light">
    <main class="login-page">
      <div class="login-panel">
        <section class="login-card">
          <header class="login-branding">
            <RouterLink
              :aria-label="appStore.appName"
              class="login-brand"
              to="/"
            >
              <span class="login-brand__mark">
                <img
                  v-if="appStore.logo"
                  :src="appStore.logo"
                  alt=""
                />
                <VIcon
                  v-else
                  icon="tabler-tools-kitchen-2"
                  size="24"
                />
              </span>
              <span class="login-brand__name">{{ appStore.appName }}</span>
            </RouterLink>
          </header>
          <slot />
        </section>
      </div>
    </main>
  </VThemeProvider>
</template>

<style lang="scss" scoped>
.login-page {
  position: relative;
  isolation: isolate;
  display: grid;
  place-items: center;
  min-block-size: 100dvh;
  padding: 3rem 1.25rem;
  overflow: clip;
  background: #f5f6f8;
  color: #202b3c;
}
.login-page::before,
.login-page::after {
  position: absolute;
  z-index: -1;
  inline-size: 70vw;
  block-size: 70vw;
  border: 1px solid #e0e4eb;
  border-radius: 50%;
  pointer-events: none;
  content: '';
}
.login-page::before {
  inset-inline-end: 77%;
  inset-block-start: -20vw;
}
.login-page::after {
  inset-inline-start: 77%;
  inset-block-end: -12vw;
}
.login-panel {
  inline-size: 100%;
  max-inline-size: 26.25rem;
  min-inline-size: 0;
}
.login-card {
  padding: 1.75rem 2.25rem 2rem;
  border: 1px solid #dde2ea;
  border-block-start: 3px solid #f58220;
  border-radius: 1rem;
  background: #fff;
  box-shadow:
    0 12px 36px #202b3c08,
    0 2px 6px #202b3c04;
}
.login-branding {
  display: flex;
  justify-content: center;
  margin-block-end: 1.75rem;
}
.login-brand {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  color: #202b3c;
  text-decoration: none;
}
.login-brand__mark {
  display: grid;
  place-items: center;
  inline-size: 2.25rem;
  block-size: 2.25rem;
  color: #e16a0b;
}
.login-brand__mark img {
  inline-size: 100%;
  block-size: 100%;
  object-fit: contain;
}
.login-brand__name {
  font-size: 1.5rem;
  line-height: 1;
  font-weight: 750;
  letter-spacing: -0.04em;
}
.login-brand:focus-visible {
  outline: 2px solid #e16a0b;
  outline-offset: 5px;
}
@media (max-width: 480px) {
  .login-page {
    padding: 2rem 1rem;
  }
  .login-card {
    padding: 1.5rem 1.5rem 1.75rem;
  }
}
</style>
