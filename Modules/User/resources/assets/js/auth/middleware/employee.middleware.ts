import type {
  NavigationGuardNext,
  RouteLocationNormalized,
  RouteLocationNormalizedLoaded,
  RouteLocationRaw,
} from 'vue-router'
import { useAuth } from '@/modules/auth/composables/auth.ts'
import { hasEmployeePanelAccess } from '@/modules/auth/utils/employeePanelAccess.ts'

export function employeePanelMiddleware(
  to: RouteLocationNormalized,
  from: RouteLocationNormalizedLoaded,
  next: NavigationGuardNext,
) {
  const auth = useAuth()
  const isAccessDeniedRoute = to.name === 'employee.access_denied'

  if (hasEmployeePanelAccess(auth.store.getUser)) {
    if (isAccessDeniedRoute) {
      next({ name: 'employee.dashboard' } as unknown as RouteLocationRaw)
      return
    }

    next()
    return
  }

  if (isAccessDeniedRoute) {
    next()
    return
  }

  next({ name: 'employee.access_denied' } as unknown as RouteLocationRaw)
}
