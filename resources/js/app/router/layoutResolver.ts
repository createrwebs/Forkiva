import type { TargetName } from '@/modules/core/contracts/Target'
import type { Component } from 'vue'
import { defineAsyncComponent } from 'vue'

const ASYNC_LAYOUT_RELOAD_KEY = 'forkiva:async-layout-reload'

function asyncLayout(loader: () => Promise<Component>) {
  return defineAsyncComponent(async () => {
    try {
      const layout = await loader()
      sessionStorage.removeItem(ASYNC_LAYOUT_RELOAD_KEY)
      return layout
    } catch (error) {
      if (
        error instanceof Error &&
        error.message.includes('Failed to fetch dynamically imported module') &&
        !sessionStorage.getItem(ASYNC_LAYOUT_RELOAD_KEY)
      ) {
        sessionStorage.setItem(ASYNC_LAYOUT_RELOAD_KEY, 'true')
        window.location.reload()

        return new Promise<never>(() => {})
      }

      throw error
    }
  })
}

const AdminLayout = asyncLayout(() => import('@/app/layouts/AdminLayout.vue'))
const EmployeeLayout = asyncLayout(() => import('@/app/layouts/EmployeeLayout.vue'))
const DriverLayout = asyncLayout(() => import('@/app/layouts/DriverLayout.vue'))
const BlankLayout = asyncLayout(() => import('@/app/layouts/BlankLayout.vue'))
const PublicLayout = asyncLayout(() => import('@/app/layouts/PublicLayout.vue'))

export function resolveLayout(target?: TargetName): Component | void {
  switch (target) {
    case 'admin': {
      return AdminLayout
    }
    case 'employee': {
      return EmployeeLayout
    }
    case 'driver': {
      return DriverLayout
    }
    case 'blank': {
      return BlankLayout
    }
  }
  return PublicLayout
}
