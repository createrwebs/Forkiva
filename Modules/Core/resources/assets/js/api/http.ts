import type { RouteLocationRaw, Router } from 'vue-router'
import axios from 'axios'
import { useAuthStore } from '@/modules/auth/stores/authStore.ts'
import { getDefaultRoute } from '@/modules/auth/utils/defaultRoute.ts'
import { useAppStore } from '@/modules/core/stores/appStore.ts'

let appRouter: Router | null = null

export const API_BASE_URL = import.meta.env.VITE_API_URL?.trim() || '/api'

export function setHttpRouter(router: Router): void {
  appRouter = router
}

export const http = axios.create({
  baseURL: API_BASE_URL,
  withCredentials: true,
  headers: {
    Accept: 'application/json',
  },
})

http.interceptors.request.use((config) => {
  const auth = useAuthStore()
  const app = useAppStore()

  if (!('Authorization' in config.headers) && auth.getToken) {
    config.headers.Authorization = `Bearer ${auth.getToken}`
  }

  config.headers['X-Forkiva-Locale'] = app.appCurrentLocale || 'en'
  config.headers['X-Forkiva-Client'] = 'web'
  return config
})

http.interceptors.response.use(
  (response) => response,
  async (error) => {
    const url = error?.config?.url ?? ''
    const isLoginRequest = url.includes('/auth/login') || url.includes('/auth/passkeys/login')

    if (error.response?.status === 401 && !isLoginRequest) {
      if (error?.config?.url?.includes('/auth/check')) {
        throw error
      }

      const auth = useAuthStore()
      await auth.logout(true)
      if (appRouter) {
        await appRouter.push(
          auth.isAuthenticated
            ? getDefaultRoute(auth, appRouter)
            : ({ name: 'auth.login' } as unknown as RouteLocationRaw),
        )
      }
    }
    throw error
  },
)
