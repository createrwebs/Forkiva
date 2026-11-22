import { createApp } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import { setHttpRouter } from '@/modules/core/api/http.ts'

import { initApp } from './app/bootstrap/initApp'
import { schedulePwaSetup } from './app/bootstrap/schedulePwaSetup'
import { createRoutes } from './app/router'
import Forkiva from './Forkiva.vue'
import '@/assets/css.ts'

const app = createApp(Forkiva)
const APP_SELECTOR = '#app'

async function bootstrap(): Promise<void> {
  // Module manifests carry every target's route and navigation metadata. Keep that
  // registry out of the entry graph and load it while boot data and auth initialize.
  const [, routes] = await Promise.all([initApp({ app }), createRoutes()])

  const router = createRouter({
    history: createWebHistory(import.meta.env.VITE_BASE_PATH || '/'),
    routes,
  })
  setHttpRouter(router)

  router.onError((err, to) => {
    if (
      !err?.message?.includes?.('Failed to fetch dynamically imported module') ||
      localStorage.getItem('vuetify:dynamic-reload')
    ) {
      return
    }

    localStorage.setItem('vuetify:dynamic-reload', 'true')
    location.assign(to.fullPath)
  })

  app.use(router)

  await router.isReady()
  localStorage.removeItem('vuetify:dynamic-reload')
  const appHost = document.querySelector(APP_SELECTOR)

  if (appHost && '__vue_app__' in appHost) {
    const existingApp = (appHost as Element & { __vue_app__?: { unmount: () => void } }).__vue_app__
    existingApp?.unmount()
  }

  app.mount(APP_SELECTOR)
  const cancelPwaSetup = schedulePwaSetup()

  if (import.meta.hot) {
    import.meta.hot.dispose(() => {
      cancelPwaSetup()
      app.unmount()
    })
  }
}

void bootstrap()
