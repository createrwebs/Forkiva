import type { ModuleManifest } from '@/modules/core/contracts/ModuleManifest.ts'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@/app/bootstrap/collectModules.ts', () => ({
  collectedModules: [
    {
      name: 'core',
      routes: [{ target: 'admin', routes: [] }],
    },
    {
      name: 'menu',
      routes: [
        {
          target: 'admin',
          workspace: 'menu_catalog',
          routes: [
            {
              path: 'menus',
              children: [{ path: 'create', component: {} }],
            },
          ],
        },
      ],
    },
  ] satisfies ModuleManifest[],
}))

describe('ModuleService route targets', () => {
  it('does not register layouts for modules that are not installed', async () => {
    const { loadModuleService } = await import('./ModuleService.ts')
    const moduleService = await loadModuleService()
    const routes = moduleService.getRoutes()

    expect(Object.keys(routes)).toEqual(['admin'])
  })

  it('adds workspace context to every route in a workspace module', async () => {
    const { loadModuleService } = await import('./ModuleService.ts')
    const moduleService = await loadModuleService()
    const routes = moduleService.getRoutes().admin ?? []
    const menuRoute = routes.find(({ path }) => path === 'menus')

    expect(menuRoute?.meta?.workspace).toBe('menu_catalog')
    expect(menuRoute?.children?.[0]?.meta?.workspace).toBe('menu_catalog')
  })
})
