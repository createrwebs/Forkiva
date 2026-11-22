import type { ModuleManifest } from '@/modules/core/contracts/ModuleManifest.ts'
import adminRoutes from '@/modules/invoice/routes/admin.routes.ts'
import { adminSidebar } from '@/modules/invoice/sidebars/admin.sidebar.ts'

const manifest: ModuleManifest = {
  name: 'invoice',
  routes: [
    {
      target: 'admin',
      routes: adminRoutes,
    },
  ],
  sidebars: [adminSidebar],
}

export default manifest
