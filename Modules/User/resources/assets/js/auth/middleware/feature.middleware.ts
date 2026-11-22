import type {
  NavigationGuardNext,
  RouteLocationNormalized,
  RouteLocationNormalizedLoaded,
  RouteLocationRaw,
} from 'vue-router'
import { useAppStore } from '@/modules/core/stores/appStore.ts'

export const featureMiddleware =
  (feature: string, fallback: RouteLocationRaw = { name: 'admin.dashboard' }) =>
  (
    to: RouteLocationNormalized,
    from: RouteLocationNormalizedLoaded,
    next: NavigationGuardNext,
  ): void => {
    if (!useAppStore().settings.features?.[feature]?.enabled) {
      next(fallback)

      return
    }

    next()
  }
