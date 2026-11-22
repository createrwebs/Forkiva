import type { ModuleManifest } from '@/modules/core/contracts/ModuleManifest.ts'
import type { SidebarList } from '@/modules/core/contracts/SidebarItem.ts'
import type { TargetName } from '@/modules/core/contracts/Target.ts'
import type { RouteRecordRaw } from 'vue-router'
import { sidebarRegistry } from '@/modules/core/services/SidebarService.ts'

class ModuleService {
  private modules: Map<string, ModuleManifest> = new Map()

  private constructor(modules: ModuleManifest[]) {
    modules.forEach((module: ModuleManifest) => {
      this.modules.set(module.name, module)
      this.registerSidebars(module)
    })
  }

  static async load(): Promise<ModuleService> {
    const { collectedModules } = await import('@/app/bootstrap/collectModules.ts')
    return new ModuleService(collectedModules)
  }

  exists(name: string): boolean {
    return this.modules.has(name)
  }

  get(name: string): ModuleManifest | undefined {
    return this.modules.get(name)
  }

  all(): ModuleManifest[] {
    return Array.from(this.modules.values())
  }

  getRoutes = (): Partial<Record<TargetName, RouteRecordRaw[]>> => {
    return this.all().reduce<Partial<Record<TargetName, RouteRecordRaw[]>>>((acc, module) => {
      if (module.routes) {
        for (const { target, routes, workspace } of module.routes) {
          const registeredRoutes = this.withWorkspace(routes, workspace)
          acc[target] = acc[target] ? acc[target].concat(registeredRoutes) : [...registeredRoutes]
        }
      }
      return acc
    }, {})
  }

  private withWorkspace(routes: RouteRecordRaw[], workspace?: string): RouteRecordRaw[] {
    if (!workspace) {
      return routes
    }

    return routes.map((route) => {
      const registeredRoute = {
        ...route,
        meta: {
          ...route.meta,
          workspace: route.meta?.workspace ?? workspace,
        },
      } as RouteRecordRaw

      if (route.children) {
        registeredRoute.children = this.withWorkspace(route.children, workspace)
      }

      return registeredRoute
    })
  }

  private registerSidebars(module: ModuleManifest): void {
    module.sidebars?.forEach((sidebarList: SidebarList) => {
      sidebarRegistry.register(sidebarList.target, sidebarList.items, sidebarList.workspace)
    })
  }
}

export const loadModuleService = (): Promise<ModuleService> => ModuleService.load()
