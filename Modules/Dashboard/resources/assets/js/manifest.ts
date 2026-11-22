import type { ModuleManifest } from '@/modules/core/contracts/ModuleManifest.ts'
import adminRoutes from '@/modules/dashboard/routes/admin.routes.ts'
import { adminSidebar } from '@/modules/dashboard/sidebars/admin.sidebar.ts'

const manifest: ModuleManifest = {
  name: 'dashboard',
  routes: [
    {
      target: 'admin',
      routes: adminRoutes,
    },
  ],
  sidebars: [adminSidebar],
}

export default manifest
