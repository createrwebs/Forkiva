import type { ModuleManifest } from '@/modules/core/contracts/ModuleManifest.ts'
import adminRoutes from '@/modules/category/routes/admin.routes.ts'
import { adminSidebar } from '@/modules/category/sidebars/admin.sidebar.ts'

const manifest: ModuleManifest = {
  name: 'category',
  routes: [
    {
      target: 'admin',
      routes: adminRoutes,
    },
  ],
  sidebars: [adminSidebar],
}

export default manifest
