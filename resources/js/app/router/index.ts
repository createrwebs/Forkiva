import type { RouteRecordRaw } from 'vue-router'
import { authMiddleware } from '@/modules/auth/middleware/auth.middleware.ts'
import { driverPanelMiddleware } from '@/modules/auth/middleware/driver.middleware.ts'
import { employeePanelMiddleware } from '@/modules/auth/middleware/employee.middleware.ts'
import { featureMiddleware } from '@/modules/auth/middleware/feature.middleware.ts'
import { permissionMiddleware } from '@/modules/auth/middleware/permission.middleware.ts'
import { loadModuleService } from '@/modules/core/services/ModuleService.ts'

export async function createRoutes(): Promise<RouteRecordRaw[]> {
  const moduleService = await loadModuleService()
  const moduleRoutes = moduleService.getRoutes()

  const routes: RouteRecordRaw[] = [
    {
      path: '/',
      redirect: '/admin',
    },
    {
      path: `/admin`,
      beforeEnter: [authMiddleware, permissionMiddleware],
      meta: {
        target: 'admin',
      },
      children: moduleRoutes['admin'] ?? [],
    },
  ]

  if (moduleRoutes['employee'] !== undefined) {
    routes.push({
      path: '/employee',
      beforeEnter: [authMiddleware, featureMiddleware('hr'), employeePanelMiddleware],
      meta: {
        target: 'employee',
      },
      children: moduleRoutes['employee'] ?? [],
    })
  }

  if (moduleRoutes['driver'] !== undefined) {
    routes.push({
      path: '/driver',
      beforeEnter: [authMiddleware, featureMiddleware('delivery'), driverPanelMiddleware],
      meta: {
        target: 'driver',
      },
      children: moduleRoutes['driver'] ?? [],
    })
  }

  if (moduleRoutes['public'] !== undefined) {
    for (const publicRoute of moduleRoutes['public']) {
      routes.push(publicRoute)
    }
  }

  return routes
}
