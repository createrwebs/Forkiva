import type { ModuleManifest } from '@/modules/core/contracts/ModuleManifest.ts'
import adminRoutes from '@/modules/service/routes/admin.routes.ts'
import { adminSidebar } from '@/modules/service/sidebars/admin.sidebar.ts'

const manifest: ModuleManifest = {
  name: 'service',
  routes: [
    {
      target: 'admin',
      workspace: 'settings',
      routes: adminRoutes,
    },
  ],
  sidebars: [adminSidebar],
}

export default manifest
