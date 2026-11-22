import type { useAuthStore } from '@/modules/auth/stores/authStore.ts'
import type { RouteLocationRaw, Router } from 'vue-router'
import { hasDriverPanelAccess } from '@/modules/auth/utils/driverPanelAccess.ts'
import { hasEmployeePanelAccess } from '@/modules/auth/utils/employeePanelAccess.ts'

type AuthStore = ReturnType<typeof useAuthStore>

const accountingRoles = ['accountant', 'accounting_manager', 'accounting_auditor']

export function getDefaultRoute(authStore: AuthStore, router?: Router): RouteLocationRaw {
  const hasDriverAccess = hasDriverPanelAccess(authStore.getUser)
  const hasEmployeeAccess = hasEmployeePanelAccess(authStore.getUser)

  if (
    (authStore.hasRole('cashier') || authStore.hasRole('waiter')) &&
    authStore.can('admin.pos.index')
  ) {
    return {
      name: 'admin.pos.index',
    } as unknown as RouteLocationRaw
  }

  if (
    router?.hasRoute('admin.hr_dashboard.index') &&
    (authStore.hasRole('hr_manager') || authStore.hasRole('hr_officer')) &&
    authStore.can('admin.hr_dashboard.index')
  ) {
    return { name: 'admin.hr_dashboard.index' } as unknown as RouteLocationRaw
  }

  if (
    router?.hasRoute('admin.accounting.dashboard') &&
    authStore.hasRole(accountingRoles) &&
    authStore.can('admin.accounting_dashboard.index')
  ) {
    return { name: 'admin.accounting.dashboard' } as unknown as RouteLocationRaw
  }

  if (authStore.hasRole('kitchen') && authStore.can('admin.pos.kitchen_viewer')) {
    return { name: 'admin.pos.kitchen_viewer' } as unknown as RouteLocationRaw
  }

  if (router?.hasRoute('driver.dashboard') && hasDriverAccess) {
    return { name: 'driver.dashboard' } as unknown as RouteLocationRaw
  }

  if (router?.hasRoute('employee.dashboard') && hasEmployeeAccess && authStore.hasRole('staff')) {
    return { name: 'employee.dashboard' } as unknown as RouteLocationRaw
  }

  return { name: 'admin.dashboard' } as unknown as RouteLocationRaw
}
