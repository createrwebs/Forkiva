import type { ModuleManifest } from '@/modules/core/contracts/ModuleManifest.ts'
import adminRoutes from '@/modules/option/routes/admin.routes.ts'
import { adminSidebar } from '@/modules/option/sidebars/admin.sidebar.ts'

const manifest: ModuleManifest = {
  name: 'option',
  routes: [
    {
      target: 'admin',
      routes: adminRoutes,
    },
  ],
  sidebars: [adminSidebar],
}

export default manifest
