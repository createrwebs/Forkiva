import type {
  NavigationGuardNext,
  RouteLocationNormalized,
  RouteLocationNormalizedLoaded,
} from 'vue-router'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import { hasDriverPanelAccess } from '@/modules/auth/utils/driverPanelAccess.ts'

export function driverPanelMiddleware(
  _to: RouteLocationNormalized,
  _from: RouteLocationNormalizedLoaded,
  next: NavigationGuardNext,
) {
  const auth = useAuth()

  if (hasDriverPanelAccess(auth.store.getUser)) {
    next()
    return
  }

  next(auth.getDefaultRoute())
}
