import type { ModuleManifest } from '@/modules/core/contracts/ModuleManifest.ts'
import adminRoutes from '@/modules/reservation/routes/admin.routes.ts'
import { adminSidebar } from '@/modules/reservation/sidebars/admin.sidebar.ts'

const manifest: ModuleManifest = {
  name: 'reservation',
  routes: [
    {
      target: 'admin',
      routes: adminRoutes,
    },
  ],
  sidebars: [adminSidebar],
}

export default manifest
