import type { ModuleManifest } from '@/modules/core/contracts/ModuleManifest.ts'
import adminRoutes from '@/modules/product/routes/admin.routes.ts'
import { adminSidebar } from '@/modules/product/sidebars/admin.sidebar.ts'

const manifest: ModuleManifest = {
  name: 'product',
  routes: [
    {
      target: 'admin',
      routes: adminRoutes,
    },
  ],
  sidebars: [adminSidebar],
}

export default manifest
