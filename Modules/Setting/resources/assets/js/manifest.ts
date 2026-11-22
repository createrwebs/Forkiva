import type { ModuleManifest } from '@/modules/core/contracts/ModuleManifest.ts'
import adminRoutes from '@/modules/setting/routes/admin.routes.ts'
import { adminSidebar } from '@/modules/setting/sidebars/admin.sidebar.ts'

const manifest: ModuleManifest = {
  name: 'setting',
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
