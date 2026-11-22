import type { ModuleManifest } from '@/modules/core/contracts/ModuleManifest.ts'
import adminRoutes from '@/modules/order/routes/admin.routes.ts'
import { adminSidebar } from '@/modules/order/sidebars/admin.sidebar.ts'

const manifest: ModuleManifest = {
  name: 'order',
  routes: [
    {
      target: 'admin',
      routes: adminRoutes,
    },
  ],
  sidebars: [adminSidebar],
}

export default manifest
