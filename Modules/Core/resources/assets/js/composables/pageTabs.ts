import { ref, watch, type ComputedRef } from 'vue'

export function usePageTabs<T extends { id: string }>(
  tabs: ComputedRef<T[]>,
  fallbackTab = 'overview',
) {
  const activeTab = ref(fallbackTab)

  watch(
    tabs,
    (values) => {
      if (!values.some((tab) => tab.id === activeTab.value)) {
        activeTab.value = values[0]?.id || fallbackTab
      }
    },
    { immediate: true },
  )

  return {
    activeTab,
  }
}
