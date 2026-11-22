import type { App } from 'vue'

import axios from 'axios'
import { setupBootData } from '@/app/bootstrap/setupBootData'
import { setupI18n, setupToast, setupVuetify } from '@/app/plugins'
import { check } from '@/modules/auth/api/auth.api.ts'
import { useAuthStore } from '@/modules/auth/stores/authStore.ts'
import EnglishNumbersWithDecimal from '@/modules/core/directives/english-numbers-with-decimal.ts'
import EnglishNumbers from '@/modules/core/directives/english-numbers.ts'
import { setupStore } from '@/modules/core/stores'
import { useUserPreferenceStore } from '@/modules/userPreference/stores/userPreferenceStore.ts'

interface InitAppOptions {
  app: App
}

async function setupAuthSession(): Promise<void> {
  const authStore = useAuthStore()
  await authStore.restoreAuth()

  while (authStore.isAuthenticated) {
    try {
      const response = await check()
      authStore.setUser(response?.data?.body?.user)
      break
    } catch (error) {
      if ((axios.isAxiosError(error) && error.response?.status === 401) || !authStore.getUser) {
        await authStore.logout(true)
        continue
      }

      break
    }
  }
}

export async function initApp(_: InitAppOptions): Promise<void> {
  setupStore(_.app)

  _.app.directive('integer-en', EnglishNumbers)
  _.app.directive('decimal-en', EnglishNumbersWithDecimal)

  await Promise.all([setupBootData(), setupAuthSession()])
  await useUserPreferenceStore().load()

  setupI18n(_.app)
  setupVuetify(_.app)
  setupToast(_.app)
}
