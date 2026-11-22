import { computed } from 'vue'
import { useAppStore } from '@/modules/core/stores/appStore.ts'

export function useFeatures() {
  const appStore = useAppStore()

  const isEnabled = (name: string): boolean => {
    return !!appStore.settings.features?.[name]?.enabled
  }

  return {
    isEnabled,
    deliveryEnabled: computed(() => isEnabled('delivery')),
  }
}
