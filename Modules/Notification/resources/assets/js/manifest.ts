import type { ModuleManifest } from '@/modules/core/contracts/ModuleManifest.ts'
import adminRoutes from '@/modules/notification/routes/admin.routes.ts'
import { adminSidebar } from '@/modules/notification/sidebars/admin.sidebar.ts'

const manifest: ModuleManifest = {
  name: 'notification',
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
