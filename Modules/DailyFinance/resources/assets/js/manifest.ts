import type { ModuleManifest } from '@/modules/core/contracts/ModuleManifest.ts'
import adminRoutes from '@/modules/dailyfinance/routes/admin.routes.ts'
import { adminSidebar } from '@/modules/dailyfinance/sidebars/admin.sidebar.ts'

const manifest: ModuleManifest = {
  name: 'dailyfinance',
  routes: [
    {
      target: 'admin',
      routes: adminRoutes,
    },
  ],
  sidebars: [adminSidebar],
}

export default manifest
