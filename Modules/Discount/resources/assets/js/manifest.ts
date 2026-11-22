import type { ModuleManifest } from '@/modules/core/contracts/ModuleManifest.ts'
import adminRoutes from '@/modules/discount/routes/admin.routes.ts'
import { adminSidebar } from '@/modules/discount/sidebars/admin.sidebar.ts'

const manifest: ModuleManifest = {
  name: 'discount',
  routes: [
    {
      target: 'admin',
      routes: adminRoutes,
    },
  ],
  sidebars: [adminSidebar],
}

export default manifest
