<script lang="ts" setup>
import type { Component } from 'vue'
import { computed, defineAsyncComponent, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import type { TargetName } from '@/modules/core/contracts/Target'
import { RouterView, useRoute } from 'vue-router'
import { useAppStore } from '@/modules/core/stores/appStore.ts'
import { useAuthStore } from '@/modules/auth/stores/authStore.ts'
import { useUserPreferenceStore } from '@/modules/userPreference/stores/userPreferenceStore.ts'
import { useLanguage } from '@/modules/translation/composables/language.ts'
import i18n from './app/plugins/i18n.ts'
import vuetify from './app/plugins/vuetify/index.ts'
import { resolveThemeMode } from './app/plugins/vuetify/theme.ts'
import { resolveLayout } from './app/router/layoutResolver'

const ImportTaskDock = defineAsyncComponent(
  () => import('@/modules/import/Partials/ImportTaskDock.vue'),
)

const route = useRoute()
const appStore = useAppStore()
const authStore = useAuthStore()
const preferences = useUserPreferenceStore()
const { parseTitlePage } = useLanguage()

const layout = computed<Component | void>(() =>
  resolveLayout(route.meta.target as TargetName | undefined),
)
const isPwaMode = ref(false)

watch(
  () => authStore.activeAccountId,
  () => void preferences.load(),
)

watch(
  () => appStore.appCurrentLocale,
  (locale) => {
    i18n.global.locale.value = locale
    vuetify.locale.current.value = locale
  },
)

watch(
  () => appStore.appThemeMode,
  (mode) => vuetify.theme.change(resolveThemeMode(mode)),
)

function detectPwaMode(): boolean {
  const nav = window.navigator as Navigator & { standalone?: boolean }

  return (
    window.matchMedia('(display-mode: standalone)').matches ||
    window.matchMedia('(display-mode: minimal-ui)').matches ||
    window.matchMedia('(display-mode: window-controls-overlay)').matches ||
    nav.standalone === true ||
    document.referrer.startsWith('android-app://')
  )
}

function syncPwaMode() {
  isPwaMode.value = detectPwaMode()
}

onMounted(() => {
  syncPwaMode()
  window.matchMedia('(display-mode: standalone)').addEventListener('change', syncPwaMode)
  window.matchMedia('(display-mode: minimal-ui)').addEventListener('change', syncPwaMode)
  window
    .matchMedia('(display-mode: window-controls-overlay)')
    .addEventListener('change', syncPwaMode)
})

onBeforeUnmount(() => {
  window.matchMedia('(display-mode: standalone)').removeEventListener('change', syncPwaMode)
  window.matchMedia('(display-mode: minimal-ui)').removeEventListener('change', syncPwaMode)
  window
    .matchMedia('(display-mode: window-controls-overlay)')
    .removeEventListener('change', syncPwaMode)
})

watch(
  [() => route.meta.title, isPwaMode],
  ([newTitle]) => {
    const pageTitle = newTitle
      ? parseTitlePage(newTitle as string, route.meta?.transParam as Record<string, string>)
      : appStore.appName

    useTitle(isPwaMode.value || !newTitle ? pageTitle : `${pageTitle} | ${appStore.appName}`)
  },
  { immediate: true },
)
</script>

<template>
  <v-app>
    <component :is="layout">
      <RouterView />
    </component>
    <ImportTaskDock v-if="authStore.isAuthenticated" />
  </v-app>
</template>
