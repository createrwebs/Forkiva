import type { ThemeMode } from '@/modules/core/contracts/AppState.ts'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import { useToast } from 'vue-toastification'
import { useAppStore } from '@/modules/core/stores/appStore.ts'
import type { UserPreferenceName, UserPreferenceValues } from '../contracts/UserPreference.ts'
import { rememberSidebarOpen } from '../helpers/userPreference.ts'
import { useUserPreferenceStore } from '../stores/userPreferenceStore.ts'

export function useUserPreference() {
  const app = useAppStore()
  const preferences = useUserPreferenceStore()
  const { values } = storeToRefs(preferences)
  const { t } = useI18n()
  const toast = useToast()

  async function persist<K extends UserPreferenceName>(
    name: K,
    value: UserPreferenceValues[K],
  ): Promise<void> {
    const saved = await preferences.save(name, value)

    if (!saved) {
      toast.error(t('admin::messages.something_went_wrong'))
    }
  }

  async function setLanguage(language: string): Promise<void> {
    if (!app.supportedLocales.includes(language) || language === app.appCurrentLocale) return

    app.setCurrentLocale(language)
    await persist('language', language)
    window.location.reload()
  }

  function setThemeMode(mode: ThemeMode): void {
    if (mode === app.appThemeMode) return

    app.setThemeMode(mode)
    void persist('themeMode', mode)
  }

  function setSidebarOpen(open: boolean): void {
    rememberSidebarOpen(open)
    void persist('sidebarOpen', open)
  }

  return {
    values,
    setLanguage,
    setThemeMode,
    setSidebarOpen,
  }
}
