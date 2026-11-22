import type { ModuleManifest } from '@/modules/core/contracts/ModuleManifest.ts'
import adminRoutes from '@/modules/voucher/routes/admin.routes.ts'
import { adminSidebar } from '@/modules/voucher/sidebars/admin.sidebar.ts'

const manifest: ModuleManifest = {
  name: 'voucher',
  routes: [
    {
      target: 'admin',
      routes: adminRoutes,
    },
  ],
  sidebars: [adminSidebar],
}

export default manifest
