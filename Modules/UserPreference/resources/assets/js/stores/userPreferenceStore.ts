import { defineStore } from 'pinia'
import { getUserPreferences, updateUserPreference } from '../api/userPreference.api.ts'
import type { UserPreferenceName, UserPreferenceValues } from '../contracts/UserPreference.ts'
import {
  normalizeUserPreferences,
  readSidebarOpen,
  rememberSidebarOpen,
} from '../helpers/userPreference.ts'
import { useAuthStore } from '@/modules/auth/stores/authStore.ts'
import { useAppStore } from '@/modules/core/stores/appStore.ts'

const pendingWrites = new Map<string, Promise<boolean>>()

export const useUserPreferenceStore = defineStore('userPreferences', {
  state: () => ({
    accountId: null as number | null,
    values: null as UserPreferenceValues | null,
    serverLoaded: false,
    revision: 0,
  }),
  actions: {
    async load(): Promise<void> {
      const auth = useAuthStore()
      const app = useAppStore()
      const accountId = auth.activeAccountId
      const token = auth.getToken
      const changedAccount = this.accountId !== null && this.accountId !== accountId
      const revision = ++this.revision
      this.accountId = accountId
      this.serverLoaded = false

      const defaults: UserPreferenceValues = {
        language: changedAccount ? app.settings.locale : app.appCurrentLocale,
        themeMode: changedAccount ? app.settings.default_theme_mode : app.appThemeMode,
        sidebarOpen: changedAccount ? true : readSidebarOpen(),
        dashboardLayout: null,
        dashboardFilters: null,
        giftCardAnalytics: null,
        inventoryAnalytics: null,
        reservationAnalytics: null,
        accountingOverview: null,
        deliveryDashboard: null,
        financialAnalytics: null,
        payrollAnalytics: null,
        hrDashboard: null,
      }

      if (changedAccount || accountId === null) {
        this.apply(defaults)
      }

      if (accountId === null || !token) {
        return
      }

      try {
        const response = await getUserPreferences(token)
        if (response.status !== 200) throw new Error('Unable to load user preferences')
        if (revision !== this.revision || auth.activeAccountId !== accountId) return

        this.apply(normalizeUserPreferences(response.data.body, app.settings, defaults))
        this.serverLoaded = true
      } catch {
        if (revision === this.revision && auth.activeAccountId === accountId) {
          this.apply(defaults)
        }
      }
    },
    apply(values: UserPreferenceValues): void {
      const app = useAppStore()
      this.values = values
      app.setCurrentLocale(values.language)
      app.setThemeMode(values.themeMode)
      rememberSidebarOpen(values.sidebarOpen)
    },
    async save<K extends UserPreferenceName>(
      name: K,
      value: UserPreferenceValues[K],
    ): Promise<boolean> {
      const auth = useAuthStore()
      const accountId = auth.activeAccountId
      const token = auth.getToken
      if (accountId === null || !token) return false

      this.revision += 1
      if (this.values) {
        this.values = { ...this.values, [name]: value }
      }

      const queueKey = `${accountId}:${name}`
      const previous = pendingWrites.get(queueKey) ?? Promise.resolve(true)
      const write = previous.then(async () => {
        try {
          const response = await updateUserPreference(name, value, token)
          return response.status === 200
        } catch {
          return false
        }
      })
      pendingWrites.set(queueKey, write)

      const saved = await write
      if (pendingWrites.get(queueKey) === write) {
        pendingWrites.delete(queueKey)
      }

      return saved
    },
  },
})
